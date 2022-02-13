#!/usr/bin/env bash

APP_INTEGRATION_DIR=$PWD
ROOT_DIR=${APP_INTEGRATION_DIR}/../../../..
composer install

php -S localhost:8080 -t ${ROOT_DIR} &
PHPPID=$!
echo $PHPPID

# also kill php process in case of ctrl+c
trap 'kill -TERM $PHPPID; wait $PHPPID' TERM

${ROOT_DIR}/occ app:enable libresign || exit 1
${ROOT_DIR}/occ app:list | grep libresign

export TEST_SERVER_URL="http://localhost:8080/"
${APP_INTEGRATION_DIR}/vendor/bin/behat -f junit -f pretty $1 $2
RESULT=$?

kill $PHPPID

wait $PHPPID

exit $RESULT
