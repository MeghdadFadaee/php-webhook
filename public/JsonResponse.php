<?php

class JsonResponse extends Response
{
    public static function successful(string $message, mixed $data = null): static
    {
        return static::json([
            'status' => 0,
            'message' => $message,
            'errors' => null,
            'data' => $data,
        ]);
    }

    public static function error(string|HttpStatus $message, ?array $errors = null, HttpStatus $status = HttpStatus::INTERNAL_SERVER_ERROR): static
    {
        if ($message instanceof HttpStatus) {
            $status = $message;
            $message = $message->message();
        }

        return static::json([
            'status' => 1,
            'message' => $message,
            'errors' => $errors,
            'data' => null,
        ], $status->value);
    }
}
