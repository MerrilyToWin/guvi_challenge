<?php
// php/profile.php
header('Content-Type: application/json');

// Don't print PHP errors into JSON (log them instead)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/profile_error.log');

require __DIR__ . '/../vendor/autoload.php';

use Predis\Client as PredisClient;
use MongoDB\Client as MongoClient;

// 1. MySQL (optional – you can skip if not needed now)
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=guvi_app;charset=utf8mb4',
        'root',
        '1234'
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // We won't block just because MySQL failed – log and continue
    error_log('MySQL connection failed: ' . $e->getMessage());
}

// 2. Redis – REQUIRED (we store sessions here)
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
        'message' => 'Redis connection failed'
    ]);
    exit;
}

// 3. MongoDB – optional but used for profile fields & images
$profilesCollection = null;
try {
    $mongoClient = new MongoClient("mongodb://localhost:27017");
    $mongoDb = $mongoClient->selectDatabase('guvi_app');
    $profilesCollection = $mongoDb->selectCollection('profiles');
} catch (Exception $e) {
    // DO NOT exit – we still want to show name/email
    error_log('MongoDB connection failed: ' . $e->getMessage());
}

// Helper: read JSON session from Redis by token
function getSessionUser($redis, $token) {
    if (!$token) return null;
    $data = $redis->get("session:$token");
    if (!$data) return null;
    return json_decode($data, true);
}

$method = $_SERVER['REQUEST_METHOD'];

/**
 * GET: Return profile data
 * POST: Update profile data (and optional images)
 */

if ($method === 'GET') {
    $token = $_GET['token'] ?? '';
    $session = getSessionUser($redis, $token);

    if (!$session) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired session']);
        exit;
    }

    $userId = (int)($session['user_id'] ?? 0);

    $profileDoc = null;
    if ($profilesCollection) {
        try {
            $profileDoc = $profilesCollection->findOne(['user_id' => $userId]) ?? null;
        } catch (Exception $e) {
            error_log('Mongo findOne failed: ' . $e->getMessage());
        }
    }

    $profile = [
        // Always available from Redis session
        'name'       => $session['name']  ?? null,
        'email'      => $session['email'] ?? null,

        // From MongoDB if present
        'age'        => $profileDoc['age']        ?? null,
        'dob'        => $profileDoc['dob']        ?? null,
        'contact'    => $profileDoc['contact']    ?? null,
        'profile_pic'=> $profileDoc['profile_pic']?? null,
        'banner_pic' => $profileDoc['banner_pic'] ?? null,
    ];

    echo json_encode(['success' => true, 'profile' => $profile]);
    exit;
}

if ($method === 'POST') {
    // Logout branch
    if (!empty($_POST['logout'])) {
        $token = $_POST['token'] ?? '';
        if ($token) {
            $redis->del("session:$token");
        }
        echo json_encode(['success' => true, 'message' => 'Logged out']);
        exit;
    }

    $token = $_POST['token'] ?? '';
    $session = getSessionUser($redis, $token);

    if (!$session) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired session']);
        exit;
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

    // If Mongo is not available, we can't save – but don't crash
    if (!$profilesCollection) {
        echo json_encode([
            'success' => false,
            'message' => 'Profile DB not available (MongoDB not connected)'
        ]);
        exit;
    }

    // Existing doc to keep old image paths if not updated
    try {
        $existing = $profilesCollection->findOne(['user_id' => $userId]) ?? [];
    } catch (Exception $e) {
        $existing = [];
        error_log('Mongo findOne before update failed: ' . $e->getMessage());
    }

    $profilePicPath = $existing['profile_pic'] ?? null;
    $bannerPicPath  = $existing['banner_pic'] ?? null;

    // Directory for uploads
    $uploadDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Handle profile image upload
    if (!empty($_FILES['profile_image']['tmp_name'])) {
        $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $userId . '_' . time() . '.' . $ext;
        $target = $uploadDir . '/' . $filename;

        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target)) {
            $profilePicPath = 'uploads/' . $filename;
        }
    }

    // Handle banner image upload
    if (!empty($_FILES['banner_image']['tmp_name'])) {
        $ext = pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION);
        $filename = 'banner_' . $userId . '_' . time() . '.' . $ext;
        $target = $uploadDir . '/' . $filename;

        if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $target)) {
            $bannerPicPath = 'uploads/' . $filename;
        }
    }

    try {
        $setData = [
            'user_id'    => $userId,
            'age'        => $age,
            'dob'        => $dob,
            'contact'    => $contact,
        ];

        if ($profilePicPath) {
            $setData['profile_pic'] = $profilePicPath;
        }
        if ($bannerPicPath) {
            $setData['banner_pic'] = $bannerPicPath;
        }

        $profilesCollection->updateOne(
            ['user_id' => $userId],
            ['$set' => $setData],
            ['upsert' => true]
        );

        echo json_encode(['success' => true, 'message' => 'Profile updated']);
    } catch (Exception $e) {
        error_log('Mongo updateOne failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Profile update failed'
        ]);
    }

    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid method']);
