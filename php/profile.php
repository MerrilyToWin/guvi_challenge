<?php
// php/profile.php
header('Content-Type: application/json');

ini_set('display_errors', 1);          // dev only
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use Predis\Client as PredisClient;
use MongoDB\Client as MongoClient;

// ---------------------------------------------------------------------
// Helper: send JSON and exit
// ---------------------------------------------------------------------
function json_response(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

// ---------------------------------------------------------------------
// 1) Redis connection  (token -> session)
// ---------------------------------------------------------------------
try {
    $redis = new PredisClient([
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
    ]);
} catch (Exception $e) {
    json_response([
        'success' => false,
        'message' => 'Redis connection failed: ' . $e->getMessage()
    ], 500);
}

// ---------------------------------------------------------------------
// 2) MongoDB connection  (profile storage)
//     DB: guvi_app
//     Collection: profiles
// ---------------------------------------------------------------------
try {
    $mongoClient = new MongoClient("mongodb://127.0.0.1:27017");
    $mongoDb = $mongoClient->selectDatabase('guvi_app');
    $profilesCollection = $mongoDb->selectCollection('profiles');
} catch (Exception $e) {
    json_response([
        'success' => false,
        'message' => 'MongoDB connection failed: ' . $e->getMessage()
    ], 500);
}

// ---------------------------------------------------------------------
// Helper: get session from Redis by token
// Redis value format (JSON string):
//  { "user_id": 1, "name": "Merwin", "email": "x@y.com" }
// ---------------------------------------------------------------------
function getSessionUser(PredisClient $redis, ?string $token): ?array {
    if (!$token) return null;
    $data = $redis->get("session:$token");
    if (!$data) return null;
    return json_decode($data, true);
}

$method = $_SERVER['REQUEST_METHOD'];

// =====================================================================
// GET  → Return profile data from Redis + MongoDB
// =====================================================================
if ($method === 'GET') {
    $token   = $_GET['token'] ?? '';
    $session = getSessionUser($redis, $token);

    if (!$session) {
        json_response([
            'success' => false,
            'message' => 'Invalid or expired session (token not found in Redis)'
        ]);
    }

    $userId = (int)($session['user_id'] ?? 0);
    if ($userId <= 0) {
        json_response([
            'success' => false,
            'message' => 'Session missing user_id'
        ]);
    }

    // Fetch profile from MongoDB by user_id
    $profileDoc = $profilesCollection->findOne(['user_id' => $userId]) ?? null;

    $profile = [
        // from Redis session (MySQL-based auth)
        'name'        => $session['name']  ?? null,
        'email'       => $session['email'] ?? null,

        // from Mongo profile doc
        'age'         => $profileDoc['age']         ?? null,
        'dob'         => $profileDoc['dob']         ?? null,
        'contact'     => $profileDoc['contact']     ?? null,
        'profile_pic' => $profileDoc['profile_pic'] ?? null,
        'banner_pic'  => $profileDoc['banner_pic']  ?? null,
    ];

    json_response(['success' => true, 'profile' => $profile]);
}

// =====================================================================
// POST → Update profile in MongoDB OR logout
// =====================================================================
if ($method === 'POST') {
    // ----- LOGOUT branch -----
    if (!empty($_POST['logout'])) {
        $token = $_POST['token'] ?? '';
        if ($token) {
            $redis->del("session:$token");
        }
        json_response(['success' => true, 'message' => 'Logged out']);
    }

    // ----- UPDATE PROFILE branch -----
    $token   = $_POST['token'] ?? '';
    $session = getSessionUser($redis, $token);

    if (!$session) {
        json_response([
            'success' => false,
            'message' => 'Invalid or expired session on update'
        ]);
    }

    $userId = (int)($session['user_id'] ?? 0);
    if ($userId <= 0) {
        json_response([
            'success' => false,
            'message' => 'Session missing user_id on update'
        ]);
    }

    // Values from form
    $age     = $_POST['age'] ?? null;
    $dob     = $_POST['dob'] ?? null;
    $contact = $_POST['contact'] ?? null;

    if ($age !== null && $age !== '') {
        $age = (int)$age;
    } else {
        $age = null;
    }

    // Existing Mongo doc (to keep old image paths if no new upload)
    $existing = $profilesCollection->findOne(['user_id' => $userId]) ?? [];

    $profilePicPath = $existing['profile_pic'] ?? null;
    $bannerPicPath  = $existing['banner_pic'] ?? null;

    // Directory for uploads (relative to project root)
    $uploadDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Handle profile image upload
    if (!empty($_FILES['profile_image']['tmp_name'])) {
        $ext      = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $userId . '_' . time() . '.' . $ext;
        $target   = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target)) {
            // path that front-end can use
            $profilePicPath = 'uploads/' . $filename;
        }
    }

    // Handle banner image upload
    if (!empty($_FILES['banner_image']['tmp_name'])) {
        $ext      = pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION);
        $filename = 'banner_' . $userId . '_' . time() . '.' . $ext;
        $target   = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $target)) {
            $bannerPicPath = 'uploads/' . $filename;
        }
    }

    // Data to upsert into MongoDB
    $setData = [
        'user_id' => $userId,
        'age'     => $age,
        'dob'     => $dob,
        'contact' => $contact,
    ];

    if ($profilePicPath) {
        $setData['profile_pic'] = $profilePicPath;
    }
    if ($bannerPicPath) {
        $setData['banner_pic'] = $bannerPicPath;
    }

    // Upsert profile
    $profilesCollection->updateOne(
        ['user_id' => $userId],
        ['$set' => $setData],
        ['upsert' => true]
    );

    json_response(['success' => true, 'message' => 'Profile updated']);
}

// Any other HTTP method:
json_response(['success' => false, 'message' => 'Invalid method'], 405);
