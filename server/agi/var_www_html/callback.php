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
// on_location = 255

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$path = $_GET['path'] ?? '';

if (empty($path)) {
    http_response_code(400);
    echo json_encode(['error' => 'Path parameter is required']);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit();
}

$registerInfoFile = $path . '/register_info.json';
$registerInfoDir = dirname($registerInfoFile);

if (!is_dir($registerInfoDir)) {
    mkdir($registerInfoDir, 0777, true);
}

file_put_contents($registerInfoFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

http_response_code(200);
echo json_encode(['success' => true]);
?>