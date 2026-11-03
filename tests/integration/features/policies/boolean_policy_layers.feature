Feature: policies/boolean_policy_layers
  Scenario Outline: Manage boolean policy layers through API
    Given as user "admin"
    And user "signer1" exists
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/signer1/<policy_key>"
    And the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/<policy_key>"
      | value              | false |
      | allowChildOverride | true  |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                 | value        |
      | (jq).ocs.data.policy.policyKey      | <policy_key> |
      | (jq).ocs.data.policy.effectiveValue | false        |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/admin/<policy_key>"
      | value              | true |
      | allowChildOverride | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                            | value        |
      | (jq).ocs.data.policy.policyKey | <policy_key> |
      | (jq).ocs.data.policy.scope     | group        |
      | (jq).ocs.data.policy.targetId  | admin        |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                  | value |
      | (jq).ocs.data.policies.<policy_key>.effectiveValue  | true  |

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                  | value |
      | (jq).ocs.data.policies.<policy_key>.effectiveValue  | false |

    Given as user "admin"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/signer1/<policy_key>"
      | value | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                             | value       |
      | (jq).ocs.data.policy.policyKey  | <policy_key> |
      | (jq).ocs.data.policy.scope      | user_policy |
      | (jq).ocs.data.policy.targetId   | signer1     |

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                | value       |
      | (jq).ocs.data.policies.<policy_key>.effectiveValue| true        |
      | (jq).ocs.data.policies.<policy_key>.sourceScope   | user_policy |

    Examples:
      | policy_key                       |
      | envelope_enabled                 |
      | show_confetti_after_signing      |

  Scenario: System-only boolean policies reject user-scope assignments
    Given as user "admin"
    And user "signer1" exists
    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/signer1/crl_external_validation_enabled"
      | value | true |
    Then the response should have a status code 403
    And the response should be a JSON array with the following mandatory values
      | key                  | value                                  |
      | (jq).ocs.data.error  | Not allowed to manage this user policy |

  Scenario: An explicitly saved default can be enforced and a null value removes it
    Given as user "admin"
    And user "signer1" exists
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/signer1/show_confetti_after_signing"
    And the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/show_confetti_after_signing"
      | value              | true  |
      | allowChildOverride | false |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                 | value |
      | (jq).ocs.data.policy.effectiveValue | true  |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/signer1/show_confetti_after_signing"
      | value | false |
    Then the response should have a status code 200

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                                | value  |
      | (jq).ocs.data.policies.show_confetti_after_signing.effectiveValue | true   |
      | (jq).ocs.data.policies.show_confetti_after_signing.sourceScope    | global |

    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/show_confetti_after_signing"
      | value | null |
    Then the response should have a status code 200

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                                | value       |
      | (jq).ocs.data.policies.show_confetti_after_signing.effectiveValue | false       |
      | (jq).ocs.data.policies.show_confetti_after_signing.sourceScope    | user_policy |

    Given as user "admin"
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/signer1/show_confetti_after_signing"
    And the response should have a status code 200
