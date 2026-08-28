<!--
 - SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
## Starting devcontainer

- Use vscode (codium don't will work)
- Stup the extension devcontainer
- Open libresign folder at vscode
- After open you will see a message "Reopen in container", do this

## Stopping devcontainer

```bash
docker stop $(docker ps -aq)
docker rm $(docker ps -aq)
```
## Cleaning all volumes

This will be necessary when you want to have a new clean environment

```bash
docker volume rm $(docker volume ls -q )
```
## Looking logs

### Nginx

```bash
docker logs libresign_devcontainer-nginx-1 -f --tail 100
```

### Nextcloud

- Open the console inside vscode
- Run:
  ```bash
  tail -f data/nextcloud.log
  ```

### Database

- Open the console inside vscode
- Run:
  ```bash
  tail -f data/database.log
  ```

## Behat integration tests

The PhpBuiltin server used by Behat does not load APCu the same way as php-fpm. Configure ArrayCache before running scenarios locally:

```bash
occ config:system:set memcache.local --value '\OC\Memcache\ArrayCache'
occ config:system:set memcache.distributed --value '\OC\Memcache\ArrayCache'
occ config:system:set memcache.locking --value '\OC\Memcache\ArrayCache'
```

Run Behat as `www-data` so the built-in server, cache directories, and test users stay aligned:

```bash
mkdir -p /tmp/behat_gherkin_cache /tmp/behat_rerun_cache
chown -R www-data:$(id -gn www-data) /tmp/behat_gherkin_cache /tmp/behat_rerun_cache
cd apps-extra/libresign/tests/integration
runuser -u www-data -- env BEHAT_ROOT_DIR=/var/www/html BEHAT_RUN_AS=www-data vendor/bin/behat features/policies/signer_geolocation_policy.feature
```

If a previous manual `occ user:add` left test users with the wrong password, delete them first (`occ user:delete signer1`) so Behat can recreate users with the default test password.
