Feature: policies/legal_information_policy
  Scenario: Manage legal_information policy layers through API
    Given as user "admin"
    And user "signer1" exists
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/signer1/legal_information"
    And the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/legal_information"
      | value              | # Terms and conditions |
      | allowChildOverride | true                   |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value             |
      | (jq).ocs.data.policy.policyKey     | legal_information |
      | (jq).ocs.data.policy.effectiveValue| # Terms and conditions |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/admin/legal_information"
      | value              | ## Group legal copy |
      | allowChildOverride | true               |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value             |
      | (jq).ocs.data.policy.policyKey     | legal_information |
      | (jq).ocs.data.policy.scope         | group             |
      | (jq).ocs.data.policy.targetId      | admin             |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                      | value              |
      | (jq).ocs.data.policies.legal_information.effectiveValue | ## Group legal copy |
      | (jq).ocs.data.policies.legal_information.sourceScope    | group              |

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                      | value                  |
      | (jq).ocs.data.policies.legal_information.effectiveValue | # Terms and conditions |
      | (jq).ocs.data.policies.legal_information.sourceScope    | global                 |

    Given as user "admin"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/signer1/legal_information"
      | value | ### User legal copy |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value             |
      | (jq).ocs.data.policy.policyKey     | legal_information |
      | (jq).ocs.data.policy.scope         | user_policy       |
      | (jq).ocs.data.policy.targetId      | signer1           |

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                      | value               |
      | (jq).ocs.data.policies.legal_information.effectiveValue | ### User legal copy |
      | (jq).ocs.data.policies.legal_information.sourceScope    | user_policy         |
