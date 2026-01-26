<?php

function enum_value($value, $default = null)
{
    return match (true) {
        $value instanceof \BackedEnum => $value->value,
        $value instanceof \UnitEnum => $value->name,

        default => $value ?? value($default),
    };
}

function value($value, ...$args)
{
    return $value instanceof Closure ? $value(...$args) : $value;
}

/**
 * Encode HTML special characters in a string.
 */
function e(BackedEnum|string|int|float|null $value, bool $doubleEncode = true): string
{
    if ($value instanceof BackedEnum) {
        $value = $value->value;
    }

    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
}

function tap(mixed $value, callable $callback)
{
    return $callback($value);
}

function environment(?string $is = null): bool|array|string
{
    $environment = getenv('APP_ENV');

    if (empty($environment)) {
        $environment = 'local';
    }

    if (is_null($is)) {
        return $environment;
    }

    return $environment === $is;
}

function data_has($target, $key): bool
{
    if (is_null($key) || $key === []) {
        return false;
    }

    $key = is_array($key) ? $key : explode('.', $key);

    foreach ($key as $segment) {
        if (Arr::accessible($target) && Arr::exists($target, $segment)) {
            $target = $target[$segment];
        } elseif (is_object($target) && property_exists($target, $segment)) {
            $target = $target->{$segment};
        } else {
            return false;
        }
    }

    return true;
}

function data_get($target, $key, $default = null)
{
    if (is_null($key)) {
        return $target;
    }

    $key = is_array($key) ? $key : explode('.', $key);

    foreach ($key as $i => $segment) {
        unset($key[$i]);

        if (is_null($segment)) {
            return $target;
        }

        if ($segment === '*') {
            if ($target instanceof Collection) {
                $target = $target->all();
            } elseif (!is_iterable($target)) {
                return value($default);
            }

            $result = [];

            foreach ($target as $item) {
                $result[] = data_get($item, $key);
            }

            return in_array('*', $key) ? Arr::collapse($result) : $result;
        }

        $segment = match ($segment) {
            '\*' => '*',
            '\{first}' => '{first}',
            '{first}' => array_key_first(Arr::from($target)),
            '\{last}' => '{last}',
            '{last}' => array_key_last(Arr::from($target)),
            default => $segment,
        };

        if (Arr::accessible($target) && Arr::exists($target, $segment)) {
            $target = $target[$segment];
        } elseif (is_object($target) && isset($target->{$segment})) {
            $target = $target->{$segment};
        } else {
            return value($default);
        }
    }

    return $target;
}

function class_basename($class): string
{
    $class = is_object($class) ? get_class($class) : $class;

    return basename(str_replace('\\', '/', $class));
}
