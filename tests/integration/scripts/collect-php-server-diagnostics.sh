#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -uo pipefail

OUTPUT_DIR="${1:-behat-native-diagnostics}"
REASON="${2:-manual-capture}"
MODE="${3:-post}"

mkdir -p "${OUTPUT_DIR}/live" "${OUTPUT_DIR}/cores"

timestamp() {
  date -u '+%Y-%m-%dT%H:%M:%SZ'
}

latest_main_pid_file() {
  ls -1t /tmp/behat-php-server-*.pid 2>/dev/null | head -n 1 || true
}

collect_php_server_pids() {
  {
    for pid_file in /tmp/behat-php-server-*.pid; do
      [ -f "${pid_file}" ] || continue
      cat "${pid_file}" 2>/dev/null || true
    done

    pgrep -f 'php([0-9.]*)? .* -S ' 2>/dev/null || true
  } | awk '/^[0-9]+$/ { print $1 }' | sort -n -u
}

collect_descendants() {
  local root_pid="$1"
  local pending="${root_pid}"
  local seen=""

  while [ -n "${pending}" ]; do
    local parent="${pending%% *}"
    if [ "${pending}" = "${parent}" ]; then
      pending=""
    else
      pending="${pending#* }"
    fi

    local children
    children="$(pgrep -P "${parent}" 2>/dev/null | tr '\n' ' ' || true)"
    for child in ${children}; do
      case " ${seen} " in
        *" ${child} "*) ;;
        *)
          seen="${seen} ${child}"
          pending="${pending:+${pending} }${child}"
          echo "${child}"
          ;;
      esac
    done
  done
}

collect_process_snapshot() {
  local pid="$1"
  local dir="${OUTPUT_DIR}/live/pid-${pid}"

  [ -d "/proc/${pid}" ] || return 0
  mkdir -p "${dir}"

  {
    echo "captured_at=$(timestamp)"
    echo "reason=${REASON}"
    echo "mode=${MODE}"
    echo "pid=${pid}"
    echo
    echo "== ps =="
    ps -o pid,ppid,pgid,sid,user,stat,etime,rss,vsz,pcpu,pmem,args -p "${pid}" || true
  } > "${dir}/summary.txt" 2>&1

  for proc_file in status limits wchan syscall stack maps smaps_rollup stat; do
    if [ -r "/proc/${pid}/${proc_file}" ]; then
      cat "/proc/${pid}/${proc_file}" > "${dir}/proc-${proc_file}.txt" 2>&1 || true
    fi
  done

  tr '\0' ' ' < "/proc/${pid}/cmdline" > "${dir}/cmdline.txt" 2>/dev/null || true
  ls -la "/proc/${pid}/fd" > "${dir}/fd.txt" 2>&1 || true
  lsof -nP -p "${pid}" > "${dir}/lsof.txt" 2>&1 || true

  if [ "${MODE}" = "live" ] && kill -0 "${pid}" 2>/dev/null; then
    sudo timeout 8s gdb --batch --quiet       -ex 'set pagination off'       -ex 'set confirm off'       -ex 'thread apply all bt full'       -ex 'info registers'       -ex 'info sharedlibrary'       -ex 'info proc mappings'       -ex 'x/16i $pc-32'       -ex 'detach'       -ex 'quit'       -p "${pid}" > "${dir}/gdb-live.txt" 2>&1 || true
  fi

  if [ "${MODE}" = "live" ] && kill -0 "${pid}" 2>/dev/null; then
    sudo timeout --signal=INT 3s strace -ff -tt -T -s 256       -p "${pid}"       -o "${dir}/strace" >/dev/null 2>&1 || true
  fi
}

collect_core_backtraces() {
  local php_bin
  php_bin="$(command -v php)"

  for core in /tmp/core.*; do
    [ -f "${core}" ] || continue
    local base
    base="$(basename "${core}")"

    cp "${core}" "${OUTPUT_DIR}/cores/${base}" 2>/dev/null || true
    timeout 20s gdb --batch --quiet       -ex 'set pagination off'       -ex 'set print pretty on'       -ex 'thread apply all bt full'       -ex 'info registers'       -ex 'info sharedlibrary'       -ex 'info proc mappings'       -ex 'info symbol $pc'       -ex 'x/24i $pc-48'       "${php_bin}" "${core}"       > "${OUTPUT_DIR}/cores/${base}.gdb.txt" 2>&1 || true
  done

  sudo timeout 20s coredumpctl --no-pager --quiet debug php     --debugger-arguments="-batch -ex 'set pagination off' -ex 'thread apply all bt full' -ex 'info registers' -ex 'info sharedlibrary' -ex 'info proc mappings' -ex 'info symbol \\$pc' -ex 'x/24i \\$pc-48'"     > "${OUTPUT_DIR}/cores/coredumpctl-gdb.txt" 2>&1 || true
}

write_failure_summary() {
  local pid_file master_pid base exit_file exit_status classification signal_name crashed_role
  pid_file="$(latest_main_pid_file)"
  master_pid=""
  exit_status=""
  classification="command-failure"
  signal_name=""
  crashed_role="unknown"

  if [ -n "${pid_file}" ]; then
    master_pid="$(tr -dc '0-9' < "${pid_file}" 2>/dev/null || true)"
    base="${pid_file%.pid}"
    exit_file="${base}.exit"
    if [ -f "${exit_file}" ]; then
      exit_status="$(tr -dc '0-9' < "${exit_file}" 2>/dev/null || true)"
    fi
  fi

  case "${REASON}" in
    no-progress-*) classification="hang" ;;
    runtime-exceeded-*) classification="timeout" ;;
    php-worker-count-dropped-*) classification="worker-exit" ;;
    php-server-master-exited-*) classification="master-exit" ;;
  esac

  if [ "${exit_status}" = "139" ]; then
    classification="segfault"
    signal_name="SIGSEGV"
  elif [ -n "${exit_status}" ] && [ "${exit_status}" -ge 128 ] 2>/dev/null; then
    signal_name="signal-$((exit_status - 128))"
  fi

  if [ -n "${master_pid}" ]; then
    for core in /tmp/core.*; do
      [ -f "${core}" ] || continue
      case "$(basename "${core}")" in
        *".${master_pid}."*) crashed_role="master" ;;
      esac
    done
  fi

  {
    echo "captured_at=$(timestamp)"
    echo "reason=${REASON}"
    echo "mode=${MODE}"
    echo "classification=${classification}"
    echo "master_pid=${master_pid:-unknown}"
    echo "expected_workers=${BEHAT_WORKERS:-unknown}"
    echo "server_exit_status=${exit_status:-unknown}"
    echo "terminating_signal=${signal_name:-unknown}"
    echo "crashed_role=${crashed_role}"
    echo
    echo "== direct core files =="
    ls -lh /tmp/core.* 2>/dev/null || true
    echo
    echo "== latest worker timeline =="
    if [ -n "${pid_file}" ]; then
      tail -n 80 "${pid_file%.pid}.workers.log" 2>/dev/null || true
    fi
    echo
    echo "== last server log lines =="
    if [ -n "${pid_file}" ]; then
      tail -n 160 "${pid_file%.pid}.log" 2>/dev/null || true
    fi
    echo
    echo "== last master log lines =="
    if [ -n "${pid_file}" ] && [ -n "${master_pid}" ]; then
      grep "^\[${master_pid}\]" "${pid_file%.pid}.log" 2>/dev/null | tail -n 80 || true
    fi
  } > "${OUTPUT_DIR}/failure-summary.txt" 2>&1
}

{
  echo "captured_at=$(timestamp)"
  echo "reason=${REASON}"
  echo "mode=${MODE}"
  echo
  echo "== process tree =="
  ps -ef --forest || true
  echo
  echo "== process details =="
  ps -eo pid,ppid,pgid,sid,user,stat,etime,rss,vsz,pcpu,pmem,args || true
  echo
  echo "== pstree =="
  pstree -ap || true
  echo
  echo "== listening and connected sockets =="
  ss -tanp || true
  echo
  echo "== memory =="
  free -m || true
  echo
  echo "== core limit =="
  ulimit -c || true
  echo
  echo "== core pattern =="
  cat /proc/sys/kernel/core_pattern || true
  echo
  echo "== ptrace scope =="
  cat /proc/sys/kernel/yama/ptrace_scope 2>/dev/null || true
  echo
  echo "== coredumpctl list =="
  sudo coredumpctl --no-pager list || true
  echo
  echo "== kernel messages =="
  sudo dmesg --ctime | tail -n 500 || true
} > "${OUTPUT_DIR}/system-state.txt" 2>&1

mapfile -t masters < <(collect_php_server_pids)
all_pids=()
for master in "${masters[@]}"; do
  all_pids+=("${master}")
  while IFS= read -r child; do
    [ -n "${child}" ] && all_pids+=("${child}")
  done < <(collect_descendants "${master}")
done

if [ "${#all_pids[@]}" -gt 0 ]; then
  mapfile -t all_pids < <(printf '%s\n' "${all_pids[@]}" | sort -n -u)
fi

printf '%s\n' "${all_pids[@]:-}" | sed '/^$/d' > "${OUTPUT_DIR}/php-server-pids.txt"

snapshot_pids=()
for pid in "${all_pids[@]:-}"; do
  [ -n "${pid}" ] || continue
  collect_process_snapshot "${pid}" &
  snapshot_pids+=("$!")
done
for snapshot_pid in "${snapshot_pids[@]:-}"; do
  [ -n "${snapshot_pid}" ] || continue
  wait "${snapshot_pid}" 2>/dev/null || true
done

collect_core_backtraces
write_failure_summary

if compgen -G "/tmp/behat-php-server-*" > /dev/null; then
  mkdir -p "${OUTPUT_DIR}/php-server-files"
  cp -a /tmp/behat-php-server-* "${OUTPUT_DIR}/php-server-files/" 2>/dev/null || true
fi
