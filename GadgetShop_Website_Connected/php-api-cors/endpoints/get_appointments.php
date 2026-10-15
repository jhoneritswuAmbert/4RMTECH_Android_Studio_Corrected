<?php
require_once __DIR__ . "/cors.php";


require_once "db.php";

header("Content-Type: application/json");

try {

    // Get customer ID from URL
    $customerId = isset($_GET["customer_id"])
        ? (int) $_GET["customer_id"]
        : 0;

    // Validate customer ID
    if ($customerId <= 0) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Valid customer ID is required."
        ]);

        exit;
    }

    // Get appointments for this customer
    $sql = "SELECT
                a.appointment_id,
                a.customer_id,
                a.employee_id,
                a.device_id,
                a.appointment_date,
                a.appointment_time,
                a.service_description,
                a.status,
                d.device_model,
                d.device_type,
                e.full_name AS employee_name,
                e.role AS employee_role
            FROM appointments a

            INNER JOIN devices d
                ON a.device_id = d.device_id

            INNER JOIN employees e
                ON a.employee_id = e.employee_id

            WHERE a.customer_id = :customer_id

            ORDER BY
                a.appointment_date DESC,
                a.appointment_time DESC";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":customer_id" => $customerId
    ]);

    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return appointments
    echo json_encode([
        "success" => true,
        "appointments" => $appointments
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve appointments."
    ]);
}

?>