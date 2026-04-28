Feature: admin/signature_text_policy
  Scenario: Manage signature_text policy layers through API
    Given as user "admin"
    And user "signer1" exists
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/signer1/signature_text_template"
    And the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_text_template"
      | value              | Welcome {{SignerCommonName}} |
      | allowChildOverride | true                         |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value                        |
      | (jq).ocs.data.policy.policyKey     | signature_text_template      |
      | (jq).ocs.data.policy.effectiveValue| Welcome {{SignerCommonName}} |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/admin/signature_text_template"
      | value              | Admin template {{SignerCommonName}} |
      | allowChildOverride | true                                |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value                               |
      | (jq).ocs.data.policy.policyKey     | signature_text_template             |
      | (jq).ocs.data.policy.scope         | group                               |
      | (jq).ocs.data.policy.targetId      | admin                               |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                         | value                               |
      | (jq).ocs.data.policies.signature_text_template.effectiveValue| Admin template {{SignerCommonName}} |

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                         | value                        |
      | (jq).ocs.data.policies.signature_text_template.effectiveValue| Welcome {{SignerCommonName}} |

    Given as user "admin"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/signer1/signature_text_template"
      | value | User-specific: {{SignerCommonName}} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                 | value                               |
      | (jq).ocs.data.policy.policyKey      | signature_text_template             |
      | (jq).ocs.data.policy.scope          | user_policy                         |
      | (jq).ocs.data.policy.targetId       | signer1                             |

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                         | value                               |
      | (jq).ocs.data.policies.signature_text_template.effectiveValue| User-specific: {{SignerCommonName}} |
      | (jq).ocs.data.policies.signature_text_template.sourceScope  | user_policy                         |

  Scenario: Manage signature render mode policy layers through API
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_render_mode"
      | value | graphic |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value    |
      | (jq).ocs.data.policy.policyKey     | signature_render_mode |
      | (jq).ocs.data.policy.effectiveValue| graphic  |

  Scenario: Manage signature dimensions policy layers through API
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_width"
      | value | 100 |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value |
      | (jq).ocs.data.policy.policyKey     | signature_width |
      | (jq).ocs.data.policy.effectiveValue| 100   |

    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_height"
      | value | 60 |
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value |
      | (jq).ocs.data.policy.policyKey     | signature_height |
      | (jq).ocs.data.policy.effectiveValue| 60    |

  Scenario: Manage signature font sizes policy layers through API
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/template_font_size"
      | value | 8.5 |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value  |
      | (jq).ocs.data.policy.policyKey     | template_font_size |
      | (jq).ocs.data.policy.effectiveValue| 8.5    |

    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_font_size"
      | value | 18 |
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value  |
      | (jq).ocs.data.policy.policyKey     | signature_font_size |
      | (jq).ocs.data.policy.effectiveValue| 18     |
