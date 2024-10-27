<?php
require_once ("../../php/db.php");
$link = connectDB();

// Шаг 1: Проверка заголовка Authorization
$headers = getallheaders();
if ($headers["Authorization"] !== '82kMDimsjagDnjsoNKmdmakNJdnvfmKKDKM') {
    http_response_code(403);
    echo json_encode(['status' => false, 'error' => 'Unauthorized']);
    exit;
}

// Шаг 2: Получение POST данных и GET параметра
$docID = $_GET['docID'] ?? null;
$category = $_POST['category'] ?? null;
$title = $_POST['title'] ?? null;
$file = $_FILES['file'] ?? null;

// Проверка обязательных полей
if (!$docID || !$category || !$title || !$file) {
    http_response_code(400);
    echo json_encode(['status' => false, 'error' => 'Missing required fields']);
    exit;
}

// Шаг 3: Запрос к базе данных на основе docID
$query = "SELECT * FROM docs WHERE id = ?";
$stmt = $link->prepare($query);
$stmt->bind_param('s', $docID);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['status' => false, 'error' => 'Document not found']);
    exit;
}

$row = $result->fetch_assoc();

// Шаг 4: Проверка поля srcTxt
if (!empty($row['srcTxt'])) {
    http_response_code(400);
    echo json_encode(['status' => false, 'error' => 'srcTxt already filled']);
    exit;
}

// Шаг 5а. Определение категорий
$cats = [];
$w = mysqli_query($link, "SELECT * FROM categories");
while ($c = mysqli_fetch_array($w)) {
    $cats[] = $c;
}
$dictionary = [];
foreach ($cats as $c) {
    $dictionary[$c["title"]] = $c["id"];
}

$category = $dictionary[$row["category"]] ?? $category;
$title = $row["title"] ?? $title;

// Шаг 5: Вставка категории и заголовка в базу данных
$updateQuery = "UPDATE docs SET category = ?, title = ? WHERE id = ?";
$updateStmt = $link->prepare($updateQuery);
$updateStmt->bind_param('sss', $category, $title, $docID);
$updateStmt->execute();

// Шаг 6: Загрузка файла с рандомным названием, сохраняя расширение
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$newFileName = uniqid('', true) . '.' . $fileExtension;
$uploadPath = "../../docs/src/" . $newFileName;

if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    http_response_code(500);
    echo json_encode(['status' => false, 'error' => 'File upload failed']);
    exit;
}

// Шаг 7: Обновление пути файла в базе данных
$fileUrl = "/docs/src/" . $newFileName;
$srcUpdateQuery = "UPDATE docs SET srcTxt = ? WHERE id = ?";
$srcUpdateStmt = $link->prepare($srcUpdateQuery);
$srcUpdateStmt->bind_param('ss', $fileUrl, $docID);
$srcUpdateStmt->execute();

// Шаг 8: Успешный вывод результата в JSON
echo json_encode(['status' => true]);