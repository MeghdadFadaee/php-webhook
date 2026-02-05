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

/**
 * Get all files inside a directory recursively.
 *
 * @param  string  $directory
 * @return array
 */
function getAllFilesRecursively(string $directory): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

function makeAppContainer(): array
{
    /** @var SplFileInfo[] $iterator */
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            APP_PATH,
            FilesystemIterator::SKIP_DOTS
        )
    );

    $files = [];
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $key = str_replace('.php', '', $file->getBasename());
        $files[$key] = $file->getPathname();
    }

    return $files;
}

/**
 * Dump variables and stop execution.
 *
 * @param  mixed  ...$vars
 * @return void
 */
function dd(...$vars): void
{
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
    $caller = $trace[0] ?? [];
    $file = $caller['file'] ?? 'unknown';
    $line = $caller['line'] ?? 0;

    // If running in CLI, print plain text.
    if (PHP_SAPI === 'cli') {
        // Print header with file:line
        fwrite(STDOUT, "Dump (dd) from {$file}:{$line}\n");
        foreach ($vars as $i => $v) {
            fwrite(STDOUT, "----- Dump #".($i + 1)." -----\n");
            // Use print_r for readable structure (works for arrays/objects/resources)
            fwrite(STDOUT, print_r($v, true)."\n");
        }
        exit(1);
    }

    // Web output (HTML)
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>dd()</title>';
    echo '<style>
        body{font-family: Menlo, Monaco, monospace; background:#f6f8fa; color:#111; padding:20px;}
        .dd-header{color:#666;margin-bottom:8px;font-size:14px;}
        .dd-block{background:#fff;border:1px solid #e1e4e8;border-radius:6px;padding:12px;margin:8px 0;box-shadow:0 1px 0 rgba(27,31,35,.04);}
        pre{white-space:pre-wrap;word-wrap:break-word;font-size:13px;line-height:1.4;margin:0;}
        .meta{font-size:12px;color:#6a737d;margin-bottom:6px;}
    </style>';
    echo '</head><body>';
    echo '<div class="dd-header">Dump (dd) from <strong>'.htmlspecialchars($file, ENT_QUOTES, 'UTF-8').':'.$line.'</strong></div>';

    foreach ($vars as $i => $v) {
        echo '<div class="dd-block">';
        echo '<div class="meta">Dump #'.($i + 1).'</div>';
        // Convert variable to a readable string
        $output = print_r($v, true);
        echo '<pre>'.htmlspecialchars($output, ENT_QUOTES, 'UTF-8').'</pre>';
        echo '</div>';
    }

    echo '</body></html>';
    exit;
}

/**
 * dump: Dump variables but do NOT stop execution.
 *
 * @param  mixed  ...$vars
 * @return void
 */
function dump(...$vars): void
{
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
    $caller = $trace[0] ?? [];
    $file = $caller['file'] ?? 'unknown';
    $line = $caller['line'] ?? 0;

    if (PHP_SAPI === 'cli') {
        fwrite(STDOUT, "Dump from {$file}:{$line}\n");
        foreach ($vars as $i => $v) {
            fwrite(STDOUT, "----- Dump #".($i + 1)." -----\n");
            fwrite(STDOUT, print_r($v, true)."\n");
        }
        return;
    }

    echo '<div style="font-family: Menlo, Monaco, monospace; background:#fff; border:1px solid #e1e4e8; padding:8px; margin:8px 0;">';
    echo '<div style="font-size:12px;color:#6a737d;margin-bottom:6px;">Dump from <strong>'.htmlspecialchars($file, ENT_QUOTES, 'UTF-8').':'.$line.'</strong></div>';
    foreach ($vars as $v) {
        $out = print_r($v, true);
        echo '<pre style="white-space:pre-wrap;word-wrap:break-word;margin:0;">'.htmlspecialchars($out, ENT_QUOTES, 'UTF-8').'</pre>';
    }
    echo '</div>';
}