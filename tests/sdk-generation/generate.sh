#!/bin/sh
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Usage: generate.sh [output [input]]
# The input defaults to the repository's openapi-full.json. Any other input
# must be a disposable copy under build/sdk-generation/.
set -eu
root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
# OpenAPI Generator 7.27.0
image='openapitools/openapi-generator-cli@sha256:949efd0d5cf3c093ef169887b2ebe0879ee2e8074b6476264232ce4fd8ef46d0'
output=${1:-build/sdk-generation/typescript}
input=${2:-openapi-full.json}
case "$output$input" in *..*) echo 'Paths must not contain ..' >&2; exit 1;; esac
case "$output" in build/sdk-generation/?*) ;; *) echo 'Output must be under build/sdk-generation/' >&2; exit 1;; esac
case "$input" in openapi-full.json|build/sdk-generation/*) ;; *) echo 'Input must be openapi-full.json or a copy under build/sdk-generation/' >&2; exit 1;; esac
cd "$root"
# Keep cleanup inside build/ and prevent aliases from hiding input/output overlap.
for candidate in "$output" "$input"; do
  case "$candidate" in */.|*/./*|*//*|*/) echo 'Paths must not contain dot or empty components' >&2; exit 1;; esac
  ancestor=$candidate
  while [ "$ancestor" != . ]; do
    if [ -L "$ancestor" ]; then
      echo 'Input/output paths and their ancestors must not be symlinks' >&2
      exit 1
    fi
    ancestor=$(dirname -- "$ancestor")
  done
done
case "$input" in "$output"|"$output"/*) echo 'Input must be outside the output directory' >&2; exit 1;; esac
docker run --rm --user "$(id -u):$(id -g)" -v "$root:/local" "$image" validate -i "/local/$input"
rm -rf -- "$output"
mkdir -p "$output"
docker run --rm --user "$(id -u):$(id -g)" -v "$root:/local" "$image" generate -i "/local/$input" -g typescript-axios -o "/local/$output" --additional-properties=useSingleRequestParameter=true
