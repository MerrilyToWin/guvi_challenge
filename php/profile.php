<?php
// php/profile.php
header('Content-Type: application/json');

// Log errors, don't throw them into JSON
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/profile_error.log');

require __DIR__ . '/../vendor/autoload.php';

use Predis\Client as PredisClient;
use MongoDB\Client as MongoClient;

// Small helper for JSON responses
function json_response(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

// -------------------
// 1) Redis connection (sessions)
// -------------------
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

// -------------------
// 2) MongoDB connection (profile data)
// -------------------
try {
    $mongoClient = new MongoClient("mongodb://127.0.0.1:27017");
    $mongoDb = $mongoClient->selectDatabase('guvi_app');
    $profilesCollection = $mongoDb->selectCollection('profiles');
} catch (Exception $e) {
    json_response([
        'success' => false,
        'message' => 'MongoDB connection failed'
    ], 500);
}

// -------------------
// 3) Session helper
// -------------------
function getSessionUser(PredisClient $redis, ?string $token): ?array {
    if (!$token) return null;
    $data = $redis->get("session:$token");
    if (!$data) return null;
    return json_decode($data, true);
}

$method = $_SERVER['REQUEST_METHOD'];

// =======================================================
// GET → Fetch profile (name/email from Redis, age/dob/contact from Mongo)
// =======================================================
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
        'name'    => $session['name']  ?? null,  // from Redis session
        'email'   => $session['email'] ?? null,  // from Redis session
        'age'     => $doc['age']       ?? null,  // from Mongo
        'dob'     => $doc['dob']       ?? null,
        'contact' => $doc['contact']   ?? null,
        // No profile_pic / banner_pic fields anymore
    ];

    json_response([
        'success' => true,
        'profile' => $profile
    ]);
}

// =======================================================
// POST → Update age, dob, contact in Mongo (NO images)
// =======================================================
if ($method === 'POST') {
    // Logout branch
    if (!empty($_POST['logout'])) {
        $token = $_POST['token'] ?? '';
        if ($token) {
            $redis->del("session:$token");
        }
        json_response(['success' => true, 'message' => 'Logged out']);
    }

    $token   = $_POST['token'] ?? '';
    $session = getSessionUser($redis, $token);

    if (!$session) {
        json_response([
            'success' => false,
            'message' => 'Invalid or expired session'
        ], 401);
    }

    $userId = (int)($session['user_id'] ?? 0);

    $age     = $_POST['age'] ?? null;
    $dob     = $_POST['dob'] ?? null;
    $contact = $_POST['contact'] ?? null;

    if ($age !== null && $age !== '') {
        $age = (int)$age;
    } else {
        $age = null;
    }

    try {
        $profilesCollection->updateOne(
            ['user_id' => $userId],
            [
                '$set' => [
                    'user_id' => $userId,
                    'age'     => $age,
                    'dob'     => $dob,
                    'contact' => $contact,
                    // No profile_pic / banner_pic here
                ]
            ],
            ['upsert' => true]
        );

        json_response(['success' => true, 'message' => 'Profile updated']);
    } catch (Exception $e) {
        json_response([
            'success' => false,
            'message' => 'Profile update failed'
        ], 500);
    }
}

// Fallback for other HTTP methods
json_response(['success' => false, 'message' => 'Invalid method'], 405);
