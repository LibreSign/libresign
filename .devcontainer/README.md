<!--
 - SPDX-FileCopyrightText: 2024-2026 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# LibreSign dev container

The dev container is a thin adapter over
`LibreCodeCoop/nextcloud-docker-development` (NCDD).

Open the LibreSign repository in VS Code and choose **Reopen in Container**.
The initialization step fetches the pinned NCDD revision and asks NCDD's
`dev-worker config` command to resolve the canonical environment for this
worktree. LibreSign only adds its source mount at
`/var/www/html/apps-extra/libresign`.

A deterministic worker id is derived from the local worktree path, so separate
worktrees resolve to separate NCDD mutable-state directories.

Nextcloud is accessed through NCDD's shared HTTPS proxy using the canonical
worker hostname printed by the setup script, for example:

```text
https://ncdev-libresign-123456.localhost
```

Do not forward `nginx:80` as the Nextcloud web endpoint. NCDD configures
Nextcloud's trusted domain, overwrite host and HTTPS protocol for the proxy
hostname, so bypassing the proxy would use a different public URL.

Mailpit is safe to forward directly through the Dev Container and remains
available through NCDD's proxy as well.

The adapter defaults to PHP 8.3, Nextcloud `master`, MariaDB 10.6. These may be
changed on the host before reopening the container:

```bash
export LIBRESIGN_PHP_VERSION=83
export LIBRESIGN_NEXTCLOUD_VERSION=master
export LIBRESIGN_DB_TYPE=mariadb
export LIBRESIGN_MARIADB_VERSION=10.6
```

Closing or rebuilding this dev container only affects its Compose project.
Do not use global Docker cleanup commands to stop unrelated containers or
delete unrelated volumes.

Canonical development documentation lives in the
[LibreSign Developer Manual](https://docs.libresign.coop/developer_manual/).
