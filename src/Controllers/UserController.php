<?php

namespace App\Controllers;

use App\Database;
use PDO;

class UserController
{
    public static function index(): void
    {
        $pdo = Database::connect();

        $stmt = $pdo->query("
            SELECT
                id,
                couple_id,
                name,
                email,
                avatar,
                is_active,
                created_at
            FROM users
            ORDER BY id
        ");

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');

        echo json_encode([
            'success' => true,
            'data' => $users
        ]);
    }
}