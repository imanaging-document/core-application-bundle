<?php

namespace Imanaging\CoreApplicationBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;

/** One database-scoped mutex shared by single-user provisioning and historical batch sync. */
final class CoreUserLock
{
  public function __construct(private readonly Connection $connection) {}

  public function run(callable $operation): mixed
  {
    $mysql = $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform;
    if (!$mysql && !$this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
      throw new UserApiException('La plateforme de verrouillage utilisateurs est indisponible.', 503);
    }
    $key = hash('sha256', $this->connection->getDatabase().':core-application:users');
    if ($mysql && (int)$this->connection->fetchOne('SELECT GET_LOCK(?, 5)', [$key]) !== 1) {
      throw new UserApiException('Une synchronisation utilisateurs est déjà en cours. Réessayez.', 503);
    }
    try {
      return $operation();
    } finally {
      if ($mysql) {
        $this->connection->fetchOne('SELECT RELEASE_LOCK(?)', [$key]);
      }
    }
  }
}
