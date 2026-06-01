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

  Scenario: Group admin deny override controls inherited request access for the managed group
    # SETUP: Prepare a managed group with one group admin and one regular member
    Given as user "admin"
    And user "ceo-request-access-policy" exists
    And user "member-request-access-policy" exists
    And run the bash command "php <nextcloudRootDir>/console.php group:delete policy-request-access-board-gadmin >/dev/null 2>&1 || true" with result code 0
    And run the command "group:add policy-request-access-board-gadmin" with result code 0
    And run the command "group:adduser policy-request-access-board-gadmin ceo-request-access-policy" with result code 0
    And run the command "group:adduser policy-request-access-board-gadmin member-request-access-policy" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"test"} |

    # RULE: Assign the group administrator to manage the target group and start from a clean policy state
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

    # VALIDATION: Without any allow policy, the group admin sees no group rule and cannot request signatures
    Given as user "ceo-request-access-policy"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/by-policy/group/groups_request_sign"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                    | value           |
      | (jq).ocs.data.policies | (jq)length == 0 |

    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file    | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"ceo-request-access-policy"}]}] |
      | name    | group-admin-without-policy |
      | status  | 0 |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                             | value                                  |
      | (jq).ocs.data.action            | 2000                                   |
      | (jq).ocs.data.errors[0].message | You are not allowed to create signature requests |

    # VALIDATION: A regular member of the same group also cannot request signatures without an allow policy
    Given as user "member-request-access-policy"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file    | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"member-request-access-policy"}]}] |
      | name    | member-without-policy |
      | status  | 0 |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                             | value                                  |
      | (jq).ocs.data.action            | 2000                                   |
      | (jq).ocs.data.errors[0].message | You are not allowed to create signature requests |

    # RULE: The system administrator authorizes the managed group and explicitly delegates child overrides
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/groups_request_sign"
      | value              | {"allowGroups":["policy-request-access-board-gadmin"],"denyGroups":[]} |
      | allowChildOverride | true                                     |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                            | value               |
      | (jq).ocs.data.policy.policyKey | groups_request_sign |

    # VALIDATION: The group admin inherits request access and still does not see the sysadmin seed rule in the group list
    Given as user "ceo-request-access-policy"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                       | value                               |
      | (jq).ocs.data.policies.groups_request_sign.sourceScope    | global                              |
      | (jq).ocs.data.policies.groups_request_sign.effectiveValue | {\"allowGroups\":[\"policy-request-access-board-gadmin\"],\"denyGroups\":[]} |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/by-policy/group/groups_request_sign"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                    | value           |
      | (jq).ocs.data.policies | (jq)length == 0 |

    # VALIDATION: The regular member now inherits the sysadmin allow rule and can request signatures
    Given as user "member-request-access-policy"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file    | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"member-request-access-policy"}]}] |
      | name    | member-with-system-allow |
      | status  | 0 |
    Then the response should have a status code 200

    # RULE: The group admin can create a deny override for the managed group
    Given as user "ceo-request-access-policy"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-gadmin/groups_request_sign"
      | value              | {"allowGroups":["policy-request-access-board-gadmin"],"denyGroups":["policy-request-access-board-gadmin"]} |
      | allowChildOverride | true                                     |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                            | value                               |
      | (jq).ocs.data.policy.policyKey | groups_request_sign                 |
      | (jq).ocs.data.policy.scope     | group                               |
      | (jq).ocs.data.policy.targetId  | policy-request-access-board-gadmin |

    # VALIDATION: The sysadmin can see the deny override created for the managed group
    Given as user "admin"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/by-policy/group/groups_request_sign"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                     | value              |
      | (jq).ocs.data.policies                                 | (jq)length == 1    |
      | (jq).ocs.data.policies[0].targetId                     | policy-request-access-board-gadmin |
      | (jq).ocs.data.policies[0].value                        | {\"allowGroups\":[\"policy-request-access-board-gadmin\"],\"denyGroups\":[\"policy-request-access-board-gadmin\"]} |

    # VALIDATION: After the deny override, the regular member loses the ability to request signatures
    Given as user "member-request-access-policy"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file    | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"member-request-access-policy"}]}] |
      | name    | member-with-group-deny |
      | status  | 0 |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                             | value                                  |
      | (jq).ocs.data.action            | 2000                                   |
      | (jq).ocs.data.errors[0].message | You are not allowed to create signature requests |

    # RULE: The group admin deletes the deny override and should return to inherited system access
    Given as user "ceo-request-access-policy"
    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-gadmin/groups_request_sign"
    Then the response should have a status code 200

    # VALIDATION: The group admin sees no remaining group rule and can still request signatures through the sysadmin allow rule
    When sending "get" to ocs "/apps/libresign/api/v1/policies/by-policy/group/groups_request_sign"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                    | value           |
      | (jq).ocs.data.policies | (jq)length == 0 |

    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file    | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"ceo-request-access-policy"}]}] |
      | name    | group-admin-after-delete |
      | status  | 0 |
    Then the response should have a status code 200

    # VALIDATION: The regular member also regains inherited request access after the deny override is deleted
    Given as user "member-request-access-policy"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file    | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"member-request-access-policy"}]}] |
      | name    | member-after-delete |
      | status  | 0 |
    Then the response should have a status code 200

    # CLEANUP: Reset system policy and remove the temporary group
    Given as user "admin"
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

  Scenario: Group admin can extend request access to another managed group from a sysadmin seed rule
    Given as user "admin"
    And user "ceo-request-access-extension" exists
    And user "company-member-request-access-extension" exists
    And run the bash command "php <nextcloudRootDir>/console.php group:delete policy-request-access-board-extension >/dev/null 2>&1 || true" with result code 0
    And run the bash command "php <nextcloudRootDir>/console.php group:delete policy-request-access-company-extension >/dev/null 2>&1 || true" with result code 0
    And run the command "group:add policy-request-access-board-extension" with result code 0
    And run the command "group:add policy-request-access-company-extension" with result code 0
    And run the command "group:adduser policy-request-access-board-extension ceo-request-access-extension" with result code 0
    And run the command "group:adduser policy-request-access-company-extension ceo-request-access-extension" with result code 0
    And run the command "group:adduser policy-request-access-company-extension company-member-request-access-extension" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"test"} |

    When sending "post" to ocs "/cloud/users/ceo-request-access-extension/subadmins"
      | groupid | policy-request-access-board-extension |
    Then the response should have a status code 200

    When sending "post" to ocs "/cloud/users/ceo-request-access-extension/subadmins"
      | groupid | policy-request-access-company-extension |
    Then the response should have a status code 200

    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-extension/groups_request_sign"
    Then the response should have a status code 200

    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-company-extension/groups_request_sign"
    Then the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/groups_request_sign"
    Then the response should have a status code 200

    # RULE: The sysadmin seeds request access only for board and delegates child overrides.
    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-extension/groups_request_sign"
      | value              | {"allowGroups":["policy-request-access-board-extension"],"denyGroups":[]} |
      | allowChildOverride | true                                                                     |
    Then the response should have a status code 200

    # VALIDATION: A company-only member still cannot request signatures before the delegated admin extends access.
    Given as user "company-member-request-access-extension"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file    | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"company-member-request-access-extension"}]}] |
      | name    | company-member-before-extension |
      | status  | 0 |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                             | value                                  |
      | (jq).ocs.data.action            | 2000                                   |
      | (jq).ocs.data.errors[0].message | You are not allowed to create signature requests |

    # RULE: The delegated group admin can create a company allow rule even though company was not in the inherited scope yet.
    Given as user "ceo-request-access-extension"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-company-extension/groups_request_sign"
      | value              | {"allowGroups":["policy-request-access-company-extension"],"denyGroups":[]} |
      | allowChildOverride | true                                                                       |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                            | value                                  |
      | (jq).ocs.data.policy.policyKey | groups_request_sign                    |
      | (jq).ocs.data.policy.targetId  | policy-request-access-company-extension |

    # VALIDATION: After the delegated extension, the company-only member can request signatures.
    Given as user "company-member-request-access-extension"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file    | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"company-member-request-access-extension"}]}] |
      | name    | company-member-after-extension |
      | status  | 0 |
    Then the response should have a status code 200

    # CLEANUP: reset policy state and remove temporary groups.
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/groups_request_sign"
    Then the response should have a status code 200
    And run the command "group:delete policy-request-access-board-extension" with result code 0
    And run the command "group:delete policy-request-access-company-extension" with result code 0

  Scenario: Group admin can deny a hidden sysadmin seed without destroying the seed or sibling allow rules
    Given as user "admin"
    And user "ceo-request-access-overlay" exists
    And run the bash command "php <nextcloudRootDir>/console.php group:delete policy-request-access-board-overlay >/dev/null 2>&1 || true" with result code 0
    And run the bash command "php <nextcloudRootDir>/console.php group:delete policy-request-access-company-overlay >/dev/null 2>&1 || true" with result code 0
    And run the command "group:add policy-request-access-board-overlay" with result code 0
    And run the command "group:add policy-request-access-company-overlay" with result code 0
    And run the command "group:adduser policy-request-access-board-overlay ceo-request-access-overlay" with result code 0
    And run the command "group:adduser policy-request-access-company-overlay ceo-request-access-overlay" with result code 0

    When sending "post" to ocs "/cloud/users/ceo-request-access-overlay/subadmins"
      | groupid | policy-request-access-board-overlay |
    Then the response should have a status code 200

    When sending "post" to ocs "/cloud/users/ceo-request-access-overlay/subadmins"
      | groupid | policy-request-access-company-overlay |
    Then the response should have a status code 200

    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-overlay/groups_request_sign"
    Then the response should have a status code 200

    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-company-overlay/groups_request_sign"
    Then the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/groups_request_sign"
    Then the response should have a status code 200

    # RULE: The sysadmin seeds request access only for board and delegates child overrides.
    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-overlay/groups_request_sign"
      | value              | {"allowGroups":["policy-request-access-board-overlay"],"denyGroups":[]} |
      | allowChildOverride | true                                                                  |
    Then the response should have a status code 200

    # VALIDATION: The delegated admin inherits board access but still sees no group rule row yet.
    Given as user "ceo-request-access-overlay"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/by-policy/group/groups_request_sign"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                    | value           |
      | (jq).ocs.data.policies | (jq)length == 0 |

    # RULE: The delegated admin can still add a sibling allow rule for company.
    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-company-overlay/groups_request_sign"
      | value              | {"allowGroups":["policy-request-access-company-overlay"],"denyGroups":[]} |
      | allowChildOverride | true                                                                    |
    Then the response should have a status code 200

    # RULE: The delegated admin can create a deny override for the hidden board seed.
    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-overlay/groups_request_sign"
      | value              | {"allowGroups":["policy-request-access-board-overlay"],"denyGroups":["policy-request-access-board-overlay"]} |
      | allowChildOverride | true                                                                                                      |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                            | value                                                                                                       |
      | (jq).ocs.data.policy.policyKey | groups_request_sign                                                                                         |
      | (jq).ocs.data.policy.targetId  | policy-request-access-board-overlay                                                                         |
      | (jq).ocs.data.policy.value     | {\"allowGroups\":[\"policy-request-access-board-overlay\"],\"denyGroups\":[\"policy-request-access-board-overlay\"]} |

    # VALIDATION: The delegated admin must keep the policy card editable and still be able to read the deny row they just created,
    # even if another managed allow rule remains the effective winner for request creation.
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                               | value |
      | (jq).ocs.data.policies.groups_request_sign.sourceScope            | group |
      | (jq).ocs.data.policies.groups_request_sign.editableByCurrentActor | true  |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-overlay/groups_request_sign"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                            | value                                                                                                       |
      | (jq).ocs.data.policy.targetId  | policy-request-access-board-overlay                                                                         |
      | (jq).ocs.data.policy.value     | {\"allowGroups\":[\"policy-request-access-board-overlay\"],\"denyGroups\":[\"policy-request-access-board-overlay\"]} |

    # RULE: Removing the board row deletes only the delegated deny override.
    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-overlay/groups_request_sign"
    Then the response should have a status code 200

    # VALIDATION: The board row disappears again for the delegated admin, while company remains editable.
    When sending "get" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-overlay/groups_request_sign"
    Then the response should have a status code 403

    Given as user "admin"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-overlay/groups_request_sign"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                        | value                                                                |
      | (jq).ocs.data.policy.value | {\"allowGroups\":[\"policy-request-access-board-overlay\"],\"denyGroups\":[]} |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-company-overlay/groups_request_sign"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                        | value                                                                  |
      | (jq).ocs.data.policy.value | {\"allowGroups\":[\"policy-request-access-company-overlay\"],\"denyGroups\":[]} |

    # CLEANUP: reset policy state and remove temporary groups.
    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-board-overlay/groups_request_sign"
    Then the response should have a status code 200

    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/policy-request-access-company-overlay/groups_request_sign"
    Then the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/groups_request_sign"
    Then the response should have a status code 200
    And run the command "group:delete policy-request-access-board-overlay" with result code 0
    And run the command "group:delete policy-request-access-company-overlay" with result code 0
