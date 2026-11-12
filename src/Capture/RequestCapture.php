<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Capture;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Config;
use OrsOmes\RequestLog\Support\Redactor;
use OrsOmes\RequestLog\Support\Truncator;

final class RequestCapture
{
    private const URL_BYTES = 4096;

    private const USER_AGENT_BYTES = 512;

    public function __construct(
        private readonly Redactor $redactor,
        private readonly Truncator $truncator,
    ) {}

    public static function maskIp(string $ip): string
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return $ip;
        }

        $keep = strlen($packed) === 4 ? 3 : 6;
        $masked = inet_ntop(substr($packed, 0, $keep).str_repeat("\0", strlen($packed) - $keep));

        return $masked === false ? $ip : $masked;
    }

    /**
     * Path with values of sensitive route parameters (e.g. {token}) redacted.
     */
    public function path(Request $request): string
    {
        $path = '/'.ltrim($request->path(), '/');
        $route = $request->route();

        if ($route instanceof Route) {
            foreach ($route->originalParameters() as $name => $value) {
                if (! is_string($value) || $value === '' || ! $this->redactor->isSensitiveKey($name)) {
                    continue;
                }

                $path = str_replace(['/'.$value, '/'.rawurlencode($value)], '/'.$this->redactor->replacement(), $path);
            }
        }

        return $this->redactor->string($path);
    }

    /**
     * Redacted path plus the query string with sensitive parameters redacted.
     */
    public function url(Request $request): string
    {
        $url = $this->path($request);
        $query = $request->query->all();

        if ($query !== []) {
            $url .= '?'.urldecode(http_build_query($this->redactor->data($query), '', '&', PHP_QUERY_RFC3986));
        }

        return $this->truncator->text($this->redactor->string($url), self::URL_BYTES);
    }

    public function headers(Request $request): ?string
    {
        if (! Config::boolean('request-log.capture.request_headers')) {
            return null;
        }

        [$encoded] = $this->truncator->json(
            $this->redactor->headers($request->headers->all(), false),
            Config::integer('request-log.capture.header_bytes'),
        );

        return $encoded;
    }

    /**
     * @return array{body: string|null, bytes: int, truncated: bool}
     */
    public function body(Request $request, bool $metadataOnly): array
    {
        $raw = $request->getContent();
        $bytes = $raw !== '' ? strlen($raw) : max(0, (int) $request->headers->get('Content-Length', '0'));

        if ($metadataOnly) {
            return ['body' => $bytes > 0 ? sprintf('[omitted: %d bytes]', $bytes) : null, 'bytes' => $bytes, 'truncated' => false];
        }

        $files = $this->files($request->allFiles());
        $contentType = strtolower((string) $request->headers->get('Content-Type', ''));

        if ($raw !== '' && ! $this->isForm($contentType)) {
            $parsed = $this->parseRaw($raw, $contentType, $bytes);
            if (! $parsed['structured']) {
                return ['body' => $parsed['text'], 'bytes' => $bytes, 'truncated' => $parsed['truncated']];
            }

            $data = $parsed['data'];
        } else {
            $data = $request->request->all();
        }

        if ($files !== []) {
            $data = is_array($data) ? array_replace_recursive($data, $files) : ['body' => $data, 'files' => $files];
        }

        if ($data === [] || $data === null) {
            return ['body' => null, 'bytes' => $bytes, 'truncated' => false];
        }

        $redacted = is_array($data) ? $this->redactor->data($data) : $this->scalar($data);
        [$encoded, $truncated] = $this->truncator->json($redacted, Config::integer('request-log.capture.request_body_bytes'));

        return ['body' => $encoded, 'bytes' => $bytes, 'truncated' => $truncated];
    }

    public function ip(Request $request): ?string
    {
        $mode = Config::string('request-log.capture.ip');
        $ip = $request->ip();

        if ($ip === null || $mode === 'none') {
            return null;
        }

        return $mode === 'masked' ? self::maskIp($ip) : $ip;
    }

    public function userAgent(Request $request): ?string
    {
        if (! Config::boolean('request-log.capture.user_agent')) {
            return null;
        }

        $agent = $request->userAgent();

        return $agent === null || $agent === '' ? null : mb_strcut($this->redactor->string($agent), 0, self::USER_AGENT_BYTES, 'UTF-8');
    }

    /**
     * @return array{structured: true, data: mixed}|array{structured: false, text: string, truncated: bool}
     */
    private function parseRaw(string $raw, string $contentType, int $bytes): array
    {
        if ($bytes > Config::integer('request-log.capture.max_parse_bytes')) {
            return ['structured' => false, 'text' => sprintf('[body too large to parse: %d bytes]', $bytes), 'truncated' => true];
        }

        if ($this->isJson($contentType, $raw)) {
            $decoded = json_decode($raw, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['structured' => false, 'text' => sprintf('[invalid json: %d bytes]', $bytes), 'truncated' => false];
            }

            return ['structured' => true, 'data' => $decoded];
        }

        $type = $contentType === '' ? 'body' : strtok($contentType, ';');

        if (! Config::boolean('request-log.capture.raw_text_bodies') || ! mb_check_encoding($raw, 'UTF-8')) {
            return ['structured' => false, 'text' => sprintf('[unparsed %s: %d bytes]', $type, $bytes), 'truncated' => false];
        }

        $text = $this->redactor->string($raw);
        $limit = Config::integer('request-log.capture.request_body_bytes');

        return ['structured' => false, 'text' => $this->truncator->text($text, $limit), 'truncated' => strlen($text) > $limit];
    }

    private function scalar(mixed $value): mixed
    {
        return is_string($value) ? $this->redactor->string($value) : $value;
    }

    private function isJson(string $contentType, string $raw): bool
    {
        if (str_contains($contentType, 'json')) {
            return true;
        }

        $first = ltrim($raw)[0] ?? '';

        return $contentType === '' && ($first === '{' || $first === '[');
    }

    private function isForm(string $contentType): bool
    {
        return str_starts_with($contentType, 'application/x-www-form-urlencoded')
            || str_starts_with($contentType, 'multipart/form-data');
    }

    /**
     * @param  array<array-key, mixed>  $files
     * @return array<array-key, mixed>
     */
    private function files(array $files): array
    {
        $described = [];

        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $described[$key] = $this->files($file);
            } elseif ($file instanceof UploadedFile) {
                $described[$key] = [
                    'file' => $this->redactor->string($file->getClientOriginalName()),
                    'size' => $file->isValid() ? $file->getSize() : 0,
                    'mime' => $file->getClientMimeType(),
                ];
            }
        }

        return $described;
    }
}
