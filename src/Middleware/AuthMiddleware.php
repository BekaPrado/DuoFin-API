<?php

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware
{
    public static function authenticate(): object
    {
        $headers = getallheaders();

        $authorization = $headers['Authorization']
            ?? $headers['authorization']
            ?? null;

        if (!$authorization || !preg_match('/Bearer\s+(.+)/i', $authorization, $matches)) {
            http_response_code(401);

            echo json_encode([
                'success' => false,
                'message' => 'Token não informado.'
            ]);

            exit;
        }

        $token = $matches[1];

        try {
            $decoded = JWT::decode(
                $token,
                new Key($_ENV['JWT_SECRET'], 'HS256')
            );

            return $decoded;

        } catch (\Throwable $e) {
            http_response_code(401);

            echo json_encode([
                'success' => false,
                'message' => 'Token inválido ou expirado.'
            ]);

            exit;
        }
    }
}