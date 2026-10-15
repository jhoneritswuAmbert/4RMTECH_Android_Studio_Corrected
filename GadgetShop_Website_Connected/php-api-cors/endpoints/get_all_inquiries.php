<?php
require_once __DIR__ . "/cors.php";


require_once "db.php";

header("Content-Type: application/json");

try {

   $sql = "SELECT
            i.inquiry_id,
            i.customer_id,
            i.message,
            i.date_submitted,

            c.first_name,
            c.last_name,
            c.email,
            c.contact_number

        FROM inquiries i

        INNER JOIN customers c
            ON i.customer_id = c.customer_id

        ORDER BY i.date_submitted DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "inquiries" => $inquiries
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve inquiries."
    ]);
}

?>