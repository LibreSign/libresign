Feature: sign-request-cancel
  Scenario: Delete pending signature request sends cancellation notification
    Given as user "admin"
    And user "signer1" exists
    And reset notifications of user "signer1"
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | document |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(SIGN_REQUEST_ID)ocs.data.data.0.signers.0.signRequestId" from previous JSON response
    When sending "delete" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/<SIGN_REQUEST_ID>"
    Then the response should have a status code 200
    When as user "signer1"
    And sending "get" to ocs "/apps/notifications/api/v2/notifications"
    Then the response should be a JSON array with the following mandatory values
      | key                      | value                                             |
      | (jq).ocs.data[0].subject | admin canceled the signature request for document |

  Scenario: Delete draft request does not send cancellation notification
    Given as user "admin"
    And user "signer1" exists
    And reset notifications of user "signer1"
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}],"notify":false}] |
      | name | document |
      | status | 0 |
    And the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(SIGN_REQUEST_ID)ocs.data.data.0.signers.0.signRequestId" from previous JSON response
    And sending "delete" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/<SIGN_REQUEST_ID>"
    Then the response should have a status code 200
    When as user "signer1"
    And sending "get" to ocs "/apps/notifications/api/v2/notifications"
    Then the response should be a JSON array with the following mandatory values
      | key           | value |
      | (jq).ocs.data | []    |

  Scenario: Delete signer removes them from file list
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | document |
    When sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(SIGN_REQUEST_ID)ocs.data.data.0.signers.0.signRequestId" from previous JSON response
    And sending "delete" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/<SIGN_REQUEST_ID>"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    Then the response should be a JSON array with the following mandatory values
      | key                           | value |
      | (jq).ocs.data.data[0].signers | []    |
  Scenario: Delete last signer reverts file status back to draft
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | document |
    And the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(SIGN_REQUEST_ID)ocs.data.data.0.signers.0.signRequestId" from previous JSON response
    Then the response should be a JSON array with the following mandatory values
      | key                   | value |
      | (jq).ocs.data.data[0].status | 1 |
    When sending "delete" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/<SIGN_REQUEST_ID>"
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    Then the response should be a JSON array with the following mandatory values
      | key                   | value |
      | (jq).ocs.data.data[0].status | 0 |

  Scenario: Delete envelope signer removes only that signer from both documents
    Given as user "admin"
    And user "signer1" exists
    And user "signer2" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | files | [{"base64":"<SMALL_VALID_PDF_BASE64>","name":"First.pdf"},{"base64":"<SMALL_VALID_PDF_BASE64>","name":"Second.pdf"}] |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]},{"identifyMethods":[{"method":"account","value":"signer2"}]}] |
      | name | Envelope cancellation |
      | status | 0 |
    And the response should have a status code 200
    And fetch field "(ENVELOPE_UUID)ocs.data.uuid" from previous JSON response
    And sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <ENVELOPE_UUID> |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]},{"identifyMethods":[{"method":"account","value":"signer2"}]}] |
      | status | 1 |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<ENVELOPE_UUID>"
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.nodeType | envelope |
      | (jq).ocs.data.files \| length | 2 |
      | (jq)[.ocs.data, .ocs.data.files[]] \| map(.status) | [1,1,1] |
      | (jq)[.ocs.data, .ocs.data.files[]] \| map([.signers[].identifyMethods[] \| select(.method == "account").value] \| sort) | [["signer1","signer2"],["signer1","signer2"],["signer1","signer2"]] |
    And fetch field "(ENVELOPE_ID)ocs.data.id" from previous JSON response
    And fetch field '(SIGN_REQUEST_ID)(jq).ocs.data.signers[] | select(any(.identifyMethods[]; .method == "account" and .value == "signer1")) | .signRequestId' from previous JSON response
    And fetch field "(FIRST_UUID)ocs.data.files.0.uuid" from previous JSON response
    And fetch field "(SECOND_UUID)ocs.data.files.1.uuid" from previous JSON response
    When sending "delete" to ocs "/apps/libresign/api/v1/sign/file_id/<ENVELOPE_ID>/<SIGN_REQUEST_ID>"
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<ENVELOPE_UUID>"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.files \| length | 2 |
      | (jq)[.ocs.data, .ocs.data.files[]] \| map(.status) | [1,1,1] |
      | (jq)[.ocs.data, .ocs.data.files[]] \| map(.signers \| length) | [1,1,1] |
      | (jq)[.ocs.data, .ocs.data.files[]] \| map([.signers[].identifyMethods[] \| select(.method == "account").value]) | [["signer2"],["signer2"],["signer2"]] |
    When sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<FIRST_UUID>"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.signers \| length | 1 |
      | (jq)[.ocs.data.signers[].identifyMethods[] \| select(.method == "account").value] | ["signer2"] |
    When sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<SECOND_UUID>"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.signers \| length | 1 |
      | (jq)[.ocs.data.signers[].identifyMethods[] \| select(.method == "account").value] | ["signer2"] |

  Scenario: Delete last envelope signer reverts both documents to draft without orphan requests
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | files | [{"base64":"<SMALL_VALID_PDF_BASE64>","name":"First.pdf"},{"base64":"<SMALL_VALID_PDF_BASE64>","name":"Second.pdf"}] |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | Last envelope signer |
      | status | 0 |
    And the response should have a status code 200
    And fetch field "(ENVELOPE_UUID)ocs.data.uuid" from previous JSON response
    And sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <ENVELOPE_UUID> |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | status | 1 |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<ENVELOPE_UUID>"
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.nodeType | envelope |
      | (jq).ocs.data.files \| length | 2 |
      | (jq)[.ocs.data, .ocs.data.files[]] \| map(.status) | [1,1,1] |
      | (jq)[.ocs.data, .ocs.data.files[]] \| map([.signers[].identifyMethods[] \| select(.method == "account").value]) | [["signer1"],["signer1"],["signer1"]] |
    And fetch field "(ENVELOPE_ID)ocs.data.id" from previous JSON response
    And fetch field '(SIGN_REQUEST_ID)(jq).ocs.data.signers[] | select(any(.identifyMethods[]; .method == "account" and .value == "signer1")) | .signRequestId' from previous JSON response
    And fetch field "(FIRST_UUID)ocs.data.files.0.uuid" from previous JSON response
    And fetch field "(SECOND_UUID)ocs.data.files.1.uuid" from previous JSON response
    When sending "delete" to ocs "/apps/libresign/api/v1/sign/file_id/<ENVELOPE_ID>/<SIGN_REQUEST_ID>"
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<ENVELOPE_UUID>"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.files \| length | 2 |
      | (jq)[.ocs.data, .ocs.data.files[]] \| map(.signers) | [[],[],[]] |
      | (jq)[.ocs.data, .ocs.data.files[]] \| map(.status) | [0,0,0] |
    When sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<FIRST_UUID>"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.signers | [] |
      | (jq).ocs.data.status | 0 |
    When sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<SECOND_UUID>"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.signers | [] |
      | (jq).ocs.data.status | 0 |
