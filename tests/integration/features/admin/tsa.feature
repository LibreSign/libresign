Feature: TSA Administration - Core Configuration

  Scenario: Configure and manage TSA configuration lifecycle
    Given as user "admin"

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/tsa_settings"
      | value | (string){"url":"<TSA_URL>","policy_oid":"1.2.3.4.1","auth_type":"none","username":""} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                    | value          |
      | (jq).ocs.data.message  | Settings saved |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                  | value       |
      | (jq).ocs.data.policies.tsa_settings.policyKey                                   | tsa_settings |
      | (jq).ocs.data.policies.tsa_settings.sourceScope                               | global       |
      | (jq)(.ocs.data.policies.tsa_settings.effectiveValue \| fromjson).url        | <TSA_URL>    |
      | (jq)(.ocs.data.policies.tsa_settings.effectiveValue \| fromjson).policy_oid | 1.2.3.4.1    |
      | (jq)(.ocs.data.policies.tsa_settings.effectiveValue \| fromjson).auth_type  | none         |

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/tsa_settings"
      | value | (string){"url":"","policy_oid":"","auth_type":"none","username":""} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                    | value          |
      | (jq).ocs.data.message  | Settings saved |

  Scenario: Validate TSA configuration errors
    Given as user "admin"

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/tsa_settings"
      | value | (string){"url":"invalid-url","policy_oid":"","auth_type":"none","username":""} |
    Then the response should have a status code 400
    And the response should be a JSON array with the following mandatory values
      | key                  | value              |
      | (jq).ocs.data.error  | Invalid URL format |

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/tsa_settings"
      | value | (string){"url":"https://tsa.example.com/tsr","policy_oid":"","auth_type":"basic","username":""} |
    Then the response should have a status code 400
    And the response should be a JSON array with the following mandatory values
      | key                  | value                                                       |
      | (jq).ocs.data.error  | Username and password are required for basic authentication |
