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

For local Docker use, NCDD exposes the environment through its shared HTTPS
proxy at the worker hostname. The Dev Container also forwards `nginx:80` and
`mailpit:8025`, so editor-managed local/remote environments can open
Nextcloud and Mailpit without publishing fixed host ports in Compose.

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
