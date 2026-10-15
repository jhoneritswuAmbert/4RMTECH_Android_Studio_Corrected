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

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "OPTIONS") {
    http_response_code(204);
    exit;
}


header("Content-Type: application/json");

require_once "db.php";

try {

    $sql = "
        SELECT
            c.category_id,
            c.category_name,
            c.description,
            COUNT(p.product_id) AS product_count
        FROM categories c
        LEFT JOIN products p
            ON p.category_id = c.category_id
        GROUP BY
            c.category_id,
            c.category_name,
            c.description
        ORDER BY c.category_id ASC
    ";

    $stmt = $conn->query($sql);

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "categories" => $categories
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve categories."
    ]);
}

?>