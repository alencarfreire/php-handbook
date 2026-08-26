<?php

namespace App\Infrastructure;

use PDO;

final class Connection
{
    public static function make(): PDO
    {
        $dir = dirname(__DIR__, 2) . '/storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        // SQLite num arquivo. Trocar o DSN é o que muda se for MySQL.
        $pdo = new PDO('sqlite:' . $dir . '/app.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql');
        $pdo->exec($schema);

        return $pdo;
    }
}
