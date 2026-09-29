<?php
require "vendor/autoload.php";
$dotenv = Dotenv\Dotenv::createImmutable(".");
$dotenv->load();

use App\Core\Database;

$db = new PDO("mysql:host=localhost;port=3306;dbname=nadcot_famo;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
Database::setConnection($db);

// Test countAll with search filter - simulate the exact test
$filters = ["search" => "Searchable", "status" => 1];
$conditions = [];
$params = [];

if (isset($filters["status"])) {
    if ($filters["status"] === 0) {
        $conditions[] = "s.is_active = 0";
    } elseif ($filters["status"] === 1) {
        $conditions[] = "s.is_active = 1";
    }
} else {
    $conditions[] = "s.is_active = 1";
}

if (!empty($filters["search"])) {
    $conditions[] = "(s.name LIKE :search OR s.phone LIKE :search)";
    $params["search"] = "%" . $filters["search"] . "%";
}

$where = count($conditions) > 0 ? "WHERE " . implode(" AND ", $conditions) : "";
$sql = "SELECT COUNT(*) FROM students s {$where}";
echo "SQL: $sql\n";
echo "Params: " . json_encode($params) . "\n";

$stmt = Database::getConnection()->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(":" . $key, $value);
}
echo "Bound params\n";
$stmt->execute();
echo "Executed\n";
echo "Result: " . $stmt->fetchColumn() . "\n";
