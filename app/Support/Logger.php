<?php

class Logger
{
    protected static string $logPath = STORAGE_PATH . '/logs';

    /* -----------------------------------------------------------------
     | Public API
     |-----------------------------------------------------------------*/

    public static function request(?Request $request = null, string $channel = 'request', bool $sanitize = true): void
    {
        $request ??= Request::capture();

        $data = [
            'timestamp'   => date('c'),
            'request_id'  => $request->id(),
            'ip'          => $request->ip(),
            'method'      => $request->method(),
            'url'         => $request->url(),
            'ajax'        => $request->isAjax(),
            'user_agent'  => $request->header('user-agent'),
            'referer'     => $request->header('referer'),
            'headers'     => $sanitize ? static::sanitize($request->headers()) : $request->headers(),
            'query'       => $request->query(),
            'body'        => $sanitize ? static::sanitize($request->all()) : $request->all(),
            'files'       => static::filesSummary($request->files()),
            'bearer'      => $request->bearerToken(),
            'duration_ms' => $request->duration(),
        ];

        static::write($channel, $data);
    }

    /* -----------------------------------------------------------------
     | Internals
     |-----------------------------------------------------------------*/

    public static function write(string $channel, array $data): void
    {
        $directory = static::ensureDirectory();

        file_put_contents(
            "$directory/$channel.log",
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    protected static function ensureDirectory(): string
    {
        if (!is_dir(static::$logPath)) {
            mkdir(static::$logPath, 0775, true);
        }

        return realpath(static::$logPath);
    }

    protected static function sanitize(array $input): array
    {
        $sensitive = ['password', 'pass', 'token', 'authorization', 'cookie'];

        foreach ($input as $key => $value) {
            if (in_array(strtolower($key), $sensitive, true)) {
                $input[$key] = '***';
            }
        }

        return $input;
    }

    protected static function filesSummary(array $files): array
    {
        $result = [];

        foreach ($files as $key => $file) {
            if (is_array($file) && isset($file['name'])) {
                $result[$key] = [
                    'name' => $file['name'],
                    'size' => $file['size'] ?? null,
                    'type' => $file['type'] ?? null,
                ];
            }
        }

        return $result;
    }
}
