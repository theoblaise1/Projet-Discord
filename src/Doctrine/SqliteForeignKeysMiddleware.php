<?php

namespace App\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * SQLite n'applique les clés étrangères (et donc les "ON DELETE CASCADE") que si
 * on le lui demande explicitement à chaque connexion. Ce middleware Doctrine
 * s'en charge. Il n'a aucun effet avec MySQL, MariaDB ou PostgreSQL.
 *
 * Il est enregistré automatiquement (autoconfiguration de l'interface Middleware).
 */
final class SqliteForeignKeysMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(#[\SensitiveParameter] array $params): Connection
            {
                $connection = parent::connect($params);
                $native = $connection->getNativeConnection();

                $isSqlite = $native instanceof \SQLite3
                    || ($native instanceof \PDO && 'sqlite' === $native->getAttribute(\PDO::ATTR_DRIVER_NAME));

                if ($isSqlite) {
                    $connection->exec('PRAGMA foreign_keys = ON');
                }

                return $connection;
            }
        };
    }
}
