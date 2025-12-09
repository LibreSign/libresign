Feature: admin/certificate_engine_switch
  Background:
    Given as user "admin"

  Scenario: Set engine to OpenSSL, configure it, then switch engine and verify
    # Define engine as OpenSSL
    When sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/certificate_engine"
      | value | openssl |
    Then the response should have a status code 200
    # Configure OpenSSL
    When sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"OpenSSL Root CA"} |
    Then the response should have a status code 200
    # Verify OpenSSL is configured correctly
    And sending "get" to ocs "/apps/libresign/api/v1/admin/configure-check"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                                  | value   |
      | (jq).ocs.data\|map(select(.resource=="openssl-configure"))[0].status | success |
    # Switch to CFSSL engine without configuring it
    When sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/certificate_engine"
      | value | cfssl |
    Then the response should have a status code 200
    # Verify CFSSL shows error because it's not configured
    And sending "get" to ocs "/apps/libresign/api/v1/admin/configure-check"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                                | value                                    |
      | (jq).ocs.data\|map(select(.resource=="cfssl-configure"))[0].status | error                                    |
      | (jq).ocs.data\|map(select(.resource=="cfssl-configure"))[0].tip    | Run occ libresign:configure:cfssl --help |

  Scenario: Set engine to none and verify error state
    # Delete engine configuration (set to none)
    When sending "delete" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/certificate_engine"
    Then the response should have a status code 200
    # Verify configure-check shows error for default engine (OpenSSL)
    And sending "get" to ocs "/apps/libresign/api/v1/admin/configure-check"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                                  | value |
      | (jq).ocs.data\|map(select(.resource=="openssl-configure"))[0].status | error |
    # Verify has-root-cert returns false
    And sending "get" to ocs "/apps/libresign/api/v1/setting/has-root-cert"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                       | value |
      | (jq).ocs.data.hasRootCert | false |
