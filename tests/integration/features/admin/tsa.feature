Feature: TSA Administration - Core Configuration

  Scenario: Configure and manage TSA configuration lifecycle
    Given as user "admin"

    When sending "post" to ocs "/apps/libresign/api/v1/admin/tsa"
      | tsa_url       | <TSA_URL> |
      | tsa_policy_oid | 1.2.3.4.1              |
      | tsa_auth_type | none                    |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                  | value   |
      | (jq).ocs.data.status | success |

    When sending "get" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/tsa_url"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                | value                   |
      | (jq).ocs.data.data | <TSA_URL>               |

    When sending "delete" to ocs "/apps/libresign/api/v1/admin/tsa"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                  | value   |
      | (jq).ocs.data.status | success |

  Scenario: Validate TSA configuration errors
    Given as user "admin"

    When sending "post" to ocs "/apps/libresign/api/v1/admin/tsa"
      | tsa_url | invalid-url |
    Then the response should have a status code 400
    And the response should be a JSON array with the following mandatory values
      | key                   | value              |
      | (jq).ocs.data.status  | error              |
      | (jq).ocs.data.message | Invalid URL format |

    When sending "post" to ocs "/apps/libresign/api/v1/admin/tsa"
      | tsa_url       | https://tsa.example.com/tsr |
      | tsa_auth_type | basic                       |
    Then the response should have a status code 400
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                                       |
      | (jq).ocs.data.status  | error                                                       |
      | (jq).ocs.data.message | Username and password are required for basic authentication |
