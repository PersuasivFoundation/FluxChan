<?php
// upload.php — Handles image uploads and returns the filename

$uploadDir = 'uploads/';
@mkdir($uploadDir, 0777, true);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No image uploaded.']);
    exit;
}

$image = $_FILES['image'];

if ($image['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Upload failed.']);
    exit;
}

$ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!in_array($ext, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid image type.']);
    exit;
}

$filename = uniqid() . '.' . $ext;
$target = $uploadDir . $filename;

if (!move_uploaded_file($image['tmp_name'], $target)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save image.']);
    exit;
}

echo json_encode(['success' => true, 'filename' => $filename]);
