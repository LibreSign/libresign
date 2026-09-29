#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -uo pipefail

if [ "$#" -lt 3 ] || [ "$2" != "--" ]; then
  echo "Usage: $0 OUTPUT_DIR -- COMMAND [ARGS...]" >&2
  exit 2
fi

OUTPUT_DIR="$1"
shift 2

NO_PROGRESS_TIMEOUT="${BEHAT_NO_PROGRESS_TIMEOUT:-120}"
MAX_RUN_SECONDS="${BEHAT_MAX_RUN_SECONDS:-600}"
EXPECTED_WORKERS="${BEHAT_WORKERS:-0}"
COLLECTOR="$(pwd)/scripts/collect-php-server-diagnostics.sh"
LOG_FILE="${OUTPUT_DIR}/behat.log"

mkdir -p "${OUTPUT_DIR}"
: > "${LOG_FILE}"

terminate_tree() {
  local pid="$1"

  kill -TERM -- "-${pid}" 2>/dev/null || true
  sleep 2

  if kill -0 "${pid}" 2>/dev/null; then
    kill -KILL -- "-${pid}" 2>/dev/null || true
  fi
}

latest_server_pid() {
  local pid_file
  pid_file="$(ls -1t /tmp/behat-php-server-*.pid 2>/dev/null | head -n 1 || true)"
  [ -n "${pid_file}" ] || return 1

  local pid
  pid="$(tr -dc '0-9' < "${pid_file}" 2>/dev/null || true)"
  [ -n "${pid}" ] || return 1

  printf '%s\n' "${pid}"
}

latest_progress_epoch() {
  local latest=0
  local file mtime

  for file in "${LOG_FILE}" /tmp/behat-php-server-*.log; do
    [ -f "${file}" ] || continue
    mtime="$(stat -c %Y "${file}" 2>/dev/null || echo 0)"
    if [ "${mtime}" -gt "${latest}" ]; then
      latest="${mtime}"
    fi
  done

  printf '%s\n' "${latest}"
}

capture_and_stop() {
  local mode="$1"
  local reason="$2"

  echo "Behat watchdog detected infrastructure failure: ${reason}" >&2
  bash "${COLLECTOR}" "${OUTPUT_DIR}/${mode}" "${reason}" "${mode}" || true
  terminate_tree "${command_pid}"
}

rm -f /tmp/behat-php-server-* 2>/dev/null || true

start_epoch="$(date +%s)"
last_progress_epoch="${start_epoch}"
reason=""

setsid "$@" > "${LOG_FILE}" 2>&1 &
command_pid=$!

tail --pid="${command_pid}" -n +1 -F "${LOG_FILE}" &
tail_pid=$!

while kill -0 "${command_pid}" 2>/dev/null; do
  sleep 2
  now="$(date +%s)"

  progress_epoch="$(latest_progress_epoch)"
  if [ "${progress_epoch}" -gt "${last_progress_epoch}" ]; then
    last_progress_epoch="${progress_epoch}"
  fi

  master_pid="$(latest_server_pid || true)"
  if [ -n "${master_pid}" ]; then
    if ! kill -0 "${master_pid}" 2>/dev/null; then
      reason="php-server-master-exited-pid-${master_pid}"
      sleep 1
      capture_and_stop "post" "${reason}"
      break
    fi

    if [ "${EXPECTED_WORKERS}" -gt 0 ] && [ $((now - start_epoch)) -gt 10 ]; then
      live_workers="$(pgrep -P "${master_pid}" 2>/dev/null | wc -l | tr -d ' ')"
      if [ "${live_workers}" -lt "${EXPECTED_WORKERS}" ]; then
        reason="php-worker-count-dropped-${live_workers}-of-${EXPECTED_WORKERS}"
        capture_and_stop "live" "${reason}"
        break
      fi
    fi
  fi

  if [ $((now - last_progress_epoch)) -ge "${NO_PROGRESS_TIMEOUT}" ]; then
    reason="no-progress-for-${NO_PROGRESS_TIMEOUT}s"
    capture_and_stop "live" "${reason}"
    break
  fi

  if [ $((now - start_epoch)) -ge "${MAX_RUN_SECONDS}" ]; then
    reason="runtime-exceeded-${MAX_RUN_SECONDS}s"
    capture_and_stop "live" "${reason}"
    break
  fi
done

wait "${command_pid}" 2>/dev/null
status=$?
wait "${tail_pid}" 2>/dev/null || true

if [ -n "${reason}" ]; then
  status=124
  echo "${reason}" > "${OUTPUT_DIR}/failure-reason.txt"
elif [ "${status}" -ne 0 ]; then
  bash "${COLLECTOR}" "${OUTPUT_DIR}/post" "command-exit-${status}" "post" || true
fi

echo "${status}" > "${OUTPUT_DIR}/exit-status.txt"
exit "${status}"
