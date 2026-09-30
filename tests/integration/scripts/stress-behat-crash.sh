#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -uo pipefail

FLOW="${STRESS_FLOW:-request-signature}"
REQUESTS="${STRESS_REQUESTS:-500}"
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
    echo "behat_workers=${BEHAT_WORKERS:-unset}"
    echo "use_zend_alloc=${USE_ZEND_ALLOC:-1}"
    echo "malloc_check=${MALLOC_CHECK_:-unset}"
    echo "malloc_perturb=${MALLOC_PERTURB_:-unset}"
    echo
    echo "== php -v =="
    php -v
    echo
    echo "== php -m =="
    php -m
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

generate_request_signature_feature() {
  cat > "${FEATURE_FILE}" <<'EOF'
Feature: PHP crash focused request-signature stress

  Scenario: Repeated request-signature calls in one PHP server process
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"can_create_account":false,"factors":[{"name":"email","enabled":true,"requirement":"required"}]} |
    And the response should have a status code 200
EOF

  for i in $(seq 1 "${REQUESTS}"); do
    cat >> "${FEATURE_FILE}" <<EOF
    # request ${i}
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | signers | [{"identifyMethods":[{"method":"email","value":"signer${i}@domain.test"}]}] |
      | name | stress-document-${i} |
    And the response should have a status code 200
EOF
  done
}

generate_validate_feature() {
  cat > "${FEATURE_FILE}" <<'EOF'
Feature: PHP crash focused validate-uuid stress

  Scenario: Repeated validate-uuid calls in one PHP server process
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"can_create_account":false,"factors":[{"name":"email","enabled":true,"requirement":"required"}]} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | signers | [{"identifyMethods":[{"method":"email","value":"validate-stress@domain.test"}]}] |
      | name | validate-stress-document |
    And the response should have a status code 200
    And fetch field "(FILE_UUID)ocs.data.uuid" from previous JSON response
EOF

  for i in $(seq 1 "${REQUESTS}"); do
    cat >> "${FEATURE_FILE}" <<EOF
    # request ${i}
    And sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<FILE_UUID>"
    And the response should have a status code 200
EOF
  done
}

case "${FLOW}" in
  request-signature)
    generate_request_signature_feature
    ;;
  validate-uuid)
    generate_validate_feature
    ;;
  *)
    echo "Unknown STRESS_FLOW: ${FLOW}" >&2
    exit 2
    ;;
esac

capture_environment
cp "${FEATURE_FILE}" "${DIAG_DIR}/generated.feature"

echo "Focused reproducer"
echo "Flow: ${FLOW}"
echo "Requests: ${REQUESTS}"
echo "Imagick: $(php -r 'echo extension_loaded("imagick") ? "on" : "off";')"
echo "PHP built-in workers: ${BEHAT_WORKERS:-unset}"
echo "Allocator: USE_ZEND_ALLOC=${USE_ZEND_ALLOC:-1} MALLOC_CHECK_=${MALLOC_CHECK_:-unset} MALLOC_PERTURB_=${MALLOC_PERTURB_:-unset}"

run_dir="${DIAG_DIR}/run"
printf '[%s] flow=%s requests=%s imagick=%s workers=%s\n' \
  "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" \
  "${FLOW}" \
  "${REQUESTS}" \
  "$(php -r 'echo extension_loaded("imagick") ? "on" : "off";')" \
  "${BEHAT_WORKERS:-unset}" | tee -a "${SUMMARY_FILE}"

bash "${WATCHDOG}" "${run_dir}" -- vendor/bin/behat "${FEATURE_FILE}" -f pretty --colors --stop-on-failure
status=$?

echo "${status}" > "${DIAG_DIR}/behat-exit-status.txt"

if [ -f "../../../../data/nextcloud.log" ]; then
  cp "../../../../data/nextcloud.log" "${DIAG_DIR}/nextcloud.log"
fi

if [ "${status}" -ne 0 ]; then
  printf 'FAILED flow=%s status=%d\n' "${FLOW}" "${status}" | tee -a "${SUMMARY_FILE}"
  exit "${status}"
fi

printf 'PASS flow=%s requests=%s\n' "${FLOW}" "${REQUESTS}" | tee -a "${SUMMARY_FILE}"
