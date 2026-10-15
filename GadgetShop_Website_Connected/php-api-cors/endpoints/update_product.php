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

    $productId = $data["product_id"] ?? null;
    $productName = trim($data["product_name"] ?? "");
    $brand = trim($data["brand"] ?? "");
    $description = trim($data["description"] ?? "");
    $price = $data["price"] ?? null;
    $stockQuantity = $data["stock_quantity"] ?? null;
    $categoryId = $data["category_id"] ?? null;
    $imageUrl = trim($data["image_url"] ?? "");

    // Validate product ID
    if (!$productId || !is_numeric($productId)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Product ID is required."
        ]);

        exit;
    }

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
            "message" => "Price must be a valid non-negative number."
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

    // Check product exists
    $checkProduct = $conn->prepare(
        "SELECT product_id
         FROM products
         WHERE product_id = :product_id"
    );

    $checkProduct->execute([
        ":product_id" => $productId
    ]);

    if (!$checkProduct->fetch()) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Product not found."
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

    // Update product
    $sql = "
        UPDATE products
        SET
            product_name = :product_name,
            brand = :brand,
            description = :description,
            price = :price,
            stock_quantity = :stock_quantity,
            category_id = :category_id,
            image_url = :image_url
        WHERE product_id = :product_id
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
        ":product_id" => (int) $productId,
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
        "message" => "Product updated successfully.",
        "product" => $product
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to update product."
    ]);
}

?>