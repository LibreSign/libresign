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

## Browser access

For local Docker/VS Code development, use NCDD's shared HTTPS proxy. The setup
script prints the canonical URL:

```text
https://ncdev-libresign-<id>.localhost
```

For GitHub Codespaces, the adapter asks NCDD to publish this worker's nginx on
port `8080` and configures Nextcloud with the corresponding Codespaces public
hostname. GitHub then forwards that port to a URL like:

```text
https://<codespace>-8080.app.github.dev
```

The actual forwarding domain comes from
`GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN`; it is not hard-coded.

The local path still uses the shared NCDD proxy. The per-worker port exists
only in Codespaces, where a concrete forwarded port is needed for browser
access.

The adapter defaults to PHP 8.3, Nextcloud `master`, MariaDB 10.6. These may be
changed on the host before reopening the container:

```bash
export LIBRESIGN_PHP_VERSION=83
export LIBRESIGN_NEXTCLOUD_VERSION=master
export LIBRESIGN_DB_TYPE=mariadb
export LIBRESIGN_MARIADB_VERSION=10.6
```

Codespaces uses port `8080` by default. It can be changed before rebuilding:

```bash
export LIBRESIGN_CODESPACES_PORT=18080
```

Closing or rebuilding this dev container only affects its Compose project.
Do not use global Docker cleanup commands to stop unrelated containers or
delete unrelated volumes.

Canonical development documentation lives in the
[LibreSign Developer Manual](https://docs.libresign.coop/developer_manual/).
