<?php

class ClientResponse
{

    public function __construct(
        protected HttpStatus $status,
        protected string $body,
        protected array $headers,
        protected bool $failed = false,
    )
    {
        //
    }

    public static function fromCurl(string $raw, array $info): self
    {
        $headerSize = $info['header_size'];
        $headerRaw  = substr($raw, 0, $headerSize);
        $body       = substr($raw, $headerSize);

        return new self(
            HttpStatus::tryFrom($info['http_code']),
            $body,
            self::parseHeaders($headerRaw)
        );
    }

    public static function error(string $message): self
    {
        return new self(HttpStatus::BAD_REQUEST, $message, [], true);
    }

    public function status(): HttpStatus
    {
        return $this->status;
    }

    public function ok(): bool
    {
        return $this->status->isSuccess();
    }

    public function failed(): bool
    {
        return $this->failed || !$this->ok();
    }

    public function body(): string
    {
        return $this->body;
    }

    public function json(): ?array
    {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function header(string $key): ?string
    {
        $key = strtolower($key);
        return $this->headers[$key] ?? null;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    /* -----------------------------------------------------------------
     | Internals
     |-----------------------------------------------------------------*/

    protected static function parseHeaders(string $raw): array
    {
        $headers = [];

        foreach (explode("\n", $raw) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
        }

        return $headers;
    }
}
