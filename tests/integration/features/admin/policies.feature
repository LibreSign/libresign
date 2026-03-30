Feature: admin/policies
  Scenario: Manage signature_flow policy layers through API
    Given as user "admin"
    And user "signer1" exists

    When sending "delete" to ocs "/apps/libresign/api/v1/policies/user/admin/signature_flow"
    Then the response should have a status code 200

    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/admin/signature_flow"
    Then the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_flow"
      | value              | ordered_numeric |
      | allowChildOverride | true            |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value           |
      | (jq).ocs.data.policy.policyKey     | signature_flow  |
      | (jq).ocs.data.policy.sourceScope   | global          |
      | (jq).ocs.data.policy.effectiveValue| ordered_numeric |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/admin/signature_flow"
      | value              | ordered_numeric |
      | allowChildOverride | true            |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                  | value           |
      | (jq).ocs.data.policy.policyKey       | signature_flow  |
      | (jq).ocs.data.policy.scope           | group           |
      | (jq).ocs.data.policy.targetId        | admin           |
      | (jq).ocs.data.policy.allowChildOverride | true         |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/group/admin/signature_flow"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value           |
      | (jq).ocs.data.policy.policyKey     | signature_flow  |
      | (jq).ocs.data.policy.scope         | group           |
      | (jq).ocs.data.policy.targetId      | admin           |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/signer1/signature_flow"
      | value | parallel |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value          |
      | (jq).ocs.data.policy.policyKey     | signature_flow |
      | (jq).ocs.data.policy.sourceScope   | user           |
      | (jq).ocs.data.policy.effectiveValue| parallel       |

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                      | value    |
      | (jq).ocs.data.policies.signature_flow.effectiveValue    | parallel |
      | (jq).ocs.data.policies.signature_flow.sourceScope       | user     |

    Given as user "admin"
    When sending "delete" to ocs "/apps/libresign/api/v1/policies/user/signer1/signature_flow"
    Then the response should have a status code 200

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                      | value           |
      | (jq).ocs.data.policies.signature_flow.effectiveValue    | ordered_numeric |

    Given as user "admin"
    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/admin/signature_flow"
    Then the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_flow"
    Then the response should have a status code 200
