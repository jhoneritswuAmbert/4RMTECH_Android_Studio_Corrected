<?php
require_once __DIR__ . '/cors.php';
header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
$user = authRequire($conn, 'any');
try {
    $stmt = $conn->prepare('DELETE FROM api_auth_tokens WHERE token_hash = :hash');
    $stmt->execute([':hash' => $user['token_hash']]);
    echo json_encode(['success' => true, 'message' => 'Logged out.']);
} catch (PDOException $e) { authFail(500, 'Could not revoke session.'); }
