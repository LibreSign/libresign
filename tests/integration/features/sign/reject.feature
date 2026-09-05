Feature: sign-signature-rejection
  Scenario: Rejection is refused while the policy is disabled
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":false} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | document |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                                 |
      | (jq).ocs.data.message | Signature rejection is not enabled for this document. |

  Scenario: An enabled policy alone does not offer rejection on a request
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | document |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                                 |
      | (jq).ocs.data.message | Signature rejection is not enabled for this document. |

  Scenario: An enabled policy alone does not offer rejection on an envelope
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | files | [{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Doc1.pdf"},{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Doc2.pdf"}] |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | Package |
      | status | 0 |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    And sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | status | 1 |
    And the response should have a status code 200
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                                 |
      | (jq).ocs.data.message | Signature rejection is not enabled for this document. |

  Scenario: Requester cannot enable rejection when the policy disables it
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":false} |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | document |
      | policy | {"overrides":{"signature_rejection":{"enabled":true}}} |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                                                              |
      | (jq).ocs.data.message | Signature rejection is disabled by policy and cannot be enabled for this document. |

  Scenario: Requester offers rejection and the signer rejects with an optional comment
    Given as user "admin"
    And user "signer1" exists
    And user "signer2" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional","cancel_workflow":false} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]},{"identifyMethods":[{"method":"account","value":"signer2"}]}] |
      | name | document |
      | policy | {"overrides":{"signature_rejection":{"enabled":true}}} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
      | comment | I am not the right person to sign this |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                            | value |
      | (jq).ocs.data.status           | 3     |
      | (jq).ocs.data.workflowCanceled | false |
    When as user "admin"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                | value                                  |
      | (jq).ocs.data.data[0].status                                                        | 1                                      |
      | (jq).ocs.data.data[0].signers[] \| select(.status == 3) \| .rejection.comment        | I am not the right person to sign this |
      | (jq).ocs.data.data[0].signers[] \| select(.status == 3) \| .rejection.commentPrivate | false                                  |

  Scenario: Rejection comment is required when the policy demands it
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"required"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | document |
      | policy | {"overrides":{"signature_rejection":{"enabled":true}}} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                                   |
      | (jq).ocs.data.message | A comment is required to reject this signature request. |
    When sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
      | comment | I do not agree with the contract |
    Then the response should have a status code 200

  Scenario: The value stored with the request survives a later policy change
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | document |
      | policy | {"overrides":{"signature_rejection":{"enabled":true}}} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":false} |
    Then the response should have a status code 200
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
      | comment | The policy changed after I was invited |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                  | value |
      | (jq).ocs.data.status | 3     |

  Scenario: A draft that did not offer rejection keeps it disabled through unrelated updates
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | document |
      | status | 0 |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    When sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | name | renamed document |
    Then the response should have a status code 200
    When sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | status | 1 |
    Then the response should have a status code 200
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                                 |
      | (jq).ocs.data.message | Signature rejection is not enabled for this document. |

  Scenario: A draft that offered rejection keeps it enabled through unrelated updates
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | document |
      | status | 0 |
      | policy | {"overrides":{"signature_rejection":{"enabled":true}}} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    When sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | name | renamed document |
    Then the response should have a status code 200
    When sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | status | 1 |
    Then the response should have a status code 200
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
      | comment | Still offered after the updates |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                  | value |
      | (jq).ocs.data.status | 3     |

  Scenario: Requester changes the rejection setting while the request is a draft
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | document |
      | status | 0 |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    When sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | policy | {"overrides":{"signature_rejection":{"enabled":true}}} |
    Then the response should have a status code 200
    When sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | status | 1 |
    Then the response should have a status code 200
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
      | comment | Enabled while the request was still a draft |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                  | value |
      | (jq).ocs.data.status | 3     |

  Scenario: The stored value cannot change after the signing flow started
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | document |
      | policy | {"overrides":{"signature_rejection":{"enabled":true}}} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    When sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | policy | {"overrides":{"signature_rejection":{"enabled":false}}} |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                                                             |
      | (jq).ocs.data.message | The signature rejection setting cannot be changed after the signing flow has started. |
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
      | comment | The stored value is still the one the request was created with |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                  | value |
      | (jq).ocs.data.status | 3     |

  Scenario: An envelope keeps the value it was created with after a later policy change
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | files | [{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Doc1.pdf"},{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Doc2.pdf"}] |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | Package |
      | status | 0 |
      | policy | {"overrides":{"signature_rejection":{"enabled":true}}} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    And sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | status | 1 |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":false} |
    Then the response should have a status code 200
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
      | comment | The envelope still offers rejection |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                  | value |
      | (jq).ocs.data.status | 3     |

  Scenario: An envelope draft that offered rejection keeps it enabled through unrelated updates
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | files | [{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Doc1.pdf"},{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Doc2.pdf"}] |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | Package |
      | status | 0 |
      | policy | {"overrides":{"signature_rejection":{"enabled":true}}} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    When sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | name | renamed package |
    Then the response should have a status code 200
    And sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | status | 1 |
    And the response should have a status code 200
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
      | comment | The envelope still offers rejection |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                  | value |
      | (jq).ocs.data.status | 3     |

  Scenario: An envelope draft that did not offer rejection keeps it disabled through unrelated updates
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | files | [{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Doc1.pdf"},{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Doc2.pdf"}] |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | Package |
      | status | 0 |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    When sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | name | renamed package |
    Then the response should have a status code 200
    And sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | status | 1 |
    And the response should have a status code 200
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                                 |
      | (jq).ocs.data.message | Signature rejection is not enabled for this document. |

  Scenario: Requester changes the rejection setting while the envelope is a draft
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | files | [{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Doc1.pdf"},{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Doc2.pdf"}] |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | Package |
      | status | 0 |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    When sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | policy | {"overrides":{"signature_rejection":{"enabled":true}}} |
    Then the response should have a status code 200
    And sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | status | 1 |
    And the response should have a status code 200
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
      | comment | The envelope still offers rejection |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                  | value |
      | (jq).ocs.data.status | 3     |

  Scenario: The stored value of an envelope cannot change after the signing flow started
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | files | [{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Doc1.pdf"},{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Doc2.pdf"}] |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | Package |
      | status | 0 |
      | policy | {"overrides":{"signature_rejection":{"enabled":true}}} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    And sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | status | 1 |
    And the response should have a status code 200
    When sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | policy | {"overrides":{"signature_rejection":{"enabled":false}}} |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                                                                 |
      | (jq).ocs.data.message | The signature rejection setting cannot be changed after the signing flow has started. |
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
      | comment | The envelope still offers rejection |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                  | value |
      | (jq).ocs.data.status | 3     |

  Scenario: A comment the signer marked as private stays between them and the requester
    Given as user "admin"
    And user "signer1" exists
    And user "signer2" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional","public_status":true,"show_comment_on_validation":true} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]},{"identifyMethods":[{"method":"account","value":"signer2"}]}] |
      | name | document |
      | policy | {"overrides":{"signature_rejection":{"enabled":true}}} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
      | comment | I am in litigation with the other party |
      | privateComment | true |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                  | value |
      | (jq).ocs.data.status | 3     |
    When sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should be a JSON array with the following mandatory values
      | key                                                                                       | value                                   |
      | (jq).ocs.data.data[0].signers[] \| select(.me == true) \| .rejection.comment        | I am in litigation with the other party |
      | (jq).ocs.data.data[0].signers[] \| select(.me == true) \| .rejection.commentPrivate | true                                    |
    When as user "admin"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should be a JSON array with the following mandatory values
      | key                                                                                        | value                                   |
      | (jq).ocs.data.data[0].signers[] \| select(.status == 3) \| .rejection.comment        | I am in litigation with the other party |
      | (jq).ocs.data.data[0].signers[] \| select(.status == 3) \| .rejection.commentPrivate | true                                    |
    When as user "signer2"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should be a JSON array with the following mandatory values
      | key                                                                                                 | value |
      | (jq).ocs.data.data[0].signers[] \| select(.status == 3) \| .rejection.rejectedAt != null     | true  |
      | (jq).ocs.data.data[0].signers[] \| select(.status == 3) \| .rejection.comment != null        | false |
      | (jq).ocs.data.data[0].signers[] \| select(.status == 3) \| .rejection.commentPrivate != null | false |

  Scenario: A comment left public is disclosed to the other signers
    Given as user "admin"
    And user "signer1" exists
    And user "signer2" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional","public_status":true,"show_comment_on_validation":true} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]},{"identifyMethods":[{"method":"account","value":"signer2"}]}] |
      | name | document |
      | policy | {"overrides":{"signature_rejection":{"enabled":true}}} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
      | comment | This document is not for me |
    Then the response should have a status code 200
    When as user "signer2"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should be a JSON array with the following mandatory values
      | key                                                                                        | value                       |
      | (jq).ocs.data.data[0].signers[] \| select(.status == 3) \| .rejection.comment        | This document is not for me |
      | (jq).ocs.data.data[0].signers[] \| select(.status == 3) \| .rejection.commentPrivate | false                       |

  Scenario: Signer rejects through the signer UUID endpoint
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | document |
      | policy | {"overrides":{"signature_rejection":{"enabled":true}}} |
    And the response should have a status code 200
    And as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(SIGN_REQUEST_UUID)ocs.data.data.0.signers.0.sign_request_uuid" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_REQUEST_UUID>/reject"
      | comment | Rejected from the signing link |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                  | value |
      | (jq).ocs.data.status | 3     |

  Scenario: Another identity cannot reject a signature request through the signer UUID
    Given as user "admin"
    And user "signer1" exists
    And user "signer2" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}],"signingOrder":1},{"identifyMethods":[{"method":"account","value":"signer2"}],"signingOrder":2}] |
      | name | document |
      | policy | {"overrides":{"signature_rejection":{"enabled":true}}} |
    And the response should have a status code 200
    And as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(SIGN_REQUEST_UUID)ocs.data.data.0.signers.0.sign_request_uuid" from previous JSON response
    When as user "signer2"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_REQUEST_UUID>/reject"
      | comment | Not my signature request |
    Then the response should have a status code 422
    When as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should be a JSON array with the following mandatory values
      | key                                                        | value |
      | (jq).ocs.data.data[0].signers[] \| select(.me == true) \| .status | 1     |

  Scenario: Rejection closes the workflow and blocks the actions that are no longer valid
    Given as user "admin"
    And user "signer1" exists
    And user "signer2" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional","cancel_workflow":true} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]},{"identifyMethods":[{"method":"account","value":"signer2"}]}] |
      | name | document |
      | policy | {"overrides":{"signature_rejection":{"enabled":true}}} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    And fetch field "(SIGN_REQUEST_ID)ocs.data.data.0.signers.1.signRequestId" from previous JSON response
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                            | value |
      | (jq).ocs.data.workflowCanceled | true  |
    When sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                                    |
      | (jq).ocs.data.message | The signing workflow of this document is already closed. |
    When as user "signer2"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>"
      | method | password |
      | token  | password |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                             | value                                                    |
      | (jq).ocs.data.errors[0].message | The signing workflow of this document is already closed. |
    When as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/notify/signer"
      | fileId | <FILE_ID> |
      | signRequestId | <SIGN_REQUEST_ID> |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                                    |
      | (jq).ocs.data.message | The signing workflow of this document is already closed. |
    When sending "delete" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/<SIGN_REQUEST_ID>"
    Then the response should have a status code 401
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                                    |
      | (jq).ocs.data.message | The signing workflow of this document is already closed. |
    When sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer2"}]}] |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                                    |
      | (jq).ocs.data.message | The signing workflow of this document is already closed. |
    When sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should be a JSON array with the following mandatory values
      | key                          | value |
      | (jq).ocs.data.data[0].status | 6     |

  Scenario: Rejecting one document of an envelope closes the whole envelope
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
      | value | {"enabled":true,"comment_mode":"optional","cancel_workflow":true} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | files | [{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Doc1.pdf"},{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Doc2.pdf"}] |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | Package |
      | status | 0 |
      | policy | {"overrides":{"signature_rejection":{"enabled":true}}} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    And sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | status | 1 |
    And the response should have a status code 200
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
      | comment | I do not agree with this package |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                            | value |
      | (jq).ocs.data.workflowCanceled | true  |
    When sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>"
      | method | password |
      | token  | password |
    Then the response should have a status code 422
    When as user "admin"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                                          | value                            |
      | (jq).ocs.data.data[0].nodeType                                               | envelope                         |
      | (jq).ocs.data.data[0].status                                                 | 6                                |
      | (jq).ocs.data.data[0].signers[] \| select(.status == 3) \| .rejection.comment | I do not agree with this package |
