#!/usr/bin/env bats
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

# The central catalog selects the published release; consumers must not mix revisions.
@test "release-tool workflows use a single immutable release commit" {
    local root pinned
    root="$(cd "${BATS_TEST_DIRNAME}/../.." && pwd)"
    pinned="$(grep -hEo 'LibreCodeCoop/release-tool/actions/[a-z-]+@[0-9a-f]{40}' \
      "${root}"/.github/workflows/*.yml | sed -E 's/.*@//' | sort -u)"

    [ "${#pinned}" -eq 40 ]
    [ "${pinned}" = "51355769089a6796c23d6c966108afbdaf858c32" ]
}
