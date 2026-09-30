#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -uo pipefail

FLOW="${STRESS_FLOW:-request-signature-email-notify}"
REQUESTS="${STRESS_REQUESTS:-500}"
SERVER_MODE="${STRESS_SERVER_MODE:-long-lived}"
MEMCHECK_MODE="${MEMCHECK_MODE:-none}"
DIAG_DIR="${GITHUB_WORKSPACE:-$(pwd)}/behat-crash-diagnostics"
SUMMARY_FILE="${DIAG_DIR}/summary.log"
WATCHDOG="$(pwd)/scripts/run-behat-with-watchdog.sh"
FEATURE_FILE="$(pwd)/features/_generated_php_crash_stress.feature"

mkdir -p "${DIAG_DIR}"
: > "${SUMMARY_FILE}"

cleanup() {
  rm -f "${FEATURE_FILE}"
}
trap cleanup EXIT

capture_environment() {
  {
    echo "timestamp=$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
    echo "flow=${FLOW}"
    echo "requests=${REQUESTS}"
    echo "server_mode=${SERVER_MODE}"
    echo "memcheck_mode=${MEMCHECK_MODE}"
    echo "behat_workers=${BEHAT_WORKERS:-unset}"
    echo "use_zend_alloc=${USE_ZEND_ALLOC:-default}"
    echo "malloc_check=${MALLOC_CHECK_:-unset}"
    echo "malloc_perturb=${MALLOC_PERTURB_:-unset}"
    echo "glibc_tunables=${GLIBC_TUNABLES:-unset}"
    echo "php_ini_scan_dir=${PHP_INI_SCAN_DIR:-default}"
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
    echo "== opcache =="
    php -r 'printf("api=%s enable=%s enable_cli=%s jit=%s jit_buffer=%s\n", function_exists("opcache_get_status") ? "yes" : "no", ini_get("opcache.enable"), ini_get("opcache.enable_cli"), ini_get("opcache.jit"), ini_get("opcache.jit_buffer_size"));'
    echo
    echo "== imagick loaded =="
    php -r 'echo extension_loaded("imagick") ? "yes\n" : "no\n";'
    echo
    echo "== limits =="
    ulimit -a
    echo
    echo "== LibreSign commit =="
    git -C ../.. rev-parse HEAD || true
    echo
    echo "== Nextcloud commit =="
    git -C ../../../.. rev-parse HEAD || true
  } > "${DIAG_DIR}/environment.txt" 2>&1
}

write_header_email_policy() {
  cat >> "${FEATURE_FILE}" <<'EOF'
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"can_create_account":false,"factors":[{"name":"email","enabled":true,"requirement":"required"}]} |
    And the response should have a status code 200
EOF
}

write_header_account_policy() {
  cat >> "${FEATURE_FILE}" <<'EOF'
    And user "stress-signer" exists
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"can_create_account":false,"factors":[{"name":"account","enabled":true,"requirement":"required"}]} |
    And the response should have a status code 200
EOF
}

append_email_request() {
  local i="$1"
  local notify="$2"
  local notify_json=""
  if [ "${notify}" = "off" ]; then
    notify_json='"notify":0,'
  fi
  cat >> "${FEATURE_FILE}" <<EOF
    # request ${i}
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | signers | [{${notify_json}"identifyMethods":[{"method":"email","value":"stress-${i}@domain.test"}]}] |
      | name | stress-document-${i} |
    And the response should have a status code 200
EOF
}

append_account_request() {
  local i="$1"
  cat >> "${FEATURE_FILE}" <<EOF
    # request ${i}
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | signers | [{"notify":0,"identifyMethods":[{"method":"account","value":"stress-signer"}]}] |
      | name | account-stress-document-${i} |
    And the response should have a status code 200
EOF
}

append_draft_request() {
  local i="$1"
  cat >> "${FEATURE_FILE}" <<EOF
    # request ${i}
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | signers | [] |
      | status | 0 |
      | name | draft-stress-document-${i} |
    And the response should have a status code 200
EOF
}

generate_request_signature_feature() {
  local count="$1"
  cat > "${FEATURE_FILE}" <<'EOF'
Feature: PHP crash focused request-signature stress

  Scenario: Repeated request-signature calls in one PHP server process
    Given as user "admin"
EOF

  case "${FLOW}" in
    request-signature|request-signature-email-notify)
      write_header_email_policy
      ;;
    request-signature-email-no-notify)
      write_header_email_policy
      ;;
    request-signature-account-no-notify)
      write_header_account_policy
      ;;
    request-signature-draft)
      ;;
    *)
      echo "Unsupported request-signature flow: ${FLOW}" >&2
      exit 2
      ;;
  esac

  local i
  for i in $(seq 1 "${count}"); do
    case "${FLOW}" in
      request-signature|request-signature-email-notify)
        append_email_request "${i}" on
        ;;
      request-signature-email-no-notify)
        append_email_request "${i}" off
        ;;
      request-signature-account-no-notify)
        append_account_request "${i}"
        ;;
      request-signature-draft)
        append_draft_request "${i}"
        ;;
    esac
  done
}

generate_validate_feature() {
  local count="$1"
  cat > "${FEATURE_FILE}" <<'EOF'
Feature: PHP crash focused validate-uuid stress

  Scenario: Repeated validate-uuid calls in one PHP server process
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"can_create_account":false,"factors":[{"name":"email","enabled":true,"requirement":"required"}]} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | signers | [{"notify":0,"identifyMethods":[{"method":"email","value":"validate-stress@domain.test"}]}] |
      | name | validate-stress-document |
    And the response should have a status code 200
    And fetch field "(FILE_UUID)ocs.data.uuid" from previous JSON response
EOF

  local i
  for i in $(seq 1 "${count}"); do
    cat >> "${FEATURE_FILE}" <<EOF
    # request ${i}
    And sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<FILE_UUID>"
    And the response should have a status code 200
EOF
  done
}

generate_feature() {
  local count="$1"
  case "${FLOW}" in
    request-signature|request-signature-email-notify|request-signature-email-no-notify|request-signature-account-no-notify|request-signature-draft)
      generate_request_signature_feature "${count}"
      ;;
    validate-uuid)
      generate_validate_feature "${count}"
      ;;
    *)
      echo "Unknown STRESS_FLOW: ${FLOW}" >&2
      exit 2
      ;;
  esac
}

run_feature() {
  local run_dir="$1"
  shift || true

  if [ "${MEMCHECK_MODE}" = "valgrind" ]; then
    mkdir -p "${run_dir}"
    bash "${WATCHDOG}" "${run_dir}" --       valgrind         --tool=memcheck         --trace-children=yes         --track-origins=yes         --leak-check=no         --errors-for-leak-kinds=none         --error-limit=no         --num-callers=40         --keep-debuginfo=yes         --error-exitcode=97         --log-file="${run_dir}/valgrind.%p.log"         php vendor/bin/behat "${FEATURE_FILE}" -f pretty --colors --stop-on-failure
    return $?
  fi

  bash "${WATCHDOG}" "${run_dir}" --     vendor/bin/behat "${FEATURE_FILE}" -f pretty --colors --stop-on-failure
}

capture_environment

echo "Focused native-crash reproducer"
echo "Flow: ${FLOW}"
echo "Requests/runs: ${REQUESTS}"
echo "Server mode: ${SERVER_MODE}"
echo "Memcheck: ${MEMCHECK_MODE}"
echo "PHP built-in workers: ${BEHAT_WORKERS:-unset}"
echo "Allocator: USE_ZEND_ALLOC=${USE_ZEND_ALLOC:-default} MALLOC_CHECK_=${MALLOC_CHECK_:-unset} MALLOC_PERTURB_=${MALLOC_PERTURB_:-unset} GLIBC_TUNABLES=${GLIBC_TUNABLES:-unset}"

status=0

case "${SERVER_MODE}" in
  long-lived)
    generate_feature "${REQUESTS}"
    cp "${FEATURE_FILE}" "${DIAG_DIR}/generated.feature"
    printf '[%s] flow=%s requests=%s server_mode=%s memcheck=%s\n'       "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "${FLOW}" "${REQUESTS}" "${SERVER_MODE}" "${MEMCHECK_MODE}"       | tee -a "${SUMMARY_FILE}"

    run_feature "${DIAG_DIR}/run"
    status=$?
    ;;
  fresh)
    generate_feature 1
    cp "${FEATURE_FILE}" "${DIAG_DIR}/generated.feature"

    for iteration in $(seq 1 "${REQUESTS}"); do
      printf '[%s] fresh-process iteration=%s/%s flow=%s\n'         "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "${iteration}" "${REQUESTS}" "${FLOW}"         | tee -a "${SUMMARY_FILE}"

      run_feature "${DIAG_DIR}/fresh-${iteration}"
      status=$?
      if [ "${status}" -ne 0 ]; then
        echo "${iteration}" > "${DIAG_DIR}/failed-fresh-iteration.txt"
        break
      fi
    done
    ;;
  *)
    echo "Unknown STRESS_SERVER_MODE: ${SERVER_MODE}" >&2
    exit 2
    ;;
esac

echo "${status}" > "${DIAG_DIR}/behat-exit-status.txt"

if [ -f "../../../../data/nextcloud.log" ]; then
  cp "../../../../data/nextcloud.log" "${DIAG_DIR}/nextcloud.log"
fi

if [ "${status}" -ne 0 ]; then
  printf 'FAILED flow=%s status=%d server_mode=%s memcheck=%s\n'     "${FLOW}" "${status}" "${SERVER_MODE}" "${MEMCHECK_MODE}" | tee -a "${SUMMARY_FILE}"
  exit "${status}"
fi

printf 'PASS flow=%s requests=%s server_mode=%s memcheck=%s\n'   "${FLOW}" "${REQUESTS}" "${SERVER_MODE}" "${MEMCHECK_MODE}" | tee -a "${SUMMARY_FILE}"
