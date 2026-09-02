Feature: policies/signer_geolocation_policy
  Scenario: Manage signer_geolocation policy layers through API
    Given as user "admin"
    And user "signer1" exists
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/signer1/signer_geolocation"
    And the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/signer_geolocation"
      | value              | {"mode":"disabled"} |
      | allowChildOverride | true                |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                              | value              |
      | (jq).ocs.data.policy.policyKey                   | signer_geolocation |
      | (jq).ocs.data.policy.effectiveValue.mode         | disabled           |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/admin/signer_geolocation"
      | value              | {"mode":"optional"} |
      | allowChildOverride | true                |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                              | value              |
      | (jq).ocs.data.policy.policyKey                   | signer_geolocation |
      | (jq).ocs.data.policy.scope                         | group              |
      | (jq).ocs.data.policy.targetId                      | admin              |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                                    | value    |
      | (jq).ocs.data.policies.signer_geolocation.effectiveValue.mode            | optional |

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                         | value    |
      | (jq).ocs.data.policies.signer_geolocation.effectiveValue.mode | disabled |

    Given as user "admin"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/signer1/signer_geolocation"
      | value | {"mode":"required"} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                 | value              |
      | (jq).ocs.data.policy.policyKey      | signer_geolocation |
      | (jq).ocs.data.policy.scope          | user_policy        |
      | (jq).ocs.data.policy.targetId       | signer1            |

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                         | value       |
      | (jq).ocs.data.policies.signer_geolocation.effectiveValue.mode | required    |
      | (jq).ocs.data.policies.signer_geolocation.sourceScope         | user_policy |
