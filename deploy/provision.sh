#!/usr/bin/env bash
# One-time setup for a fresh Ubuntu VPS to host this Laravel app.
# Run ONCE on the VPS as root: sudo bash provision.sh
#
# Installs: Nginx, PHP 8.2 + extensions, Composer, Node 20, MySQL 8,
# Supervisor (queue worker + Reverb), Certbot, deploy user, firewall.

set -euo pipefail

DOMAIN="${DOMAIN:-eprism.com}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
APP_DIR="${APP_DIR:-/var/www/eprism}"
DB_NAME="${DB_NAME:-eprism}"
DB_USER="${DB_USER:-eprism}"
REPO_URL="${REPO_URL:-https://github.com/ariesazing/eprism_restart.git}"

if [[ $EUID -ne 0 ]]; then
  echo "Run as root (sudo bash provision.sh)" >&2
  exit 1
fi

echo "==> Updating apt and installing base packages"
apt-get update -y
apt-get install -y software-properties-common curl gnupg2 ca-certificates lsb-release unzip git ufw

echo "==> Adding ondrej/php PPA for PHP 8.2"
add-apt-repository -y ppa:ondrej/php
apt-get update -y

echo "==> Installing PHP 8.2 and required extensions"
apt-get install -y php8.2-fpm php8.2-cli php8.2-common php8.2-mysql php8.2-mbstring \
  php8.2-xml php8.2-bcmath php8.2-curl php8.2-zip php8.2-gd php8.2-intl php8.2-sqlite3 php8.2-redis

echo "==> Installing Composer"
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

echo "==> Installing Node 20 LTS"
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt-get install -y nodejs

echo "==> Installing Nginx"
apt-get install -y nginx

echo "==> Installing MySQL 8"
apt-get install -y mysql-server
DB_PASSWORD="$(openssl rand -base64 24)"
mysql --execute="CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql --execute="CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
mysql --execute="GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"
echo "==> MySQL database '${DB_NAME}' created. Save this password for your .env DB_PASSWORD:"
echo "    ${DB_PASSWORD}"

echo "==> Installing Supervisor and Certbot"
apt-get install -y supervisor certbot python3-certbot-nginx

echo "==> Creating deploy user"
if ! id -u "${DEPLOY_USER}" >/dev/null 2>&1; then
  adduser --disabled-password --gecos "" "${DEPLOY_USER}"
  usermod -aG www-data "${DEPLOY_USER}"
fi
mkdir -p "/home/${DEPLOY_USER}/.ssh"
touch "/home/${DEPLOY_USER}/.ssh/authorized_keys"
chmod 700 "/home/${DEPLOY_USER}/.ssh"
chmod 600 "/home/${DEPLOY_USER}/.ssh/authorized_keys"
chown -R "${DEPLOY_USER}:${DEPLOY_USER}" "/home/${DEPLOY_USER}/.ssh"
echo "==> Add the GitHub Actions deploy public key to: /home/${DEPLOY_USER}/.ssh/authorized_keys"

echo "==> Cloning application"
mkdir -p "$(dirname "$APP_DIR")"
if [[ ! -d "${APP_DIR}/.git" ]]; then
  git clone "${REPO_URL}" "${APP_DIR}"
fi
chown -R "${DEPLOY_USER}:www-data" "${APP_DIR}"

echo "==> Granting deploy user sudo rights limited to service reload/restart (no password)"
cat > /etc/sudoers.d/deploy-restart <<EOF
${DEPLOY_USER} ALL=(root) NOPASSWD: /bin/systemctl reload php8.2-fpm, /bin/systemctl restart php8.2-fpm, /usr/bin/supervisorctl restart eprism-queue:*, /usr/bin/supervisorctl restart eprism-reverb:*
EOF
chmod 440 /etc/sudoers.d/deploy-restart

echo "==> Writing Nginx vhost for ${DOMAIN}"
sed "s/__DOMAIN__/${DOMAIN}/g; s#__APP_DIR__#${APP_DIR}#g" "$(dirname "$0")/nginx.conf.template" > "/etc/nginx/sites-available/${DOMAIN}"
ln -sf "/etc/nginx/sites-available/${DOMAIN}" "/etc/nginx/sites-enabled/${DOMAIN}"
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

echo "==> Writing Supervisor configs"
sed "s#__APP_DIR__#${APP_DIR}#g" "$(dirname "$0")/supervisor-queue.conf.template" > /etc/supervisor/conf.d/eprism-queue.conf
sed "s#__APP_DIR__#${APP_DIR}#g" "$(dirname "$0")/supervisor-reverb.conf.template" > /etc/supervisor/conf.d/eprism-reverb.conf
supervisorctl reread
supervisorctl update

echo "==> Configuring firewall"
ufw allow OpenSSH
ufw allow "Nginx Full"
ufw --force enable

echo "==> Adding Laravel scheduler cron for ${DEPLOY_USER}"
( crontab -u "${DEPLOY_USER}" -l 2>/dev/null; echo "* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1" ) | crontab -u "${DEPLOY_USER}" -

cat <<EOF

==================================================================
Provisioning complete.

Next manual steps:
1. Add your GitHub Actions deploy public key to:
   /home/${DEPLOY_USER}/.ssh/authorized_keys

2. Create ${APP_DIR}/.env (copy from .env.example) and set:
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://${DOMAIN}
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_DATABASE=${DB_NAME}
   DB_USERNAME=${DB_USER}
   DB_PASSWORD=${DB_PASSWORD}
   REVERB_APP_ID / REVERB_APP_KEY / REVERB_APP_SECRET  (php artisan reverb:install or set manually)

3. Run once inside ${APP_DIR} as the deploy user:
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   npm ci && npm run build
   php artisan migrate --force
   php artisan storage:link
   sudo supervisorctl restart eprism-queue:* eprism-reverb:*

4. Issue SSL certificate:
   certbot --nginx -d ${DOMAIN}

5. Add these secrets to your GitHub repo (Settings > Secrets and variables > Actions):
   VPS_HOST=<server IP or hostname>
   VPS_USER=${DEPLOY_USER}
   VPS_SSH_KEY=<private key matching the authorized_keys entry above>
   VPS_PORT=22
   VPS_APP_DIR=${APP_DIR}
==================================================================
EOF
