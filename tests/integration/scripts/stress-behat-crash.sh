#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -uo pipefail

ITERATIONS="${STRESS_ITERATIONS:-100}"
DIAG_DIR="${GITHUB_WORKSPACE:-$(pwd)}/behat-crash-diagnostics"
SUMMARY_FILE="${DIAG_DIR}/summary.log"

mkdir -p "${DIAG_DIR}"
: > "${SUMMARY_FILE}"

capture_environment() {
  {
    echo "timestamp=$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
    echo "iteration_limit=${ITERATIONS}"
    echo "behat_workers=${BEHAT_WORKERS:-unset}"
    echo
    echo "== uname =="
    uname -a
    echo
    echo "== os-release =="
    cat /etc/os-release || true
    echo
    echo "== php -v =="
    php -v
    echo
    echo "== php --ini =="
    php --ini
    echo
    echo "== php -m =="
    php -m
    echo
    echo "== php --ri imagick =="
    php --ri imagick || true
    echo
    echo "== php --ri opcache =="
    php --ri opcache || true
    echo
    echo "== php binary libraries =="
    ldd "$(command -v php)" || true
    echo
    echo "== limits =="
    ulimit -a
    echo
    echo "== core pattern =="
    cat /proc/sys/kernel/core_pattern || true
    echo
    echo "== LibreSign commit =="
    git -C ../.. rev-parse HEAD || true
    echo
    echo "== Nextcloud commit =="
    git -C ../../../.. rev-parse HEAD || true
  } > "${DIAG_DIR}/environment.txt" 2>&1

  php -i > "${DIAG_DIR}/php-info.txt" 2>&1 || true
}

capture_core_backtraces() {
  local found=0
  mkdir -p "${DIAG_DIR}/cores"

  for core in /tmp/core.*; do
    [ -f "${core}" ] || continue
    found=1

    base="$(basename "${core}")"
    cp "${core}" "${DIAG_DIR}/cores/${base}" 2>/dev/null || true

    gdb --batch \
      -ex 'set pagination off' \
      -ex 'thread apply all bt full' \
      -ex 'info registers' \
      -ex 'info sharedlibrary' \
      "$(command -v php)" "${core}" \
      > "${DIAG_DIR}/cores/${base}.gdb.txt" 2>&1 || true
  done

  if [ "${found}" -eq 0 ]; then
    echo "No direct core files found in /tmp." > "${DIAG_DIR}/cores/README.txt"
  fi
}

capture_failure() {
  local iteration="$1"
  local status="$2"
  local iteration_log="$3"

  echo "${iteration}" > "${DIAG_DIR}/failed-iteration.txt"
  echo "${status}" > "${DIAG_DIR}/behat-exit-status.txt"

  {
    echo "Failure at iteration ${iteration}/${ITERATIONS}"
    echo "Behat exit status: ${status}"
    echo
    echo "== process tree =="
    ps -ef --forest || true
    echo
    echo "== PHP processes =="
    ps -eo pid,ppid,pgid,sid,user,stat,etime,rss,vsz,pcpu,pmem,args | grep '[p]hp' || true
    echo
    echo "== coredumpctl list =="
    sudo coredumpctl --no-pager list || true
    echo
    echo "== coredumpctl info =="
    sudo coredumpctl --no-pager info || true
    echo
    echo "== kernel messages =="
    sudo dmesg --ctime | tail -n 300 || true
  } > "${DIAG_DIR}/failure-system-state.txt" 2>&1

  capture_core_backtraces

  if [ -f "../../../../data/nextcloud.log" ]; then
    cp "../../../../data/nextcloud.log" "${DIAG_DIR}/nextcloud.log"
  fi

  if compgen -G "/tmp/behat-php-server-*" > /dev/null; then
    mkdir -p "${DIAG_DIR}/php-server"
    cp -a /tmp/behat-php-server-* "${DIAG_DIR}/php-server/" 2>/dev/null || true
  fi

  {
    echo "== crash markers in failing iteration =="
    grep -Ei 'SIGSEGV|segmentation fault|exit status: 139|signal=11|cURL error 52|Empty reply from server|became unhealthy|Native backtrace|Core dump' "${iteration_log}" || true
    echo
    echo "== crash markers in PHP server diagnostics =="
    grep -RniE 'SIGSEGV|segmentation fault|exit status: 139|signal=11|Native backtrace|Core dump' "${DIAG_DIR}/php-server" 2>/dev/null || true
    echo
    echo "== crash markers in direct core backtraces =="
    grep -RniE 'Program terminated with signal SIGSEGV|SIGSEGV|#0 |imagick|Magick|curl|openssl|opcache' "${DIAG_DIR}/cores" 2>/dev/null || true
  } > "${DIAG_DIR}/crash-markers.txt"
}

capture_environment

echo "Stress reproducer: ${ITERATIONS} full-suite iteration(s) with one long-lived PHP server per suite run"
echo "PHP built-in workers: ${BEHAT_WORKERS:-unset}"

for iteration in $(seq 1 "${ITERATIONS}"); do
  iteration_log="${DIAG_DIR}/iteration-${iteration}.log"
  printf '[%s] full-suite iteration %d/%d\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "${iteration}" "${ITERATIONS}" | tee -a "${SUMMARY_FILE}"

  vendor/bin/behat \
    -f pretty \
    --colors \
    --stop-on-failure 2>&1 | tee "${iteration_log}"
  status=${PIPESTATUS[0]}

  if [ "${status}" -ne 0 ]; then
    printf 'FAILED full-suite iteration=%d status=%d\n' "${iteration}" "${status}" | tee -a "${SUMMARY_FILE}"
    capture_failure "${iteration}" "${status}" "${iteration_log}"
    exit "${status}"
  fi

  printf 'PASS full-suite iteration=%d\n' "${iteration}" >> "${SUMMARY_FILE}"
  rm -f "${iteration_log}"
done

capture_core_backtraces

{
  echo "No failure reproduced after ${ITERATIONS} iterations."
  echo
  echo "== coredumpctl list after successful stress run =="
  sudo coredumpctl --no-pager list || true
} > "${DIAG_DIR}/result.txt" 2>&1

echo "No failure reproduced after ${ITERATIONS} iterations."
