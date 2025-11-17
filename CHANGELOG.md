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
## Unreleased
### Added
- [#94](https://github.com/CCSDForge/episciences-front/issues/94): New labels for boards
### Fixed
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
