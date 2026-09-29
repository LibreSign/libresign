#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -uo pipefail

ITERATIONS="${STRESS_ITERATIONS:-3}"
NO_OUTPUT_TIMEOUT="${BEHAT_NO_OUTPUT_TIMEOUT:-180}"
MAX_RUN_SECONDS="${BEHAT_MAX_RUN_SECONDS:-1200}"
DIAG_DIR="${GITHUB_WORKSPACE:-$(pwd)}/behat-crash-diagnostics"
SUMMARY_FILE="${DIAG_DIR}/summary.log"
COLLECTOR="$(pwd)/scripts/collect-php-server-diagnostics.sh"

mkdir -p "${DIAG_DIR}"
: > "${SUMMARY_FILE}"

capture_environment() {
  {
    echo "timestamp=$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
    echo "iteration_limit=${ITERATIONS}"
    echo "behat_workers=${BEHAT_WORKERS:-unset}"
    echo "no_output_timeout_seconds=${NO_OUTPUT_TIMEOUT}"
    echo "max_run_seconds=${MAX_RUN_SECONDS}"
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

terminate_behat_tree() {
  local pid="$1"

  kill -TERM -- "-${pid}" 2>/dev/null || true
  sleep 2

  if kill -0 "${pid}" 2>/dev/null; then
    kill -KILL -- "-${pid}" 2>/dev/null || true
  fi

  for server_pid in $(pgrep -f 'php([0-9.]*)? .* -S ' 2>/dev/null || true); do
    kill -TERM "${server_pid}" 2>/dev/null || true
  done
}

run_suite_with_watchdog() {
  local iteration="$1"
  local iteration_log="${DIAG_DIR}/iteration-${iteration}.log"
  local live_diag="${DIAG_DIR}/iteration-${iteration}-live"
  local post_diag="${DIAG_DIR}/iteration-${iteration}-post"
  local start_epoch
  local last_output_epoch
  local now
  local status
  local reason=""

  : > "${iteration_log}"
  start_epoch="$(date +%s)"
  last_output_epoch="${start_epoch}"

  setsid vendor/bin/behat     -f pretty     --colors     --stop-on-failure     > "${iteration_log}" 2>&1 &
  local behat_pid=$!

  tail --pid="${behat_pid}" -n +1 -F "${iteration_log}" &
  local tail_pid=$!

  while kill -0 "${behat_pid}" 2>/dev/null; do
    sleep 5
    now="$(date +%s)"

    if [ -s "${iteration_log}" ]; then
      local mtime
      mtime="$(stat -c %Y "${iteration_log}" 2>/dev/null || echo "${last_output_epoch}")"
      if [ "${mtime}" -gt "${last_output_epoch}" ]; then
        last_output_epoch="${mtime}"
      fi
    fi

    if [ $((now - last_output_epoch)) -ge "${NO_OUTPUT_TIMEOUT}" ]; then
      reason="no-output-for-${NO_OUTPUT_TIMEOUT}s"
      printf '[%s] watchdog detected hang: %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "${reason}" | tee -a "${SUMMARY_FILE}"
      bash "${COLLECTOR}" "${live_diag}" "${reason}" || true
      terminate_behat_tree "${behat_pid}"
      break
    fi

    if [ $((now - start_epoch)) -ge "${MAX_RUN_SECONDS}" ]; then
      reason="suite-runtime-exceeded-${MAX_RUN_SECONDS}s"
      printf '[%s] watchdog detected timeout: %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "${reason}" | tee -a "${SUMMARY_FILE}"
      bash "${COLLECTOR}" "${live_diag}" "${reason}" || true
      terminate_behat_tree "${behat_pid}"
      break
    fi
  done

  wait "${behat_pid}" 2>/dev/null
  status=$?
  wait "${tail_pid}" 2>/dev/null || true

  if [ -n "${reason}" ]; then
    status=124
    echo "${reason}" > "${DIAG_DIR}/failure-reason.txt"
  fi

  if [ "${status}" -ne 0 ]; then
    bash "${COLLECTOR}" "${post_diag}" "behat-exit-${status}" || true
  fi

  return "${status}"
}

capture_environment

echo "Stress reproducer: ${ITERATIONS} full-suite iteration(s) with one long-lived PHP server per suite run"
echo "PHP built-in workers: ${BEHAT_WORKERS:-unset}"
echo "Watchdog: ${NO_OUTPUT_TIMEOUT}s without output, ${MAX_RUN_SECONDS}s maximum per suite"

for iteration in $(seq 1 "${ITERATIONS}"); do
  printf '[%s] full-suite iteration %d/%d\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "${iteration}" "${ITERATIONS}" | tee -a "${SUMMARY_FILE}"

  run_suite_with_watchdog "${iteration}"
  status=$?

  if [ "${status}" -ne 0 ]; then
    printf 'FAILED full-suite iteration=%d status=%d\n' "${iteration}" "${status}" | tee -a "${SUMMARY_FILE}"
    echo "${iteration}" > "${DIAG_DIR}/failed-iteration.txt"
    echo "${status}" > "${DIAG_DIR}/behat-exit-status.txt"

    if [ -f "../../../../data/nextcloud.log" ]; then
      cp "../../../../data/nextcloud.log" "${DIAG_DIR}/nextcloud.log"
    fi

    exit "${status}"
  fi

  printf 'PASS full-suite iteration=%d\n' "${iteration}" >> "${SUMMARY_FILE}"
done

bash "${COLLECTOR}" "${DIAG_DIR}/final-state" "successful-stress-run" || true
echo "No failure reproduced after ${ITERATIONS} iterations."
