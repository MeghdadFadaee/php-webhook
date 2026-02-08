<?php

class Request
{
    public static self $instance;

    public static function capture(): static
    {
        return static::$instance ??= ((new static(
            $_GET ?? [],
            $_POST ?? [],
            $_FILES ?? [],
            $_SERVER ?? [],
        ))->setup());
    }

    public function __construct(
        protected array $get,
        protected array $post,
        protected array $files,
        protected array $server,
        protected ?array $headers = null,
        protected ?array $json = null,
        protected ?float $setup_at = null,
    ) {
        //
    }

    public function setup(): static
    {
        $this->parseHeaders();
        $this->parseJsonBody();
        $this->getSetupTime();

        return $this;
    }

    public function pipe(array $hooks): static
    {
        foreach ($hooks as $hook => $action) {
            if ($this->uriIs($hook)) {
                if (is_callable($action)) {
                    call_user_func($action, $this);
                }
                if (is_string($action) and class_exists($action)) {
                    (new $action())($this);
                }
            }
        }

        return $this;
    }

    /* -----------------------------------------------------------------
     | Request Meta Data
     |-----------------------------------------------------------------*/

    public function id(): string
    {
        return $this->server['REQUEST_ID'] ??= bin2hex(random_bytes(8));
    }

    public function duration(): int
    {
        $this->server['REQUEST_TIME_FLOAT'] ??= APP_START_AT;
        return (int) (($this->setup_at - $this->server['REQUEST_TIME_FLOAT']) * 1000);
    }

    protected function getSetupTime(): float
    {
        return $this->setup_at ??= microtime(true);
    }

    /* -----------------------------------------------------------------
     | Basic input handling
     |-----------------------------------------------------------------*/

    public function all(): array
    {
        return array_merge($this->get, $this->post, $this->json);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->get;
        }

        return $this->get[$key] ?? $default;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }

        return $this->post[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function filled(string $key): bool
    {
        return $this->has($key) && trim((string) $this->input($key)) !== '';
    }

    /* -----------------------------------------------------------------
     | HTTP info
     |-----------------------------------------------------------------*/

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    public function scheme(): string
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            ? 'https'
            : 'http';
    }

    public function host(): string
    {
        return trim($this->server['HTTP_HOST'] ?? '', '/');
    }

    public function uri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function uriWithQuery(): string
    {
        return $this->server['HTTP_URI'] ?? '/';
    }

    public function url(): string
    {
        return "{$this->scheme()}://{$this->host()}{$this->uri()}";
    }

    public function ip(): ?string
    {
        return $this->server['REMOTE_ADDR'] ?? null;
    }

    public function isAjax(): bool
    {
        return ($this->header('X-Requested-With') === 'XMLHttpRequest');
    }

    public function uriIs(string|iterable $pattern): bool
    {
        return Str::is($pattern, $this->uri());
    }

    /* -----------------------------------------------------------------
     | Headers
     |-----------------------------------------------------------------*/

    protected function parseHeaders(): array
    {
        $headers = [];

        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        return $this->headers = $headers;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $key = strtolower($key);
        return $this->headers[$key] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('authorization');

        if (!$header) {
            return null;
        }

        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /* -----------------------------------------------------------------
     | JSON
     |-----------------------------------------------------------------*/

    protected function parseJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return $this->json = is_array($decoded) ? $decoded : [];
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->json;
        }

        return $this->json[$key] ?? $default;
    }

    /* -----------------------------------------------------------------
     | Files
     |-----------------------------------------------------------------*/

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function files(): ?array
    {
        return $this->files;
    }

    /* -----------------------------------------------------------------
     | Old input (session-based)
     |-----------------------------------------------------------------*/

    public function flash(): void
    {
        $_SESSION['_old_input'] = $this->all();
    }

    public function old(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_old_input'][$key] ?? $default;
    }

    /* -----------------------------------------------------------------
     | Validation (minimal but useful)
     |-----------------------------------------------------------------*/

    public function validate(array $rules): array
    {
        $errors = [];
        $validated = [];
        $data = $this->all();

        foreach ($rules as $field => $ruleSet) {
            $rulesArr = explode('|', $ruleSet);

            foreach ($rulesArr as $rule) {
                if ($rule === 'required' && !isset($data[$field])) {
                    $errors[$field][] = 'The field is required.';
                }

                if ($rule === 'numeric' && isset($data[$field]) && !is_numeric($data[$field])) {
                    $errors[$field][] = 'The field must be numeric.';
                }
            }

            if (isset($data[$field])) {
                $validated[$field] = $data[$field];
            }
        }

        if (!empty($errors)) {
            JsonResponse::error(HttpStatus::UNPROCESSABLE_ENTITY, $errors)->exit();
        }

        return $validated;
    }
}
