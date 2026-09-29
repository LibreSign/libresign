#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -uo pipefail

OUTPUT_DIR="${1:-behat-native-diagnostics}"
REASON="${2:-manual-capture}"

mkdir -p "${OUTPUT_DIR}/live" "${OUTPUT_DIR}/cores"

timestamp() {
  date -u '+%Y-%m-%dT%H:%M:%SZ'
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

  if kill -0 "${pid}" 2>/dev/null; then
    sudo timeout 15s gdb --batch --quiet       -ex 'set pagination off'       -ex 'set confirm off'       -ex 'thread apply all bt full'       -ex 'info registers'       -ex 'info sharedlibrary'       -ex 'detach'       -ex 'quit'       -p "${pid}" > "${dir}/gdb-live.txt" 2>&1 || true
  fi

  if kill -0 "${pid}" 2>/dev/null; then
    sudo timeout --signal=INT 8s strace -ff -tt -T -s 256       -p "${pid}"       -o "${dir}/strace" >/dev/null 2>&1 || true
  fi

  if kill -0 "${pid}" 2>/dev/null && command -v gcore >/dev/null 2>&1; then
    sudo timeout 30s gcore -o "${dir}/gcore" "${pid}" > "${dir}/gcore.txt" 2>&1 || true
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
    timeout 30s gdb --batch --quiet       -ex 'set pagination off'       -ex 'thread apply all bt full'       -ex 'info registers'       -ex 'info sharedlibrary'       "${php_bin}" "${core}"       > "${OUTPUT_DIR}/cores/${base}.gdb.txt" 2>&1 || true
  done

  sudo timeout 30s coredumpctl --no-pager --quiet debug php     --debugger-arguments="-batch -ex 'set pagination off' -ex 'thread apply all bt full' -ex 'info registers' -ex 'info sharedlibrary'"     > "${OUTPUT_DIR}/cores/coredumpctl-gdb.txt" 2>&1 || true
}

{
  echo "captured_at=$(timestamp)"
  echo "reason=${REASON}"
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
  echo "== lsof TCP =="
  lsof -nP -iTCP || true
  echo
  echo "== memory =="
  free -m || true
  echo
  echo "== vmstat =="
  vmstat 1 3 || true
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
  echo "== coredumpctl info =="
  sudo coredumpctl --no-pager info || true
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

for pid in "${all_pids[@]:-}"; do
  [ -n "${pid}" ] || continue
  collect_process_snapshot "${pid}"
done

collect_core_backtraces

if compgen -G "/tmp/behat-php-server-*" > /dev/null; then
  mkdir -p "${OUTPUT_DIR}/php-server-files"
  cp -a /tmp/behat-php-server-* "${OUTPUT_DIR}/php-server-files/" 2>/dev/null || true
fi
