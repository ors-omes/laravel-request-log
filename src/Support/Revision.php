<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Support;

use Illuminate\Support\Facades\Config;

/**
 * Deployed revision: config value, else read from .git once per process
 * (HEAD, loose refs, packed-refs, and worktrees where .git is a file).
 */
final class Revision
{
    private static bool $resolved = false;

    private static ?string $fromGit = null;

    public static function current(): ?string
    {
        $configured = Config::get('request-log.revision');
        if (is_string($configured) && $configured !== '') {
            return substr($configured, 0, 64);
        }

        if (! self::$resolved) {
            self::$fromGit = self::readGit(base_path('.git'));
            self::$resolved = true;
        }

        return self::$fromGit;
    }

    public static function flush(): void
    {
        self::$resolved = false;
        self::$fromGit = null;
    }

    private static function readGit(string $gitPath): ?string
    {
        if (is_file($gitPath)) {
            $pointer = self::read($gitPath);
            if ($pointer === null || ! str_starts_with($pointer, 'gitdir:')) {
                return null;
            }

            $gitDir = trim(substr($pointer, 7));
            $gitPath = str_starts_with($gitDir, '/') ? $gitDir : dirname($gitPath).'/'.$gitDir;
        }

        $head = self::read($gitPath.'/HEAD');
        if ($head === null) {
            return null;
        }

        if (! str_starts_with($head, 'ref:')) {
            return self::sha($head);
        }

        $ref = trim(substr($head, 4));
        $commonDir = self::commonDir($gitPath);

        foreach ([$gitPath, $commonDir] as $directory) {
            $loose = self::read($directory.'/'.$ref);
            if ($loose !== null) {
                return self::sha($loose);
            }
        }

        return self::packedRef($commonDir.'/packed-refs', $ref);
    }

    private static function commonDir(string $gitPath): string
    {
        $common = self::read($gitPath.'/commondir');
        if ($common === null) {
            return $gitPath;
        }

        return str_starts_with($common, '/') ? $common : $gitPath.'/'.$common;
    }

    private static function packedRef(string $file, string $ref): ?string
    {
        $content = self::read($file);
        if ($content === null) {
            return null;
        }

        foreach (explode("\n", $content) as $line) {
            $parts = explode(' ', trim($line), 2);
            if (count($parts) === 2 && $parts[1] === $ref) {
                return self::sha($parts[0]);
            }
        }

        return null;
    }

    private static function sha(string $value): ?string
    {
        $value = strtolower(trim($value));

        return preg_match('/^[0-9a-f]{40}([0-9a-f]{24})?$/', $value) === 1 ? $value : null;
    }

    private static function read(string $file): ?string
    {
        if (! is_file($file) || ! is_readable($file)) {
            return null;
        }

        $content = file_get_contents($file);

        return is_string($content) ? trim($content) : null;
    }
}
