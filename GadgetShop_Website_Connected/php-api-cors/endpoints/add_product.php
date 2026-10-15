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

    $productName = trim($data["product_name"] ?? "");
    $brand = trim($data["brand"] ?? "");
    $description = trim($data["description"] ?? "");
    $price = $data["price"] ?? null;
    $stockQuantity = $data["stock_quantity"] ?? null;
    $categoryId = $data["category_id"] ?? null;
    $imageUrl = trim($data["image_url"] ?? "");

    // Required fields
    if ($productName === "") {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Product name is required."
        ]);

        exit;
    }

    if ($brand === "") {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Brand is required."
        ]);

        exit;
    }

    if ($description === "") {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Description is required."
        ]);

        exit;
    }

    if (!is_numeric($price) || $price < 0) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Price must be a valid positive number."
        ]);

        exit;
    }

    if (!is_numeric($stockQuantity)
            || $stockQuantity < 0
            || intval($stockQuantity) != $stockQuantity) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Stock must be a valid non-negative integer."
        ]);

        exit;
    }

    if (!is_numeric($categoryId)) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Category ID is required."
        ]);

        exit;
    }

    // Check category exists
    $checkCategory = $conn->prepare(
        "SELECT category_id
         FROM categories
         WHERE category_id = :category_id"
    );

    $checkCategory->execute([
        ":category_id" => $categoryId
    ]);

    if (!$checkCategory->fetch()) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Category not found."
        ]);

        exit;
    }

    // Insert product
    $sql = "
        INSERT INTO products (
            product_name,
            brand,
            description,
            price,
            stock_quantity,
            category_id,
            image_url
        )
        VALUES (
            :product_name,
            :brand,
            :description,
            :price,
            :stock_quantity,
            :category_id,
            :image_url
        )
        RETURNING
            product_id,
            product_name,
            brand,
            description,
            price,
            stock_quantity,
            category_id,
            image_url
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":product_name" => $productName,
        ":brand" => $brand,
        ":description" => $description,
        ":price" => $price,
        ":stock_quantity" => (int) $stockQuantity,
        ":category_id" => (int) $categoryId,
        ":image_url" => $imageUrl
    ]);

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "message" => "Product added successfully.",
        "product" => $product
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to add product."
    ]);
}

?>