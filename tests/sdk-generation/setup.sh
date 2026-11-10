#!/bin/sh
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eu
root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
npm ci --prefix "$root/tests/sdk-generation" --ignore-scripts --no-audit --no-fund
mkdir -p "$root/build/sdk-generation"
if [ ! -e "$root/build/sdk-generation/node_modules" ]; then
  ln -s ../../tests/sdk-generation/node_modules "$root/build/sdk-generation/node_modules"
fi
