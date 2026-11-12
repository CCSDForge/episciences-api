<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Doctrine\Migration\SchemaGuardTrait;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop the `is_public` generated columns of pages and news (and their indexes), added by a
 * previous version of Version20260527000000.
 *
 * The visibility is now read from the `visibility_set` (pages) and `visibility_enum` (news)
 * columns maintained by Episciences. The generated columns were computed from the former JSON
 * `visibility` column, which Episciences will drop: MySQL refuses to drop a column used by a
 * generated column. Only what exists is dropped, so the migration can be re-run.
 */
final class Version20261112120000 extends AbstractMigration
{
    use SchemaGuardTrait;

    /** Table => index of its `is_public` column */
    private const array IS_PUBLIC_INDEXES = [
        'pages' => 'idx_pages_is_public',
        'news' => 'idx_news_is_public',
    ];

    public function getDescription(): string
    {
        return 'Drop the is_public generated columns of pages and news.';
    }

    /**
     * MySQL commits the DDL statements implicitly: a transaction would already be closed
     * when Doctrine tries to commit it.
     */
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        foreach (self::IS_PUBLIC_INDEXES as $table => $indexName) {
            $this->dropIndexIfExists($table, $indexName);

            if ($this->columnExists($table, 'is_public')) {
                $this->addSql("ALTER TABLE $table DROP COLUMN is_public");
            }
        }
    }

    /**
     * The columns are not restored: nothing reads them any more.
     */
    public function down(Schema $schema): void
    {
        $this->write('The is_public columns of pages and news are not restored.');
    }
}
