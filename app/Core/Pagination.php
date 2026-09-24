<?php

namespace App\Core;

class Pagination
{
    public static function build(int $page, int $perPage, int $total): array
    {
        if ($page < 1) $page = 1;
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $totalPages = (int) ceil($total / $perPage);
        if ($totalPages < 1) $totalPages = 1;
        if ($page > $totalPages) $page = $totalPages;

        return [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => $totalPages,
        ];
    }

    public static function offset(int $page, int $perPage): int
    {
        if ($page < 1) $page = 1;
        return ($page - 1) * $perPage;
    }
}