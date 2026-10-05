<?php
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use App\Core\Database;
$sql = "UPDATE students s 
    INNER JOIN supporters sup ON sup.field = s.field
            SET s.supporter_id = sup.id
            WHERE s.supporter_id IS NULL
               OR s.supporter_id <> sup.id";

$stmt = Database::getConnection()->prepare($sql);
$stmt->execute();

return $stmt->rowCount();