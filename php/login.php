<?php
header('Content-Type: application/json');

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

require __DIR__ . '/../vendor/autoload.php';

use Predis\Client as PredisClient;

/* ===============================
   MYSQL CONNECTION (FIXED)
================================ */
try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=guvi_app;charset=utf8mb4',
        'root',
        '1234'
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

/* ===============================
   REDIS CONNECTION
================================ */
$redis = new PredisClient([
    'scheme' => 'tcp',
    'host'   => '127.0.0.1',
    'port'   => 6379,
]);

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Email and password required']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    exit;
}

$token = bin2hex(random_bytes(32));

$redis->setex(
    "session:$token",
    3600,
    json_encode([
        'user_id' => $user['id'],
        'name'    => $user['name'],
        'email'   => $user['email'],
    ])
);

echo json_encode([
    'success' => true,
    'token'   => $token,
    'user'    => [
        'id'    => $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
    ]
]);
