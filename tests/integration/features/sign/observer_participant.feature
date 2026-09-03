Feature: sign/observer_participant
  Background: Make setup ok
    Given run the command "libresign:configure:openssl --cn test" with result code 0

  Scenario: Observer participants are stored separately and cannot sign
    Given as user "admin"
    And user "signer1" exists
    And user "observer1" exists
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/enable_observer_profile"
      | value | true |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/make_validation_url_private"
      | value | false |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"factors":[{"name":"account","enabled":true,"requirement":"required","signatureMethods":{"clickToSign":{"enabled":true}}}]} |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"displayName":"Signer Name","participantRole":"signer","identifyMethods":[{"method":"account","value":"signer1"}]},{"displayName":"Observer Name","participantRole":"observer","identifyMethods":[{"method":"account","value":"observer1"}]}] |
      | name | Observer document |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should be a JSON array with the following mandatory values
      | key                                                        | value            |
      | (jq).ocs.data.data[0].name                                 | Observer document |
      | (jq).ocs.data.data[0].signers\|length                      | 2                |
      | (jq).ocs.data.data[0].signers[0].displayName               | Observer Name    |
      | (jq).ocs.data.data[0].signers[0].participantRole           | observer         |
      | (jq).ocs.data.data[0].signers[1].displayName               | Signer Name      |
      | (jq).ocs.data.data[0].signers[1].participantRole           | signer           |
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    And as user "observer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(OBSERVER_UUID)ocs.data.data.0.signers.0.sign_request_uuid" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<OBSERVER_UUID>"
      | method | clickToSign |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                             | value                                  |
      | (jq).ocs.data.errors[0].message | Observers cannot sign this document    |
    When sending "get" to "/apps/libresign/p/sign/<OBSERVER_UUID>"
    Then the response should have a status code 303
    And the response should be a JSON array with the following mandatory values
      | key                                        | value |
      | action                                     | 1000  |
      | (jq)(.redirect \| test("validation/<FILE_UUID>")) | true  |
    When sending "get" to "/apps/libresign/validation/<FILE_UUID>"
    Then the response should have a status code 200
    And as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(SIGNER_UUID)ocs.data.data.0.signers.1.sign_request_uuid" from previous JSON response
    When sending "get" to "/apps/libresign/p/sign/<SIGNER_UUID>"
    Then the response should have a status code 200

  Scenario: Observer participants are rejected when the feature is disabled
    Given as user "admin"
    And user "signer1" exists
    And user "observer1" exists
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/enable_observer_profile"
      | value | false |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"displayName":"Signer Name","participantRole":"signer","identifyMethods":[{"method":"account","value":"signer1"}]},{"displayName":"Observer Name","participantRole":"observer","identifyMethods":[{"method":"account","value":"observer1"}]}] |
      | name | Observer disabled document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                  |
      | (jq).ocs.data.message | Observer participants are not enabled  |
