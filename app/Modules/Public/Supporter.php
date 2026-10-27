<?php

namespace App\Modules\Public;

use App\Core\Database;

class Supporter
{
    public static function findAllPublished(): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT id, name, grade, field, avatar FROM supporters'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}