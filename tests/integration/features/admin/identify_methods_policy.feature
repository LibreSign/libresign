Feature: admin/identify_methods_policy
  Scenario: Manage identify_methods policy layers through API
    Given as user "admin"
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/admin/identify_methods"
    And the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"requirement":"required","signatureMethods":{"clickToSign":{"enabled":true}}}] |
      | allowChildOverride | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policy.policyKey | identify_methods |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/admin/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"requirement":"optional","signatureMethods":{"emailToken":{"enabled":true}}}] |
      | allowChildOverride | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policy.policyKey | identify_methods |
      | (jq).ocs.data.policy.scope | group |
      | (jq).ocs.data.policy.targetId | admin |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policies.identify_methods.sourceScope | group |
      | (jq).ocs.data.policies.identify_methods.effectiveValue.factors[0].name | email |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/admin/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"requirement":"required","signatureMethods":{"emailToken":{"enabled":true}}}] |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policy.policyKey | identify_methods |
      | (jq).ocs.data.policy.scope | user_policy |
      | (jq).ocs.data.policy.targetId | admin |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policies.identify_methods.sourceScope | user_policy |
      | (jq).ocs.data.policies.identify_methods.effectiveValue.factors[0].name | email |
