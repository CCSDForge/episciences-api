<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Speed up the statistics endpoints (GET /api/statistics/...).
 *
 * The statistics queries filter PAPER_LOG on the generated `status` column, then read
 * PAPERID, RVID, DATE and DOCID: with the single-column `idx_status` index, MySQL fetches
 * every matching row, and the rows are large because of the DETAIL JSON column.
 *
 * Changes
 * -------
 * PAPER_LOG
 *   - Covering index (status, RVID, PAPERID, DATE, DOCID): the counts and the median delays
 *     are computed from the index only
 *   - Index (PAPERID, status): used by the NOT EXISTS subqueries looking for a status
 *     of the same paper
 *
 * Both indexes are built online (no table lock).
 */
final class Version20261016120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add covering indexes on PAPER_LOG for the statistics queries.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE PAPER_LOG ' .
            'ADD INDEX idx_status_rvid_paperid_date_docid (status, RVID, PAPERID, DATE, DOCID), ' .
            'ADD INDEX idx_paperid_status (PAPERID, status), ' .
            'ALGORITHM=INPLACE, LOCK=NONE'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE PAPER_LOG ' .
            'DROP INDEX idx_status_rvid_paperid_date_docid, ' .
            'DROP INDEX idx_paperid_status'
        );
    }
}
