Feature: policies/confetti_policy_group_admin
  Scenario: Group admin can create user overrides but cannot inspect the sysadmin confetti seed rule
    Given as user "admin"
    And user "ceo-confetti-policy" exists
    And user "member-confetti-policy" exists
    And run the bash command "php <nextcloudRootDir>/console.php group:delete policy-confetti-board-gadmin >/dev/null 2>&1 || true" with result code 0
    And run the command "group:add policy-confetti-board-gadmin" with result code 0
    And run the command "group:adduser policy-confetti-board-gadmin ceo-confetti-policy" with result code 0
    And run the command "group:adduser policy-confetti-board-gadmin member-confetti-policy" with result code 0

    When sending "post" to ocs "/cloud/users/ceo-confetti-policy/subadmins"
      | groupid | policy-confetti-board-gadmin |
    Then the response should have a status code 200

    When sending "get" to ocs "/cloud/users/ceo-confetti-policy/subadmins"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key           | value                                             |
      | (jq).ocs.data | (jq)index("policy-confetti-board-gadmin") != null |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/policy-confetti-board-gadmin/show_confetti_after_signing"
      | value              | false |
      | allowChildOverride | true  |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                  | value                      |
      | (jq).ocs.data.policy.policyKey       | show_confetti_after_signing |
      | (jq).ocs.data.policy.scope           | group                      |
      | (jq).ocs.data.policy.targetId        | policy-confetti-board-gadmin |
      | (jq).ocs.data.policy.allowChildOverride | true                    |

    Given as user "ceo-confetti-policy"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                                 | value |
      | (jq).ocs.data.policies.show_confetti_after_signing.sourceScope      | group |
      | (jq).ocs.data.policies.show_confetti_after_signing.canSaveAsUserDefault | true |
      | (jq).ocs.data.policies.show_confetti_after_signing.groupCount       | 0     |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/group/policy-confetti-board-gadmin/show_confetti_after_signing"
    Then the response should have a status code 403
    And the response should be a JSON array with the following mandatory values
      | key                 | value                                 |
      | (jq).ocs.data.error | Not allowed to manage this group policy |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/by-policy/group/show_confetti_after_signing"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                  | value              |
      | (jq).ocs.data.policies | (jq)length == 0 |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/policy-confetti-board-gadmin/show_confetti_after_signing"
      | value              | true  |
      | allowChildOverride | false |
    Then the response should have a status code 403
    And the response should be a JSON array with the following mandatory values
      | key                 | value                                                           |
      | (jq).ocs.data.error | Group policy management requires explicit delegation from the system administrator |

    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/policy-confetti-board-gadmin/show_confetti_after_signing"
    Then the response should have a status code 403
    And the response should be a JSON array with the following mandatory values
      | key                 | value                                                                   |
      | (jq).ocs.data.error | Only system administrators can delete group rules created by a system administrator |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/member-confetti-policy/show_confetti_after_signing"
      | value              | true  |
      | allowChildOverride | false |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                            | value                      |
      | (jq).ocs.data.policy.policyKey | show_confetti_after_signing |
      | (jq).ocs.data.policy.scope     | user_policy                |
      | (jq).ocs.data.policy.targetId  | member-confetti-policy     |
      | (jq).ocs.data.policy.value     | true                       |

    Given as user "member-confetti-policy"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                                | value       |
      | (jq).ocs.data.policies.show_confetti_after_signing.effectiveValue  | true        |
      | (jq).ocs.data.policies.show_confetti_after_signing.sourceScope     | user_policy |

    Given as user "admin"
    And run the command "group:delete policy-confetti-board-gadmin" with result code 0
