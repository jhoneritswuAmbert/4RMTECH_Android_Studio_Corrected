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

header("Content-Type: application/json");

require_once "db.php";
require_once __DIR__ . "/auth.php";
if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    header("Allow: POST, OPTIONS");
    authFail(405, "Only POST requests are allowed.");
}

 $authUser = authRequire($conn, "admin");


try {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!is_array($data)) authFail(400, "Invalid JSON request.");
    $id = filter_var($data['appointment_id'] ?? null, FILTER_VALIDATE_INT);
    $status = $data['status'] ?? null;
    if (!$id || $id < 1) authFail(400, "Valid appointment ID is required.");
    if (!is_string($status)) authFail(400, "Invalid appointment status.");
    $status = trim($status);
    $transitions = [
        'Pending' => ['Approved', 'Cancelled'],
        'Approved' => ['Ready to Claim'],
        'Ready to Claim' => ['Completed'],
        'Completed' => [], 'Cancelled' => []
    ];
    if (!array_key_exists($status, $transitions)) authFail(400, "Invalid appointment status.");
    $notes = null;
    $total = null;
    if ($status === 'Ready to Claim') {
        if (!is_string($data['repair_notes'] ?? null)) authFail(400, "Repair notes are required.");
        $notes = trim($data['repair_notes']);
        if ($notes === '' || strlen($notes) > 12000) authFail(400, "Enter repair notes (maximum 12,000 bytes).");
        $rawTotal = $data['repair_total'] ?? null;
        if (!is_string($rawTotal) && !is_int($rawTotal) && !is_float($rawTotal)) authFail(400, "Enter a valid repair total.");
        $total = trim((string) $rawTotal);
        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/D', $total)) authFail(400, "Total must be between 0 and 99,999,999.99 pesos, with at most two decimal places.");
    }
    $check = $conn->prepare("SELECT status FROM appointments WHERE appointment_id = :id");
    $check->execute([':id' => $id]);
    $current = $check->fetchColumn();
    if ($current === false) authFail(404, "Appointment not found.");
    if (!in_array($status, $transitions[$current] ?? [], true)) authFail(409, "This status change is not allowed. Refresh appointments and try again.");
    // Compare the previous status in the UPDATE so concurrent requests cannot skip a step.
    $extra = $status === 'Ready to Claim'
        ? ', repair_notes = :notes, repair_total = :total, ready_to_claim_at = CURRENT_TIMESTAMP' : '';
    $stmt = $conn->prepare("UPDATE appointments SET status = :status" . $extra . "
        WHERE appointment_id = :id AND status = :current
        RETURNING appointment_id, status, repair_notes, repair_total, ready_to_claim_at");
    $params = [':id' => $id, ':status' => $status, ':current' => $current];
    if ($status === 'Ready to Claim') { $params[':notes'] = $notes; $params[':total'] = $total; }
    $stmt->execute($params);
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$updated) authFail(409, "Appointment changed in another request. Refresh and try again.");
    echo json_encode(['success' => true, 'message' => 'Appointment status updated.', 'appointment' => $updated]);
} catch (PDOException $e) {
    authFail(500, "Could not save appointment. Check that the Ready to Claim database update was applied.");
}
