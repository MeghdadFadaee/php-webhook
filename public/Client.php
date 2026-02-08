<?php

class Client
{
    protected array $headers = [];
    protected array $options = [];
    protected ?array $auth = null;
    protected ?string $body = null;
    protected int $timeout = 30;
    protected int $retry = 0;
    protected int $retryDelayMs = 100;

    /* -----------------------------------------------------------------
     | Fluent builders
     |-----------------------------------------------------------------*/

    public static function make(): self
    {
        return new self();
    }

    public function withHeaders(array $headers): self
    {
        foreach ($headers as $k => $v) {
            $this->headers[$k] = $v;
        }
        return $this;
    }

    public function withToken(string $token, string $type = 'Bearer'): self
    {
        return $this->withHeaders([
            'Authorization' => "{$type} {$token}",
        ]);
    }

    public function withBasicAuth(string $username, string $password): self
    {
        $this->auth = [$username, $password];
        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    public function retry(int $times, int $delayMs = 100): self
    {
        $this->retry = $times;
        $this->retryDelayMs = $delayMs;
        return $this;
    }

    /* -----------------------------------------------------------------
     | Body builders
     |-----------------------------------------------------------------*/

    public function json(array $data): self
    {
        $this->body = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->headers['Content-Type'] = 'application/json';
        return $this;
    }

    public function form(array $data): self
    {
        $this->body = http_build_query($data);
        $this->headers['Content-Type'] = 'application/x-www-form-urlencoded';
        return $this;
    }

    public function body(string $raw): self
    {
        $this->body = $raw;
        return $this;
    }

    /* -----------------------------------------------------------------
     | HTTP verbs
     |-----------------------------------------------------------------*/

    public function get(string $url, array $query = []): ClientResponse
    {
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $this->send('GET', $url);
    }

    public function post(string $url): ClientResponse
    {
        return $this->send('POST', $url);
    }

    public function put(string $url): ClientResponse
    {
        return $this->send('PUT', $url);
    }

    public function patch(string $url): ClientResponse
    {
        return $this->send('PATCH', $url);
    }

    public function delete(string $url): ClientResponse
    {
        return $this->send('DELETE', $url);
    }

    /* -----------------------------------------------------------------
     | Core sender
     |-----------------------------------------------------------------*/

    protected function send(string $method, string $url): ClientResponse
    {
        $attempts = 0;

        start:

        $attempts++;

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => $this->timeout,
        ]);

        if ($this->auth) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->auth[0]}:{$this->auth[1]}");
        }

        if ($this->body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
        }

        if (!empty($this->headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->formatHeaders());
        }

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);

        curl_close($ch);

        if ($raw === false || $error) {
            if ($attempts <= $this->retry) {
                usleep($this->retryDelayMs * 1000);
                goto start;
            }

            return ClientResponse::error($error ?: 'Curl error');
        }

        return ClientResponse::fromCurl($raw, $info);
    }

    protected function formatHeaders(): array
    {
        $result = [];

        foreach ($this->headers as $k => $v) {
            $result[] = "{$k}: {$v}";
        }

        return $result;
    }
}
