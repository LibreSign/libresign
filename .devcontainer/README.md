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

For GitHub Codespaces, the same shared proxy remains the only HTTP entry point.
The Dev Container forwards `host.docker.internal:443`, which is the Docker
host port owned by the shared NCDD proxy, and the adapter configures Nextcloud
with the public hostname GitHub assigns to forwarded port `443`:

```text
https://<codespace>-443.<forwarding-domain>
```

The forwarding domain comes from
`GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN`; it is not hard-coded.

Individual workers do not publish separate nginx host ports. Mailpit is
forwarded directly from its Compose service on port `8025`.

The Playwright service is optional and is not started as part of normal Dev
Container use. When enabled, NCDD routes its canonical Nextcloud hostname back
through the shared proxy so browser tests exercise the same public URL.

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
