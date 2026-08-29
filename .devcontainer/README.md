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
