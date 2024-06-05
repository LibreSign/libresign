# This file is licensed under the Affero General Public License version 3 or
# later. See the LICENSE file.

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
npm=$(shell which npm 2> /dev/null)
composer=$(shell which composer 2> /dev/null)

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

updateocp:
	php -r 'if (shell_exec("diff -qr ../../lib/public/ vendor/nextcloud/ocp/OCP/")) {\exec("rm -rf vendor/nextcloud/ocp/OCP/");\exec("cp -r ../../lib/public vendor/nextcloud/ocp/OCP/");}'

# Builds the source package for the app store, ignores php and js tests
.PHONY: appstore
appstore:
	rm -rf $(appstore_build_directory)
	mkdir -p $(appstore_sign_dir)/$(app_name)
	cp -r \
		appinfo \
		composer \
		img \
		js \
		l10n \
		lib \
		templates \
		vendor \
		CHANGELOG.md \
		LICENSE \
		$(appstore_sign_dir)/$(app_name)

	rm $(appstore_sign_dir)/$(app_name)/vendor/endroid/qr-code/assets/*
	mkdir -p $(appstore_sign_dir)/$(app_name)/tests/fixtures
	cp tests/fixtures/small_valid.pdf $(appstore_sign_dir)/$(app_name)/tests/fixtures

	# Remove stray .htaccess files since they are filtered by Nextcloud
	find $(appstore_sign_dir) -name .htaccess -exec rm {} \;

	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing app files…"; \
		php ../../occ integrity:sign-app \
			--privateKey=$(cert_dir)/$(app_name).key\
			--certificate=$(cert_dir)/$(app_name).crt\
			--path=$(appstore_sign_dir)/$(app_name); \
	fi
	tar -czf $(appstore_package_name).tar.gz \
		-C $(appstore_sign_dir) $(app_name)

	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name).tar.gz | openssl base64; \
	fi

# Earlier version of appstore command that builds the app and has some custom
# support for local signing. Left here in case it's needed by some developer
# used to it.
.PHONY: appstore-local
appstore-local: clean
	mkdir -p $(appstore_sign_dir)/$(app_name)
	composer install --no-dev
	npm ci
	npm run build
	cp -r \
		appinfo \
		composer \
		img \
		js \
		l10n \
		lib \
		templates \
		vendor \
		CHANGELOG.md \
		LICENSE \
		$(appstore_sign_dir)/$(app_name)
	rm $(appstore_sign_dir)/$(app_name)/vendor/endroid/qr-code/assets/*
	find $(appstore_sign_dir)/$(app_name)/vendor/mpdf/mpdf/ttfonts -type f -not -name 'DejaVuSerifCondensed.ttf' -delete
	find $(appstore_sign_dir)/$(app_name)/vendor/mpdf/mpdf/data/ -type f -delete
	rm -rf $(appstore_sign_dir)/$(app_name)/img/screenshot/
	mkdir -p $(appstore_sign_dir)/$(app_name)/tests/fixtures
	cp tests/fixtures/small_valid.pdf $(appstore_sign_dir)/$(app_name)/tests/fixtures \

	@if [ -z "$$GITHUB_ACTION" ]; then \
		chown -R www-data:www-data $(appstore_sign_dir)/$(app_name) ; \
	fi

	mkdir -p $(cert_dir)
	@if [ ! -f $(cert_dir)/$(app_name).crt ]; then \
		curl -o $(cert_dir)/$(app_name).crt \
			"https://github.com/nextcloud/app-certificate-requests/raw/master/$(app_name)/$(app_name).crt"; \
	fi
	@if [ -n "$$APP_PRIVATE_KEY" ]; then \
		echo "$$APP_PRIVATE_KEY" > $(cert_dir)/$(app_name).key; \
		echo "Signing app files…"; \
		runuser -u www-data -- \
		php ../../occ integrity:sign-app \
			--privateKey=$(cert_dir)/$(app_name).key\
			--certificate=$(cert_dir)/$(app_name).crt\
			--path=$(appstore_sign_dir)/$(app_name); \
		echo "Signing app files ... done"; \
	fi
	tar -czf $(appstore_package_name).tar.gz -C $(appstore_sign_dir) $(app_name)
	@if [ -n "$$APP_PRIVATE_KEY" ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(appstore_package_name).tar.gz | openssl base64; \
	fi
