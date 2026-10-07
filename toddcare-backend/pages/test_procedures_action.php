<?php
require __DIR__ . '/../src/session.php';

if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/test_procedures_schema.php';
test_procedures_ensure($conn);

header('Content-Type: application/json');
$rows = $conn->query("SELECT id, name FROM test_procedures ORDER BY name");
$out = [];
if ($rows) {
    while ($r = $rows->fetch_assoc()) {
        $out[] = ['id' => (int)$r['id'], 'name' => $r['name']];
    }
}
echo json_encode($out);
$conn->close();