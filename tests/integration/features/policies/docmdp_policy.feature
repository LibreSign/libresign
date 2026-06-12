Feature: policies/docmdp_policy
  Scenario: Manage docmdp policy lifecycle through API
    Given as user "admin"

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/docmdp"
      | value              | 2    |
      | allowChildOverride | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value  |
      | (jq).ocs.data.policy.policyKey     | docmdp |
      | (jq).ocs.data.policy.effectiveValue| 2      |

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/docmdp"
      | value              | 0    |
      | allowChildOverride | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value  |
      | (jq).ocs.data.policy.policyKey     | docmdp |
      | (jq).ocs.data.policy.effectiveValue| 0      |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                       | value |
      | (jq).ocs.data.policies.docmdp.effectiveValue | 0  |

  Scenario: docmdp policy group scope overrides system scope
    Given as user "admin"

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/docmdp"
      | value              | 0    |
      | allowChildOverride | true |
    Then the response should have a status code 200

    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/admin/docmdp"
      | value              | 2    |
      | allowChildOverride | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                               | value  |
      | (jq).ocs.data.policy.policyKey    | docmdp |
      | (jq).ocs.data.policy.scope        | group  |
      | (jq).ocs.data.policy.value        | 2      |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                          | value |
      | (jq).ocs.data.policies.docmdp.effectiveValue | 2     |
