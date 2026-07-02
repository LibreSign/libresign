Feature: policies/signature_text_policy
  Scenario: Manage consolidated signature_stamp policy layers through API
    Given as user "admin"
    And user "signer1" exists
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/signer1/signature_stamp"
    And the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_stamp"
      | value              | (string){"template":"Welcome {{SignerCommonName}}","template_font_size":9.8,"signature_font_size":20,"signature_width":350,"signature_height":100,"background_type":"default","render_mode":"default"} |
      | allowChildOverride | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                     | value                        |
      | (jq).ocs.data.policy.policyKey                          | signature_stamp              |
      | (jq).ocs.data.policy.effectiveValue\|fromjson\|.template | Welcome {{SignerCommonName}} |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/admin/signature_stamp"
      | value              | (string){"template":"Admin template {{SignerCommonName}}","template_font_size":9.8,"signature_font_size":20,"signature_width":350,"signature_height":100,"background_type":"default","render_mode":"default"} |
      | allowChildOverride | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                | value                  |
      | (jq).ocs.data.policy.policyKey                     | signature_stamp        |
      | (jq).ocs.data.policy.scope                         | group                  |
      | (jq).ocs.data.policy.targetId                      | admin                  |
      | (jq).ocs.data.policy.effectiveValue\|fromjson\|.template | Admin template {{SignerCommonName}} |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                          | value                               |
      | (jq).ocs.data.policies.signature_stamp.effectiveValue\|fromjson\|.template | Admin template {{SignerCommonName}} |

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                          | value                        |
      | (jq).ocs.data.policies.signature_stamp.effectiveValue\|fromjson\|.template | Welcome {{SignerCommonName}} |

    Given as user "admin"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/signer1/signature_stamp"
      | value | (string){"template":"User-specific: {{SignerCommonName}}","template_font_size":9.8,"signature_font_size":20,"signature_width":350,"signature_height":100,"background_type":"default","render_mode":"default"} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                | value            |
      | (jq).ocs.data.policy.policyKey                     | signature_stamp  |
      | (jq).ocs.data.policy.scope                         | user_policy      |
      | (jq).ocs.data.policy.targetId                      | signer1          |

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                          | value                               |
      | (jq).ocs.data.policies.signature_stamp.effectiveValue\|fromjson\|.template | User-specific: {{SignerCommonName}} |
      | (jq).ocs.data.policies.signature_stamp.sourceScope           | user_policy                         |

  Scenario: Manage consolidated signature stamp config through API
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_stamp"
      | value | (string){"template":"Signed with LibreSign","template_font_size":8.5,"signature_font_size":18,"signature_width":350,"signature_height":100,"background_type":"default","render_mode":"description_only"} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                        | value                |
      | (jq).ocs.data.policy.policyKey                             | signature_stamp      |
      | (jq).ocs.data.policy.effectiveValue\|fromjson\|.render_mode       | description_only     |
      | (jq).ocs.data.policy.effectiveValue\|fromjson\|.template_font_size | 8.5                  |
      | (jq).ocs.data.policy.effectiveValue\|fromjson\|.signature_font_size | 18                 |
      | (jq).ocs.data.policy.effectiveValue\|fromjson\|.signature_width | 350                  |
      | (jq).ocs.data.policy.effectiveValue\|fromjson\|.signature_height | 100                 |
