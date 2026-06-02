#!/bin/bash
#
# SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#

set -e

(
	. /var/www/scripts/entrypoint.sh && php-fpm --daemonize
)

echo "🚀 Bootstrapping GoPaperless..."

# =========================
# GIT SAFE DIRECTORIES
# =========================
git config --global --add safe.directory /var/www/html
git config --global --add safe.directory /var/www/html/apps-extra/libresign

# =========================
# PROJECT SETUP
# =========================
cd /var/www/html/apps-extra/libresign

# If 3rdparty exists but is not a proper submodule (e.g., leftover files
# from a broken init), remove it so submodule update can clone it fresh.
if [[ -d "3rdparty" && ! -f "3rdparty/.git" ]]; then
	echo "⚠️  3rdparty exists but is not a submodule. Cleaning up..."
	rm -rf 3rdparty
fi

git submodule update --init --recursive

# =========================
# PHP DEPENDENCIES
# =========================
if [[ ! -d "vendor" ]]; then
	echo "📦 Installing composer dependencies..."
	composer install
fi

# =========================
# ENABLE APP
# =========================
occ app:enable libresign

# =========================
# LIBRESIGN INSTALLERS
# =========================
occ libresign:install --use-local-cert --java || true
occ libresign:install --use-local-cert --pdftk || true
occ libresign:install --use-local-cert --jsignpdf || true

# =========================
# OPENSSL CONFIG
# =========================
occ libresign:configure:openssl \
	--cn=CommonName \
	--c=BR \
	--ou=OrganizationUnit \
	--st=RioDeJaneiro \
	--o=LibreSign \
	--l=RioDeJaneiro || true

# =========================
# NEXTCLOUD PREVIEWS
# =========================
echo "🖼️ Configuring previews..."

occ config:system:set enable_previews \
	--value=true \
	--type=boolean

occ config:system:set enabledPreviewProviders 0 \
	--value="OC\\Preview\\PNG"

occ config:system:set enabledPreviewProviders 1 \
	--value="OC\\Preview\\JPEG"

occ config:system:set enabledPreviewProviders 2 \
	--value="OC\\Preview\\GIF"

occ config:system:set enabledPreviewProviders 3 \
	--value="OC\\Preview\\PDF"

occ config:system:set preview_max_x --value=2048
occ config:system:set preview_max_y --value=2048

# =========================
# THEMING
# =========================
echo "🎨 Configuring GoPaperless theme..."

occ theming:config name 'GoPaperless'
occ theming:config url 'https://gopaperless.ke'
occ theming:config primary_color '#04D56D'
occ theming:config background_color '#0F172A'

# =========================
# DEFAULT SETTINGS
# =========================
echo "⚙️ Applying default configuration..."

occ config:app:set libresign extra_settings --value=1

occ config:system:set defaultapp \
	--value=libresign

occ maintenance:theme:update

# =========================
# FRONTEND
# =========================
if [[ ! -d "node_modules" ]]; then
	echo "📦 Installing npm dependencies..."
	npm ci
fi

# =========================
# DEV SERVER
# =========================
echo "🧪 Starting frontend dev server..."

npm run dev

echo ""
echo "✍️ LibreSign is up!"
echo "🌱 GoPaperless development environment ready!"
echo ""
echo "If you want to develop at frontend, run:"
echo "npm run watch"
