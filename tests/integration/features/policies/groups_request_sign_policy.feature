Feature: policies/groups_request_sign_policy
  Scenario: Manage groups_request_sign policy with group scope and guard restrictions
    Given as user "admin"
    And user "signer1" exists

    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/admin/groups_request_sign"
    Then the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/groups_request_sign"
    Then the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/groups_request_sign"
      | value              | {"allowGroups":["admin"],"denyGroups":[]} |
      | allowChildOverride | true        |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value               |
      | (jq).ocs.data.policy.policyKey     | groups_request_sign |

    Given as user "admin"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/admin/groups_request_sign"
      | value              | {"allowGroups":["admin"],"denyGroups":[]} |
      | allowChildOverride | true        |
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
      | value              | {"allowGroups":["admin"],"denyGroups":[]} |
      | allowChildOverride | true        |
    Then the response should have a status code 403
    And the response should be a JSON array with the following mandatory values
      | key                 | value                                   |
      | (jq).ocs.data.error | Not allowed to manage this group policy |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                    | value  |
      | (jq).ocs.data.policies.groups_request_sign.sourceScope | global |

    Given as user "admin"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/signer1/groups_request_sign"
      | value | {"allowGroups":["admin"],"denyGroups":[]} |
    Then the response should have a status code 400
    And the response should be a JSON array with the following mandatory values
      | key                 | value                                            |
      | (jq).ocs.data.error | User-level scope is not supported for this policy |

    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/admin/groups_request_sign"
    Then the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/groups_request_sign"
    Then the response should have a status code 200

  Scenario: Group admin sees effective request access but cannot inspect the sysadmin seed rule
    Given as user "admin"
    And user "ceo-request-access-policy" exists
    And run the bash command "php <nextcloudRootDir>/console.php group:delete policy-request-access-board-gadmin >/dev/null 2>&1 || true" with result code 0
    And run the command "group:add policy-request-access-board-gadmin" with result code 0
    And run the command "group:adduser policy-request-access-board-gadmin ceo-request-access-policy" with result code 0

    When sending "post" to ocs "/cloud/users/ceo-request-access-policy/subadmins"
      | groupid | policy-request-access-board-gadmin |
    Then the response should have a status code 200

    When sending "get" to ocs "/cloud/users/ceo-request-access-policy/subadmins"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key           | value                                                   |
      | (jq).ocs.data | (jq)index("policy-request-access-board-gadmin") != null |

    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-gadmin/groups_request_sign"
    Then the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/groups_request_sign"
    Then the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/groups_request_sign"
      | value              | {"allowGroups":["policy-request-access-board-gadmin"],"denyGroups":[]} |
      | allowChildOverride | true                                     |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value               |
      | (jq).ocs.data.policy.policyKey     | groups_request_sign |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-gadmin/groups_request_sign"
      | value              | {"allowGroups":["policy-request-access-board-gadmin"],"denyGroups":[]} |
      | allowChildOverride | true                                     |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value                               |
      | (jq).ocs.data.policy.policyKey     | groups_request_sign                 |
      | (jq).ocs.data.policy.scope         | group                               |
      | (jq).ocs.data.policy.targetId      | policy-request-access-board-gadmin |

    Given as user "ceo-request-access-policy"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                     | value                               |
      | (jq).ocs.data.policies.groups_request_sign.sourceScope | group                               |
      | (jq).ocs.data.policies.groups_request_sign.effectiveValue | {\"allowGroups\":[\"policy-request-access-board-gadmin\"],\"denyGroups\":[]} |
      | (jq).ocs.data.policies.groups_request_sign.groupCount  | 0                                   |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-gadmin/groups_request_sign"
    Then the response should have a status code 403
    And the response should be a JSON array with the following mandatory values
      | key                 | value                                   |
      | (jq).ocs.data.error | Not allowed to manage this group policy |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/by-policy/group/groups_request_sign"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                  | value              |
      | (jq).ocs.data.policies | (jq)length == 0 |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-gadmin/groups_request_sign"
      | value              | {"allowGroups":["policy-request-access-board-gadmin"],"denyGroups":[]} |
      | allowChildOverride | false                                    |
    Then the response should have a status code 403
    And the response should be a JSON array with the following mandatory values
      | key                 | value                                                           |
      | (jq).ocs.data.error | Group policy management requires explicit delegation from the system administrator |

    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-gadmin/groups_request_sign"
    Then the response should have a status code 403
    And the response should be a JSON array with the following mandatory values
      | key                 | value                                                                   |
      | (jq).ocs.data.error | Only system administrators can delete group rules created by a system administrator |

    Given as user "admin"
    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-gadmin/groups_request_sign"
    Then the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/groups_request_sign"
    Then the response should have a status code 200

    And run the command "group:delete policy-request-access-board-gadmin" with result code 0

  Scenario: denyGroups takes precedence over allowGroups for request creation
    Given as user "admin"
    And user "allow-only-requester" exists
    And user "deny-precedence-requester" exists
    And run the bash command "php <nextcloudRootDir>/console.php group:delete policy-request-access-allow >/dev/null 2>&1 || true" with result code 0
    And run the bash command "php <nextcloudRootDir>/console.php group:delete policy-request-access-deny >/dev/null 2>&1 || true" with result code 0
    And run the command "group:add policy-request-access-allow" with result code 0
    And run the command "group:add policy-request-access-deny" with result code 0
    And run the command "group:adduser policy-request-access-allow allow-only-requester" with result code 0
    And run the command "group:adduser policy-request-access-allow deny-precedence-requester" with result code 0
    And run the command "group:adduser policy-request-access-deny deny-precedence-requester" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"test"} |

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/groups_request_sign"
      | value              | {"allowGroups":["policy-request-access-allow","policy-request-access-deny"],"denyGroups":["policy-request-access-deny"]} |
      | allowChildOverride | true                                                                                                                       |
    Then the response should have a status code 200

    Given as user "allow-only-requester"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file    | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"allow-only-requester"}]}] |
      | name    | document |
    Then the response should have a status code 200

    Given as user "deny-precedence-requester"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file    | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"deny-precedence-requester"}]}] |
      | name    | document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                             | value                                  |
      | (jq).ocs.data.action            | 2000                                   |
      | (jq).ocs.data.errors[0].message | You are not allowed to create signature requests |

    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/groups_request_sign"
    Then the response should have a status code 200
    And run the command "group:delete policy-request-access-allow" with result code 0
    And run the command "group:delete policy-request-access-deny" with result code 0

