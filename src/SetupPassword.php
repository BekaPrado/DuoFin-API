<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use App\Database;

$pdo = Database::connect();

$email = 'luiz.barreto@gmail.com';
$password = '19Rebeka2008';

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    UPDATE users
    SET password_hash = :password_hash
    WHERE email = :email
");

$stmt->execute([
    'password_hash' => $hash,
    'email' => $email
]);

echo "Senha cadastrada com sucesso!";