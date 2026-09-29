#!/usr/bin/env bash
set -euo pipefail

output="$(GITHUB_ACTIONS=true make --dry-run appstore)"

if ! grep -Fq '[ "true" = "true" ]' <<<"${output}"; then
  echo "Expected GNU Make to expand GITHUB_ACTIONS=true inside the appstore recipe." >&2
  exit 1
fi

if ! grep -Fq 'maintenance:install' <<<"${output}"; then
  echo "Expected appstore recipe to include Nextcloud setup when packaging in CI." >&2
  exit 1
fi

if ! grep -Fq 'app:enable --force libresign' <<<"${output}"; then
  echo "Expected appstore recipe to enable LibreSign before running its occ commands." >&2
  exit 1
fi
