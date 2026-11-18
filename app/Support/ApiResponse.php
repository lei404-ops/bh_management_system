<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

/** Consistent JSON responses for the fetch() API endpoints (the shape app.js expects). */
final class ApiResponse
{
    public static function ok($data = null, string $message = 'OK'): JsonResponse
    {
        return self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    public static function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return self::json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }

    private static function json(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status, ['Cache-Control' => 'no-store'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
