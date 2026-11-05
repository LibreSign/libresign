@api
Feature: policies/signature_rejection_authorization
  Background:
    Given as user "admin"
    And user "rejection-group-admin" exists
    And user "rejection-regular-user" exists
    And run the bash command "php <nextcloudRootDir>/console.php group:delete rejection-managed >/dev/null 2>&1 || true" with result code 0
    And run the bash command "php <nextcloudRootDir>/console.php group:delete rejection-unmanaged >/dev/null 2>&1 || true" with result code 0
    And run the command "group:add rejection-managed" with result code 0
    And run the command "group:add rejection-unmanaged" with result code 0
    And run the command "group:adduser rejection-managed rejection-group-admin" with result code 0
    When sending "post" to ocs "/cloud/users/rejection-group-admin/subadmins"
      | groupid | rejection-managed |
    Then the response should have a status code 200

  Scenario Outline: Instance admin changes a locked system rule from <before> to <after>
    When sending "post" to ocs "/apps/libresign/api/v1/policies/compound/system/rejection_enabled"
      | values             | {"rejection_enabled":<before>,"rejection_behavior":"cancel","rejection_comment_mode":"disabled","rejection_visibility":"requester","rejection_comment_visibility":"requester"} |
      | allowChildOverride | {"rejection_enabled":false,"rejection_behavior":false,"rejection_comment_mode":false,"rejection_visibility":false,"rejection_comment_visibility":false}                       |
    Then the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/policies/compound/system/rejection_enabled"
      | values             | {"rejection_enabled":<after>,"rejection_behavior":"cancel","rejection_comment_mode":"disabled","rejection_visibility":"requester","rejection_comment_visibility":"requester"} |
      | allowChildOverride | {"rejection_enabled":false,"rejection_behavior":false,"rejection_comment_mode":false,"rejection_visibility":false,"rejection_comment_visibility":false}                      |
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/policies/system/rejection_enabled"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                     | value   |
      | (jq).ocs.data.policy.value              | <after> |
      | (jq).ocs.data.policy.allowChildOverride | false   |

    Examples:
      | before | after |
      | false  | true  |
      | true   | false |

  Scenario Outline: Instance admin writes a group rule while the system rule allows override: <allowOverride>
    When sending "post" to ocs "/apps/libresign/api/v1/policies/compound/system/rejection_enabled"
      | values             | {"rejection_enabled":false} |
      | allowChildOverride | {"rejection_enabled":<allowOverride>} |
    Then the response should have a status code 200
    When sending "put" to ocs "/apps/libresign/api/v1/policies/compound/group/rejection-managed/rejection_enabled"
      | values             | {"rejection_enabled":true,"rejection_behavior":"continue"} |
      | allowChildOverride | {"rejection_enabled":true,"rejection_behavior":true}       |
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/policies/group/rejection-managed/rejection_behavior"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                        | value    |
      | (jq).ocs.data.policy.value | continue |

    Examples:
      | allowOverride |
      | true          |
      | false         |

  Scenario Outline: <case> is refused and changes nothing
    When sending "post" to ocs "/apps/libresign/api/v1/policies/compound/system/rejection_enabled"
      | values             | {"rejection_enabled":false,"rejection_behavior":"cancel"}                     |
      | allowChildOverride | {"rejection_enabled":<delegated>,"rejection_behavior":<delegated>} |
    Then the response should have a status code 200
    Given as user "<actor>"
    When sending "<verb>" to ocs "/apps/libresign/api/v1/policies/compound/<target>/rejection_enabled"
      | values             | {"rejection_enabled":true,"rejection_behavior":"continue"} |
      | allowChildOverride | {"rejection_enabled":true,"rejection_behavior":true}       |
    Then the response should have a status code 403
    Given as user "admin"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/system/rejection_enabled"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                        | value |
      | (jq).ocs.data.policy.value | false |
    Given as user "admin"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/system/rejection_behavior"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                     | value       |
      | (jq).ocs.data.policy.value              | cancel      |
      | (jq).ocs.data.policy.allowChildOverride | <delegated> |
    When sending "get" to ocs "/apps/libresign/api/v1/policies/group/rejection-managed/rejection_enabled"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                        | value |
      | (jq).ocs.data.policy.value | null  |
    When sending "get" to ocs "/apps/libresign/api/v1/policies/user/rejection-group-admin/rejection_behavior"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                        | value |
      | (jq).ocs.data.policy.value | null  |
    When sending "get" to ocs "/apps/libresign/api/v1/policies/group/rejection-managed/rejection_behavior"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                        | value |
      | (jq).ocs.data.policy.value | null  |
    When sending "get" to ocs "/apps/libresign/api/v1/policies/group/rejection-unmanaged/rejection_behavior"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                        | value |
      | (jq).ocs.data.policy.value | null  |

    Examples:
      | case                                                | actor                  | verb | target                    | delegated |
      | Group admin writing the system rule                 | rejection-group-admin  | post | system                    | true      |
      | Group admin writing a managed group without delegation | rejection-group-admin  | put  | group/rejection-managed   | false     |
      | Group admin writing an unmanaged group              | rejection-group-admin  | put  | group/rejection-unmanaged | true      |
      | Regular user writing the system rule                | rejection-regular-user | post | system                    | true      |
      | Regular user writing a group rule                   | rejection-regular-user | put  | group/rejection-managed   | true      |
      | Regular user writing a rule for another user        | rejection-regular-user | put  | user/rejection-group-admin | true      |

  Scenario: Group admin with delegation writes a managed group rule
    When sending "post" to ocs "/apps/libresign/api/v1/policies/compound/system/rejection_enabled"
      | values             | {"rejection_enabled":true,"rejection_behavior":"cancel","rejection_comment_mode":"optional","rejection_visibility":"participants","rejection_comment_visibility":"requester"} |
      | allowChildOverride | {"rejection_enabled":true,"rejection_behavior":true,"rejection_comment_mode":true,"rejection_visibility":true,"rejection_comment_visibility":true}                         |
    Then the response should have a status code 200
    Given as user "rejection-group-admin"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/compound/group/rejection-managed/rejection_enabled"
      | values             | {"rejection_enabled":true,"rejection_behavior":"continue","rejection_comment_mode":"required","rejection_visibility":"requester","rejection_comment_visibility":"requester"} |
      | allowChildOverride | {"rejection_enabled":false,"rejection_behavior":false,"rejection_comment_mode":false,"rejection_visibility":false,"rejection_comment_visibility":false}                     |
    Then the response should have a status code 200
    Given as user "admin"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/group/rejection-managed/rejection_behavior"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                        | value    |
      | (jq).ocs.data.policy.value | continue |

  Scenario: Group admin narrows the rule an instance admin delegated to the group
    When sending "put" to ocs "/apps/libresign/api/v1/policies/compound/group/rejection-managed/rejection_enabled"
      | values             | {"rejection_enabled":true,"rejection_behavior":"cancel","rejection_comment_mode":"optional","rejection_visibility":"participants","rejection_comment_visibility":"requester"} |
      | allowChildOverride | {"rejection_enabled":true,"rejection_behavior":true,"rejection_comment_mode":true,"rejection_visibility":true,"rejection_comment_visibility":true}                            |
    Then the response should have a status code 200
    Given as user "rejection-group-admin"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/compound/group/rejection-managed/rejection_enabled"
      | values             | {"rejection_enabled":true,"rejection_behavior":"cancel","rejection_comment_mode":"required","rejection_visibility":"requester","rejection_comment_visibility":"requester"} |
      | allowChildOverride | {"rejection_enabled":false,"rejection_behavior":false,"rejection_comment_mode":false,"rejection_visibility":false,"rejection_comment_visibility":false}                   |
    Then the response should have a status code 200
    Given as user "admin"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/group/rejection-managed/rejection_visibility"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                        | value     |
      | (jq).ocs.data.policy.value | requester |

  Scenario: Group admin changes the locked rule they wrote for their own group
    When sending "post" to ocs "/apps/libresign/api/v1/policies/compound/system/rejection_enabled"
      | values             | {"rejection_enabled":false} |
      | allowChildOverride | {"rejection_enabled":true}  |
    Then the response should have a status code 200
    Given as user "rejection-group-admin"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/compound/group/rejection-managed/rejection_enabled"
      | values             | {"rejection_enabled":true}  |
      | allowChildOverride | {"rejection_enabled":false} |
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                    | value  |
      | (jq).ocs.data.policies.rejection_enabled.allowedValues | [true] |
    When sending "put" to ocs "/apps/libresign/api/v1/policies/compound/group/rejection-managed/rejection_enabled"
      | values             | {"rejection_enabled":false} |
      | allowChildOverride | {"rejection_enabled":false} |
    Then the response should have a status code 200
    Given as user "admin"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/group/rejection-managed/rejection_enabled"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                        | value |
      | (jq).ocs.data.policy.value | false |

  Scenario Outline: Group admin cannot go beyond the rule an instance admin delegated to the group: <case>
    When sending "put" to ocs "/apps/libresign/api/v1/policies/compound/group/rejection-managed/rejection_enabled"
      | values             | {"rejection_enabled":true,"rejection_behavior":"cancel","rejection_comment_mode":"required","rejection_visibility":"participants","rejection_comment_visibility":"requester"} |
      | allowChildOverride | {"rejection_enabled":true,"rejection_behavior":true,"rejection_comment_mode":true,"rejection_visibility":true,"rejection_comment_visibility":true}                            |
    Then the response should have a status code 200
    Given as user "rejection-group-admin"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/compound/group/rejection-managed/rejection_enabled"
      | values             | {"rejection_enabled":true,"rejection_behavior":"cancel","rejection_comment_mode":"<commentMode>","rejection_visibility":"<visibility>","rejection_comment_visibility":"requester"} |
      | allowChildOverride | {"rejection_enabled":false,"rejection_behavior":false,"rejection_comment_mode":false,"rejection_visibility":false,"rejection_comment_visibility":false}                            |
    Then the response should have a status code 400
    And the response should be a JSON array with the following mandatory values
      | key                 | value   |
      | (jq).ocs.data.error | <error> |
    Given as user "admin"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/group/rejection-managed/rejection_behavior"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                     | value  |
      | (jq).ocs.data.policy.value              | cancel |
      | (jq).ocs.data.policy.allowChildOverride | true   |
    When sending "get" to ocs "/apps/libresign/api/v1/policies/group/rejection-managed/rejection_visibility"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                        | value        |
      | (jq).ocs.data.policy.value | participants |

    Examples:
      | case                       | commentMode | visibility   | error                                                                                    |
      | a wider audience           | required    | public       | A delegated rule cannot disclose a rejection to a wider audience than the parent policy allows |
      | a required comment relaxed | optional    | participants | A delegated rule cannot make the rejection comment optional when it is required by a parent policy |

  Scenario Outline: A personal preference of the five values while the system rule allows override: <allowOverride>
    When sending "post" to ocs "/apps/libresign/api/v1/policies/compound/system/rejection_enabled"
      | values             | {"rejection_enabled":true,"rejection_behavior":"cancel","rejection_comment_mode":"optional","rejection_visibility":"public","rejection_comment_visibility":"participants"} |
      | allowChildOverride | {"rejection_enabled":<allowOverride>,"rejection_behavior":<allowOverride>,"rejection_comment_mode":<allowOverride>,"rejection_visibility":<allowOverride>,"rejection_comment_visibility":<allowOverride>} |
    Then the response should have a status code 200
    Given as user "rejection-regular-user"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/compound/user/rejection_enabled"
      | values | {"rejection_enabled":false,"rejection_behavior":"continue","rejection_comment_mode":"required","rejection_visibility":"participants","rejection_comment_visibility":"requester"} |
    Then the response should have a status code <status>
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                                | value               |
      | (jq).ocs.data.policies.rejection_enabled.effectiveValue            | <enabled>           |
      | (jq).ocs.data.policies.rejection_behavior.effectiveValue           | <behavior>          |
      | (jq).ocs.data.policies.rejection_comment_mode.effectiveValue       | <commentMode>       |
      | (jq).ocs.data.policies.rejection_visibility.effectiveValue         | <visibility>        |
      | (jq).ocs.data.policies.rejection_comment_visibility.effectiveValue | <commentVisibility> |

    Examples:
      | allowOverride | status | enabled | behavior | commentMode | visibility   | commentVisibility |
      | true          | 200    | false   | continue | required    | participants | requester         |
      | false         | 400    | true    | cancel   | optional    | public       | participants      |

  Scenario: Clearing the personal preference of the five values restores the system rule
    When sending "post" to ocs "/apps/libresign/api/v1/policies/compound/system/rejection_enabled"
      | values             | {"rejection_enabled":true,"rejection_behavior":"cancel","rejection_comment_mode":"optional","rejection_visibility":"public","rejection_comment_visibility":"participants"} |
      | allowChildOverride | {"rejection_enabled":true,"rejection_behavior":true,"rejection_comment_mode":true,"rejection_visibility":true,"rejection_comment_visibility":true}                       |
    Then the response should have a status code 200
    Given as user "rejection-regular-user"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/compound/user/rejection_enabled"
      | values | {"rejection_enabled":false,"rejection_behavior":"continue","rejection_comment_mode":"required","rejection_visibility":"participants","rejection_comment_visibility":"requester"} |
    Then the response should have a status code 200
    When sending "delete" to ocs "/apps/libresign/api/v1/policies/user/rejection_enabled"
    Then the response should have a status code 200
    When sending "delete" to ocs "/apps/libresign/api/v1/policies/user/rejection_behavior"
    Then the response should have a status code 200
    When sending "delete" to ocs "/apps/libresign/api/v1/policies/user/rejection_comment_mode"
    Then the response should have a status code 200
    When sending "delete" to ocs "/apps/libresign/api/v1/policies/user/rejection_visibility"
    Then the response should have a status code 200
    When sending "delete" to ocs "/apps/libresign/api/v1/policies/user/rejection_comment_visibility"
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                                | value        |
      | (jq).ocs.data.policies.rejection_enabled.effectiveValue            | true         |
      | (jq).ocs.data.policies.rejection_behavior.effectiveValue           | cancel       |
      | (jq).ocs.data.policies.rejection_comment_mode.effectiveValue       | optional     |
      | (jq).ocs.data.policies.rejection_visibility.effectiveValue         | public       |
      | (jq).ocs.data.policies.rejection_comment_visibility.effectiveValue | participants |
