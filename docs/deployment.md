# Deployment Guide (Ansistrano)

This project uses **Ansistrano** (an Ansible-based deployment tool) to handle automated, capistrano-style deployments with zero downtime.

## Prerequisites

On your local machine (or CI server), you need:
1. **Ansible** installed.
2. **Ansistrano roles** installed:
   ```bash
   ansible-galaxy install -r ansible/requirements.yml -p ansible/roles
   ```
3. SSH access to the target server(s) with a user that has sudo privileges.

## Project Structure

- `ansible/deploy.yml`: The main deployment playbook.
- `ansible/rollback.yml`: Playbook to revert to the previous release.
- `ansible/group_vars/all.yml`: Common configuration (shared paths, repo URL).
- `ansible/inventory/`: Contains environment-specific inventory files (e.g., `production`).
- `ansible/hooks/`: Custom tasks executed during deployment (Composer, Yarn, Migrations).

## Initial Server Setup (One-time)

Before the first deployment, you must prepare the server:

1. **Create the deployment directory**:
   ```bash
   sudo mkdir -p /var/www/episciences-api
   sudo chown -R your-deploy-user:your-deploy-user /var/www/episciences-api
   ```

2. **Prepare shared files**:
   Ansistrano uses a `shared` folder for persistent data across releases.
   ```bash
   mkdir -p /var/www/episciences-api/shared
   ```
   Create the `.env.local` file manually in the shared folder:
   ```bash
   nano /var/www/episciences-api/shared/.env.local
   ```

## Configuration

1. **Inventory**: Create your private inventory file by copying the template:
   ```bash
   cp ansible/inventory/production.dist ansible/inventory/production
   ```
   Then edit `ansible/inventory/production` to set your server's IP and SSH user. This file is ignored by Git.

2. **Variables**: Review `ansible/group_vars/all.yml`. Key variables:
   - `ansistrano_deploy_to`: The path on the remote server.
   - `ansistrano_git_repo`: The SSH git URL of the repository.
   - `ansistrano_shared_paths`: List of directories to persist (logs, uploads).

## Usage

### Deploying

To deploy to production:
```bash
ansible-playbook -i ansible/inventory/production ansible/deploy.yml
```

You can specify a branch or tag:
```bash
ansible-playbook -i ansible/inventory/production ansible/deploy.yml -e "ansistrano_git_branch=develop"
```

### Rolling Back

If a deployment fails or introduces a critical bug:
```bash
ansible-playbook -i ansible/inventory/production ansible/rollback.yml
```

## Deployment Workflow

When you run the deployment:
1. Ansistrano creates a new timestamped directory in `releases/`.
2. It clones the repository.
3. It symlinks the `shared/` files and directories (like `.env.local` and `var/log`).
4. It runs the **hooks** (`ansible/hooks/after-symlink-shared.yml`):
   - Generates `version.php`.
   - Installs Composer dependencies.
   - Dumps environment variables.
   - Installs Yarn dependencies and builds assets (Webencore).
   - Runs database migrations.
   - Clears the Symfony cache.
5. It updates the `current` symlink to point to the new release.
6. It cleans up old releases (keeps the last 5 by default).
