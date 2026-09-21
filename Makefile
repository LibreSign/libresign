# SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

# Dependencies:
# * make
# * npm
# * curl: used if phpunit and composer are not installed to fetch them from the web
# * tar: for building the archive

app_name=$(notdir $(CURDIR))
project_directory=$(CURDIR)/../$(app_name)
build_tools_directory=$(CURDIR)/build/tools
appstore_build_directory=$(CURDIR)/build/artifacts
appstore_package_name=$(appstore_build_directory)/$(app_name)
appstore_sign_dir=$(appstore_build_directory)/sign
cert_dir=$(build_tools_directory)/certificates
release_version=$(shell sed -n 's:.*<version>\([^<]*\)</version>.*:\1:p' appinfo/info.xml)
release_major=$(word 1,$(subst ., ,$(release_version)))
release_changelog=$(CURDIR)/docs/changelogs/changelog-$(release_major).md
npm=$(shell which npm 2> /dev/null)
composer=$(shell which composer 2> /dev/null)
ifneq (,$(wildcard $(CURDIR)/../nextcloud/occ))
	occ=php $(CURDIR)/../nextcloud/occ
else ifneq (,$(wildcard $(CURDIR)/../../occ))
	occ=php $(CURDIR)/../../occ
endif

all: dev-setup build-js-production
serve: dev-setup watch-js

# Installs and updates the composer dependencies. If composer is not installed
# a copy is fetched from the web
.PHONY: composer
composer:
ifeq (,$(composer))
	@echo "No composer command available, downloading a copy from the web"
	mkdir -p $(build_tools_directory)
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar $(build_tools_directory)
	php $(build_tools_directory)/composer.phar install --prefer-dist
else
	composer install --prefer-dist
endif

# Dev env management
dev-setup: clean clean-dev composer npm-install

npm-install:
	npm ci

# Building
build-js:
	npm run dev

build-js-production:
	npm run build

watch-js:
	npm run watch

# Linting
lint:
	npm run lint

lint-fix:
	npm run lint:fix
	npm run stylelint:fix

# Style linting
stylelint:
	npm run stylelint

# Cleaning
.PHONY: clean
clean:
	rm -rf js/
	rm -rf $(appstore_build_directory)

clean-dev:
	rm -rf node_modules
	rm -rf vendor
	rm -rf $(appstore_build_directory)

.PHONY: test
test: composer
	$(CURDIR)/vendor/bin/phpunit -c phpunit.xml

.PHONY: update-workflows
update-workflows:
	@echo "Updating GitHub workflows from the Nextcloud template repository..."
	@if [ ! -d ./.github/workflows/ ]; then \
		echo "Error: .github/workflows does not exist"; \
		exit 1; \
	fi
	@temp=$(mktemp -d); \
	git clone --depth=1 https://github.com/nextcloud/.github.git "$temp"; \
	rsync -vr --existing --include='*/' --include='*.yml' --exclude='*' "$temp/workflow-templates/" ./.github/workflows/; \
	rm -rf "$temp"; \
	echo "Workflows updated successfully."

updateocp:
	php -r 'if (shell_exec("diff -qr ../../lib/public/ vendor/nextcloud/ocp/OCP/")) {\exec("rm -rf vendor/nextcloud/ocp/OCP/");\exec("cp -r ../../lib/public vendor/nextcloud/ocp/OCP/");}'

.PHONY: verify-release-metadata
verify-release-metadata:
	@test -n "$(release_version)" || (echo "Unable to read app version from appinfo/info.xml" >&2; exit 1)
	@test -f "$(release_changelog)" || (echo "Missing changelog for app major $(release_major): $(release_changelog)" >&2; exit 1)
	@node -e 'const fs=require("fs"); const p=require("./package.json").version; const l=require("./package-lock.json").version; const xml=fs.readFileSync("appinfo/info.xml","utf8").match(/<version>([^<]+)<\/version>/)?.[1]; if (!xml || p !== xml || l !== xml) { console.error(`Release version mismatch: info.xml=${xml} package.json=${p} package-lock.json=${l}`); process.exit(1); }'

.PHONY: print-release-changelog
print-release-changelog: verify-release-metadata
	@printf '%s\n' "$(release_changelog)"

# Builds the source package for the app store, ignores php and js tests
.PHONY: appstore
appstore: verify-release-metadata
	rm -rf $(appstore_build_directory)
	mkdir -p $(appstore_sign_dir)/$(app_name)
	cp -r \
		appinfo \
		composer \
		css \
		img \
		js \
		l10n \
		lib \
		templates \
		vendor \
		3rdparty \
		openapi*.json \
		$(appstore_sign_dir)/$(app_name)
	cp $(release_changelog) $(appstore_sign_dir)/$(app_name)/CHANGELOG.md
	if [ -d dist ]; then \
		cp -r dist $(appstore_sign_dir)/$(app_name)/; \
	fi
	rm -rf $(appstore_sign_dir)/$(app_name)/img/screenshot/
	rm -rf $(appstore_sign_dir)/$(app_name)/3rdparty/.git
	rm -rf $(appstore_sign_dir)/$(app_name)/3rdparty/.github
	rm -rf $(appstore_sign_dir)/$(app_name)/3rdparty/vendor
	rm -rf $(appstore_sign_dir)/$(app_name)/3rdparty/vendor-bin
	rm -rf $(appstore_sign_dir)/$(app_name)/3rdparty/scoper.inc.php
	mkdir -p $(appstore_sign_dir)/$(app_name)/tests/php/fixtures
	cp tests/php/fixtures/pdfs/small_valid.pdf $(appstore_sign_dir)/$(app_name)/tests/php/fixtures

	mkdir -p $(cert_dir)
	if [ -n "$$GITHUB_ACTION" ]; then \
		echo "⌛️ Starting Nextcloud setup..."; \
		mkdir $(CURDIR)/../nextcloud/data; \
		ln -s $(CURDIR) $(CURDIR)/../nextcloud/apps/libresign; \
		$(occ) maintenance:install \
			--verbose \
			--database=sqlite \
			--database-name=nextcloud \
			--database-host=127.0.0.1 \
			--database-user=root \
			--database-pass=rootpassword \
			--admin-user admin \
			--admin-pass admin; \
		$(occ) --version; \
		$(occ) app:enable --force libresign; \
		echo "🏁 Setup finished"; \
	fi

	if [ -f $(cert_dir)/$(app_name).key ]; then \
		curl -o $(cert_dir)/$(app_name).crt \
			"https://raw.githubusercontent.com/nextcloud/app-certificate-requests/master/$(app_name)/$(app_name).crt"; \
		$(occ) libresign:install --all --all-distros --architecture=aarch64; \
		$(occ) libresign:install --all --all-distros --architecture=x86_64; \
		echo "Signing setup files…"; \
		$(occ) config:system:set debug --value true --type boolean; \
		$(occ) libresign:developer:sign-setup \
			--privateKey=$(cert_dir)/$(app_name).key \
			--certificate=$(cert_dir)/$(app_name).crt; \
		cp -r appinfo $(appstore_sign_dir)/$(app_name); \
		chmod -R a+w $(appstore_sign_dir)/$(app_name); \
		echo "Signing app files…"; \
		$(occ) integrity:sign-app \
			--privateKey=$(cert_dir)/$(app_name).key\
			--certificate=$(cert_dir)/$(app_name).crt\
			--path=$(appstore_sign_dir)/$(app_name); \
	fi
	tar -czf $(appstore_package_name).tar.gz \
		-C $(appstore_sign_dir) $(app_name)

	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(appstore_package_name).tar.gz | openssl base64; \
	fi

.PHONY: verify-appstore-package
verify-appstore-package:
	test -d $(appstore_sign_dir)/$(app_name)/css
	test -d $(appstore_sign_dir)/$(app_name)/js
	find \
		$(appstore_sign_dir)/$(app_name)/js \
		$(appstore_sign_dir)/$(app_name)/dist \
		-maxdepth 1 -name 'pdf.worker.min-*.mjs' 2>/dev/null | grep -q .
	if [ -d dist ]; then \
		test -d $(appstore_sign_dir)/$(app_name)/dist; \
	fi
