#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

# Long-term archive only. Octocov's operational reports are stored in
# artifact://LibreSign/libresign, not this release. Never amend a saved run.
set -euo pipefail

: "${GH_TOKEN:?A scoped release-write GitHub App token is required}"
: "${SOURCE_SHA:?Source commit required}"
: "${SOURCE_RUN_ID:?Source run ID required}"
: "${SOURCE_RUN_ATTEMPT:?Run attempt required}"
[[ "${SOURCE_SHA}" =~ ^[0-9a-f]{40}$ ]] || { echo "::error::Invalid source SHA"; exit 1; }
[[ "${SOURCE_RUN_ID}" =~ ^[0-9]+$ && "${SOURCE_RUN_ATTEMPT}" =~ ^[0-9]+$ ]] || exit 1
target=LibreSign/coverage
snapshot=coverage-snapshot
test -s "${snapshot}/libresign-octocov.json.gz"
test -s "${snapshot}/libresign-clover.xml.gz"
test -s "${snapshot}/metadata.json"
(cd "${snapshot}" && sha256sum -c checksums.sha256)

archive_tag="coverage-history-$(date -u +%Y-%m)"
archive_key="${SOURCE_SHA}-run-${SOURCE_RUN_ID}-attempt-${SOURCE_RUN_ATTEMPT}"
mkdir -p coverage-history
cp "${snapshot}/libresign-octocov.json.gz" "coverage-history/libresign-${archive_key}-octocov.json.gz"
cp "${snapshot}/libresign-clover.xml.gz" "coverage-history/libresign-${archive_key}-clover.xml.gz"
cp "${snapshot}/metadata.json" "coverage-history/libresign-${archive_key}-metadata.json"
(
  cd coverage-history
  sha256sum "libresign-${archive_key}-octocov.json.gz" \
            "libresign-${archive_key}-clover.xml.gz" \
            "libresign-${archive_key}-metadata.json" \
      > "libresign-${archive_key}-checksums.sha256"
)

if ! gh release view "${archive_tag}" --repo "${target}" >/dev/null 2>&1; then
  gh release create "${archive_tag}" --repo "${target}" --target main \
    --title "LibreSign coverage archive ${archive_tag#coverage-history-}" \
    --notes 'Write-once, SHA/run-addressed Octocov and Clover metrics. Archived reports do not include full HTML. The upstream source artifacts are the operational coverage datastore.' \
      || gh release view "${archive_tag}" --repo "${target}" >/dev/null
fi

# Releases are mutable by administrators, but this workflow treats the
# historical assets as write-once. A rerun cannot silently replace metrics
# already recorded under the same commit/run/attempt identifier.
for file in coverage-history/*; do
  name="${file##*/}"
  expected="sha256:$(sha256sum "${file}" | cut -d' ' -f1)"
  actual="$(gh api "repos/${target}/releases/tags/${archive_tag}" \
    --jq ".assets[] | select(.name == \"${name}\") | .digest" | head -n 1)"
  if [[ -n "${actual}" ]]; then
    if [[ "${actual}" != "${expected}" ]]; then
      echo "::error::Conflicting archived coverage asset: ${name}"
      exit 1
    fi
    echo "Existing SHA-256 verified for archive asset ${name}"
    continue
  fi
  gh release upload "${archive_tag}" "${file}" --repo "${target}"
done

# Optional resiliency fallback for the Pages site. Native Octocov artifacts
# remain primary; the latest release is intentionally mutable and is *not*
# the long-term archive.
gh release upload coverage-latest "${snapshot}"/* --repo "${target}" --clobber
gh release edit coverage-latest --repo "${target}" \
  --title 'Current coverage HTML fallback snapshot' \
  --notes "Optional Pages fallback from LibreSign/libresign main commit ${SOURCE_SHA}, run ${SOURCE_RUN_ID}. Native Octocov artifacts are the primary metric source."
gh api --method POST "repos/${target}/dispatches" -f event_type=coverage-updated
