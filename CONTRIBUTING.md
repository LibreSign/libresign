# How to contribute?

**Simple way**: Fork and pull request! 😃

**NOTE**: If the project does not have an issue for what you want to do, create an issue first.

Feel free to submit [Github Issues](https://github.com/libresign/libresign/issues) or pull requests.

But... follow the complete way:

## To start front and backend development environment

* Start the Nextcloud development. I suggest to use this: https://github.com/LibreCodeCoop/nextcloud-docker-development/
* Wait to be possible access `localhost` in your browser
* Inside the folder `volumes/nextloud/apps-extra` clone the LibreSign repository
* open bash in nextcloud container with `docker compose exec -u www-data nextcloud bash`
* Inside bash of Nextcloud, go to `folder apps-extra/libresign`
* Run the commands:
  ```bash
  # download composer dependencies
  composer install
  # download JS dependencies
  npm ci
  # build and watch JS changes
  npm run watch
  ```
* Now, access the `localhost` on your browser

## To update API documentation

[Repository of site and API documentation](https://github.com/libresign/libresign.github.io)

## Executing tests
### PHPUnit, Psalm, PHPCS,...

* Run all tests inside `bash` of `nextcloud` service

Read more in composer.json scripts section

### Behat

* Access bash of `nextcloud` service
* Go to folder `tests/integration` of Libresign app
* Install dependencies:
  ```bash
  composer i
  ```
* Run tests:
  ```bash
  vendor/bin/behat
  ```

You also can run a specific scenario
