#!/bin/sh
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eu
root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
image='openapitools/openapi-generator-cli@sha256:509f01c3c7eee9d1ad286506a7b6aa4624a95b410be9a238a306d209e900621f'
output=${1:-build/sdk-generation/typescript}
case "$output" in *..*) echo "Output must not contain .." >&2; exit 1;; esac
case "$output" in build/sdk-generation/*) ;; *) echo 'Output must be under build/sdk-generation/' >&2; exit 1;; esac
cd "$root"
mkdir -p "$output"
docker run --rm --user "$(id -u):$(id -g)" -v "$root:/local" "$image" validate -i /local/openapi-full.json
docker run --rm --user "$(id -u):$(id -g)" -v "$root:/local" "$image" generate -i /local/openapi-full.json -g typescript-axios -o "/local/$output" --additional-properties=useSingleRequestParameter=true
