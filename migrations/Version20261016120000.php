<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Doctrine\Migration\SchemaGuardTrait;
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
 * Both indexes are built online (no table lock). The online build still needs a short
 * metadata lock at its start and end: the lock wait is capped so that the ALTER fails
 * (and can be re-run) instead of queueing the Episciences queries on PAPER_LOG behind it.
 * Only the missing indexes are added, so the migration can be re-run.
 */
final class Version20261016120000 extends AbstractMigration
{
    use SchemaGuardTrait;

    private const string TABLE = 'PAPER_LOG';
    private const int LOCK_WAIT_TIMEOUT = 10;

    /** Index name => columns */
    private const array INDEXES = [
        'idx_status_rvid_paperid_date_docid' => 'status, RVID, PAPERID, DATE, DOCID',
        'idx_paperid_status' => 'PAPERID, status',
    ];

    public function getDescription(): string
    {
        return 'Add covering indexes on PAPER_LOG for the statistics queries.';
    }

    public function up(Schema $schema): void
    {
        $clauses = [];

        foreach (self::INDEXES as $name => $columns) {
            if (!$this->indexExists(self::TABLE, $name)) {
                $clauses[] = sprintf('ADD INDEX %s (%s)', $name, $columns);
            }
        }

        $this->alterTable($clauses, 'ALGORITHM=INPLACE, LOCK=NONE');
    }

    public function down(Schema $schema): void
    {
        $clauses = [];

        foreach (array_keys(self::INDEXES) as $name) {
            if ($this->indexExists(self::TABLE, $name)) {
                $clauses[] = sprintf('DROP INDEX %s', $name);
            }
        }

        $this->alterTable($clauses);
    }

    /**
     * @param string[] $clauses
     */
    private function alterTable(array $clauses, string $options = ''): void
    {
        if ($clauses === []) {
            $this->write(sprintf('Nothing to do on %s.', self::TABLE));
            return;
        }

        if ($options !== '') {
            $clauses[] = $options;
        }

        $this->addSql(sprintf('SET SESSION lock_wait_timeout = %d', self::LOCK_WAIT_TIMEOUT));
        $this->addSql(sprintf('ALTER TABLE %s %s', self::TABLE, implode(', ', $clauses)));
        $this->addSql('SET SESSION lock_wait_timeout = DEFAULT');
    }
}
