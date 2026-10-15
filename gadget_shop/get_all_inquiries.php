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

   $sql = "SELECT
            i.inquiry_id,
            i.customer_id,
            i.message,
            i.date_submitted,
            i.admin_read_at,

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

    foreach ($inquiries as &$item) { $item['is_new'] = $item['admin_read_at'] === null; }
    unset($item);

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