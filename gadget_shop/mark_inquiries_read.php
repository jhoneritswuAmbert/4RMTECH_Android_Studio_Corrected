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
if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    header("Allow: POST, OPTIONS");
    authFail(405, "Only POST requests are allowed.");
}

 $authUser = authRequire($conn, "admin");


header("Content-Type: application/json");

try {
 $input=json_decode(file_get_contents('php://input'), true);
 $ids=is_array($input) ? ($input['inquiry_ids'] ?? null) : null;
 if (!is_array($ids) || count($ids) < 1 || count($ids) > 10000) authFail(400, 'Provide 1 to 10,000 inquiry IDs.');
 foreach ($ids as $id) {
  if (!is_int($id) || $id < 1) authFail(400, 'Inquiry IDs must be positive integers.');
 }
 $ids=array_values(array_unique($ids));
 $placeholders=implode(',', array_fill(0, count($ids), '?'));
 // Acknowledge only messages actually loaded by the client. New arrivals stay unread.
 $stmt=$conn->prepare("UPDATE inquiries SET admin_read_at=CURRENT_TIMESTAMP WHERE admin_read_at IS NULL AND inquiry_id IN ($placeholders)");
 $stmt->execute($ids);
 $count=(int) $conn->query("SELECT COUNT(*) FROM inquiries WHERE admin_read_at IS NULL")->fetchColumn();
 echo json_encode(['success'=>true, 'marked_count'=>$stmt->rowCount(), 'unread_count'=>$count]);
} catch (PDOException $e) { authFail(500, 'Could not clear the inquiry indicator. Please reopen Inquiries to retry.'); }
