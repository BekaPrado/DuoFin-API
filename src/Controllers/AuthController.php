<?php

namespace App\Controllers;

use App\Database;
use Firebase\JWT\JWT;
use PDO;

class AuthController
{
    public static function login(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        if (!$email || !$password) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Email e senha são obrigatórios.'
            ]);

            return;
        }

        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT
                id,
                couple_id,
                name,
                email,
                password_hash,
                avatar,
                is_active
            FROM users
            WHERE email = :email
            LIMIT 1
        ");

        $stmt->execute([
            'email' => $email
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            http_response_code(401);

            echo json_encode([
                'success' => false,
                'message' => 'Email ou senha inválidos.'
            ]);

            return;
        }

        if (!$user['is_active']) {
            http_response_code(403);

            echo json_encode([
                'success' => false,
                'message' => 'Usuário inativo.'
            ]);

            return;
        }

        $issuedAt = time();
        $expiration = $issuedAt + (60 * 60 * 24);

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expiration,
            'user_id' => $user['id'],
            'couple_id' => $user['couple_id']
        ];

        $token = JWT::encode(
            $payload,
            $_ENV['JWT_SECRET'],
            'HS256'
        );

        unset($user['password_hash']);

        echo json_encode([
            'success' => true,
            'message' => 'Login realizado com sucesso.',
            'token' => $token,
            'user' => $user
        ]);
    }
}