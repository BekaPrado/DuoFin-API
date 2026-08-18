<?php

namespace App\Controllers;

use App\Database;
use PDO;

class AccountController
{
    public static function index(object $auth): void
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                institution,
                type,
                balance,
                responsible_user_id,
                is_active,
                created_at,
                updated_at
            FROM accounts
            WHERE couple_id = :couple_id
            ORDER BY name
        ");

        $stmt->execute([
            'couple_id' => $auth->couple_id
        ]);

        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $accounts
        ]);
    }

    public static function store(object $auth): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        $name = trim($input['name'] ?? '');
        $institution = trim($input['institution'] ?? '');
        $type = $input['type'] ?? '';
        $balance = $input['balance'] ?? 0;
        $responsibleUserId = $input['responsible_user_id'] ?? null;

        if (!$name || !$type) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Nome e tipo são obrigatórios.'
            ]);

            return;
        }

        $allowedTypes = [
            'bank',
            'joint',
            'savings',
            'wallet',
            'reserve',
            'other'
        ];

        if (!in_array($type, $allowedTypes, true)) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Tipo de conta inválido.'
            ]);

            return;
        }

        $pdo = Database::connect();

        /*
         * Se um responsável foi informado,
         * verificamos se ele pertence ao mesmo casal.
         */
        if ($responsibleUserId !== null) {
            $userStmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE id = :user_id
                AND couple_id = :couple_id
                AND is_active = true
            ");

            $userStmt->execute([
                'user_id' => $responsibleUserId,
                'couple_id' => $auth->couple_id
            ]);

            if (!$userStmt->fetch()) {
                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => 'Usuário responsável inválido.'
                ]);

                return;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO accounts (
                couple_id,
                name,
                institution,
                type,
                balance,
                responsible_user_id,
                is_active
            )
            VALUES (
                :couple_id,
                :name,
                :institution,
                :type,
                :balance,
                :responsible_user_id,
                true
            )
            RETURNING
                id,
                name,
                institution,
                type,
                balance,
                responsible_user_id,
                is_active,
                created_at,
                updated_at
        ");

        $stmt->execute([
            'couple_id' => $auth->couple_id,
            'name' => $name,
            'institution' => $institution ?: null,
            'type' => $type,
            'balance' => $balance,
            'responsible_user_id' => $responsibleUserId
        ]);

        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Conta criada com sucesso.',
            'data' => $account
        ]);
    }
    public static function update(object $auth, int $id): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        $name = trim($input['name'] ?? '');
        $institution = trim($input['institution'] ?? '');
        $type = $input['type'] ?? '';
        $balance = $input['balance'] ?? 0;
        $responsibleUserId = $input['responsible_user_id'] ?? null;

        if (!$name || !$type) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Nome e tipo são obrigatórios.'
            ]);

            return;
        }

        $allowedTypes = [
            'bank',
            'joint',
            'savings',
            'wallet',
            'reserve',
            'other'
        ];

        if (!in_array($type, $allowedTypes, true)) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Tipo de conta inválido.'
            ]);

            return;
        }

        $pdo = Database::connect();

        if ($responsibleUserId !== null) {
            $userStmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE id = :user_id
                AND couple_id = :couple_id
                AND is_active = true
            ");

            $userStmt->execute([
                'user_id' => $responsibleUserId,
                'couple_id' => $auth->couple_id
            ]);

            if (!$userStmt->fetch()) {
                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => 'Usuário responsável inválido.'
                ]);

                return;
            }
        }

        $stmt = $pdo->prepare("
            UPDATE accounts
            SET
                name = :name,
                institution = :institution,
                type = :type,
                balance = :balance,
                responsible_user_id = :responsible_user_id,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
            AND couple_id = :couple_id
            RETURNING
                id,
                name,
                institution,
                type,
                balance,
                responsible_user_id,
                is_active,
                created_at,
                updated_at
        ");

        $stmt->execute([
            'id' => $id,
            'couple_id' => $auth->couple_id,
            'name' => $name,
            'institution' => $institution ?: null,
            'type' => $type,
            'balance' => $balance,
            'responsible_user_id' => $responsibleUserId
        ]);

        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            http_response_code(404);

            echo json_encode([
                'success' => false,
                'message' => 'Conta não encontrada.'
            ]);

            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Conta atualizada com sucesso.',
            'data' => $account
        ]);
    }

    public static function delete(object $auth, int $id): void
{
    $pdo = Database::connect();

    $stmt = $pdo->prepare("
        UPDATE accounts
        SET
            is_active = false,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
        AND couple_id = :couple_id
        AND is_active = true
        RETURNING id, name, is_active
    ");

    $stmt->execute([
        'id' => $id,
        'couple_id' => $auth->couple_id
    ]);

    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Conta não encontrada ou já está desativada.'
        ]);

        return;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Conta desativada com sucesso.',
        'data' => $account
    ]);
}
}