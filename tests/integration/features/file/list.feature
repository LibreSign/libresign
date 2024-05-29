Feature: file-list
  Background: Create users
    Given user "signer1" exists
    Given user "signer2" exists

  Scenario: Return a list with two files
    Given as user "admin"
    And set the email of user "signer1" to "signer1@domain.test"
    And set the email of user "signer2" to ""
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"signer1@domain.test"}},{"identify":{"account":"signer2"}}] |
      | name | document |
    And the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/file/list"
    Then the response should be a JSON array with the following mandatory values
      | key                                                        | value                   |
      | (jq).ocs.data.data[0].name                                 | document                |
      | (jq).ocs.data.data[0].status                               | 1                       |
      | (jq).ocs.data.data[0].statusText                           | available for signature |
      | (jq).ocs.data.data[0].requested_by.uid                     | admin                   |
      | (jq).ocs.data.data[0].signers\|length                      | 2                       |
      | (jq).ocs.data.data[0].signers[0].email                     | signer1@domain.test     |
      | (jq).ocs.data.data[0].signers[0].identifyMethods\|length   | 1                       |
      | (jq).ocs.data.data[0].signers[0].identifyMethods[0].method | email                   |
      | (jq).ocs.data.data[0].signers[0].me                        | false                   |
      | (jq).ocs.data.data[0].signers[1].email                     |                         |
      | (jq).ocs.data.data[0].signers[1].me                        | false                   |
      | (jq).ocs.data.data[0].signers[0].identifyMethods\|length   | 1                       |
      | (jq).ocs.data.data[0].signers[1].identifyMethods[0].method | account                 |
    When fetch field "(NODE_ID)ocs.data.data.0.nodeId" from prevous JSON response
    And fetch field "(SIGN_REQUEST_ID)ocs.data.data.0.signers.0.signRequestId" from prevous JSON response
    And sending "delete" to ocs "/apps/libresign/api/v1/sign/file_id/<NODE_ID>/<SIGN_REQUEST_ID>"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    Then the response should be a JSON array with the following mandatory values
      | key                                                        | value                   |
      | (jq).ocs.data.data[0].name                                 | document                |
      | (jq).ocs.data.data[0].status                               | 1                       |
      | (jq).ocs.data.data[0].statusText                           | available for signature |
      | (jq).ocs.data.data[0].requested_by.uid                     | admin                   |
      | (jq).ocs.data.data[0].signers\|length                      | 1                       |
      | (jq).ocs.data.data[0].signers[0].email                     |                         |
      | (jq).ocs.data.data[0].signers[0].me                        | false                   |
      | (jq).ocs.data.data[0].signers[0].identifyMethods\|length   | 1                       |
      | (jq).ocs.data.data[0].signers[0].identifyMethods[0].method | account                 |
