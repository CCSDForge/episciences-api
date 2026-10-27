# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

<!--
## Unreleased
### Fixed
- Tightened the access checks of the papers voter (confidential fields of `GET /api/papers/{docid}`): every role, `epiadmin` included, is now checked in the journal of the paper as in Episciences (the roles of a token only apply to its journal; epiadmin > chief editor > administrator > secretary and editor); the epiadmin of the journal is never in conflict of interest; an administrator who declared a conflict of interest on a paper is no longer granted; `papers_follow` is granted to the editors without a conflict of interest.
- Papers voter: the conflicts of interest follow Episciences: only the editorial roles allowed to declare a conflict (copy editor, guest editor, editor, secretary, editor in chief) are in conflict until they answer "no" (no answer counts as "later"), an administrator only when they answered "yes", the other users (authors, reviewers) never; a secretary in conflict no longer sees the confidential fields.
- Papers voter: as on the member page of Episciences, the author of a paper always views its details (`papers_view`), even with an editorial role in conflict of interest.
- Papers voter: the editors, guest editors and copy editors only view the details of a paper and manage it (`papers_view`, `papers_edit`, `papers_manage`) when they are assigned to it, whatever the encapsulation settings of the journal (`encapsulateEditors`, `encapsulateCopyEditors`); the secretaries and the roles above handle every paper of their journal.
- Papers voter: as on the page of a paper in Episciences, its non-public details (`papers_view`) are shown to its author, not to its co-authors.
- Papers voter: as on the page of a paper in Episciences, the editors (copy editors) not assigned to it view its details (`papers_view`) when the journal sets `encapsulateEditors` (`encapsulateCopyEditors`) to "0"; managing it still requires being assigned to it.
- `GET /api/papers`: the copy editors see the papers they are assigned to; their role is `ROLE_COPYEDITOR` (role id `copyeditor`), also in the role hierarchy, instead of `ROLE_COPY_EDITOR`, which no user held.
- `GET /api/papers` and `GET /api/papers/{docid}`: as on Episciences, a connected author also reads the papers of their journal of which they are a co-author, not only those they submitted.
- `GET /api/papers` and `GET /api/papers/{docid}`: as on Episciences, an editor (copy editor) reads every paper of their journal when it sets `encapsulateEditors` (`encapsulateCopyEditors`) to "0", and only the papers they are assigned to otherwise (a setting never saved counts as restrictive).
- `GET /api/papers/{docid}`: as on Episciences, a published paper is readable by every connected user, whatever the journal of their token (it returned a `404` while readable anonymously); its confidential fields stay reserved to the users allowed to manage it. `GET /api/papers` keeps listing, with a token, the papers opened by the role of the user.
### Added
### Changed
- `GET /api/statistics/`: the numbers of submissions, imported and submitted papers are counted in a single query instead of three.
### Deprecated
### Removed
### Security
-->
# Unreleased

### Added
- The journal dashboard (`GET /api/journals/stats/dashboard/{code}`) now follows the setting `displayStatistics` of the journal, as the statistics page of Episciences: hidden by default (empty or "0"), public ("1" or any other value), or reserved for the editorial secretaries of the journal ("2", and the roles above). A hidden or non-public dashboard returns a `403` instead of being computed.
- Added an internal token exchange so that Episciences can display the papers of the statistics to the journal administrators (`DELEGATION_SECRET`, disabled when not set).
- Statistics: `withPapers=true` on `GET /api/statistics/` (with `rvcode`) and on the journal dashboard (`GET /api/journals/stats/dashboard/{code}`) adds `papers`, the papers counted by each figure, to understand the figures: one DOCID by paper (its latest version), computed with the same queries as the figures (by figure for `acceptance-rate`, `nb-submissions-details` and the dashboard; not for `evaluation`). Reserved for the administrators of the journal, the editorial secretaries and the platform administrators (JWT); the papers for which a journal administrator has declared a conflict of interest are not listed, their number is in `hiddenPapers`.
- Statistics: `groupBy=journal` now works with all the indicators (`nb-submissions`, `acceptance-rate`, the median delays, `evaluation`, one or several, all of them without `indicator`) on `GET /api/statistics/`, and on `GET /api/statistics/evaluation`: one item by active journal and by indicator, computed as for a single journal, with the journal code in `journal` (one query by indicator for all the journals). It was limited to a single median delay.
- Statistics: several indicators can be requested at once with `indicator[]` (`GET /api/statistics/` and `GET /api/statistics/evaluation`), returned in the requested order; it was rejected ("only one indicator can be requested at a time"). `groupBy=journal` still requires a single median indicator.
- Statistics: the median delays (`median-submission-publication`, `median-submission-acceptance`) now return `count`, the number of articles the median is computed from. `GET /api/statistics/` accepts `groupBy=journal` with one of these indicators (and without `rvcode`) to return the median of each journal in a single call, with its code in `journal`. The OpenAPI documentation describes how the delay is computed and that `year` filters on the year of publication (or acceptance) for these indicators; `unit` is now documented on `GET /api/statistics/`.
- `make openapi-lint` exports the OpenAPI specification and lints it with Redocly (`redocly.yaml`); it runs in CI.
- Added covering indexes on `PAPER_LOG` (migration `Version20261016120000`) to speed up the statistics endpoints (`GET /api/statistics/` from about 6 s to under 1 s on a full dataset).
- Added `is_public` stored generated column to `Pages` and `News` entities to optimize query filtering.
- Added regression tests for the `is_public` generated column filter.
- Added in-memory regression tests (`tests/Unit/Entity/UserRolesTest.php`) proving that `User::getRoles($rvId)` (the JWT `roles` construction path used by `JWTSubscriber`) yields the journal-scoped role (e.g. `ROLE_SECRETARY`) when the matching `USER_ROLES` row is present, is strictly scoped to its journal `rvid`, and falls back to `ROLE_USER` otherwise. This regression-tests the secretary access to the statistics paper lists.
- Added a database test (`tests/Functional/`, MySQL in CI through `TEST_DATABASE_URL`, skipped otherwise) checking that the cap of the unpaginated collections keeps the to-many fetch-joined collections complete.

### Changed
- OpenAPI documentation: the paginated collections document the 400 response returned for an invalid pagination parameter (e.g. `page` lower than 1).
- OpenAPI documentation: the 4xx responses (except the 401 of the JWT authentication) describe their body (`Error` schemas), as the responses documented by API Platform; the 404 of the journal statistics keeps its body. The journal dashboard documents its 403 (`displayStatistics` setting).
- OpenAPI documentation of the statistics (`/api/statistics/*`, `/api/journals/stats/*`): the 404 response says that it is also returned for an invalid `year` or `startAfterDate`, not only for an unknown journal.
- OpenAPI documentation: the `info` object gives the license of the API (GNU General Public License v3.0).
- Statistics: a median computed from no value (e.g. no article in the period) is `null` without being logged as a critical error.
- Statistics without journal (`GET /api/statistics/*` without `rvcode`, `groupBy=journal`, the years of `hydra:range`) only take into account the active journals, as the statistics of a journal (a disabled journal cannot be requested).
- OpenAPI documentation of the statistics: the `year` parameter is described for each indicator (year of submission, of the decision, of publication or acceptance, or of the invitation or report), instead of "The Year of submission" everywhere. No change in the computed figures.
- OpenAPI documentation of the journal statistics (`/api/journals/stats/*`): the `year` parameter says which date it filters on (year of submission, of acceptance, of publication, or of each figure for the dashboard), instead of "The Year of submission" everywhere. `indicator` lists its possible values and the empty default values of `rvcode` and `indicator` are removed. No change in the computed figures.
- `GET /api/news/` and `GET /api/news/{id}`: the `creator` is embedded without its IRI (`screenName` only).
- JSON-LD: the objects without an item operation (`GET /api/browse/authors/`, `GET /api/browse/authors-search/{author_fullname}`, `GET /api/search/`, `GET /api/journals/stats/*`, embedded objects such as the journal settings) no longer have a generated `@id` (`/api/.well-known/genid/...`, not dereferenceable and different on every request): they are blank nodes.
- The OpenAPI documentation (`/api/docs`) no longer declares `bearerAuth` as required on the purely public and "double mode" endpoints: `journals/`, `journals/{code}`, `journals/front/configuration` are documented as public, and `papers`, `volumes` and `sections` (item and collection) as publicly readable with an optional JWT that enlarges/restricts the returned scope according to the role (a note documents this behaviour on each operation; the optional `bearerAuth` requirement and the `401` returned for an invalid or expired token are declared, so that Swagger UI sends the token once authorized). Only `/me`, `/users` and `/users/{uid}` still require a JWT.
- The OpenAPI schemas now document, on each response property, the confidentiality of the role-conditioned fields (mirroring the `#[ApiProperty(security: ...)]` declarations): the `papers_manage`-gated fields of `Paper` (`flag`, `user`, `assignments`, `editors`, `reviewers`, `copyEditors`, `coAuthors`, `conflicts`), the `settings` of `Volume`/`Section` (ROLE_SECRETARY), the `username` of `User` (ROLE_MEMBER) and its `registrationDate`/`modificationDate`/`isValid` (ROLE_EPIADMIN), and the `status`/`creation`/`piwikid` of `Review` (ROLE_EPIADMIN).
- OpenAPI documentation: the `info` summary, description and `contact` (`Episciences - CCSD`) describe the API and its audience; the resource and entity operations (`/api/login`, `/api/token/refresh`, `Search`, `Browse`, `Statistic`, `News`, `Page`, `Volume`, `Section`, `Paper`, `User` and `Review`) get a rich `description`, and the `Credentials`/`Token`/`RefreshTokenCredential` and business resources (`Search`, `Browse`, `Statistic`, `News`, `Page`, `Volume`, `Section`, `Paper`, `User`, `Review`) document their properties (units, semantics) with examples for the dates and the audience arrays (`visibility`); the French `Review::subtitle` description is now in English. The descriptions of the MCP tools already in `src/Resource/` (Browse, Search) are reused. No change in the computed figures or responses.
- `GET /api/journals/boards/{code}`: the board members are returned as `BoardMember` objects (no longer `User` resources) and no longer include `uid`, `uuid` and `email`; their `id` is their position in the list (display key only), the list being sorted by last name, first name then account. The `committee` of volumes and sections no longer includes `uuid`.
- Merged the fragmented OpenAPI `Statistics | UX` tag into the canonical `Statistics` tag (`src/Resource/Statistic.php`).
- Replaced non-indexed `JSON_EXTRACT` visibility filter with the new `is_public` column in Doctrine queries.
- Migrated codebase to PHP 8.4: applied Rector transformations (explicit nullable parameter types, typed properties, Doctrine `Types::*` constants, PHP 8.4 `RoundingMode`/`#[\Deprecated]` features) to fix PHP 8.4 deprecations.
- Migrated to API Platform 4.3: updated `api-platform/core` to `^4.3`, removed legacy config keys (`keep_legacy_inflector`, `item_Operations`, `collection_Operations`), switched `UrlGeneratorInterface` to the `ApiPlatform\Metadata` namespace, moved `ApiPlatform\Exception\RuntimeException` to `ApiPlatform\Metadata\Exception\RuntimeException`, and converted OpenAPI operation `output`/`responses` from arrays to proper `ApiPlatform\OpenApi\Model` objects.
- Journal dashboard statistics (`withDetails`): the submissions are counted by year and by repository in grouped queries instead of one query per year and per repository (92 queries down to 29 for a journal with 11 years of activity).
- Migrated to Doctrine ORM 3.7: updated `doctrine/orm` constraint from `^2.14` to `^3.7` and aligned unit test mocks (mocked `Doctrine\DBAL\Exception` and `Doctrine\ORM\Query` instances) with the new ORM/DBAL APIs.
- CI runs the tests against MySQL 8.4 (was 8.0), with `DATABASE_SERVER_VERSION` set accordingly.

### Removed
- `GET /api/journals/{code}` and `GET /api/journals/` no longer expose the `settings` field of the journals (a raw `REVIEW_SETTING` `{setting, value}` array, without external consumer). The settings still work internally via `Review::getSetting()` and the public, structured configuration remains available through `GET /api/journals/front/configuration` (`{code}/front/configuration`).

### Fixed
- Journal dashboard (`/api/journals/stats/dashboard/{code}`) and `get_journal_dashboard`: with a single `year` filter (e.g. `2017`), the delay figures of a specific journal returned a `500` error (`Cannot access offset of type array on array`); the per-journal delay is now computed from the rows of the requested year(s) and returned. The all-platform delay by year also handles a `year` sent as an array.
- Statistics and journal dashboard: the submission date of a paper is the one of its first version (the oldest of its versions): a paper whose versions were submitted in different years is counted once, in the year of its first submission (`year`, `startAfterDate`, counts by year, lists of `withPapers`), so that the years add up to the total. The submissions by repository (`withDetails`) ignore the temporary versions (local repository) and count a paper in the repository of its first other version.
- Journal dashboard (`/api/journals/stats/dashboard/{code}`): `nbOtherStatus` and the `others` of each year (`withDetails`) are now the number of articles awaiting an editorial decision, counted directly by submission date, as `being-to-publish.other-status` of the statistics. They were the submissions minus the acceptances and refusals of the period.
- Statistics without start date: removed a PHP deprecation (`DateTime::createFromFormat()` called with `null`).
- Statistics and journal dashboard: with several years, the accepted, refused, published and accepted-not-yet-published totals (also with `groupBy=journal`) count each paper once (as `withPapers`), instead of adding up the counts of each year; the counts by year are unchanged.
- Statistics and journal dashboard: the accepted articles not yet published (`being-to-publish.accepted`, `nbAcceptedNotYetPublished`) now include the abandoned ones, an abandoned article being only paused.
- Statistics (`nb-submissions-details`): `being-to-publish.other-status` is now the number of submitted articles still awaiting an editorial decision (neither published, accepted nor refused, deleted nor removed; abandoned articles, only paused, are counted; imported articles ignored), counted by submission date. It was the difference between the submissions of the period and the decisions of the period, which mixed two populations when filtered by year (e.g. 20 instead of 91 for one journal and the current year).
- Statistics: each item of the statistics collections (`GET /api/statistics/`, `GET /api/statistics/evaluation`) now has the IRI of its own indicator instead of `/api/statistics/nb-submissions` for all of them.
- `GET /api/volumes` filtered by journal: `hydra:totalPublishedArticles` did not count the papers only published in secondary volumes.
- `GET /api/volumes` and `GET /api/sections` without journal filter: `hydra:totalPublishedArticles` was `null`.
- `GET /api/volumes` filtered by year: `hydra:totalPublishedArticles` ignored the volumes whose year is a range (e.g. "2024-2025"), which are listed.
- `GET /api/journals/boards/{code}`: the sections assigned to the board members returned `totalPublishedArticles` = 0; the number of published articles is now counted by the assigned sections query, without loading the papers.
- Fixed the Doctrine mappings that made the schema tools fail (`doctrine:schema:create`, schema comparison): string `unsigned` option of `UserInvitationAnswer`, MySQL `ENUM` columns mapped as strings of length 0 (now the DBAL `enum` type), unused `LegacyNews` entity clashing with the `news` table, `ReviewSetting` primary key and `VALUE` type, index typos (`UserAssignment`), nonexistent indexes (`Paper.RECORD`, unique `news.uid`), join column order to `VolumePaperPosition`. No database change; same API responses.
- The migrations `Version20260527000000` and `Version20261016120000` can be re-run after a partial failure: each step checks the existing schema. `Version20261016120000` caps the lock wait on `PAPER_LOG` (10 s), and the down migration of `Version20260527000000` no longer restores the erroneous unique constraints of `pages` and `news`.
- `PaperLog` now declares the existing `PAPER_LOG` indexes, so the schema tools no longer propose to drop them.
- `/api/statistics/*`: the `indicator[]` form (documented) is accepted again (a single indicator), and an invalid `startAfterDate` (expected format: `YYYY-MM-DD`) or `unit` is now rejected with a clear message instead of a server error or an ignored value; an empty `unit` falls back to `week`.
- `/api/journals/stats/*` (dashboard, submissions, delays, users): an invalid `startAfterDate` is now rejected (`404`) like on `/api/statistics/*`, instead of being ignored (or a server error when sent as an array); an empty value is still ignored. The OpenAPI documentation of the parameter is fixed accordingly.
- `GET /api/journals/stats/nb-submissions/{code}?withDetails`: `submissionsByYear[*].imported` now counts the imported submissions of each year (it repeated the total of all the years).
- An invalid pagination parameter (`page` lower than 1, negative `itemsPerPage`) now returns a `400` error instead of a server error.
- `GET /api/volumes/{vid}` : the `rvcode` journal is now taken into account, a volume of another journal is no longer returned (`404`).
- `GET /api/journals/stats/nb-submissions/{code}`: the documented `startAfterDate` filter is now accepted (it returned a `404` error), and ignored when its format is wrong; `status` is no longer listed twice in the available filters.
- `GET /api/browse/authors/` : the letter `O` no longer includes the authors listed under `others`.
- Fixed OpenAPI spec: documented summaries, required request bodies and `401` responses for authentication endpoints, removed the bearer requirement from the token refresh endpoint, replaced the placeholder `operationId` of `GET /api/browse/authors/` and the OpenAPI 3.0 `nullable` keyword.
- Fixed OpenAPI spec: documented `401` responses on bearer-secured operations, removed unreferenced component schemas, aligned the front configuration schema with the journal settings schema (property names, types and missing properties), clarified the citations permission endpoint description.
- Fixed OpenAPI spec to match the returned JSON: translated texts (`title`, `content`, `titles`, `descriptions`, `link`) documented as objects indexed by language, `vol_type` as an array, paper `document` and user `additionalProfileInformation` as objects, `userRoles[].user` as an IRI, statistics `value`, `details`, `availableFilters` and `requestedFilters` types, boolean response of the citations permission endpoint; documented `text/xml` for the feeds, the export media types and formats, and `404` responses for unknown journals.
- Fixed OpenAPI spec to match the returned JSON: board members documented as users (`GET /api/journals/boards/{code}`), author facets of `GET /api/browse/authors/`, the `proceedings` volume type, the volume papers, the user `webSites`, the `value` of the dashboard and delay statistics, and the `committee`, `totalPublishedArticles` and `hydra:totalPublishedArticles` properties of the JSON-LD volumes and sections.
- Fixed OpenAPI spec validation (Redocly): documented the schema of the `year`, `startAfterDate`, `withDetails` and `documentId` query parameters, replaced the invalid `datetime` type; added a `redocly.yaml` allowing the trailing slash of the collection routes.
- Removed the `text/html` format from `GET /api/me`, `GET /api/journals/boards/{code}` and `GET /api/journals/indexing-database/{code}`, which returned a server error for this format; the feeds also accept `Accept: text/xml`, and the export `text/plain` and `text/xml`.
- Fixed invalid `UniqueConstraints` on `pages` and `news` tables.
- Fixed `/api/docs.json` returning 404 (`Format "json" is not supported`) since API Platform 4.3, whose default `docs_formats` no longer include `json`: the documentation formats are now declared explicitly in `config/packages/api_platform.yaml`, restoring the OpenAPI JSON on `/api/docs.json`.
- Made migration `Version20260527000000` idempotent: indexes are only dropped if they exist and the `is_public` columns/indexes only created if missing, so it no longer fails on databases where `news` has no `rvcode` index and can be re-run after a partial failure.
- Fixed Swagger UI template (`templates/bundles/ApiPlatformBundle/SwaggerUi/index.html.twig`) for API Platform 4.3: replaced the removed `app.request.attributes.get('_route')` usage with `originalRoute`/`originalRouteParams` and removed the removed `graphQlPlaygroundEnabled` variable (GraphQL Playground).
- Fixed CI `docker-validate` failure: the Doctrine mapping check now runs as `root` in the container so the kernel can create its cache directory (default `CACHE_PATH="/var/cache/"` is not writable by the non-root `DOCKER_USER`); ownership of the cache/log directories is then restored to the non-root user so subsequent steps (e.g. `docker-test-coverage`) can still write to them.
- Fixed the `test` environment, where the kernel could not boot: `config/bundles.php` registered `WebProfilerBundle` and `DoctrineFixturesBundle` under a corrupted `'ApiCollectionNormalizer.php'` environment key instead of `'test'` (`Container extension "web_profiler" is not registered`), and `config/packages/test/framework.yaml` used the `framework.session.storage_id` option, removed in Symfony 6 (replaced by `storage_factory_id`).
- Fixed `GET /api/volumes` performance (N+1): the papers of all the volumes of the page are now fetched in a single query (`VolumeRepository::fetchSortedPapersByVolumes()`), filtered in `WHERE` instead of a `HAVING` applied after a full scan of `PAPERS` for each volume (~10 s → ~0.4 s for a 30-volume page). Papers of a secondary volume are now sorted by their position in that volume (was the position in their primary volume), and when several versions of a paper are attached to a volume, the latest one is kept (was arbitrary).
- Fixed statistics performance (journal dashboard, `/api/statistics`): the `PAPER_LOG` counters by year (accepted, refused, published, accepted not yet published) are computed in one grouped query instead of one query per year; year-independent queries were moved out of the loop; rates reuse the totals already computed; the "accepted not yet published" `NOT EXISTS` no longer uses an `OR` on the status (it caused a full scan of `PAPER_LOG`). Dashboard with details: ~88 s → ~11 s on a large journal, same results.
- Fixed operator precedence in the delay statistics query (`status = 4 OR status = 25 AND rvid = …`): the journal filter is now applied to both statuses.
- Fixed `GET /api/papers` memory usage: the collection now loads only the `PAPERS` columns serialized in the `read:Papers` group (list derived from the serializer metadata), so the heavy `RECORD` and `DOCUMENT` columns are no longer hydrated (`?pagination=false` exhausted the PHP memory limit).
- Fixed `GET /api/sections` memory usage: the papers fetch-joined in the sections are now loaded partially too (identifier, `paperid`, `rvid`, `status`), without `RECORD` and `DOCUMENT` (230 → 62 MiB for the 109 public sections, 276 → 70 MiB for a journal's 13 sections as secretary; same responses).
- Fixed possible out of memory errors on the Doctrine collections requested without pagination (`?pagination=false`): they are now capped to the global maximum number of items per page (1000), and a truncated response holds the `X-Truncated-Max-Items` header (exposed through CORS). With a to-many join (journals, sections, users), the limit applies to the root entities, so the nested collections stay complete.
- Fixed possible out of memory errors on `GET /api/volumes?pagination=false`: the volumes are capped like the Doctrine collections (1000, `X-Truncated-Max-Items` header when truncated).
- The Solr collections requested without pagination (`/api/search/`, `/api/browse/authors/`, `/api/browse/authors-search/{author_fullname}`, `/api/statistics/`, `/api/statistics/evaluation`) now also hold the `X-Truncated-Max-Items` header when capped.
- Fixed `GET /api/browse/authors-search/{author_fullname}` returning at most 10 papers (Solr default `rows`): up to 1000 are now requested, like the other Solr collections. When paginated, only the requested page is now fetched from Solr (`start`, `rows`) — so results beyond 1000 are reachable too — and `hydra:totalItems` is the Solr `numFound` (the page default size is 30 like the other collections).
- The OpenAPI documentation (`/api/docs`) describes the cap of the collections requested without pagination on the `pagination` parameter, and the `X-Truncated-Max-Items` response header.
- Fixed the JSON-LD collections requested without pagination (`?pagination=false`): they were serialized as a plain list whose items had the collection URI as `@id` and lacked their nested data (e.g. journal settings, volume papers). They are now Hydra collections (`hydra:member`, `hydra:totalItems`…) with the same items as the paginated responses. Breaking change for the clients reading a plain list.
- Fixed the paginated search (`/api/search/`): only the first 1000 Solr results were fetched then paginated in PHP, so `hydra:totalItems` was at most 1000 and the next results were unreachable. Only the requested page is now fetched from Solr (`start`, `rows`), and `hydra:totalItems` is the Solr `numFound`.
- The paginated search (`/api/search/`) without `itemsPerPage` now returns pages of the default size (30), like the other collections, instead of the maximum (1000). Without pagination, at most 1000 results are still returned.
- The paginated authors browsing (`/api/browse/authors/`) without `itemsPerPage` now returns pages of the default size (30) instead of 1000.
- Fixed the paginated authors browsing (`/api/browse/authors/`): only the first 1000 authors (Solr facet limit) were fetched then paginated in PHP, so the next authors were unreachable. Only the requested page is now fetched from Solr (JSON Facet API: `offset`, `limit`), and `hydra:totalItems` is the real number of authors (`numBuckets`). With the `search` filter, the matching authors are all fetched then paginated.
- `GET /api/papers/` in connected mode: the `rvcode` and `year` filters are now applied, and the papers of an author are scoped to the journal of the token; the connected behavior is documented.

### Security
- Reduce the user data exposed by the public endpoints.
- The `startAfterDate` filter is now validated (`Y-m-d`) before being concatenated into the delay statistics SQL query.
- Harden the year filter of the statistics and journal dashboard queries: an invalid `year` on the dashboard is now rejected (404), as on `/api/statistics/`.
- Upgraded `symfony/expression-language` from 7.4.18 to 7.4.21 (CVE-2026-109449: unbounded nesting in the expression parser lets untrusted expressions crash the process).
- Raised the Composer platform PHP version to 8.4.27, required by `roave/security-advisories`; upgraded `api-platform/core` to v4.4.6 and `composer/semver` to 3.5.2.

## v1.3.1 2026-09-22
### Fixed
- Sections collection (`GET /api/sections`) is now sorted by ascending `position` within each journal (was sorted by descending identifier); `position` is now exposed in section outputs.
- Relative `CACHE_PATH` / `LOG_PATH` values (local Docker setup) are now resolved against the project directory instead of the current working directory, so PHP-FPM and `bin/console` share the same cache and log directories.
- `CACHE_PATH` / `LOG_PATH` are also read from real environment variables (`$_SERVER`), and are normalized (a missing trailing slash on `CACHE_PATH` no longer yields `var/cachedev`).
- CI: `make docker-test-coverage` now generates the `coverage.xml` Clover report expected by the workflow.
## v1.3.0 2026-07-15
### Added
- New endpoint "/api/journals/front/configuration?code=rvcode": To retrieve the public interface configuration for sites migrated to the new interfaces
- Added unit tests for `JournalSettingNg` entities, repository queries, and state providers.

### Changed
- Migrated codebase and Docker infrastructure to PHP 8.3, applying Rector transformations (constructor promotion, typed properties, return types, modern PHP 8.x syntax) and fixing PHPStan level-1 type errors.
- Moved deployment configurations to `deployment/ansible/` using Ansistrano, renamed the staging environment to `preprod`, and added deployment hooks for cache/Composer/Yarn tasks and php-fpm reload.
- Routed HTTPS via Traefik, shared database/Solr services with `episciences-infrastructure`, and cleaned up Apache SSL configuration.
- Updated project dependencies in `composer.lock`.

### Fixed
- [#1015](https://github.com/CCSDForge/episciences/issues/1015) / [RT#281920]: miscalculation of average times: Previously, when a statistics start date was set in the parameters, document versions prior to that date were not taken into account. As a result, the first submission date depended on this parameter, which could lead to inaccurate results for some documents. Now, if the first submission date is earlier than the statistics start date, the document is excluded.
- Fixed indentation of grouped and streamed handlers in production Monolog configuration.
- Fixed duplicate `ORM\Column` attribute in `UserAssignment` entity.
- Fixed variable resolution bug in `VolumeRepository::listQuery()` by replacing `$context` with `$filters`.
- Fixed variable mapping bug in `findOneByWithContext()` by mapping `$context` to `$context['isGranted']`.
- Fixed default `$appVersion` initialization in `Kernel` before include.
- Removed undefined class in `JournalSettingNgProvider`.

## v1.2.6 2026-04-24
### Fixed
- [RT#282494]: the number of reviews requested is incorrect.
- The range years for the evaluation statistics does include the period during witch no article was published.
### Changed
- SQl Query optimization

## v1.2.5 2026-04-09
### Added
- Docs: document testing and static analysis tools in README
- Unit tests
- ATOM feed endpoint alongside RSS
- [#129](https://github.com/CCSDForge/episciences-api/issues/129): Add the creation date and last update to the pages
### Changed
- Consideration of the section order when generating the Boards
- RSS 30 publications instead of 2
- Statistics rate calculations: 100% now refers to the total of accepted and declined articles
### Fixed
- Run composer install inside PHP 8.2 container in CI
- Detect Docker in coverage script for reliable report generation
- Bugs found during test
- Deprecation warnings for Symfony 7.3+, API Platform 3.3+, and related dependencies.
- RSS Incorrect Feed name
- RSS feed link generation using `Request::createFromGlobals()` to ensure correct scheme and host.

## v1.2.4 2026-02-04
### Changed
- Allow volume years to be a string
### Added
- related to [#106](https://github.com/CCSDForge/episciences-front/issues/106): "webmaster" and "copy editor" roles added to boards
- [#94](https://github.com/CCSDForge/episciences-front/issues/94): New labels for boards
### Fixed
- [#869](https://github.com/CCSDForge/episciences/issues/869#issue-3839027009): on the Editorial Board page, the sections are only partially displayed
- RT#267717: Volumes without published articles that were displayed even with
 the "do not display empty volumes (without published articles)" option enabled;
  the same applies to years and types
- Secondary volumes are not included in the list of volumes unless they are declared as primary volumes.
- [#100](https://github.com/CCSDForge/episciences-api/issues/100)
- The number of published articles returned by the collection of volumes included unpublished articles.
- The number of publications with one author is incorrect because the search is done by Surname or First name instead of First name and Surname.
- Wrong statistical indicator "Reviews received".
## v1.2.2 2025-03-18
### Changed
- Updated robots.txt rules
- Updated API Platform component and related project dependencies
- Updated the API documentation code

## v1.2.1 2025-01-09
### Changed
- Updated label for status 'reviewed' has been updated to 'reviewed pending editorial decision'
- Updated API Platform component and related project dependencies

## v1.2.0 2024-11-25
### Fixed
 - In some situations (COI enabled), the API return does not contain any private data (/papers/11072 @jtcam)
 - truncated uid identifier in hydra metadata "@id": uid identifier is now serialized
### Changed
- Return all volume's metadata on GET /volumes.
- Possibility of retrieving all accepted documents: /api/papers/?rvcode=rvcode&only_accepted=true
- "range" endpoints are no longer required: they have been merged with the "news" and "volumes" endpoints.
- Volumes: search by list of values [type].
- /api/boards: show only the following roles: 'editorial_board','scientific_board','former_member','technical_board','guest_editor','secretary','chief_editor' and 'editor'.
- automatic generation of the deployment tag
- JSON representation of document:
  - New class attribute Paper::document () ;
  - New query filter 'status' (/papers endpoint) ;
  - Do not expose duplicate informations available now in 'document' attribute
  - In offline mode, a document can be retrieved using either DocID or PaperID.
  - Do not include article's details when collecting all documents

### Added
- added robots.txt to disallow all bots
- Search endpoint: /api/search/
- Export endpoint: /api/papers/export/{docid}/{format}.
- /browse/authors/: Addition of a table of authors' 1st letters + addition of an element to the 'Others' table, which will contain everything that doesn't fit into [A-Z].
- Extensive research: GET /browse/authors?search=Bacc
- new endpoint "/browse/authors-search/{author_fullname}": author search by text.
- volumes & sections endpoints: returns the number of articles and the assigned editors.
- new query parameter for volumes and sections
- type range for volumes
- New attribute Volume::vol_number (volume number)
- Year range endpoints: Year filter is now dynamically build for News & volumes.
- New endpoint: /browse/authors/
- New Feed RSS endpoint.
- Set "pagination_maximum_items_per_page" to 1000 & "pagination_items_per_page" to 30.
- NEWS PAGE - Sorted by DESC date_creation
- Allow number of elements to be paginated client-side
- Volumes endpoint: add year & type filters
- News endpoint: add year filter.
- new field user's uuid
- Sections information to editorial Staff members
- New endpoints:
  1. Boards (/api/journals/boards/{code})
  2. Pages (/api/pages; /api/pages/{id})
  3. News (/api/news; /api/news/{id})

## v1.1.9 2024-04-19
### Fixed
- An extra character "s" is added to the "api/papers" endpoint.

## v1.1.8 2024-04-17
### Changed
- Upgrade from Symfony 6.4 to 7.0
- Adding sections End points for sections
- Adding Extra Data: statusLabel and repository
- refactoring + adding measurement's unit to publication delay indicators.
- if the journal's code is not recognized during authentication, an error 400 is returned.
- it is now possible to override the Cache and Log directories

## v1.1.7 2024-02-15
### Fixed
- related to the option to ignore statistics before a given date: not considered parameter: startAfterDate.

## v1.1.6 2024-02-14
### Fixed
- Improvements: related to the option to ignore statistics before a given date

## v1.1.5 2024-02-12
### Fixed
- not available "year" parameter in query.

## v1.1.4 2024-02-05
### Changed
- add an option to ignore statistics before a given date + improvements
- JWT Refresh Token: set 'single_use' param. to true: if refresh token is consumed, a new refresh token will be provided.

### Fixed
- Loss of roles when requesting a new token with a refresh token & new attribute User::currentJournalID & refactoring.
- Improvements: consuming a REST API endpoint: taking into account "conflicts of interest"

## v1.1.3 2024-02-01
### Fixed:
- [#11](https://github.com/CCSDForge/episciences-api/issues/11) Administrator do not have access to all journal's papers.

## v1.1.2 2024-01-31
### Changed
- Disabling the Pagination Client-side Globally:
  The pagination can now be enabled or disabled by adding a query parameter named pagination:
  GET /papers?pagination=false: disabled
  GET /papers?pagination=true: enabled
### Fixed:
- A new endpoint "api_login_check" for retrieving a JWT token is added in OpenAPI documentation:
  turn off API Platform compatibility (remove default JWT check_path in OpenAPI documentation) because a custom endpoint has been set up.

## v1.1.1 2024-01-17
### Changed
- Limit API Platform to v. 3.1.*

## v1.1 2023-11-24
### Changed
- New version for PHP 8.2 and updated dependencies

## v1.0.2 2023-04-06
### Fixed
- Stats: don't count duplicate users + update dependencies

## v1.0.1 2023-04-06
### Changed
- Stats limited to 2013
- Updates dependencies

## v1.0 2023-02-01
Updating 'Papers' entity:
- Renaming 'description' field
- Adding new field 'tag' (indicates whether the paper has been submitted or imported)
- Remove unused code
- Fixed: method 'fetchAllAssociative' not found in \Doctrine\DBAL\Statement.
- Avoid including imported articles in journal statistics.
