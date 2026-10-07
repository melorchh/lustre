<?php
require __DIR__ . '/../src/session.php';

if (!isset($_SESSION["admin_id"])) {
    echo "error: Unauthorized access";
    exit;
}

require_once __DIR__ . '/../src/db.php';

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if ($action === 'list') {
    header('Content-Type: application/json');
    $rows = $conn->query("SELECT id, name FROM doctor_specialties ORDER BY name");
    $out = [];
    if ($rows) {
        while ($r = $rows->fetch_assoc()) {
            $out[] = ['id' => (int)$r['id'], 'name' => $r['name']];
        }
    }
    echo json_encode($out);
    $conn->close();
    exit;
}

if ($action === 'add') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    if ($name === '') { echo "error: Name is required"; $conn->close(); exit; }
    if (strlen($name) > 140) { echo "error: Name is too long (max 140 characters)"; $conn->close(); exit; }
    $stmt = $conn->prepare("INSERT INTO doctor_specialties (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    if ($stmt->execute()) {
        echo "success";
    } else {
        echo (stripos($stmt->error, 'Duplicate') !== false)
            ? "error: That specialty already exists"
            : "error: Failed to add - " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
    exit;
}

if ($action === 'rename') {
    $id   = isset($_POST['id'])   ? intval($_POST['id'])   : 0;
    $name = isset($_POST['name']) ? trim($_POST['name'])   : '';
    if ($id <= 0 || $name === '') { echo "error: Invalid request"; $conn->close(); exit; }
    if (strlen($name) > 140) { echo "error: Name is too long (max 140 characters)"; $conn->close(); exit; }
    $stmt = $conn->prepare("UPDATE doctor_specialties SET name = ? WHERE id = ?");
    $stmt->bind_param("si", $name, $id);
    if ($stmt->execute()) {
        echo "success";
    } else {
        echo (stripos($stmt->error, 'Duplicate') !== false)
            ? "error: That specialty already exists"
            : "error: Failed to rename - " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
    exit;
}

if ($action === 'delete') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) { echo "error: Invalid request"; $conn->close(); exit; }
    $stmt = $conn->prepare("DELETE FROM doctor_specialties WHERE id = ?");
    $stmt->bind_param("i", $id);
    echo $stmt->execute() ? "success" : "error: Failed to delete - " . $stmt->error;
    $stmt->close();
    $conn->close();
    exit;
}

echo "error: Unknown action";
$conn->close();