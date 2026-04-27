Feature: admin/identification_documents_policy
  Scenario: Manage identification_documents policy layers through API
    Given as user "admin"
    And user "signer1" exists
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/signer1/identification_documents"
    And the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/identification_documents"
      | value              | false |
      | allowChildOverride | true  |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                               | value                    |
      | (jq).ocs.data.policy.policyKey    | identification_documents |
      | (jq).ocs.data.policy.effectiveValue| false                   |

    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/admin/identification_documents"
      | value              | true |
      | allowChildOverride | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                               | value                    |
      | (jq).ocs.data.policy.policyKey    | identification_documents |
      | (jq).ocs.data.policy.scope        | group                    |
      | (jq).ocs.data.policy.targetId     | admin                    |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                           | value |
      | (jq).ocs.data.policies.identification_documents.effectiveValue | true  |

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                           | value |
      | (jq).ocs.data.policies.identification_documents.effectiveValue | false |

    Given as user "admin"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/signer1/identification_documents"
      | value | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                        | value                    |
      | (jq).ocs.data.policy.policyKey             | identification_documents |
      | (jq).ocs.data.policy.scope                 | user_policy              |
      | (jq).ocs.data.policy.targetId              | signer1                  |

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                           | value |
      | (jq).ocs.data.policies.identification_documents.effectiveValue | true  |
      | (jq).ocs.data.policies.identification_documents.sourceScope    | user_policy |

  Scenario: Identification document approval visibility follows policy and role
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identification_documents"
      | value | true |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":true}}}] |
    And the response should have a status code 200

    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | Identification flow document |
    Then the response should have a status code 200

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/file/list"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                     | value |
      | (jq).ocs.data.settings.needIdentificationDocuments      | true  |
      | (jq).ocs.data.settings.identificationDocumentsWaitingApproval | false |

    When sending "post" to ocs "/apps/libresign/api/v1/id-docs"
      | files | [{"file":{"url":"<BASE_URL>/apps/libresign/develop/pdf"},"type":"IDENTIFICATION"}] |

    When sending "get" to ocs "/apps/libresign/api/v1/id-docs/approval/list"
    Then the response should have a status code 404
