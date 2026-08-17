#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/fintracker"
BACKEND_DIR="$APP_DIR/backend"
WEB_DIR="$APP_DIR/web"

echo "==> Ensuring packages..."
export DEBIAN_FRONTEND=noninteractive
sudo apt-get update -qq
sudo apt-get install -y -qq nginx php8.3-fpm php8.3-cli php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath unzip git certbot python3-certbot-nginx composer rsync

echo "==> Preparing directories..."
sudo mkdir -p "$BACKEND_DIR" "$WEB_DIR"
sudo chown -R ubuntu:ubuntu "$APP_DIR"

echo "==> Installing nginx site..."
sudo cp "$BACKEND_DIR/deploy/nginx-fintracker.conf" /etc/nginx/sites-available/fintracker
sudo ln -sf /etc/nginx/sites-available/fintracker /etc/nginx/sites-enabled/fintracker
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl enable nginx php8.3-fpm
sudo systemctl restart php8.3-fpm nginx

if [ ! -f "$BACKEND_DIR/.env" ]; then
  echo "==> Creating .env..."
  cp "$BACKEND_DIR/.env.example" "$BACKEND_DIR/.env"
  sed -i "s|APP_ENV=local|APP_ENV=production|" "$BACKEND_DIR/.env"
  sed -i "s|APP_DEBUG=true|APP_DEBUG=false|" "$BACKEND_DIR/.env"
  sed -i "s|APP_URL=.*|APP_URL=https://fintracker.ztox.in|" "$BACKEND_DIR/.env"
  cd "$BACKEND_DIR"
  php artisan key:generate --force
fi

echo "==> Composer install & migrate..."
cd "$BACKEND_DIR"
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:cache
php artisan route:cache

echo "==> Permissions..."
sudo chown -R www-data:www-data "$BACKEND_DIR/storage" "$BACKEND_DIR/bootstrap/cache" "$BACKEND_DIR/database"
sudo chmod -R 775 "$BACKEND_DIR/storage" "$BACKEND_DIR/bootstrap/cache" "$BACKEND_DIR/database"
sudo chown -R www-data:www-data "$WEB_DIR"

if ! sudo certbot certificates 2>/dev/null | grep -q fintracker.ztox.in; then
  echo "==> Obtaining SSL certificate..."
  sudo certbot --nginx -d fintracker.ztox.in --non-interactive --agree-tos -m admin@ztox.in --redirect || true
fi

echo "==> Done. Visit https://fintracker.ztox.in"
