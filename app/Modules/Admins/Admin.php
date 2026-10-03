<?php
declare(strict_types=1);

namespace App\Modules\Admins;

use App\Core\Database;

/**
 * Model for the `admins` table. An admin is considered active while its row
 * exists; the table carries no is_active flag and the linked users row is the
 * actual credential holder.
 */
class Admin
{
    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function isAccountActive(int $id): bool
    {
        return self::findById($id) !== null;
    }
}
