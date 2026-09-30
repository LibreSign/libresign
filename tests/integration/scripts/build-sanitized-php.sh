#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -euo pipefail

PHP_TAG="${PHP_SANITIZER_TAG:-php-8.3.35}"
PREFIX="${PHP_SANITIZER_PREFIX:-${RUNNER_TEMP:-/tmp}/php-sanitized}"
SOURCE_DIR="${PHP_SANITIZER_SOURCE_DIR:-${RUNNER_TEMP:-/tmp}/php-src-sanitized}"
BUILD_JOBS="${PHP_SANITIZER_BUILD_JOBS:-2}"

rm -rf "${SOURCE_DIR}" "${PREFIX}"
git clone --depth 1 --branch "${PHP_TAG}" https://github.com/php/php-src.git "${SOURCE_DIR}"

cd "${SOURCE_DIR}"
./buildconf --force

export CC=clang
export CXX=clang++
export CFLAGS="-O1 -g3 -fno-omit-frame-pointer -fsanitize=address,undefined -fno-sanitize-recover=all"
export CXXFLAGS="${CFLAGS}"
export LDFLAGS="-fsanitize=address,undefined -fno-sanitize-recover=all"

./configure \
  --prefix="${PREFIX}" \
  --with-config-file-path="${PREFIX}/etc" \
  --with-config-file-scan-dir="${PREFIX}/etc/conf.d" \
  --enable-debug \
  --disable-opcache \
  --disable-cgi \
  --disable-phpdbg \
  --with-openssl \
  --with-curl \
  --with-zlib \
  --with-zip \
  --with-bz2 \
  --with-sodium \
  --enable-mbstring \
  --enable-intl \
  --enable-gd \
  --with-jpeg \
  --with-freetype \
  --enable-pcntl \
  --enable-posix \
  --enable-sockets \
  --with-mysqli=mysqlnd \
  --with-pdo-mysql=mysqlnd

make -j"${BUILD_JOBS}"
make install

mkdir -p "${PREFIX}/etc/conf.d"
cp php.ini-development "${PREFIX}/etc/php.ini"

cat > "${PREFIX}/etc/conf.d/99-libresign-sanitizer.ini" <<'INI'
memory_limit=1G
date.timezone=UTC
display_errors=1
display_startup_errors=1
error_reporting=-1
zend.assertions=1
assert.exception=1
opcache.enable=0
opcache.enable_cli=0
INI

"${PREFIX}/bin/php" -v
"${PREFIX}/bin/php" --ini
"${PREFIX}/bin/php" -m

if [ -n "${GITHUB_PATH:-}" ]; then
  echo "${PREFIX}/bin" >> "${GITHUB_PATH}"
fi
if [ -n "${GITHUB_ENV:-}" ]; then
  echo "PHP_SANITIZER_PREFIX=${PREFIX}" >> "${GITHUB_ENV}"
  echo "PHP_INI_SCAN_DIR=${PREFIX}/etc/conf.d" >> "${GITHUB_ENV}"
fi
