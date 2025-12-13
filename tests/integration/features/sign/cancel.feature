Feature: sign-request-cancel
  Scenario: Delete pending signature request sends cancellation notification
    Given as user "admin"
    And user "signer1" exists
    And reset notifications of user "signer1"
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer1"}}] |
      | name | document |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    And fetch field "(NODE_ID)ocs.data.data.0.nodeId" from previous JSON response
    And fetch field "(SIGN_REQUEST_ID)ocs.data.data.0.signers.0.signRequestId" from previous JSON response
    When sending "delete" to ocs "/apps/libresign/api/v1/sign/file_id/<NODE_ID>/<SIGN_REQUEST_ID>"
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
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer1"},"notify":false}] |
      | name | document |
      | status | 0 |
    And the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/file/list"
    And fetch field "(NODE_ID)ocs.data.data.0.nodeId" from previous JSON response
    And fetch field "(SIGN_REQUEST_ID)ocs.data.data.0.signers.0.signRequestId" from previous JSON response
    And sending "delete" to ocs "/apps/libresign/api/v1/sign/file_id/<NODE_ID>/<SIGN_REQUEST_ID>"
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
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer1"}}] |
      | name | document |
    When sending "get" to ocs "/apps/libresign/api/v1/file/list"
    And fetch field "(NODE_ID)ocs.data.data.0.nodeId" from previous JSON response
    And fetch field "(SIGN_REQUEST_ID)ocs.data.data.0.signers.0.signRequestId" from previous JSON response
    And sending "delete" to ocs "/apps/libresign/api/v1/sign/file_id/<NODE_ID>/<SIGN_REQUEST_ID>"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    Then the response should be a JSON array with the following mandatory values
      | key                           | value |
      | (jq).ocs.data.data[0].signers | []    |
