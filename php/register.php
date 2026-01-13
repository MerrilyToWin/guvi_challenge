<?php
// php/register.php
header('Content-Type: application/json');

/* ===============================
   ERROR LOGGING
================================ */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/register_error.log');

require __DIR__ . '/../vendor/autoload.php';

/* ===============================
   HELPER: STRONG PASSWORD
================================ */
function is_strong_password(string $password): bool {
    if (strlen($password) < 12) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/[a-z]/', $password)) return false;
    if (!preg_match('/\d/', $password)) return false;
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return false;
    return true;
}

/* ===============================
   ALLOW ONLY POST
================================ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

/* ===============================
   MYSQL CONNECTION (FIXED)
================================ */
try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=guvi_app;charset=utf8mb4',
        'root',     // ✅ dedicated user
        '1234'           // ✅ correct password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('DB connection failed (register): ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

/* ===============================
   READ INPUTS
================================ */
$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm'] ?? '';

/* ===============================
   VALIDATION
================================ */
if ($name === '' || $email === '' || $password === '' || $confirm === '') {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

if ($password !== $confirm) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}

if (!is_strong_password($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 12 characters and include uppercase, lowercase, number and special character'
    ]);
    exit;
}

/* ===============================
   INSERT USER
================================ */
try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $insert = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)'
    );
    $insert->execute([$name, $email, $hash]);

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful. Please log in.'
    ]);

} catch (PDOException $e) {
    error_log('Register query failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error during registration']);
}
