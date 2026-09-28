<?php

namespace App\Core;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;

final class Eloquent
{
    private static ?Capsule $capsule = null;

    public static function boot(): Capsule
    {
        if (self::$capsule !== null) {
            return self::$capsule;
        }

        $capsule = new Capsule(new Container());
        $capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => $_ENV['DB_HOST'] ?? 'localhost',
            'port'      => $_ENV['DB_PORT'] ?? 3306,
            'database'  => $_ENV['DB_NAME'] ?? 'nadcot_famo',
            'username'  => $_ENV['DB_USER'] ?? 'root',
            'password'  => $_ENV['DB_PASS'] ?? '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_persian_ci',
            'prefix'    => '',
            'strict'    => true,
        ]);
        $capsule->setEventDispatcher(new Dispatcher($capsule->getContainer()));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        self::$capsule = $capsule;
        return $capsule;
    }
}
