<?php

class Response
{
    protected int $status = 200;
    protected array $headers = [];
    protected array $cookies = [];
    protected mixed $content = null;
    protected float $sent_at;

    /* -----------------------------------------------------------------
     | Constructors
     |-----------------------------------------------------------------*/

    public static function make(mixed $content = '', int $status = 200): static
    {
        return (new static())
            ->setContent($content)
            ->setStatus($status);
    }

    public static function json(array $data, int $status = 200): static
    {
        return (new static())
            ->setStatus($status)
            ->header('Content-Type', 'application/json')
            ->setContent(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public static function text(string $text, int $status = 200): static
    {
        return (new static())
            ->setStatus($status)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->setContent($text);
    }

    public static function html(string $html, int $status = 200): static
    {
        return (new static())
            ->setStatus($status)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->setContent($html);
    }

    /* -----------------------------------------------------------------
     | Headers
     |-----------------------------------------------------------------*/

    public function header(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function headers(array $headers): static
    {
        foreach ($headers as $k => $v) {
            $this->header($k, $v);
        }
        return $this;
    }

    /* -----------------------------------------------------------------
     | Cookies
     |-----------------------------------------------------------------*/

    public function cookie(
        string $name,
        string $value,
        int $minutes = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true
    ): static {
        $this->cookies[] = compact(
            'name', 'value', 'minutes', 'path', 'domain', 'secure', 'httpOnly'
        );

        return $this;
    }

    /* -----------------------------------------------------------------
     | Status & Content
     |-----------------------------------------------------------------*/

    public function setStatus(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function setContent(mixed $content): static
    {
        $this->content = $content;
        return $this;
    }

    /* -----------------------------------------------------------------
     | Redirects
     |-----------------------------------------------------------------*/

    public static function redirect(string $url, int $status = 302): static
    {
        return (new static())
            ->setStatus($status)
            ->header('Location', $url);
    }

    /* -----------------------------------------------------------------
     | Downloads
     |-----------------------------------------------------------------*/

    public static function download(string $filePath, ?string $filename = null): static
    {
        if (!is_file($filePath)) {
            return static::abort(404, 'File not found');
        }

        $filename ??= basename($filePath);

        $response = (new static())
            ->setStatus(200)
            ->headers([
                'Content-Type'        => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length'      => filesize($filePath),
            ]);

        $response->content = function () use ($filePath) {
            readfile($filePath);
        };

        return $response;
    }

    /* -----------------------------------------------------------------
     | Errors / Abort
     |-----------------------------------------------------------------*/

    public static function abort(int $status, string $message = ''): static
    {
        return static::text($message ?: "HTTP {$status}", $status);
    }

    /* -----------------------------------------------------------------
     | Send response
     |-----------------------------------------------------------------*/

    public function send(): void
    {
        if (isset($this->sent_at)) {
            return;
        }

        http_response_code($this->status);

        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}", true);
        }

        foreach ($this->cookies as $cookie) {
            setcookie(
                $cookie['name'],
                $cookie['value'],
                $cookie['minutes'] > 0 ? time() + ($cookie['minutes'] * 60) : 0,
                $cookie['path'],
                $cookie['domain'],
                $cookie['secure'],
                $cookie['httpOnly']
            );
        }

        if (is_callable($this->content)) {
            call_user_func($this->content, $this);
        } else {
            echo $this->content;
        }

        $this->sent_at = microtime(true);
    }

    /* -----------------------------------------------------------------
     | Fluent termination
     |-----------------------------------------------------------------*/

    public function exit(): never
    {
        $this->send();
        exit;
    }
}
