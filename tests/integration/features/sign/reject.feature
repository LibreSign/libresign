Feature: sign-signature-rejection
  Scenario: Rejection is refused while the policy is disabled
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/system/signature_rejection"
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
      | key                     | value                                                |
      | (jq).ocs.data.message   | Signature rejection is not enabled for this document. |

  Scenario: Signer rejects with an optional comment and the workflow continues
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
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
      | comment | I am not the right person to sign this |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                 | value    |
      | (jq).ocs.data.status                | 3        |
      | (jq).ocs.data.workflowCanceled      | false    |
    When as user "admin"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                     | value                                  |
      | (jq).ocs.data.data[0].status                            | 1                                      |
      | (jq).ocs.data.data[0].signers[0].status                 | 3                                      |
      | (jq).ocs.data.data[0].signers[0].rejection.comment      | I am not the right person to sign this |

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
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                                  |
      | (jq).ocs.data.message | A comment is required to reject this signature request. |
    When sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
      | comment | I do not agree with the contract |
    Then the response should have a status code 200

  Scenario: Rejection closes the workflow and blocks further actions
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
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    When as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                            | value |
      | (jq).ocs.data.workflowCanceled | true  |
    When sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/reject"
    Then the response should have a status code 422
    When as user "signer2"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>"
      | method | password |
      | token  | password |
    Then the response should have a status code 422
    When as user "admin"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should be a JSON array with the following mandatory values
      | key                          | value |
      | (jq).ocs.data.data[0].status | 6     |
