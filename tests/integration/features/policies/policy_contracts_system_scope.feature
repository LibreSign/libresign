Feature: policies/policy_contracts_system_scope
  Scenario: Manage expiration-related policies at system scope
    Given as user "admin"

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/maximum_validity"
      | value              | 86400 |
      | allowChildOverride | true  |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value            |
      | (jq).ocs.data.policy.policyKey     | maximum_validity |
      | (jq).ocs.data.policy.effectiveValue| 86400            |

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/expiry_in_days"
      | value              | 30   |
      | allowChildOverride | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value          |
      | (jq).ocs.data.policy.policyKey     | expiry_in_days |
      | (jq).ocs.data.policy.effectiveValue| 30             |

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/maximum_validity"
      | value              | -10  |
      | allowChildOverride | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value            |
      | (jq).ocs.data.policy.policyKey     | maximum_validity |
      | (jq).ocs.data.policy.effectiveValue| 0                |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                  | value |
      | (jq).ocs.data.policies.maximum_validity.effectiveValue | 0  |
      | (jq).ocs.data.policies.expiry_in_days.effectiveValue   | 30 |

  Scenario: Manage signature_hash_algorithm policy normalization
    Given as user "admin"

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_hash_algorithm"
      | value              | SHA512 |
      | allowChildOverride | true   |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value                    |
      | (jq).ocs.data.policy.policyKey     | signature_hash_algorithm |
      | (jq).ocs.data.policy.effectiveValue| SHA512                   |

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_hash_algorithm"
      | value              | invalid_hash |
      | allowChildOverride | true         |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value                    |
      | (jq).ocs.data.policy.policyKey     | signature_hash_algorithm |
      | (jq).ocs.data.policy.effectiveValue| SHA256                   |

  Scenario: Manage system behavior policies for validation and workers
    Given as user "admin"

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/make_validation_url_private"
      | value              | true |
      | allowChildOverride | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value                       |
      | (jq).ocs.data.policy.policyKey     | make_validation_url_private |
      | (jq).ocs.data.policy.effectiveValue| true                        |

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/default_user_folder"
      | value              | Customer Signatures |
      | allowChildOverride | true                |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value               |
      | (jq).ocs.data.policy.policyKey     | default_user_folder |
      | (jq).ocs.data.policy.effectiveValue| Customer Signatures |

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/signing_mode"
      | value              | async |
      | allowChildOverride | false |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value        |
      | (jq).ocs.data.policy.policyKey     | signing_mode |
      | (jq).ocs.data.policy.effectiveValue| async        |

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/worker_config"
      | value              | (string){"worker_type":"external","parallel_workers":8} |
      | allowChildOverride | false                                               |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                              | value         |
      | (jq).ocs.data.policy.policyKey   | worker_config |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                           | value               |
      | (jq).ocs.data.policies.make_validation_url_private.effectiveValue | true          |
      | (jq).ocs.data.policies.default_user_folder.effectiveValue         | Customer Signatures |
      | (jq).ocs.data.policies.signing_mode.effectiveValue                | async              |
