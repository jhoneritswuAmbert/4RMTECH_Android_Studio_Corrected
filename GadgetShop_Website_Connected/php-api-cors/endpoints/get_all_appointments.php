<?php
require_once __DIR__ . "/cors.php";


require_once "db.php";

header("Content-Type: application/json");

try {

    $sql = "SELECT
                a.appointment_id,
                a.customer_id,
                a.employee_id,
                a.device_id,
                a.appointment_date,
                a.appointment_time,
                a.service_description,
                a.status,

                c.first_name,
                c.last_name,
                c.contact_number,
                c.email,

                d.device_model,
                d.device_type,

                e.full_name AS employee_name,
                e.role AS employee_role

            FROM appointments a

            INNER JOIN customers c
                ON a.customer_id = c.customer_id

            INNER JOIN devices d
                ON a.device_id = d.device_id

            INNER JOIN employees e
                ON a.employee_id = e.employee_id

            ORDER BY
                a.appointment_date DESC,
                a.appointment_time DESC";

    $stmt = $conn->prepare($sql);

    $stmt->execute();

    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

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