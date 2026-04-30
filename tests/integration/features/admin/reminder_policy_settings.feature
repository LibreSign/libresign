Feature: admin/reminder_policy_settings
  Scenario: Manage reminder_settings policy layers through API
    Given as user "admin"
    And user "signer1" exists
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/signer1/reminder_settings"
    And the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/reminder_settings"
      | value              | (string){"days_before":2,"days_between":3,"max":4,"send_timer":"09:30"} |
      | allowChildOverride | true                                                           |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                           | value             |
      | (jq).ocs.data.policy.policyKey                | reminder_settings |
      | (jq).ocs.data.policy.effectiveValue\|fromjson\|.days_before  | 2                 |
      | (jq).ocs.data.policy.effectiveValue\|fromjson\|.send_timer   | 09:30             |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/admin/reminder_settings"
      | value              | (string){"days_before":1,"days_between":2,"max":3,"send_timer":"08:00"} |
      | allowChildOverride | true                                                           |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                 | value             |
      | (jq).ocs.data.policy.policyKey      | reminder_settings |
      | (jq).ocs.data.policy.scope          | group             |
      | (jq).ocs.data.policy.targetId       | admin             |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                      | value |
      | (jq).ocs.data.policies.reminder_settings.effectiveValue\|fromjson\|.days_before  | 1     |
      | (jq).ocs.data.policies.reminder_settings.effectiveValue\|fromjson\|.send_timer   | 08:00 |

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                      | value |
      | (jq).ocs.data.policies.reminder_settings.effectiveValue\|fromjson\|.days_before  | 2     |
      | (jq).ocs.data.policies.reminder_settings.effectiveValue\|fromjson\|.send_timer   | 09:30 |

    Given as user "admin"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/signer1/reminder_settings"
      | value | (string){"days_before":5,"days_between":6,"max":7,"send_timer":"07:15"} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                             | value             |
      | (jq).ocs.data.policy.policyKey  | reminder_settings |
      | (jq).ocs.data.policy.scope      | user_policy       |
      | (jq).ocs.data.policy.targetId   | signer1           |

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                    | value       |
      | (jq).ocs.data.policies.reminder_settings.effectiveValue\|fromjson\|.days_before | 5           |
      | (jq).ocs.data.policies.reminder_settings.effectiveValue\|fromjson\|.send_timer  | 07:15       |
      | (jq).ocs.data.policies.reminder_settings.sourceScope  | user_policy |
