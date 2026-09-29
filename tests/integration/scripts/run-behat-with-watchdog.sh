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

NO_OUTPUT_TIMEOUT="${BEHAT_NO_OUTPUT_TIMEOUT:-180}"
MAX_RUN_SECONDS="${BEHAT_MAX_RUN_SECONDS:-1200}"
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

  for server_pid in $(pgrep -f 'php([0-9.]*)? .* -S ' 2>/dev/null || true); do
    kill -TERM "${server_pid}" 2>/dev/null || true
  done
}

start_epoch="$(date +%s)"
last_output_epoch="${start_epoch}"
reason=""

setsid "$@" > "${LOG_FILE}" 2>&1 &
command_pid=$!

tail --pid="${command_pid}" -n +1 -F "${LOG_FILE}" &
tail_pid=$!

while kill -0 "${command_pid}" 2>/dev/null; do
  sleep 5
  now="$(date +%s)"

  if [ -s "${LOG_FILE}" ]; then
    mtime="$(stat -c %Y "${LOG_FILE}" 2>/dev/null || echo "${last_output_epoch}")"
    if [ "${mtime}" -gt "${last_output_epoch}" ]; then
      last_output_epoch="${mtime}"
    fi
  fi

  if [ $((now - last_output_epoch)) -ge "${NO_OUTPUT_TIMEOUT}" ]; then
    reason="no-output-for-${NO_OUTPUT_TIMEOUT}s"
    echo "Behat watchdog detected hang: ${reason}" >&2
    bash "${COLLECTOR}" "${OUTPUT_DIR}/live" "${reason}" || true
    terminate_tree "${command_pid}"
    break
  fi

  if [ $((now - start_epoch)) -ge "${MAX_RUN_SECONDS}" ]; then
    reason="runtime-exceeded-${MAX_RUN_SECONDS}s"
    echo "Behat watchdog detected timeout: ${reason}" >&2
    bash "${COLLECTOR}" "${OUTPUT_DIR}/live" "${reason}" || true
    terminate_tree "${command_pid}"
    break
  fi
done

wait "${command_pid}" 2>/dev/null
status=$?
wait "${tail_pid}" 2>/dev/null || true

if [ -n "${reason}" ]; then
  status=124
  echo "${reason}" > "${OUTPUT_DIR}/failure-reason.txt"
fi

if [ "${status}" -ne 0 ]; then
  bash "${COLLECTOR}" "${OUTPUT_DIR}/post" "command-exit-${status}" || true
fi

echo "${status}" > "${OUTPUT_DIR}/exit-status.txt"
exit "${status}"
