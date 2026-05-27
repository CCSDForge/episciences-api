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

    public function up(Schema $schema): void
    {
        // ------------------------------------------------------------------ pages
        // Drop the two incorrect single-column unique constraints
        $this->addSql("ALTER TABLE pages DROP INDEX rvcode");
        $this->addSql("ALTER TABLE pages DROP INDEX page_code");

        // Add the correct composite unique constraint
        $this->addSql("ALTER TABLE pages ADD CONSTRAINT rvcode_page_code UNIQUE (code, page_code)");

        // Add stored generated column for indexed visibility filtering
        $this->addSql(
            "ALTER TABLE pages ADD COLUMN is_public TINYINT(1) " .
            "GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(visibility, '\$[0]')) = 'public') STORED"
        );

        // Index the generated column so WHERE is_public = 1 uses an index scan
        $this->addSql("CREATE INDEX idx_pages_is_public ON pages (is_public)");

        // ------------------------------------------------------------------- news
        // Drop the incorrect single-column unique constraint
        $this->addSql("ALTER TABLE news DROP INDEX rvcode");

        // Add stored generated column for indexed visibility filtering
        $this->addSql(
            "ALTER TABLE news ADD COLUMN is_public TINYINT(1) " .
            "GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(visibility, '\$[0]')) = 'public') STORED"
        );

        $this->addSql("CREATE INDEX idx_news_is_public ON news (is_public)");
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
}