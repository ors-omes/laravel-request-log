<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Support;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

final class RouteInfo
{
    /**
     * "Fully\Qualified\Controller@method", or the class alone for invokable
     * controllers/actions. Null for closures and unmatched requests.
     */
    public static function action(?Route $route): ?string
    {
        if ($route === null) {
            return null;
        }

        $controller = $route->getAction('controller');
        if (! is_string($controller) || $controller === '') {
            return null;
        }

        return Str::endsWith($controller, '@__invoke') ? Str::beforeLast($controller, '@') : $controller;
    }

    public static function shortAction(string $action): string
    {
        $class = Str::before($action, '@');
        $method = Str::contains($action, '@') ? '@'.Str::after($action, '@') : '';

        return class_basename($class).$method;
    }

    /**
     * Every controller action registered in the router, for filter options.
     *
     * @return list<string>
     */
    public static function allActions(Router $router): array
    {
        $actions = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            $action = self::action($route);
            if ($action !== null) {
                $actions[$action] = true;
            }
        }

        $names = array_keys($actions);
        sort($names);

        return $names;
    }

    /**
     * The record the request acted on: the last route-model-bound parameter
     * ("Order:01J..."), else the last id-like parameter ("order:42").
     * Parameters named like a secret ({token}), models whose primary key is
     * named like one, and raw values looked up by a sensitive column
     * ({invitation:token}) are never used.
     *
     * @param  Closure(string): bool  $isSensitive
     */
    public static function subject(?Route $route, Closure $isSensitive): ?string
    {
        if ($route === null) {
            return null;
        }

        $parameters = array_reverse($route->parameters(), true);

        foreach ($parameters as $name => $value) {
            if ($value instanceof Model && ! $isSensitive((string) $name) && ! $isSensitive($value->getKeyName())) {
                return self::modelSubject($value);
            }
        }

        foreach ($parameters as $name => $value) {
            if ($value instanceof Model || $value instanceof BackedEnum || self::isSensitiveParameter($route, (string) $name, $isSensitive)) {
                continue;
            }

            if ((is_string($value) || is_int($value)) && self::looksLikeId((string) $value)) {
                return $name.':'.$value;
            }
        }

        return null;
    }

    /**
     * Whether the raw value of a route parameter is a secret: its name is
     * sensitive ({token}), or the record is looked up by a sensitive column
     * ({invitation:token}, or a bound model whose route key is "token").
     *
     * @param  Closure(string): bool  $isSensitive
     */
    public static function isSensitiveParameter(Route $route, string $name, Closure $isSensitive): bool
    {
        if ($isSensitive($name)) {
            return true;
        }

        $field = $route->bindingFieldFor($name);
        $value = $route->parameter($name);

        if ($field === null && $value instanceof Model) {
            $field = $value->getRouteKeyName();
        }

        return is_string($field) && $isSensitive($field);
    }

    private static function modelSubject(Model $model): ?string
    {
        $key = $model->getKey();

        if ($key instanceof BackedEnum) {
            $key = $key->value;
        }

        if (! is_scalar($key)) {
            return null;
        }

        return class_basename($model).':'.$key;
    }

    private static function looksLikeId(string $value): bool
    {
        return ctype_digit($value) || Str::isUuid($value) || Str::isUlid($value);
    }
}
