Feature: file id authorization
  Background:
    Given as user "admin"
    And user "signer1" exists
    And user "attacker" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"factors":[{"name":"account","enabled":true,"requirement":"required","signatureMethods":{"clickToSign":{"enabled":true}}}]} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file    | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | signers | [{"displayName":"Signer","identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name    | Authorization boundary document |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And the response should have a status code 200
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response

  Scenario: An unrelated authenticated user cannot validate a document by internal file id
    Given as user "attacker"
    When sending "get" to ocs "/apps/libresign/api/v1/file/validate/file_id/<FILE_ID>?showVisibleElements=false&showMessages=false&showValidateFile=false"
    Then the response should have a status code 403

  Scenario: The document owner can validate the document by internal file id
    Given as user "admin"
    When sending "get" to ocs "/apps/libresign/api/v1/file/validate/file_id/<FILE_ID>?showVisibleElements=false&showMessages=false&showValidateFile=false"
    Then the response should have a status code 200

  Scenario: An associated signer can validate the document by internal file id
    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/file/validate/file_id/<FILE_ID>?showVisibleElements=false&showMessages=false&showValidateFile=false"
    Then the response should have a status code 200

  Scenario: Knowing the document UUID keeps the existing validation behavior
    Given as user "attacker"
    When sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<FILE_UUID>?showVisibleElements=false&showMessages=false&showValidateFile=false"
    Then the response should have a status code 200
