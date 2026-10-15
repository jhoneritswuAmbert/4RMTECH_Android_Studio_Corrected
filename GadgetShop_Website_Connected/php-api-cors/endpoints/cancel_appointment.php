<?php
require_once __DIR__ . "/cors.php";


require_once "db.php";

header("Content-Type: application/json");

try {

    // Get JSON request
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!$input) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid request."
        ]);

        exit;
    }

    // Get appointment ID
    $appointmentId =
        (int) ($input["appointment_id"] ?? 0);

    // Get customer ID
    $customerId =
        (int) ($input["customer_id"] ?? 0);

    // Validate IDs
    if ($appointmentId <= 0 || $customerId <= 0) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Valid appointment and customer IDs are required."
        ]);

        exit;
    }

    // Check that the appointment belongs to the customer
    $checkStmt = $conn->prepare(
        "SELECT appointment_id, status
         FROM appointments
         WHERE appointment_id = :appointment_id
         AND customer_id = :customer_id"
    );

    $checkStmt->execute([
        ":appointment_id" => $appointmentId,
        ":customer_id" => $customerId
    ]);

    $appointment =
        $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Appointment not found."
        ]);

        exit;
    }

    // Only Pending appointments can be cancelled
    if (strtolower($appointment["status"]) !== "pending") {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Only pending appointments can be cancelled."
        ]);

        exit;
    }

    // Update appointment status
    $updateStmt = $conn->prepare(
        "UPDATE appointments
         SET status = 'Cancelled'
         WHERE appointment_id = :appointment_id
         AND customer_id = :customer_id"
    );

    $updateStmt->execute([
        ":appointment_id" => $appointmentId,
        ":customer_id" => $customerId
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Appointment cancelled successfully."
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to cancel appointment."
    ]);
}

?>