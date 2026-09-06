# Deploying to the Hostinger VPS

This app deploys via GitHub Actions: every push to `master` runs the test
suite, then (if it passes) SSHes into the VPS and runs `deploy/deploy.sh`,
which pulls the latest code, rebuilds, migrates, and restarts services.

For a step-by-step first-time deploy with this project's concrete server, domain,
and mail settings filled in, see [DEPLOY-RUNBOOK.md](DEPLOY-RUNBOOK.md).

## One-time server setup

1. **Point DNS** for `eprism.online` (and `www.eprism.online`) at the VPS's public IP.

2. **SSH into the VPS as root** and run the provisioning script:

   ```bash
   git clone https://github.com/ariesazing/eprism_restart.git /tmp/eprism_restart
   cd /tmp/eprism_restart
   sudo DOMAIN=eprism.online bash deploy/provision.sh
   ```

   This installs Nginx, PHP 8.2, MySQL 8, Composer, Node 20, Supervisor,
   Certbot, creates a `deploy` system user, clones the repo to
   `/var/www/eprism`, writes the Nginx vhost and Supervisor configs for the
   queue worker + Reverb, opens the firewall, and prints a generated MySQL
   password — **save that password**.

   > **PHP version on newer Ubuntu:** the scripts pin **PHP 8.2 via the
   > `ondrej/php` PPA**. On Ubuntu releases the PPA doesn't yet build for (e.g.
   > 26.04), provisioning fails at the PHP install step. The app allows
   > `php: ^8.2`, so replace `php8.2` with the distro's version (`php8.3` /
   > `php8.4`) in `deploy/provision.sh`, `deploy/deploy.sh`,
   > `deploy/nginx.conf.template`, and `/etc/sudoers.d/deploy-restart`, then
   > re-run.

3. **Generate a dedicated SSH deploy key** on your own machine (not the VPS):

   ```bash
   ssh-keygen -t ed25519 -f eprism_deploy_key -C "github-actions-deploy" -N ""
   ```

   Append `eprism_deploy_key.pub` to `/home/deploy/.ssh/authorized_keys` on
   the VPS.

4. **Create `/var/www/eprism/.env`** on the server (copy `.env.example`) and set at minimum:

   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://eprism.online
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_DATABASE=eprism
   DB_USERNAME=eprism
   DB_PASSWORD=<password printed by provision.sh>
   REVERB_APP_ID=<generate, e.g. random number>
   REVERB_APP_SECRET=<openssl rand -hex 20>
   REVERB_APP_KEY=<openssl rand -hex 20>
   REVERB_HOST=eprism.online
   REVERB_PORT=443
   REVERB_SCHEME=https
   VITE_REVERB_HOST=eprism.online
   VITE_REVERB_PORT=443
   VITE_REVERB_SCHEME=https
   ```

5. **Run the first build manually** as the `deploy` user:

   ```bash
   sudo -iu deploy
   cd /var/www/eprism
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   npm ci && npm run build
   php artisan migrate --force
   php artisan storage:link
   sudo supervisorctl restart eprism-queue:* eprism-reverb:*
   ```

6. **Issue the SSL certificate**:

   ```bash
   sudo certbot --nginx -d eprism.online -d www.eprism.online
   ```

7. **Add GitHub Actions secrets** (repo Settings → Secrets and variables →
   Actions):

   | Secret | Value |
   |---|---|
   | `VPS_HOST` | VPS public IP or hostname |
   | `VPS_USER` | `deploy` |
   | `VPS_SSH_KEY` | contents of the **private** key `eprism_deploy_key` |
   | `VPS_PORT` | `22` |
   | `VPS_APP_DIR` | `/var/www/eprism` |

## Ongoing deploys

Push to `master` (or merge a PR into it). GitHub Actions will:

1. Run `php artisan test` against an in-memory SQLite DB.
2. If tests pass, SSH into the VPS and run `deploy/deploy.sh`, which does
   `git pull`, `composer install`, `npm run build`, `migrate --force`,
   cache warms, and restarts the queue worker, Reverb, and PHP-FPM.

You can also trigger a deploy manually from the Actions tab
(`workflow_dispatch`).

## Rolling back

SSH in as `deploy`, then:

```bash
cd /var/www/eprism
git log --oneline -5
git reset --hard <previous-commit-sha>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force   # only if the rollback also needs a down-migration
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo supervisorctl restart eprism-queue:* eprism-reverb:*
sudo systemctl reload php8.2-fpm
```
