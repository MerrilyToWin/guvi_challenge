<?php
// php/profile.php
header('Content-Type: application/json');

/* =========================================================
   1. ERROR LOGGING (PRODUCTION-SAFE)
========================================================= */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/profile_error.log');

/* =========================================================
   2. AUTOLOAD & NAMESPACES
========================================================= */
require __DIR__ . '/../vendor/autoload.php';

use Predis\Client as PredisClient;
use MongoDB\Client as MongoClient;

/* =========================================================
   3. COMMON JSON RESPONSE HELPER
========================================================= */
function json_response(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

/* =========================================================
   4. REDIS CONNECTION (SESSIONS)
========================================================= */
try {
    $redis = new PredisClient([
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
    ]);
} catch (Exception $e) {
    json_response([
        'success' => false,
        'message' => 'Redis connection failed'
    ], 500);
}

/* =========================================================
   5. MONGODB CONNECTION (PROFILE DATA)
========================================================= */
try {
    $mongoClient = new MongoClient('mongodb://127.0.0.1:27017');
    $mongoDb = $mongoClient->selectDatabase('guvi_app');
    $profilesCollection = $mongoDb->selectCollection('profiles');
} catch (Exception $e) {
    json_response([
        'success' => false,
        'message' => 'MongoDB connection failed'
    ], 500);
}

/* =========================================================
   6. SESSION HELPER
========================================================= */
function getSessionUser(PredisClient $redis, ?string $token): ?array {
    if (!$token) {
        return null;
    }

    $data = $redis->get("session:$token");
    if (!$data) {
        return null;
    }

    return json_decode($data, true);
}

/* =========================================================
   7. REQUEST METHOD ROUTING
========================================================= */
$method = $_SERVER['REQUEST_METHOD'];

/* =========================================================
   GET → FETCH PROFILE
========================================================= */
if ($method === 'GET') {

    $token   = $_GET['token'] ?? '';
    $session = getSessionUser($redis, $token);

    if (!$session) {
        json_response([
            'success' => false,
            'message' => 'Invalid or expired session'
        ], 401);
    }

    $userId = (int)($session['user_id'] ?? 0);

    // Fetch profile from MongoDB
    $doc = $profilesCollection->findOne(['user_id' => $userId]) ?? [];

    $profile = [
        'name'    => $session['name']  ?? null,
        'email'   => $session['email'] ?? null,
        'age'     => $doc['age']       ?? null,
        'dob'     => $doc['dob']       ?? null,
        'contact' => $doc['contact']   ?? null,
    ];

    json_response([
        'success' => true,
        'profile' => $profile
    ]);
}

/* =========================================================
   POST → UPDATE PROFILE / LOGOUT
========================================================= */
if ($method === 'POST') {

    /* -------------------
       LOGOUT
    ------------------- */
    if (!empty($_POST['logout'])) {
        $token = $_POST['token'] ?? '';
        if ($token) {
            $redis->del("session:$token");
        }

        json_response([
            'success' => true,
            'message' => 'Logged out'
        ]);
    }

    /* -------------------
       SESSION VALIDATION
    ------------------- */
    $token   = $_POST['token'] ?? '';
    $session = getSessionUser($redis, $token);

    if (!$session) {
        json_response([
            'success' => false,
            'message' => 'Invalid or expired session'
        ], 401);
    }

    $userId = (int)($session['user_id'] ?? 0);

    /* -------------------
       INPUT HANDLING
    ------------------- */
    $age     = $_POST['age'] ?? null;
    $dob     = $_POST['dob'] ?? null;
    $contact = $_POST['contact'] ?? null;

    $age = ($age !== null && $age !== '') ? (int)$age : null;

    /* -------------------
       UPDATE MONGODB
    ------------------- */
    try {
        $profilesCollection->updateOne(
            ['user_id' => $userId],
            [
                '$set' => [
                    'user_id' => $userId,
                    'age'     => $age,
                    'dob'     => $dob,
                    'contact' => $contact,
                ]
            ],
            ['upsert' => true]
        );

        json_response([
            'success' => true,
            'message' => 'Profile updated'
        ]);

    } catch (Exception $e) {
        json_response([
            'success' => false,
            'message' => 'Profile update failed'
        ], 500);
    }
}

/* =========================================================
   FALLBACK
========================================================= */
json_response([
    'success' => false,
    'message' => 'Invalid method'
], 405);