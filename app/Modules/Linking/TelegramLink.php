<?php

namespace App\Modules\Linking;

use App\Core\Database;

class TelegramLink{

    public statis function isLinked(int $chatId)
    {
        $stmt = Database::getConnection()->prepare('SELECT `username`, `role` WHERE chat_id = ?');
        $stmt->execute([$chatId]);
        $link = $stmt->fetch();
        if($link){
            return ([
                'phone' => $link['username'],
                'role' => $link['role']
            ]);
        } else {
            return;
        }
    }
    public static function linkUser(int $chatId, string $phone)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE users SET chat_id = ? WHERE username = ?'
        );
        $stmt->execute([$chatId, $phone]);
        return $stmt->rowCount() > 0;
    }
}