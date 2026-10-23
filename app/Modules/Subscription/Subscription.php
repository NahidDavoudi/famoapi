<?php
namespace App\Modules\Subscription;

use App\Core\Database;
class Subscription {
    public static function findAll(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("select * from subscriptions where 1");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    public static function findById(int $id): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("select * from subscriptions where id = :id");
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }
    public static function create( array $data): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO `subscriptions`(
            `course_id`,
            `student_id`,
            `note`,
            `end_at`
        )
        VALUES(?,?,?,?)");
        $stmt->execute([$data['course_id'], $data['student_id'], $data['note'], $data['end_at']]);
        $id = $pdo->lastInsertId();
        return $id;
    }

}