<?php

namespace App\Presentation;

final class Json
{
    public static function send(int $status, mixed $payload): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(int $status, string $message): never
    {
        self::send($status, ['error' => $message]);
    }

    public static function body(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            self::error(400, 'JSON inválido.');
        }

        return $data;
    }
}
