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