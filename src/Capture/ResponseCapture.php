<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Capture;

use Illuminate\Support\Facades\Config;
use OrsOmes\RequestLog\Support\Redactor;
use OrsOmes\RequestLog\Support\Truncator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class ResponseCapture
{
    public function __construct(
        private readonly Redactor $redactor,
        private readonly Truncator $truncator,
    ) {}

    public function headers(Response $response): ?string
    {
        if (! Config::boolean('request-log.capture.response_headers')) {
            return null;
        }

        [$encoded] = $this->truncator->json(
            $this->redactor->headers($response->headers->all(), true),
            Config::integer('request-log.capture.header_bytes'),
        );

        return $encoded;
    }

    /**
     * @return array{kind: string, body: string|null, bytes: int, truncated: bool}
     */
    public function body(Response $response, bool $metadataOnly): array
    {
        if ($response instanceof BinaryFileResponse) {
            return $this->file($response);
        }

        if ($response instanceof StreamedResponse) {
            $type = $this->type($response);

            return ['kind' => 'stream', 'body' => sprintf('[streamed %s]', $type === '' ? 'response' : $type), 'bytes' => 0, 'truncated' => false];
        }

        if ($response instanceof RedirectResponse) {
            return ['kind' => 'redirect', 'body' => $this->redirectTarget($response->getTargetUrl()), 'bytes' => 0, 'truncated' => false];
        }

        $content = $response->getContent();
        if ($content === false || $content === '') {
            return ['kind' => 'empty', 'body' => null, 'bytes' => 0, 'truncated' => false];
        }

        $bytes = strlen($content);
        $type = $this->type($response);
        $isJson = str_contains($type, 'json');
        $kind = $this->kind($type, $content, $isJson);

        if ($metadataOnly) {
            return ['kind' => $kind, 'body' => sprintf('[omitted: %d bytes]', $bytes), 'bytes' => $bytes, 'truncated' => false];
        }

        if ($isJson) {
            return ['kind' => 'json', ...$this->json($content, $bytes)];
        }

        if ($kind === 'text' && Config::boolean('request-log.capture.raw_text_bodies')) {
            $text = $this->redactor->string($content);
            $limit = Config::integer('request-log.capture.response_body_bytes');

            return ['kind' => 'text', 'body' => $this->truncator->text($text, $limit), 'bytes' => $bytes, 'truncated' => strlen($text) > $limit];
        }

        return ['kind' => $kind, 'body' => sprintf('[%s %s: %d bytes]', $kind, $type === '' ? 'response' : $type, $bytes), 'bytes' => $bytes, 'truncated' => false];
    }

    /**
     * @return array{body: string, bytes: int, truncated: bool}
     */
    private function json(string $content, int $bytes): array
    {
        if ($bytes > Config::integer('request-log.capture.max_parse_bytes')) {
            return ['body' => sprintf('[json too large to parse: %d bytes]', $bytes), 'bytes' => $bytes, 'truncated' => true];
        }

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['body' => sprintf('[invalid json: %d bytes]', $bytes), 'bytes' => $bytes, 'truncated' => false];
        }

        $redacted = is_array($decoded) ? $this->redactor->data($decoded) : (is_string($decoded) ? $this->redactor->string($decoded) : $decoded);
        [$encoded, $truncated] = $this->truncator->json($redacted, Config::integer('request-log.capture.response_body_bytes'));

        return ['body' => $encoded, 'bytes' => $bytes, 'truncated' => $truncated];
    }

    /**
     * @return array{kind: string, body: string, bytes: int, truncated: bool}
     */
    private function file(BinaryFileResponse $response): array
    {
        $size = (int) $response->headers->get('Content-Length', '0');

        try {
            $name = $response->getFile()->getFilename();
        } catch (Throwable) {
            $name = 'file';
        }

        return ['kind' => 'file', 'body' => sprintf('[file %s: %d bytes]', $name, $size), 'bytes' => $size, 'truncated' => false];
    }

    private function redirectTarget(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (is_string($query) && $query !== '') {
            parse_str($query, $parameters);
            $redacted = urldecode(http_build_query($this->redactor->data($parameters), '', '&', PHP_QUERY_RFC3986));
            $url = str_replace($query, $redacted, $url);
        }

        return $this->truncator->text($this->redactor->string($url), 4096);
    }

    private function kind(string $type, string $content, bool $isJson): string
    {
        if ($isJson) {
            return 'json';
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            return 'binary';
        }

        return str_contains($type, 'html') ? 'html' : 'text';
    }

    private function type(Response $response): string
    {
        return strtolower(trim((string) strtok((string) $response->headers->get('Content-Type', ''), ';')));
    }
}
