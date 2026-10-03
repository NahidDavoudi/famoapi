<?php

namespace App\Modules\Linking;

use App\Linking\TelegramLink;

class LinkService
{
    public function getTelegramLink(string $username): TelegramLink
    {
        return new TelegramLink($username);
    }
}