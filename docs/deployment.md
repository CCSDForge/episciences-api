# Deployment Guide (Ansistrano)

This project uses **Ansistrano** (Ansible-based, Capistrano-style) for zero-downtime deployments.
All deployment files live in `deployment/ansible/`.

## Environments

| Environment | Branch   | Inventory                                   |
|-------------|----------|---------------------------------------------|
| preprod     | `preprod`| `deployment/ansible/inventory/preprod`      |
| production  | `main`   | `deployment/ansible/inventory/production`   |

## Prerequisites

On your local machine or CI server:

1. **Ansible** installed.
2. Ansistrano roles installed:
   ```bash
   ansible-galaxy install -r deployment/ansible/requirements.yml -p deployment/ansible/roles
   ```
3. SSH access to the target servers as `example-user-deploy`.

## Project Structure

```
deployment/ansible/
├── ansible.cfg
├── deploy.yml                        # Main deployment playbook
├── rollback.yml                      # Rollback to previous release
├── requirements.yml                  # Ansistrano role dependencies
├── group_vars/
│   ├── all.yml                       # Shared config (repo, shared paths)
│   ├── preprod.yml                   # Preprod branch override
│   └── production.yml                # Production branch override
├── inventory/
│   ├── preprod.dist                  # Preprod inventory template (commit this)
│   ├── preprod                       # Actual preprod inventory (gitignored)
│   ├── production.dist               # Production inventory template (commit this)
│   └── production                    # Actual production inventory (gitignored)
└── hooks/
    ├── after-symlink-shared.yml      # Composer, Yarn, cache warmup
    └── after-symlink.yml             # PHP-FPM reload
```

## Server Requirements

Each server must have:
- PHP 8.2 + `php8.2-fpm`
- `composer` available in `$PATH`
- `yarn` (v4) available in `$PATH`
- Apache configured to serve from `{deploy_to}/current/public/`

### Sudoers (on each server)

The `example-user-deploy` user needs the following entries in `/etc/sudoers.d/example-user-deploy`:

```
Defaults:example-user-deploy !requiretty
example-user-deploy ALL=(root) NOPASSWD: /bin/systemctl reload php8.2-fpm
example-user-deploy ALL=(root) NOPASSWD: /bin/systemctl daemon-reload
```

## Initial Server Setup (one-time per server)

### 1. Create the deployment directory

```bash
sudo mkdir -p /path/to/deploy
sudo chown example-user-deploy:<The Application User> /path/to/deploy
sudo chmod 750 /path/to/deploy
```

### 2. Upload the JWT keypair

The JWT keys are never committed to git. Place them in the shared directory so all releases share the same keys:

```bash
mkdir -p /path/to/deploy/shared/config/jwt
# copy your private.pem and public.pem
chmod 600 /path/to/deploy/shared/config/jwt/private.pem
chmod 644 /path/to/deploy/shared/config/jwt/public.pem
```

### 3. Create `.env.local`

Create the environment file in the shared directory. Ansistrano will symlink it into each release automatically.

```bash
nano /path/to/deploy/shared/.env.local
```

Minimum required variables:

```dotenv
APP_SECRET=<strong-random-secret>
DATABASE_URL="mysql://user:password@host:3306/dbname"
JWT_PASSPHRASE=<your-jwt-passphrase>
CORS_ALLOW_ORIGIN='^https?://...$'
APP_SOLR_HOST=http://solr-host:8983/solr/core
```

> Do **not** set `LOG_PATH` or `CACHE_PATH`: Symfony will use `var/log/` and `var/cache/`
> within the release directory, which Ansistrano manages correctly via shared symlinks.

### 4. Create the inventory file

```bash
cp deployment/ansible/inventory/production.dist deployment/ansible/inventory/production
# edit the file: replace placeholder hostnames with real ones
```

The inventory file is gitignored and holds infrastructure-specific values
(`ansible_user`, `ansistrano_deploy_to`, server hostnames).

## Deploying

```bash
cd deployment/ansible

# preprod
ansible-playbook deploy.yml -i inventory/preprod

# production
ansible-playbook deploy.yml -i inventory/production
```

To deploy a specific branch or tag:
```bash
ansible-playbook deploy.yml -i inventory/production -e "ansistrano_git_branch=v2.1.0"
```

## Rolling Back

Reverts the `current` symlink to the previous release without touching the database:

```bash
ansible-playbook rollback.yml -i inventory/production
```

## Database Migrations

Migrations are **always run manually** after a successful deploy:

```bash
ssh example-user-deploy@api-01.example.org
cd /path/to/deploy/current
php bin/console doctrine:migrations:migrate --no-interaction
```

Run on one server first, verify, then on the remaining servers.

## Deployment Workflow

When `deploy.yml` runs, Ansistrano:

1. Creates a new timestamped directory under `releases/`.
2. Clones the configured git branch into it.
3. Symlinks shared paths and files (`var/log/`, `var/sessions/`, `public/uploads/`, `config/jwt/`, `.env.local`).
4. Runs `hooks/after-symlink-shared.yml`:
   - Generates `version.php` with the current git tag and date.
   - Installs Composer dependencies (`--no-dev --optimize-autoloader`).
   - Compiles `.env.local` into `.env.local.php` (`composer dump-env prod`).
   - Installs Yarn dependencies and builds frontend assets.
   - Warms up the Symfony cache.
5. Updates the `current` symlink to the new release.
6. Runs `hooks/after-symlink.yml`: reloads `php8.2-fpm` to flush opcache.
7. Removes old releases, keeping the last 3.
