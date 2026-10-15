<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix OOM sort-buffer errors on GET /api/pages and GET /api/news.
 *
 * Root cause: JSON_EXTRACT(visibility, '$[0]') in WHERE clauses has no index,
 * forcing full-table scans followed by a sort on rows that include large JSON
 * title/content columns, exhausting MySQL's sort_buffer_size (error 1038).
 *
 * Changes
 * -------
 * pages
 *   - Drop erroneous unique constraints:
 *       UNIQUE(code)       — incorrectly allowed only one page per journal
 *       UNIQUE(page_code)  — incorrectly made page_code globally unique
 *   - Add composite unique constraint UNIQUE(code, page_code) instead
 *   - Add STORED generated column `is_public` with an index so the visibility
 *     filter can use an index scan instead of a full-table sort
 *
 * news
 *   - Drop erroneous unique constraint UNIQUE(code) (prevented journals from
 *     having more than one news item)
 *   - Add STORED generated column `is_public` with an index
 */
final class Version20260527000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexed is_public generated column to pages and news; fix UniqueConstraints on pages and news.';
    }

    /**
     * Every step is guarded so the migration can run on databases whose schema
     * differs from the expected one (e.g. `news` without the `rvcode` index) and
     * can be re-run after a partial failure (MySQL DDL is not transactional).
     */
    public function up(Schema $schema): void
    {
        // ------------------------------------------------------------------ pages
        // Drop the two incorrect single-column unique constraints
        $this->dropIndexIfExists('pages', 'rvcode');
        $this->dropIndexIfExists('pages', 'page_code');

        // Add the correct composite unique constraint
        if (!$this->indexExists('pages', 'rvcode_page_code')) {
            $this->addSql("ALTER TABLE pages ADD CONSTRAINT rvcode_page_code UNIQUE (code, page_code)");
        }

        // Add stored generated column for indexed visibility filtering,
        // then index it so WHERE is_public = 1 uses an index scan
        $this->addIsPublicColumn('pages', 'idx_pages_is_public');

        // ------------------------------------------------------------------- news
        // Drop the incorrect single-column unique constraint
        $this->dropIndexIfExists('news', 'rvcode');

        $this->addIsPublicColumn('news', 'idx_news_is_public');
    }

    public function down(Schema $schema): void
    {
        // ------------------------------------------------------------------- news
        $this->addSql("DROP INDEX idx_news_is_public ON news");
        $this->addSql("ALTER TABLE news DROP COLUMN is_public");
        $this->addSql("ALTER TABLE news ADD CONSTRAINT rvcode UNIQUE (code)");

        // ------------------------------------------------------------------ pages
        $this->addSql("DROP INDEX idx_pages_is_public ON pages");
        $this->addSql("ALTER TABLE pages DROP COLUMN is_public");
        $this->addSql("ALTER TABLE pages DROP INDEX rvcode_page_code");
        $this->addSql("ALTER TABLE pages ADD CONSTRAINT rvcode UNIQUE (code)");
        $this->addSql("ALTER TABLE pages ADD CONSTRAINT page_code UNIQUE (page_code)");
    }

    private function addIsPublicColumn(string $table, string $indexName): void
    {
        if (!$this->columnExists($table, 'is_public')) {
            $this->addSql(
                "ALTER TABLE $table ADD COLUMN is_public TINYINT(1) " .
                "GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(visibility, '\$[0]')) = 'public') STORED"
            );
        }

        if (!$this->indexExists($table, $indexName)) {
            $this->addSql("CREATE INDEX $indexName ON $table (is_public)");
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            $this->addSql("ALTER TABLE $table DROP INDEX $indexName");
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $indexName]
        );
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }
}
