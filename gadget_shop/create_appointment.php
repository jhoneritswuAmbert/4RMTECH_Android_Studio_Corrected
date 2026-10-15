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

 $authUser = authRequire($conn, "customer");


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

    // Get values
    $customerId = (int) ($input["customer_id"] ?? 0);
    authOwn($authUser, $customerId);
    $deviceType = trim($input["device_type"] ?? "");
    $deviceModel = trim($input["device_model"] ?? "");
    $serviceDescription =
        trim($input["service_description"] ?? "");
    $appointmentDate =
        trim($input["appointment_date"] ?? "");
    $appointmentTime =
        trim($input["appointment_time"] ?? "");

    // Validate required fields
    if (
        $customerId <= 0 ||
        $deviceType === "" ||
        $deviceModel === "" ||
        $serviceDescription === "" ||
        $appointmentDate === "" ||
        $appointmentTime === ""
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "All appointment fields are required."
        ]);

        exit;
    }

    // Check that customer exists
    $customerStmt = $conn->prepare(
        "SELECT customer_id
         FROM customers
         WHERE customer_id = :customer_id"
    );

    $customerStmt->execute([
        ":customer_id" => $customerId
    ]);

    if (!$customerStmt->fetch()) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Customer not found."
        ]);

        exit;
    }

    // Start transaction
    $conn->beginTransaction();

    // Create device
    $deviceStmt = $conn->prepare(
        "INSERT INTO devices (
            device_model,
            device_type
        )
        VALUES (
            :device_model,
            :device_type
        )
        RETURNING device_id"
    );

    $deviceStmt->execute([
        ":device_model" => $deviceModel,
        ":device_type" => $deviceType
    ]);

    $deviceId = (int) $deviceStmt->fetchColumn();

    // Create appointment
    // Employee ID 1 = Admin User
    $appointmentStmt = $conn->prepare(
        "INSERT INTO appointments (
            customer_id,
            employee_id,
            device_id,
            appointment_date,
            appointment_time,
            service_description,
            status
        )
        VALUES (
            :customer_id,
            1,
            :device_id,
            :appointment_date,
            :appointment_time,
            :service_description,
            'Pending'
        )
        RETURNING appointment_id"
    );

    $appointmentStmt->execute([
        ":customer_id" => $customerId,
        ":device_id" => $deviceId,
        ":appointment_date" => $appointmentDate,
        ":appointment_time" => $appointmentTime,
        ":service_description" => $serviceDescription
    ]);

    $appointmentId =
        (int) $appointmentStmt->fetchColumn();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Appointment created successfully.",
        "appointment_id" => $appointmentId
    ]);

} catch (PDOException $e) {

    // Roll back if something failed
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to create appointment."
    ]);
}

?>