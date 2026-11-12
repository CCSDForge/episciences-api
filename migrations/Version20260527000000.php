<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Doctrine\Migration\SchemaGuardTrait;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix the unique constraints of the pages and news tables.
 *
 * The `is_public` generated columns this migration first added are no longer created:
 * the visibility is now read from the `visibility_set` (pages) and `visibility_enum` (news)
 * columns of Episciences (see Version20261112120000, which drops them where they exist).
 *
 * Changes
 * -------
 * pages
 *   - Drop erroneous unique constraints:
 *       UNIQUE(code)       — incorrectly allowed only one page per journal
 *       UNIQUE(page_code)  — incorrectly made page_code globally unique
 *   - Add composite unique constraint UNIQUE(code, page_code) instead
 *
 * news
 *   - Drop erroneous unique constraint UNIQUE(code) (prevented journals from
 *     having more than one news item)
 */
final class Version20260527000000 extends AbstractMigration
{
    use SchemaGuardTrait;

    public function getDescription(): string
    {
        return 'Fix UniqueConstraints on pages and news.';
    }

    /**
     * MySQL commits the DDL statements implicitly: a transaction would already be closed
     * when Doctrine tries to commit it.
     */
    public function isTransactional(): bool
    {
        return false;
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

        // ------------------------------------------------------------------- news
        // Drop the incorrect single-column unique constraint
        $this->dropIndexIfExists('news', 'rvcode');
    }

    /**
     * The unique constraints fixed by up() are kept: the former UNIQUE(code) constraints
     * (one news / one page per journal) and UNIQUE(page_code) were wrong, and restoring
     * them fails as soon as a journal has two news or two pages.
     */
    public function down(Schema $schema): void
    {
        $this->write('The unique constraints fixed on pages and news are kept.');
    }
}
