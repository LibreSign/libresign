Feature: admin/collect_metadata_policy
  Scenario: Manage collect_metadata policy layers through API
    Given as user "admin"
    And user "signer1" exists
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/signer1/collect_metadata"
    And the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/collect_metadata"
      | value              | false |
      | allowChildOverride | true  |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value            |
      | (jq).ocs.data.policy.policyKey     | collect_metadata |
      | (jq).ocs.data.policy.effectiveValue| false            |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/admin/collect_metadata"
      | value              | true |
      | allowChildOverride | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value            |
      | (jq).ocs.data.policy.policyKey     | collect_metadata |
      | (jq).ocs.data.policy.scope         | group            |
      | (jq).ocs.data.policy.targetId      | admin            |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                    | value |
      | (jq).ocs.data.policies.collect_metadata.effectiveValue | true  |

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                    | value |
      | (jq).ocs.data.policies.collect_metadata.effectiveValue | false |

    Given as user "admin"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/signer1/collect_metadata"
      | value | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                 | value            |
      | (jq).ocs.data.policy.policyKey      | collect_metadata |
      | (jq).ocs.data.policy.scope          | user_policy      |
      | (jq).ocs.data.policy.targetId       | signer1          |

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                    | value |
      | (jq).ocs.data.policies.collect_metadata.effectiveValue | true  |
      | (jq).ocs.data.policies.collect_metadata.sourceScope    | user_policy |
