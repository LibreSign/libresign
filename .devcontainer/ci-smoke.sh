#!/bin/bash
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -Eeo pipefail

repo_root="$(cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$repo_root"

compose=(
	docker compose
	--file .devcontainer/ncdd.generated.yml
	--file .devcontainer/docker-compose.yml
	--profile playwright
)

cleanup() {
	"${compose[@]}" down --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

sh .devcontainer/prepare.sh

grep -q "HOST_UID: $(id -u)" .devcontainer/ncdd.generated.yml
grep -q "HOST_GID: $(id -g)" .devcontainer/ncdd.generated.yml

CODESPACES=true CODESPACE_NAME=libresign-test GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN=app.github.dev 	sh .devcontainer/prepare.sh

grep -q 'NEXTCLOUD_HOST: libresign-test-443.app.github.dev' 	.devcontainer/ncdd.generated.yml

sh .devcontainer/prepare.sh

"${compose[@]}" config --quiet
"${compose[@]}" up -d

for _ in {1..120}; do
	if "${compose[@]}" exec -T nextcloud occ status 2>/dev/null |
		grep -q 'installed: true'; then
		break
	fi
	sleep 2
done

"${compose[@]}" exec -T nextcloud occ status |
	grep -q 'installed: true'

nextcloud_host="$("${compose[@]}" config |
	awk '/NEXTCLOUD_HOST:/ { print $2; exit }')"
mailpit_host="$("${compose[@]}" config |
	awk '/VIRTUAL_HOST: .*mailpit/ { print $2; exit }')"

curl --fail --insecure --retry 20 --retry-all-errors 	--resolve "$nextcloud_host:443:127.0.0.1" 	"https://$nextcloud_host/status.php" >/dev/null

curl --fail --insecure --retry 20 --retry-all-errors 	--resolve "$mailpit_host:443:127.0.0.1" 	"https://$mailpit_host/api/v1/info" >/dev/null

"${compose[@]}" exec -T playwright node -e '
	const https = require("https");
	const target = new URL(process.env.PLAYWRIGHT_BASE_URL + "/status.php");
	const request = https.get(target, { rejectUnauthorized: false }, response => {
		if (response.statusCode !== 200) {
			console.error("Unexpected status:", response.statusCode);
			process.exitCode = 1;
		}
		response.resume();
	});
	request.on("error", error => {
		console.error(error);
		process.exitCode = 1;
	});
'
