<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use OrsOmes\RequestLog\RequestLog;
use Throwable;

/**
 * Display labels for user ids. Uses the application callback when set, else
 * the auth provider's model (name, then username, then the id). E-mail
 * addresses are never used by default.
 */
final class UserLabels
{
    /**
     * @param  array<array-key, mixed>  $ids
     * @return array<string, string>
     */
    public static function for(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (mixed $id): bool => is_string($id) && $id !== '')));
        if ($ids === []) {
            return [];
        }

        $callback = RequestLog::userLabelsCallback();
        if ($callback !== null) {
            return $callback($ids);
        }

        $model = Config::get('auth.providers.users.model');
        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            return [];
        }

        try {
            $labels = [];

            foreach ($model::query()->whereKey($ids)->get() as $user) {
                $key = $user->getKey();
                if (! is_scalar($key)) {
                    continue;
                }

                $label = $user->getAttribute('name') ?? $user->getAttribute('username');
                $labels[(string) $key] = is_scalar($label) && $label !== '' ? (string) $label : (string) $key;
            }

            return $labels;
        } catch (Throwable) {
            return [];
        }
    }
}
