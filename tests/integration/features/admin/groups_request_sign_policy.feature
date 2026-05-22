Feature: admin/groups_request_sign_policy
  Scenario: Manage groups_request_sign policy with group scope and guard restrictions
    Given as user "admin"
    And user "signer1" exists

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/groups_request_sign"
      | value              | ["admin"] |
      | allowChildOverride | true        |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value               |
      | (jq).ocs.data.policy.policyKey     | groups_request_sign |

    Given as user "admin"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/admin/groups_request_sign"
      | value              | ["admin"] |
      | allowChildOverride | true      |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value               |
      | (jq).ocs.data.policy.policyKey     | groups_request_sign |
      | (jq).ocs.data.policy.scope         | group               |
      | (jq).ocs.data.policy.targetId      | admin               |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                    | value |
      | (jq).ocs.data.policies.groups_request_sign.sourceScope | group |

    Given as user "signer1"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/admin/groups_request_sign"
      | value              | ["admin"] |
      | allowChildOverride | true      |
    Then the response should have a status code 403
    And the response should be a JSON array with the following mandatory values
      | key                 | value                                 |
      | (jq).ocs.data.error | Not allowed to manage this group policy |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                     | value |
      | (jq).ocs.data.policies.groups_request_sign.sourceScope | global |

    Given as user "admin"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/signer1/groups_request_sign"
      | value | ["admin"] |
    Then the response should have a status code 400
    And the response should be a JSON array with the following mandatory values
      | key                | value                                           |
      | (jq).ocs.data.error| User-level scope is not supported for this policy |

