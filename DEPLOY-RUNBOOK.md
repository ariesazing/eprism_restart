# eprism.online — first deployment runbook

Working checklist for the initial deploy of this app to the Hostinger VPS.
For the general/ongoing story (how CI works, rollbacks) see [DEPLOYMENT.md](DEPLOYMENT.md).

## Target facts

| | |
|---|---|
| Server | `187.53.134.169` — Ubuntu 26.04 LTS, KVM 2, 8 GB RAM, 100 GB disk |
| SSH | `ssh root@187.53.134.169` |
| Domain | `eprism.online` (registered at Hostinger, DNS managed in hPanel) |
| App dir | `/var/www/eprism` |
| Deploy user | `deploy` |
| Repo | `https://github.com/ariesazing/eprism_restart.git` (public) |
| Mail | Gmail SMTP, `From: ariezpanganiban09@gmail.com` |

Secrets are **not** stored in this file — fill the `__PLACEHOLDER__` values from the
sources noted inline.

---

## Phase 0 — DNS (do first, then wait for propagation)

hPanel -> Domains -> eprism.online -> DNS zone editor. Delete the default `@` / `www`
records, then add:

```
A   @     187.53.134.169
A   www   187.53.134.169
```

Wait until this returns the IP before doing Phase 6 (SSL will fail otherwise):

```bash
nslookup eprism.online
```

## Phase 1 — Generate the CI deploy key (on your Windows machine)

```bash
ssh-keygen -t ed25519 -f "$HOME/.ssh/eprism_deploy_key" -C "github-actions-deploy" -N ""
```

- `eprism_deploy_key`      -> private half, goes in the `VPS_SSH_KEY` GitHub secret (Phase 7)
- `eprism_deploy_key.pub`  -> public half, goes in the server's authorized_keys (Phase 3)

Do **not** reuse your personal `~/.ssh/id_ed25519` private key as a CI secret.

## Phase 2 — Provision the server

```bash
ssh root@187.53.134.169
git clone https://github.com/ariesazing/eprism_restart.git /tmp/eprism_restart
cd /tmp/eprism_restart
sudo DOMAIN=eprism.online bash deploy/provision.sh
```

**Save the generated MySQL password** it prints near the end — it goes into `.env`
as `DB_PASSWORD`.

> PHP version note: the scripts install **PHP 8.2 from the `ondrej/php` PPA**. If
> provisioning fails at "Installing PHP 8.2" because that PPA has no packages for
> Ubuntu 26.04, switch to the distro PHP (8.3/8.4 — the app allows `php: ^8.2`):
> replace `php8.2` with `php8.4` in `deploy/provision.sh`, `deploy/deploy.sh`,
> `deploy/nginx.conf.template`, and `/etc/sudoers.d/deploy-restart`, then re-run.

## Phase 3 — Authorize SSH keys on the server

As `root` on the VPS:

```bash
# your laptop's public key -> lets you SSH in without the root password
echo 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOiMuEuhaat9HnfUZ3WC1FrPXTaBhIds5be5Vg5LecnT eprism ssh key' >> /home/deploy/.ssh/authorized_keys

# the CI deploy key -> paste the full contents of eprism_deploy_key.pub
echo '__CONTENTS_OF_eprism_deploy_key.pub__' >> /home/deploy/.ssh/authorized_keys
```

## Phase 4 — Create `/var/www/eprism/.env`

```bash
sudo -iu deploy
cd /var/www/eprism
cp .env.example .env
openssl rand -hex 20    # value for REVERB_APP_KEY
openssl rand -hex 20    # value for REVERB_APP_SECRET
nano .env
```

Paste this, then fill the four `__PLACEHOLDER__` values:

```ini
APP_NAME="ePrism"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://eprism.online

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=eprism
DB_USERNAME=eprism
DB_PASSWORD=__MYSQL_PASSWORD_FROM_PROVISION__

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

REVERB_APP_ID=__RANDOM_NUMBER__
REVERB_APP_KEY=__openssl rand -hex 20__
REVERB_APP_SECRET=__openssl rand -hex 20__
REVERB_HOST="eprism.online"
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SCHEME=smtp
MAIL_USERNAME=ariezpanganiban09@gmail.com
MAIL_PASSWORD="__GMAIL_APP_PASSWORD_NO_SPACES__"
MAIL_FROM_ADDRESS="ariezpanganiban09@gmail.com"
MAIL_FROM_NAME="ePrism"
```

Notes:
- Gmail sends with `From:` = the Gmail address, **not** `@eprism.online`. Move to a
  transactional provider (Brevo/Postmark/SES) or Google Workspace when you need
  proper domain-authenticated mail.
- `SESSION_SECURE_COOKIE=true` needs HTTPS working — keep it and just always use
  `https://` once Phase 6 is done.

## Phase 5 — First build (as `deploy`, in `/var/www/eprism`)

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
npm ci && npm run build
php artisan migrate --force
php artisan storage:link
sudo supervisorctl restart eprism-queue:* eprism-reverb:*
```

## Phase 6 — SSL

```bash
exit    # back to root
certbot --nginx -d eprism.online -d www.eprism.online
```

Then load `https://eprism.online` — the app should render.

## Phase 7 — GitHub Actions secrets

Repo -> Settings -> Secrets and variables -> Actions -> New repository secret:

| Name | Value |
|---|---|
| `VPS_HOST` | `187.53.134.169` |
| `VPS_USER` | `deploy` |
| `VPS_SSH_KEY` | entire contents of `eprism_deploy_key` (private file, incl. `BEGIN`/`END` lines) |
| `VPS_PORT` | `22` |
| `VPS_APP_DIR` | `/var/www/eprism` |

## Phase 8 — Prove the pipeline

Push a trivial commit to `master`, open the repo **Actions** tab, watch `test`
then `deploy` succeed, reload `https://eprism.online`.

Manual trigger also available: Actions tab -> CI/CD -> Run workflow.

---

## After it's live

- [ ] **Regenerate the Gmail app password** (Google Account -> Security -> App passwords) —
      the one used during setup was shared in chat.
- [ ] Send a test email:
      `php artisan tinker` then
      `Mail::raw('deploy test', fn($m) => $m->to('ariezpanganiban09@gmail.com')->subject('deploy test'));`
- [ ] Confirm websockets: open the app in a browser, check the console for a
      successful Reverb connection to `wss://eprism.online/app/...`.
- [ ] Take a Hostinger snapshot (hPanel -> VPS -> Snapshots) before real traffic.
- [ ] Confirm the scheduler cron is present: `crontab -u deploy -l`.

## If a deploy half-fails

`deploy/deploy.sh` runs under `set -euo pipefail`, so a failed migration or build
stops it partway. Recovery is in [DEPLOYMENT.md](DEPLOYMENT.md) under "Rolling back".
