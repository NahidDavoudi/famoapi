<?php

namespace App\Modules\Linking;

use App\Modules\Linking\TelegramLink;

class LinkController
{
    public function __construct()
    {
        $telegramLink = new TelegramLink();
    }

    public function getTelegramLink(string $username)
    {
        
    }
}