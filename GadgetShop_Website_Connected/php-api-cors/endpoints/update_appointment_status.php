<?php
require_once __DIR__ . "/cors.php";


header("Content-Type: application/json");

require_once "db.php";

try {

    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($data)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON request."
        ]);

        exit;
    }

    $appointmentId = $data["appointment_id"] ?? null;
    $status = trim($data["status"] ?? "");

    // Validate appointment ID
    if (!$appointmentId || !is_numeric($appointmentId)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Appointment ID is required."
        ]);

        exit;
    }

    // Validate status
    $allowedStatuses = [
        "Pending",
        "Approved",
        "Completed",
        "Cancelled"
    ];

    if (!in_array($status, $allowedStatuses, true)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid appointment status."
        ]);

        exit;
    }

    // Check appointment exists
    $check = $conn->prepare(
        "SELECT appointment_id, status
         FROM appointments
         WHERE appointment_id = :appointment_id"
    );

    $check->execute([
        ":appointment_id" => $appointmentId
    ]);

    $appointment = $check->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Appointment not found."
        ]);

        exit;
    }

    // Update status
    $sql = "
        UPDATE appointments
        SET status = :status
        WHERE appointment_id = :appointment_id
        RETURNING
            appointment_id,
            status
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":appointment_id" => (int) $appointmentId,
        ":status" => $status
    ]);

    $updatedAppointment =
        $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "message" => "Appointment status updated successfully.",
        "appointment" => $updatedAppointment
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to update appointment status."
    ]);
}

?>