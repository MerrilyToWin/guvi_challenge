<?php
// php/login.php
header('Content-Type: application/json');

// Show errors while developing
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Composer autoload (Predis, Mongo, etc.)
require __DIR__ . '/../vendor/autoload.php';

use Predis\Client as PredisClient;

// 1. MySQL connection
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=guvi_app;charset=utf8mb4',
        'root',     // your MySQL user
        '1234'      // your MySQL password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// 2. Redis connection via Predis
try {
    $redis = new PredisClient([
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Redis connection failed: ' . $e->getMessage()
    ]);
    exit;
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Email and password are required'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password'
        ]);
        exit;
    }

    // Create session token
    $token = bin2hex(random_bytes(32));

    $sessionData = json_encode([
        'user_id' => $user['id'],
        'name'    => $user['name'],
        'email'   => $user['email'],
    ]);

    // Store in Redis with expiry (e.g., 1 hour)
    $redis->setex("session:$token", 3600, $sessionData);

    echo json_encode([
        'success' => true,
        'token'   => $token,
        'user'    => [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error during login: ' . $e->getMessage()
    ]);
}
