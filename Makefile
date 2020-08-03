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
	docker-compose exec --user www-data app php occ db: convert-filecache-bigint -n

init-cron: 
	docker-compose up -d cron


