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

## v1.1.2 2025-01-31
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