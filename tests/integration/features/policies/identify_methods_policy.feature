Feature: policies/identify_methods_policy
  Scenario: Manage identify_methods policy layers through API
    # Reset previous user-level state before building a fresh precedence chain.
    Given as user "admin"
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/admin/identify_methods"
    And the response should have a status code 200

    # Persist the system rule that will act as the baseline for lower scopes.
    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"requirement":"required","signatureMethods":{"clickToSign":{"enabled":true}}}] |
      | allowChildOverride | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policy.policyKey | identify_methods |

    # Override the system rule at group scope and verify the persisted target metadata.
    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/admin/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"requirement":"optional","signatureMethods":{"emailToken":{"enabled":true}}}] |
      | allowChildOverride | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policy.policyKey | identify_methods |
      | (jq).ocs.data.policy.scope | group |
      | (jq).ocs.data.policy.targetId | admin |

    # Confirm that the effective policy now resolves from the group layer.
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policies.identify_methods.sourceScope | group |
      | (jq).ocs.data.policies.identify_methods.effectiveValue.factors[0].name | email |

    # Add a user-level override and verify that it takes precedence over the group rule.
    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/admin/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"requirement":"required","signatureMethods":{"emailToken":{"enabled":true}}}] |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policy.policyKey | identify_methods |
      | (jq).ocs.data.policy.scope | user_policy |
      | (jq).ocs.data.policy.targetId | admin |

    # Confirm that the effective policy now resolves from the user policy layer.
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policies.identify_methods.sourceScope | user_policy |
      | (jq).ocs.data.policies.identify_methods.effectiveValue.factors[0].name | email |

  Scenario: Empty identify_methods payload still exposes available factors in effective policy
    # Clear user and group overrides so the scenario exercises only the system payload behavior.
    Given as user "admin"
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/admin/identify_methods"
    And the response should have a status code 200
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/group/admin/identify_methods"
    And the response should have a status code 200

    # Persist an empty system payload and ensure runtime expansion still exposes the catalog factors.
    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string)[] |
      | allowChildOverride | true |
    Then the response should have a status code 200

    # The effective policy must still include the available identify factors.
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq)(.ocs.data.policies.identify_methods.effectiveValue.factors \| map(select(.name == "account")) \| length) | 1 |
      | (jq)(.ocs.data.policies.identify_methods.effectiveValue.factors \| map(select(.name == "email")) \| length) | 1 |

  Scenario: Group admin creates and deletes a delegated identify_methods override without destroying the sysadmin seed
    # Create dedicated users and idempotently reset the ad-hoc groups used by this scenario.
    Given as user "admin"
    And user "ceo-identify-methods-policy" exists
    And user "board-member-identify-methods-policy" exists
    And user "company-member-identify-methods-policy" exists
    And run the bash command "php <nextcloudRootDir>/console.php group:delete policy-identify-board-overlay >/dev/null 2>&1 || true" with result code 0
    And run the bash command "php <nextcloudRootDir>/console.php group:delete policy-identify-company-overlay >/dev/null 2>&1 || true" with result code 0
    And run the command "group:add policy-identify-board-overlay" with result code 0
    And run the command "group:add policy-identify-company-overlay" with result code 0
    And run the command "group:adduser policy-identify-board-overlay ceo-identify-methods-policy" with result code 0
    And run the command "group:adduser policy-identify-company-overlay ceo-identify-methods-policy" with result code 0
    And run the command "group:adduser policy-identify-board-overlay board-member-identify-methods-policy" with result code 0
    And run the command "group:adduser policy-identify-company-overlay company-member-identify-methods-policy" with result code 0

    # Delegate subadmin management of both groups to the CEO user.
    When sending "post" to ocs "/cloud/users/ceo-identify-methods-policy/subadmins"
      | groupid | policy-identify-board-overlay |
    Then the response should have a status code 200

    When sending "post" to ocs "/cloud/users/ceo-identify-methods-policy/subadmins"
      | groupid | policy-identify-company-overlay |
    Then the response should have a status code 200

    # Seed both group rules as the system administrator so delegated overrides have a parent rule.
    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/policy-identify-board-overlay/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"requirement":"required","signatureMethods":{"password":{"enabled":true}},"signatureMethodEnabled":"password"},{"name":"email","enabled":true,"requirement":"optional","signatureMethods":{"emailToken":{"enabled":true}},"signatureMethodEnabled":"emailToken"}] |
      | allowChildOverride | true |
    Then the response should have a status code 200

    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/policy-identify-company-overlay/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"requirement":"required","signatureMethods":{"password":{"enabled":true}},"signatureMethodEnabled":"password"},{"name":"email","enabled":true,"requirement":"optional","signatureMethods":{"emailToken":{"enabled":true}},"signatureMethodEnabled":"emailToken"}] |
      | allowChildOverride | true |
    Then the response should have a status code 200

    # The delegated group admin must not see the system-created seeds as personal explicit rules.
    Given as user "ceo-identify-methods-policy"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/by-policy/group/identify_methods"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policies | (jq)length == 0 |

    # Narrow only the board group through a delegated override that disables email.
    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/policy-identify-board-overlay/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"requirement":"required","signatureMethods":{"password":{"enabled":true}},"signatureMethodEnabled":"password"},{"name":"email","enabled":false,"requirement":"optional","signatureMethods":{"emailToken":{"enabled":true}},"signatureMethodEnabled":"emailToken"}] |
      | allowChildOverride | false |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policy.policyKey | identify_methods |
      | (jq).ocs.data.policy.scope | group |
      | (jq).ocs.data.policy.targetId | policy-identify-board-overlay |

    # Members of the board group must receive the narrowed delegated override.
    Given as user "board-member-identify-methods-policy"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policies.identify_methods.sourceScope | group |
      | (jq)(.ocs.data.policies.identify_methods.effectiveValue.factors[] \| select(.name == "account") \| .enabled) | true |
      | (jq)(.ocs.data.policies.identify_methods.effectiveValue.factors[] \| select(.name == "email") \| .enabled) | false |

    # Members of the untouched company group must continue inheriting the original seed.
    Given as user "company-member-identify-methods-policy"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policies.identify_methods.sourceScope | group |
      | (jq)(.ocs.data.policies.identify_methods.effectiveValue.factors[] \| select(.name == "account") \| .enabled) | true |
      | (jq)(.ocs.data.policies.identify_methods.effectiveValue.factors[] \| select(.name == "email") \| .enabled) | true |

    # Deleting the delegated override must remove only the overlay and keep the system seed intact.
    Given as user "ceo-identify-methods-policy"
    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/policy-identify-board-overlay/identify_methods"
    Then the response should have a status code 200

    # After deletion, the delegated group admin still must not see a persisted explicit group rule.
    When sending "get" to ocs "/apps/libresign/api/v1/policies/by-policy/group/identify_methods"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policies | (jq)length == 0 |

    # Board members must inherit the original system-created seed again after overlay removal.
    Given as user "board-member-identify-methods-policy"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policies.identify_methods.sourceScope | group |
      | (jq)(.ocs.data.policies.identify_methods.effectiveValue.factors[] \| select(.name == "account") \| .enabled) | true |
      | (jq)(.ocs.data.policies.identify_methods.effectiveValue.factors[] \| select(.name == "email") \| .enabled) | true |

    # Clean up the persisted policies and temporary groups created by this scenario.
    Given as user "admin"
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/group/policy-identify-board-overlay/identify_methods"
    And the response should have a status code 200
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/group/policy-identify-company-overlay/identify_methods"
    And the response should have a status code 200
    And run the command "group:delete policy-identify-board-overlay" with result code 0
    And run the command "group:delete policy-identify-company-overlay" with result code 0
