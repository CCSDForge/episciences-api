# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

<!-- 
## Unreleased
### Fixed
### Added
### Changed
### Deprecated
### Removed
### Security
-->
### Changed
- /api/boards: show only the following roles: 'editorial_board','scientific_board','former_member','technical_board','guest_editor','secretary','chief_editor' and 'editor'.
### Added
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

## Unreleased
### Changed
- automatic generation of the deployment tag
- JSON representation of document: 
  - New class attribute Paper::document () ;
  - New query filter 'status' (/papers endpoint) ;
  - Do not expose duplicate informations available now in 'document' attribute
  - In offline mode, a document can be retrieved using either DocID or PaperID.
  - Do not include article's details when collecting all documents

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