<?php

require_once "db.php";

header("Content-Type: application/json");

$sql = "SELECT 
            category_id,
            category_name,
            description
        FROM categories
        ORDER BY category_id ASC";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve categories."
    ]);

    exit;
}

$categories = [];

while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

echo json_encode([
    "success" => true,
    "categories" => $categories
]);

$conn->close();

?>