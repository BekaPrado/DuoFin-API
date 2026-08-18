<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\UserController;
use App\Controllers\AuthController;
use App\Middleware\AuthMiddleware;
use App\Controllers\CategoryController;
use App\Controllers\AccountController;
use App\Controllers\FinancialEntryController;


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

try {

    if ($method === 'POST' && $uri === '/api/login') {
        AuthController::login();
        exit;
    }

    if ($method === 'GET' && $uri === '/api/categories') {
        $auth = AuthMiddleware::authenticate();
    
        CategoryController::index($auth);
        exit;
    }

    if ($method === 'POST' && $uri === '/api/categories') {
        $auth = AuthMiddleware::authenticate();
    
        CategoryController::store($auth);
        exit;
    }

    if ($method === 'PUT' && preg_match('#^/api/categories/(\d+)$#', $uri, $matches)) {
        $auth = AuthMiddleware::authenticate();
    
        CategoryController::update($auth, (int) $matches[1]);
        exit;
    }

    if ($method === 'DELETE' && preg_match('#^/api/categories/(\d+)$#', $uri, $matches)) {
        $auth = AuthMiddleware::authenticate();
    
        CategoryController::delete($auth, (int) $matches[1]);
        exit;
    }

    if ($method === 'GET' && $uri === '/api/accounts') {
        $auth = AuthMiddleware::authenticate();
    
        AccountController::index($auth);
        exit;
    }
    
    if ($method === 'POST' && $uri === '/api/accounts') {
        $auth = AuthMiddleware::authenticate();
    
        AccountController::store($auth);
        exit;
    }

    if ($method === 'PUT' && preg_match('#^/api/accounts/(\d+)$#', $uri, $matches)) {
        $auth = AuthMiddleware::authenticate();
    
        AccountController::update($auth, (int) $matches[1]);
        exit;
    }


    if ($method === 'DELETE' && preg_match('#^/api/accounts/(\d+)$#', $uri, $matches)) {
        $auth = AuthMiddleware::authenticate();
    
        AccountController::delete($auth, (int) $matches[1]);
        exit;
    }

    if ($method === 'GET' && $uri === '/api/entries') {
        $auth = AuthMiddleware::authenticate();
    
        FinancialEntryController::index($auth);
        exit;
    }
    
    if ($method === 'POST' && $uri === '/api/entries') {
        $auth = AuthMiddleware::authenticate();
    
        FinancialEntryController::store($auth);
        exit;
    }

    if (
        $method === 'PUT' &&
        preg_match('#^/api/entries/(\d+)$#', $uri, $matches)
    ) {
        $auth = AuthMiddleware::authenticate();
    
        FinancialEntryController::update(
            $auth,
            (int) $matches[1]
        );
    
        exit;
    }


    if (
        $method === 'DELETE' &&
        preg_match('#^/api/entries/(\d+)$#', $uri, $matches)
    ) {
        $auth = AuthMiddleware::authenticate();
    
        FinancialEntryController::delete(
            $auth,
            (int) $matches[1]
        );
    
        exit;
    }

    if ($method === 'GET' && $uri === '/api/users') {
        $auth = AuthMiddleware::authenticate();
    
        UserController::index();
        exit;
    }
    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Rota não encontrada'
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}