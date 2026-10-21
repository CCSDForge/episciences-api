# CLAUDE.md

## Database
- MySQL database shared with Episciences

## Commands
- Locally use `php8.4` and `./composer`
- `make test`, `make test-unit`, `make test-file FILE=...`
- In Docker: `make phpstan [TARGET=path] [LEVEL=X]`, `make rector [DRY_RUN=1]`
- `make docker-validate` - after changing entities

## Key Files
- `config/packages/security.yaml` - Firewalls (`login` before `api`)
- `.env.local.php` - Overrides every `.env*` file
- `version.php` - Generated at deploy time

## Gotchas
- `POST /api/login`: `username`, API `password`, journal `code`
- Local URL: `https://api-dev.episciences.org`

## Code & Git
- Write comments, documentation in English
- Git flow: `develop` → `preprod` → `main`; conventional commits; update `CHANGELOG.md`
- Makefile `deploy*` targets auto-stash changes

# General information
- writing outside the project directory is forbidden, even in /tmp
- when asked to write a plan, write it in the ./tmp/ directory of the project
- when creating new code files, chmod 644
- never use `git add -A` or `git add .`: list the files to commit explicitly
