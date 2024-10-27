<?php
require_once ("../../php/db.php");
$link = connectDB();

$file = $_FILES['file'] ?? null;
if (!$file) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/rtf'];
if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid file type']);
    exit;
}

$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$newFileName = uniqid('', true) . '.' . $fileExtension;
$uploadPath = "../../docs/src/" . $newFileName;

if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'File upload failed']);
    exit;
}

$title = $_POST["title"] == "" ? null : $_POST["title"];
$category = $_POST["category"] == "" ? null : $_POST["title"];
$fileUrl = "/docs/src/" . $newFileName;
$insertQuery = "INSERT INTO docs (title, category, srcPdf) VALUES (?, ?, ?)";
$stmt = $link->prepare($insertQuery);
$stmt->bind_param('sss', $title, $category, $fileUrl);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database insert failed']);
    exit;
}

// Получение ID новой записи
$newId = $stmt->insert_id;

// Успешный JSON ответ
header("Location: /docs?a=success&id=".$newId);
exit();

