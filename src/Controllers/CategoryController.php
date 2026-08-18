<?php

namespace App\Controllers;

use App\Database;
use PDO;

class CategoryController
{
    public static function index(object $auth): void
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                type,
                color,
                icon,
                created_at
            FROM categories
            WHERE couple_id = :couple_id
            ORDER BY name
        ");

        $stmt->execute([
            'couple_id' => $auth->couple_id
        ]);

        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $categories
        ]);
    }
    public static function store(object $auth): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $name = trim($input['name'] ?? '');
    $type = $input['type'] ?? '';
    $color = $input['color'] ?? null;
    $icon = $input['icon'] ?? null;

    if (!$name || !$type) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Nome e tipo são obrigatórios.'
        ]);

        return;
    }

    if (!in_array($type, ['income', 'expense'])) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Tipo de categoria inválido.'
        ]);

        return;
    }

    $pdo = Database::connect();

    $stmt = $pdo->prepare("
        INSERT INTO categories (
            couple_id,
            name,
            type,
            color,
            icon
        )
        VALUES (
            :couple_id,
            :name,
            :type,
            :color,
            :icon
        )
        RETURNING id, name, type, color, icon, created_at
    ");

    $stmt->execute([
        'couple_id' => $auth->couple_id,
        'name' => $name,
        'type' => $type,
        'color' => $color,
        'icon' => $icon
    ]);

    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Categoria criada com sucesso.',
        'data' => $category
    ]);
}

public static function update(object $auth, int $id): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $name = trim($input['name'] ?? '');
    $type = $input['type'] ?? '';
    $color = $input['color'] ?? null;
    $icon = $input['icon'] ?? null;

    if (!$name || !$type) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Nome e tipo são obrigatórios.'
        ]);

        return;
    }

    if (!in_array($type, ['income', 'expense'])) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Tipo de categoria inválido.'
        ]);

        return;
    }

    $pdo = Database::connect();

    $stmt = $pdo->prepare("
        UPDATE categories
        SET
            name = :name,
            type = :type,
            color = :color,
            icon = :icon
        WHERE id = :id
        AND couple_id = :couple_id
        RETURNING id, name, type, color, icon, created_at
    ");

    $stmt->execute([
        'id' => $id,
        'couple_id' => $auth->couple_id,
        'name' => $name,
        'type' => $type,
        'color' => $color,
        'icon' => $icon
    ]);

    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Categoria não encontrada.'
        ]);

        return;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Categoria atualizada com sucesso.',
        'data' => $category
    ]);
}
public static function delete(object $auth, int $id): void
{
    $pdo = Database::connect();

    $stmt = $pdo->prepare("
        DELETE FROM categories
        WHERE id = :id
        AND couple_id = :couple_id
    ");

    $stmt->execute([
        'id' => $id,
        'couple_id' => $auth->couple_id
    ]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Categoria não encontrada.'
        ]);

        return;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Categoria excluída com sucesso.'
    ]);
}
}