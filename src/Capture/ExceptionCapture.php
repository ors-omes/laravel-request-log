<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Capture;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use OrsOmes\RequestLog\Support\Redactor;
use OrsOmes\RequestLog\Support\Truncator;
use PDOException;
use SplFileObject;
use Throwable;

final class ExceptionCapture
{
    /**
     * Frames where framework dispatch starts; everything outward is omitted.
     *
     * @var array<string, list<string>|null>
     */
    private const DISPATCH_FRAMES = [
        'Illuminate\Routing\ControllerDispatcher' => ['dispatch', 'callAction'],
        'Illuminate\Routing\CallableDispatcher' => ['dispatch'],
        'Illuminate\Routing\Route' => ['run', 'runController', 'runCallable'],
        'Lorisleiva\Actions\Decorators\ControllerDecorator' => null,
    ];

    private const SOURCE_LINE_BYTES = 300;

    public function __construct(
        private readonly Redactor $redactor,
        private readonly Truncator $truncator,
    ) {}

    public static function relative(string $file): string
    {
        $base = rtrim(base_path(), '/\\').DIRECTORY_SEPARATOR;

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }

    public static function isProjectFile(string $file): bool
    {
        $relative = str_replace('\\', '/', self::relative($file));
        if ($relative === $file || str_contains($relative, 'vendor/')) {
            return false;
        }

        foreach (Config::array('request-log.exceptions.project_paths') as $path) {
            if (is_string($path) && $path !== '' && str_starts_with($relative, trim($path, '/').'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *     exception_class: string,
     *     exception_message: string,
     *     exception_location: string,
     *     exception_hash: string,
     *     exception_snippet: string|null,
     *     exception_trace: list<array{file: string|null, line: int|null, class: string|null, function: string|null, type: string|null}>|null
     * }
     */
    public function capture(Throwable $exception, int $status): array
    {
        $file = self::relative($exception->getFile());
        $location = $file.':'.$exception->getLine();
        $debug = $status >= Config::integer('request-log.exceptions.debug_min_status');

        return [
            'exception_class' => mb_strcut($exception::class, 0, 255, 'UTF-8'),
            'exception_message' => $this->message($exception),
            'exception_location' => mb_strcut($location, 0, 512, 'UTF-8'),
            'exception_hash' => md5($exception::class.'|'.$location),
            'exception_snippet' => $debug ? $this->snippet($exception) : null,
            'exception_trace' => $debug ? $this->trace($exception) : null,
        ];
    }

    private function message(Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (! Config::boolean('request-log.exceptions.query_bindings')) {
            if ($exception instanceof QueryException) {
                $message = $this->queryMessage($exception);
            } elseif ($exception instanceof PDOException) {
                $message = $this->redactor->driverMessage($message);
            }
        }

        return $this->truncator->text(
            $this->redactor->string($message),
            Config::integer('request-log.exceptions.message_bytes'),
        );
    }

    /**
     * Driver message without the values it quotes (see
     * Redactor::driverMessage()), and the SQL with its placeholders.
     */
    private function queryMessage(QueryException $exception): string
    {
        $driverMessage = $exception->getPrevious()?->getMessage() ?? $exception->getMessage();

        return trim($this->redactor->driverMessage($driverMessage)).' (Connection: '.$exception->getConnectionName().', SQL: '.$exception->getSql().')';
    }

    private function snippet(Throwable $exception): ?string
    {
        [$file, $line] = $this->sourceLocation($exception);
        if ($file === '' || $line <= 0 || ! is_file($file) || ! is_readable($file)) {
            return null;
        }

        $context = max(0, Config::integer('request-log.exceptions.context_lines'));
        $start = max(1, $line - $context);
        $end = $line + $context;
        $lines = [self::relative($file).':'.$line];

        $source = new SplFileObject($file);
        $source->seek($start - 1);

        for ($number = $start; $number <= $end && ! $source->eof(); $number++) {
            $sourceLine = $source->current();
            $text = is_string($sourceLine) ? rtrim($sourceLine, "\r\n") : '';
            $lines[] = sprintf('%s%5d| %s', $number === $line ? '>' : ' ', $number, mb_strcut($text, 0, self::SOURCE_LINE_BYTES, 'UTF-8'));
            $source->next();
        }

        return $this->redactor->string(implode("\n", $lines));
    }

    /**
     * First location inside the project: the throw site when it is project
     * code, else the first project frame of the trace, else the throw site.
     *
     * @return array{0: string, 1: int}
     */
    private function sourceLocation(Throwable $exception): array
    {
        if (self::isProjectFile($exception->getFile())) {
            return [$exception->getFile(), $exception->getLine()];
        }

        foreach ($exception->getTrace() as $frame) {
            $file = $frame['file'] ?? '';
            if ($file !== '' && self::isProjectFile($file)) {
                return [$file, $frame['line'] ?? 0];
            }
        }

        return [$exception->getFile(), $exception->getLine()];
    }

    /**
     * @return list<array{file: string|null, line: int|null, class: string|null, function: string|null, type: string|null}>
     */
    private function trace(Throwable $exception): array
    {
        $maxFrames = max(1, Config::integer('request-log.exceptions.max_frames'));
        $frames = $exception->getTrace();
        $trace = [];
        $omitted = 0;

        foreach ($frames as $index => $frame) {
            if ($this->isDispatchFrame($frame['class'] ?? null, $frame['function'])) {
                $omitted = count($frames) - $index;

                break;
            }

            if (count($trace) === $maxFrames) {
                $omitted = count($frames) - $index;

                break;
            }

            $trace[] = [
                'file' => isset($frame['file']) ? self::relative($frame['file']) : null,
                'line' => $frame['line'] ?? null,
                'class' => $frame['class'] ?? null,
                'function' => $frame['function'],
                'type' => $frame['type'] ?? null,
            ];
        }

        if ($omitted > 0) {
            $trace[] = ['file' => null, 'line' => null, 'class' => null, 'function' => sprintf('…[%d framework frames omitted]', $omitted), 'type' => null];
        }

        return $trace;
    }

    private function isDispatchFrame(?string $class, string $function): bool
    {
        if ($class === null || ! array_key_exists($class, self::DISPATCH_FRAMES)) {
            return false;
        }

        $functions = self::DISPATCH_FRAMES[$class];

        return $functions === null || in_array($function, $functions, true);
    }
}
