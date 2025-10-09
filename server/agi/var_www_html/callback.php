<?php
// UpdateInfoType enum:
// searching = -1
// en_route = 1
// arrived = 2
// pickup = 3
// dropOff = 8
// accepted = 10
// failed = 20
// passenger_canceled = 30
// driver_canceled = 31
// admin_canceled = 32
// payment_registered = 40
// driver_time_registered = 50
// driver_select_response = 60
// driver_select_result = 70
// reservation_modified = 80
// completed = 100
// message = 101
// on_location = 255 (FILTERED - not saved)

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Validate and sanitize path parameter
$path = $_GET['path'] ?? '';
if (empty($path)) {
    http_response_code(400);
    echo json_encode(['error' => 'Path parameter is required']);
    exit();
}

// Security: Prevent path traversal attacks
$path = realpath($path);
if ($path === false || strpos($path, '..') !== false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid path']);
    exit();
}

// Parse and validate JSON input
$input = file_get_contents('php://input');
if (empty($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty request body']);
    exit();
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit();
}

// Validate required fields
if (!isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required field: status']);
    exit();
}

// Filter out status 255 (on_location) - we don't want to save these
if ((int)$data['status'] === 255) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'saved' => false,
        'message' => 'Status 255 (on_location) filtered, not saved'
    ]);
    exit();
}

// Prepare file path
$registerInfoFile = $path . '/register_info.json';
$registerInfoDir = dirname($registerInfoFile);

// Create directory if it doesn't exist
if (!is_dir($registerInfoDir)) {
    if (!mkdir($registerInfoDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create directory']);
        exit();
    }
}

// Check if directory is writable
if (!is_writable($registerInfoDir)) {
    http_response_code(500);
    echo json_encode(['error' => 'Directory is not writable']);
    exit();
}

// Atomic write: write to temporary file first, then rename
$tempFile = $registerInfoFile . '.tmp.' . uniqid();
$jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if ($jsonData === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to encode JSON']);
    exit();
}

// Write to temporary file
if (file_put_contents($tempFile, $jsonData, LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to write data']);
    exit();
}

// Atomic rename
if (!rename($tempFile, $registerInfoFile)) {
    @unlink($tempFile); // Clean up temp file
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save data']);
    exit();
}

// Success response
http_response_code(200);
echo json_encode([
    'success' => true,
    'saved' => true,
    'status' => $data['status'],
    'tripID' => $data['tripID'] ?? null
]);
?>