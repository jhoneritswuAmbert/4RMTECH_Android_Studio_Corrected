<?php

$origin = $_SERVER["HTTP_ORIGIN"] ?? "";

$allowedOrigins = [
    "http://localhost:5173",
    "http://127.0.0.1:5173"
];

if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: " . $origin);
    header("Vary: Origin");
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if (($_SERVER["REQUEST_METHOD"] ?? "") === "OPTIONS") {
    http_response_code(204);
    exit;
}

require_once "db.php";
require_once __DIR__ . "/auth.php";
if (($_SERVER["REQUEST_METHOD"] ?? "") !== "GET") {
    header("Allow: GET, OPTIONS");
    authFail(405, "Only GET requests are allowed.");
}

 $authUser = authRequire($conn, "admin");


header("Content-Type: application/json");

try {
 $count = (int) $conn->query("SELECT COUNT(*) FROM inquiries WHERE admin_read_at IS NULL")->fetchColumn();
 echo json_encode(['success'=>true, 'unread_count'=>$count]);
} catch (PDOException $e) { authFail(500, 'Could not check new inquiries. Check the inquiry database update.'); }
