# Statistics endpoints performance

Analysis and fix of the response time of `GET /api/statistics/` and the related statistics endpoints.

## Symptom

`GET /api/statistics/` (all journals, no filter) took about **9.5 s** on a full local dataset
(`PAPER_LOG`: 665,673 rows, `PAPERS`: 19,256 rows). Filtered calls were also slow
(`?rvcode=hrj`: 2.6 s, `?startAfterDate=2022-01-01`: 7.1 s).

## Analysis

The SQL queries were collected with the Symfony profiler (`X-Debug-Token`) and analysed with `EXPLAIN ANALYZE`.

### Before any change (`develop`)

15 queries, 8.45 s of SQL out of 8.55 s in total. The slowest ones:

| Time | Query |
|---|---|
| 2.6 s | Accepted but not yet published papers: `COUNT(DISTINCT PAPERID)` on `PAPER_LOG` with a `NOT EXISTS` subquery on `status = x OR status = y` |
| 1.6 s / 1.5 s | Median delays submission → publication / acceptance (`MIN(DATE)` / `MAX(DATE)` per paper, grouped by `PAPERID, RVID`) |
| 0.3 – 0.6 s each | Accepted / refused / published counts on `PAPER_LOG` joined with `PAPERS` |

### After `fix/performance-audit`

That branch already splits the `NOT EXISTS ... OR ...` subquery into one `NOT EXISTS` per status and reuses the
accepted / refused totals for the acceptance rate. The endpoint dropped to **6.1 s** (13 queries, 5.99 s of SQL):

| Time | Query |
|---|---|
| 1.4 s + 1.6 s | Median delays (publication, acceptance) |
| 0.9 s | Accepted but not yet published papers |
| 0.35 – 0.57 s each | Accepted / refused / published counts |

### Root cause

Every slow query filters `PAPER_LOG` on the generated `status` column, then reads `PAPERID`, `RVID`, `DATE` and `DOCID`.
The only usable index was the single-column `idx_status`: MySQL finds the matching entries in the index, then reads
each full row from the table to get the other columns. The rows are large because of the `DETAIL` JSON column, so these
row lookups dominate the execution time. For the median query:

```
-> Index lookup on pl1 using idx_status (status=0)  (actual time=4.3..1002 rows=12594)
```

About 1 s is spent reading 12,594 rows, while the aggregation itself is negligible.

The `NOT EXISTS (... WHERE PAPERID = ? AND status = ?)` subqueries had the same problem: the existing
`idx_paperid_docid_date_logid` index starts with `PAPERID` but does not contain `status`.

## Fix

Migration `migrations/Version20261016120000.php` adds two indexes on `PAPER_LOG` (also declared in `src/Entity/PaperLog.php`):

| Index | Columns | Used by |
|---|---|---|
| `idx_status_rvid_paperid_date_docid` | `status, RVID, PAPERID, DATE, DOCID` | Counts and median delays: covering index, the queries are answered from the index only, with or without the journal filter (`RVID`) |
| `idx_paperid_status` | `PAPERID, status` | `NOT EXISTS` subqueries looking for a given status of the same paper |

The indexes are built online (`ALGORITHM=INPLACE, LOCK=NONE`): the table stays writable during the migration.
The build takes about 2.4 s on the local dataset. The migration is reversible (`down()` drops both indexes).

No PHP code change was needed: the queries are unchanged.

## Results

Local measurements (`curl`, warm cache):

| Request | `develop` | `fix/performance-audit` | With the indexes |
|---|---|---|---|
| `GET /api/statistics/` | ~9.5 s | 6.1 s | **0.65 s** |
| `GET /api/statistics/?rvcode=hrj` | – | 2.6 s | **0.07 s** |
| `GET /api/statistics/?rvcode=hrj&year[]=2023&year[]=2024` | – | 1.5 s | **0.05 s** |
| `GET /api/statistics/?startAfterDate=2022-01-01` | – | 7.1 s | **0.61 s** |
| `GET /api/statistics/evaluation` | – | – | 0.30 s |
| `GET /api/statistics/median-submission-publication` | – | – | 0.19 s |
| `GET /api/statistics/acceptance-rate` | – | – | 0.19 s |
| `GET /api/journals/stats/dashboard/hrj` | – | – | 0.19 s |

## Verification

- The JSON responses of the four `GET /api/statistics/` requests above are byte-identical with and without the indexes.
- The migration was run up, down and up again.
- `make docker-validate`: mapping OK.
- `make test-unit`: OK.

## Deployment notes

- `PAPER_LOG` belongs to the database shared with Episciences: the migration must be run on every environment
  (preprod, then production) with `bin/console doctrine:migrations:migrate`.
- The index build is online but reads the whole table: run it outside peak hours on production.
- The existing single-column `idx_status` index is now redundant with the prefix of
  `idx_status_rvid_paperid_date_docid`. It is kept on purpose, as Episciences may rely on it; it can be dropped later
  once confirmed unused.

## Possible follow-ups

- `GET /api/statistics/` still runs three `COUNT(DISTINCT PAPERID)` queries on `PAPERS` with nearly the same
  filters; they could be merged into a single query with conditional aggregates.
- `nb-submissions` is serialized as a float (`330.0`) instead of an integer.
