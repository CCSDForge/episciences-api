<?php

declare(strict_types=1);

namespace App\Doctrine\Migration;

/**
 * Schema checks for the migrations: MySQL DDL is not transactional, so a migration
 * must be re-runnable after a partial failure and tolerate a schema that differs
 * from the expected one (the database is shared with Episciences).
 * To be used in a Doctrine\Migrations\AbstractMigration.
 */
trait SchemaGuardTrait
{
    private function indexExists(string $table, string $indexName): bool
    {
        return (bool)$this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $indexName]
        );
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool)$this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            $this->addSql("ALTER TABLE $table DROP INDEX $indexName");
        }
    }
}
