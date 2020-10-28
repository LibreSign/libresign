all: init-all

init-all: init-db init-app set-locale set-configs install-apps init-cron

init-db:
	docker-compose up -d db
	until docker-compose exec db pg_isready; do echo "Awaiting for Postgres"; sleep 5; done

init-app: 
	docker-compose up -d web app
	until docker-compose exec --user www-data app php occ status --output=json | grep -e "{\"installed\":true,"; do echo "Awaiting for NextCloud installation"; sleep 10; done

install-apps:
	docker-compose exec --user www-data app php occ app:install deck
	docker-compose exec --user www-data app php occ app:install calendar
	docker-compose exec --user www-data app php occ app:install contacts

set-locale: 
	docker-compose exec --user www-data app php occ config:system:set default_locale --value pt_BR 
	docker-compose exec --user www-data app php occ config:system:set default_language --value pt_BR 
	docker-compose exec --user www-data app sh -c "php occ user:setting \$$NEXTCLOUD_ADMIN_USER core lang pt_BR"

set-configs:
	docker-compose exec --user www-data app php occ config:system:set skeletondirectory --value  ""
	docker-compose exec --user www-data app php occ db:add-missing-indices -n
	docker-compose exec --user www-data app php occ db:convert-filecache-bigint -n

init-cron: 
	docker-compose up -d cron

install-dsv: build-dsv

fix-database:
	docker-compose run --rm --user www-data app sh -c "php occ config:system:set dbname --value \$$POSTGRES_DB"
	docker-compose exec db sh -c 'psql -U $$POSTGRES_USER postgres -c "DROP DATABASE $${POSTGRES_DB}"'
	docker-compose exec db sh -c 'psql -U $$POSTGRES_USER postgres -c "ALTER DATABASE db RENAME TO $${POSTGRES_DB}"'

build-dsv:
	docker-compose build
	docker-compose down
	docker-compose up -d
	docker-compose exec app bash -c "cd /tmp/dsv/lib; composer install --no-interaction --no-dev"
	docker-compose exec app sh -c "cp -r /tmp/dsv /var/www/html/apps/"
	docker-compose exec --user www-data app php occ app:enable dsv

install-signer:
	docker-compose build
	docker-compose down
	docker-compose up -d
	docker-compose exec app sh -c "mkdir /var/www/html/apps/signer && \
		cp -r /tmp/signer/appinfo \
		/tmp/signer/img \
		/tmp/signer/js \
		/tmp/signer/lib \
		/tmp/signer/src \
		/tmp/signer/templates \
		/tmp/signer/composer.json \
		/tmp/signer/composer.lock /var/www/html/apps/signer"
	docker-compose exec -w /var/www/html/apps/signer app bash -c "composer install --no-interaction --no-dev"
	docker-compose exec -w /var/www/html/apps/signer app bash -c "chmod +x vendor/jeidison/jsignpdf-php/bin/jre1.8.0_241_linux/bin/java"
	docker-compose exec --user www-data app php occ app:enable signer

update-signer:
	docker-compose exec app sh -c "rsync -av /tmp/signer /var/www/html/apps/ --delete --exclude node_modules --exclude vendor"

test-signer: update-signer
	docker-compose exec -w /var/www/html/apps/signer app sh -c "vendor/phpunit/phpunit/phpunit -c phpunit.xml"

build-signer-frontend:
	cd ./signer; make

lint:
	cd ./signer; make lint-fix
	make update-signer

INSTALL=0
serve-signer-frontend-dev: update-signer
ifeq ($(INSTALL), 1)
	cd ./volumes/nextcloud/apps/signer; make serve
else
	cd ./volumes/nextcloud/apps/signer; make watch
endif
