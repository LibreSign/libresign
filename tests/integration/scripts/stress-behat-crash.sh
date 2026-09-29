#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -uo pipefail

ITERATIONS="${STRESS_ITERATIONS:-1}"
DIAG_DIR="${GITHUB_WORKSPACE:-$(pwd)}/behat-crash-diagnostics"
SUMMARY_FILE="${DIAG_DIR}/summary.log"
WATCHDOG="$(pwd)/scripts/run-behat-with-watchdog.sh"

mkdir -p "${DIAG_DIR}"
: > "${SUMMARY_FILE}"

capture_environment() {
  {
    echo "timestamp=$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
    echo "iteration_limit=${ITERATIONS}"
    echo "behat_workers=${BEHAT_WORKERS:-unset}"
    echo "no_progress_timeout_seconds=${BEHAT_NO_PROGRESS_TIMEOUT:-120}"
    echo "max_run_seconds=${BEHAT_MAX_RUN_SECONDS:-600}"
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
    echo "== ptrace scope =="
    cat /proc/sys/kernel/yama/ptrace_scope 2>/dev/null || true
    echo
    echo "== LibreSign commit =="
    git -C ../.. rev-parse HEAD || true
    echo
    echo "== Nextcloud commit =="
    git -C ../../../.. rev-parse HEAD || true
  } > "${DIAG_DIR}/environment.txt" 2>&1

  php -i > "${DIAG_DIR}/php-info.txt" 2>&1 || true
}

capture_environment

echo "Controlled reproducer: ${ITERATIONS} full-suite iteration(s)"
echo "PHP built-in workers: ${BEHAT_WORKERS:-unset}"
echo "Watchdog: ${BEHAT_NO_PROGRESS_TIMEOUT:-120}s without Behat/server progress, ${BEHAT_MAX_RUN_SECONDS:-600}s maximum per suite"

for iteration in $(seq 1 "${ITERATIONS}"); do
  iteration_dir="${DIAG_DIR}/iteration-${iteration}"
  printf '[%s] full-suite iteration %d/%d workers=%s\n'     "$(date -u '+%Y-%m-%dT%H:%M:%SZ')"     "${iteration}"     "${ITERATIONS}"     "${BEHAT_WORKERS:-unset}" | tee -a "${SUMMARY_FILE}"

  bash "${WATCHDOG}" "${iteration_dir}" -- vendor/bin/behat -f pretty --colors --stop-on-failure
  status=$?

  if [ "${status}" -ne 0 ]; then
    printf 'FAILED full-suite iteration=%d status=%d workers=%s\n'       "${iteration}" "${status}" "${BEHAT_WORKERS:-unset}" | tee -a "${SUMMARY_FILE}"
    echo "${iteration}" > "${DIAG_DIR}/failed-iteration.txt"
    echo "${status}" > "${DIAG_DIR}/behat-exit-status.txt"

    if [ -f "../../../../data/nextcloud.log" ]; then
      cp "../../../../data/nextcloud.log" "${DIAG_DIR}/nextcloud.log"
    fi

    exit "${status}"
  fi

  printf 'PASS full-suite iteration=%d workers=%s\n'     "${iteration}" "${BEHAT_WORKERS:-unset}" | tee -a "${SUMMARY_FILE}"
done

echo "No failure reproduced after ${ITERATIONS} iteration(s), workers=${BEHAT_WORKERS:-unset}."
