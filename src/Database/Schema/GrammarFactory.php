<?php

namespace YasserElgammal\Green\Database\Schema;

use PDO;
use YasserElgammal\Green\Database\Schema\Grammar\Grammar;
use YasserElgammal\Green\Database\Schema\Grammar\MySqlGrammar;
use YasserElgammal\Green\Database\Schema\Grammar\SqliteGrammar;
use RuntimeException;

/**
 * Resolves the correct Grammar implementation based on the PDO driver.
 */
class GrammarFactory
{
    public static function resolve(PDO $pdo): Grammar
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return match ($driver) {
            'mysql'  => new MySqlGrammar(),
            'sqlite' => new SqliteGrammar(),
            default  => throw new RuntimeException(
                "Unsupported database driver [{$driver}]. Supported: mysql, sqlite."
            ),
        };
    }
}
