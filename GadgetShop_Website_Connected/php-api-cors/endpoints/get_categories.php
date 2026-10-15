<?php
require_once __DIR__ . "/cors.php";


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