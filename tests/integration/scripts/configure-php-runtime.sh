#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -euo pipefail

MODE="${PHP_RUNTIME_MODE:-controlled}"
OPCACHE_MODE="${PHP_OPCACHE_MODE:-unloaded}"
OUTPUT_DIR="${PHP_RUNTIME_OUTPUT_DIR:-php-runtime-controls}"
RUNTIME_SCAN_DIR="${RUNNER_TEMP:-/tmp}/libresign-php-scan-${PHP_VERSION:-unknown}-${MODE}-${OPCACHE_MODE}"

mkdir -p "${OUTPUT_DIR}"
rm -rf "${RUNTIME_SCAN_DIR}"
mkdir -p "${RUNTIME_SCAN_DIR}"

source_scan_dir="$(php --ini | sed -n 's|^Scan for additional .ini files in: ||p' | head -n 1)"
if [ -z "${source_scan_dir}" ] || [ "${source_scan_dir}" = "(none)" ] || [ ! -d "${source_scan_dir}" ]; then
  echo "Unable to find the PHP additional ini scan directory." >&2
  php --ini >&2
  exit 1
fi

minimal_modules='bz2 ctype curl dom fileinfo gd iconv intl json libxml mbstring mysql mysqli mysqlnd openssl pcntl pdo pdo_mysql phar posix session simplexml sodium sockets tokenizer xml xmlreader xmlwriter zip zlib'
extra_group_a='amqp apcu dba enchant ffi imap ldap memcache memcached mongodb'
extra_group_b='odbc pdo_dblib pdo_firebird pdo_pgsql pgsql redis snmp soap tidy xsl yaml zmq'

ini_modules() {
  local ini="$1"
  local base
  base="$(basename "${ini}" .ini | tr '[:upper:]' '[:lower:]')"
  base="$(printf '%s' "${base}" | sed -E 's/^[0-9]+-//')"
  printf '%s\n' "${base}"

  sed -nE 's/^[[:space:]]*(zend_)?extension[[:space:]]*=[[:space:]]*"?([^";[:space:]]+).*/\2/p' "${ini}" 2>/dev/null     | while IFS= read -r extension_path; do
        basename "${extension_path}" .so | tr '[:upper:]' '[:lower:]'
      done
}

ini_identity() {
  local ini="$1"
  ini_modules "${ini}" | tr '\n' ' '
}

contains_module() {
  local ini="$1"
  local modules="$2"
  local candidate module

  while IFS= read -r candidate; do
    [ -n "${candidate}" ] || continue
    for module in ${modules}; do
      if [ "${candidate}" = "${module}" ]; then
        return 0
      fi
    done
  done < <(ini_modules "${ini}")

  return 1
}

is_opcache_ini() {
  contains_module "$1" "opcache"
}

is_imagick_ini() {
  contains_module "$1" "imagick"
}

select_ini() {
  local ini="$1"
  local identity
  identity="$(ini_identity "${ini}")"

  if is_opcache_ini "${ini}"; then
    [ "${OPCACHE_MODE}" != "unloaded" ]
    return
  fi

  case "${MODE}" in
    default)
      # Default means preserve the runner extension layout, except Imagick is
      # excluded unless the case explicitly asks for it.
      ! is_imagick_ini "${ini}"
      ;;
    controlled)
      contains_module "${ini}" "${minimal_modules}"
      ;;
    controlled-imagick)
      contains_module "${ini}" "${minimal_modules}" || is_imagick_ini "${ini}"
      ;;
    controlled-xdebug)
      contains_module "${ini}" "${minimal_modules} xdebug"
      ;;
    controlled-extra-a)
      contains_module "${ini}" "${minimal_modules} ${extra_group_a}"
      ;;
    controlled-extra-b)
      contains_module "${ini}" "${minimal_modules} ${extra_group_b}"
      ;;
    controlled-extra-all)
      contains_module "${ini}" "${minimal_modules} ${extra_group_a} ${extra_group_b}"
      ;;
    *)
      echo "Unknown PHP_RUNTIME_MODE: ${MODE}" >&2
      exit 2
      ;;
  esac
}

{
  echo "mode=${MODE}"
  echo "opcache_mode=${OPCACHE_MODE}"
  echo "source_scan_dir=${source_scan_dir}"
  echo "runtime_scan_dir=${RUNTIME_SCAN_DIR}"
  echo
  echo "== source ini files =="
  find "${source_scan_dir}" -maxdepth 1 -type f -name '*.ini' -printf '%f\n' | sort
} > "${OUTPUT_DIR}/runtime-selection.txt"

for ini in "${source_scan_dir}"/*.ini; do
  [ -f "${ini}" ] || continue
  if select_ini "${ini}"; then
    ln -s "${ini}" "${RUNTIME_SCAN_DIR}/$(basename "${ini}")"
    echo "selected $(basename "${ini}") :: $(ini_identity "${ini}")" >> "${OUTPUT_DIR}/runtime-selection.txt"
  else
    echo "omitted  $(basename "${ini}") :: $(ini_identity "${ini}")" >> "${OUTPUT_DIR}/runtime-selection.txt"
  fi
done

case "${OPCACHE_MODE}" in
  unloaded)
    ;;
  loaded-cli-nojit)
    cat > "${RUNTIME_SCAN_DIR}/99-libresign-opcache-controls.ini" <<'INI'
opcache.enable=1
opcache.enable_cli=1
opcache.jit=disable
opcache.jit_buffer_size=0
INI
    ;;
  jit-tracing)
    cat > "${RUNTIME_SCAN_DIR}/99-libresign-opcache-controls.ini" <<'INI'
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=64M
INI
    ;;
  *)
    echo "Unknown PHP_OPCACHE_MODE: ${OPCACHE_MODE}" >&2
    exit 2
    ;;
esac

export PHP_INI_SCAN_DIR="${RUNTIME_SCAN_DIR}"
if [ -n "${GITHUB_ENV:-}" ]; then
  echo "PHP_INI_SCAN_DIR=${RUNTIME_SCAN_DIR}" >> "${GITHUB_ENV}"
fi

php -v > "${OUTPUT_DIR}/php-version.txt" 2>&1
php --ini > "${OUTPUT_DIR}/php-ini.txt" 2>&1
php -m | sort > "${OUTPUT_DIR}/php-modules.txt"
php -i > "${OUTPUT_DIR}/php-info.txt" 2>&1

opcache_function="$(php -r 'echo function_exists("opcache_get_status") ? "yes" : "no";')"
imagick_loaded="$(php -r 'echo extension_loaded("imagick") ? "yes" : "no";')"

{
  echo "php_version=$(php -r 'echo PHP_VERSION;')"
  echo "runtime_mode=${MODE}"
  echo "opcache_mode=${OPCACHE_MODE}"
  echo "opcache_api_available=${opcache_function}"
  echo "opcache_enable=$(php -r 'echo ini_get("opcache.enable");')"
  echo "opcache_enable_cli=$(php -r 'echo ini_get("opcache.enable_cli");')"
  echo "opcache_jit=$(php -r 'echo ini_get("opcache.jit");')"
  echo "opcache_jit_buffer_size=$(php -r 'echo ini_get("opcache.jit_buffer_size");')"
  echo "imagick_loaded=${imagick_loaded}"
} | tee "${OUTPUT_DIR}/effective-controls.txt"

if [ "${OPCACHE_MODE}" = "unloaded" ] && [ "${opcache_function}" != "no" ]; then
  echo "OPcache was requested as unloaded but its API is still available." >&2
  exit 1
fi

if [ "${OPCACHE_MODE}" != "unloaded" ] && [ "${opcache_function}" != "yes" ]; then
  echo "OPcache was requested as loaded but its API is unavailable." >&2
  exit 1
fi

case "${MODE}" in
  controlled-imagick)
    if [ "${imagick_loaded}" != "yes" ]; then
      echo "Imagick was requested but is not loaded." >&2
      exit 1
    fi
    ;;
  *)
    if [ "${imagick_loaded}" != "no" ]; then
      echo "Imagick must be absent in runtime mode ${MODE}." >&2
      exit 1
    fi
    ;;
esac

echo "PHP runtime controls verified."
