all: dev-setup build-production 
serve: dev-setup watch 

dev-setup: clean npm-init

npm-init:
	docker-compose run --rm node npm ci

npm-update:
	docker-compose run --rm node npm update

watch: npm-update
	docker-compose up 

build-production: lint
	docker-compose run --rm node npm run build

lint-fix:
	docker-compose run --rm node npm run lint:fix
	docker-compose run --rm node npm run stylelint:fix

lint:
	docker-compose run --rm node npm run lint
	docker-compose run --rm node npm run stylelint

clean:
	rm -rf js/*
	rm -rf node_modules
