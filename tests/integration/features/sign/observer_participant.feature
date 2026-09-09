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

  Scenario: Existing observer requests keep the policy snapshot after the policy is disabled
    Given as user "admin"
    And user "signer1" exists
    And user "observer1" exists
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/enable_observer_profile"
      | value | true |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"displayName":"Signer Name","participantRole":"signer","identifyMethods":[{"method":"account","value":"signer1"}]},{"displayName":"Observer Name","participantRole":"observer","identifyMethods":[{"method":"account","value":"observer1"}]}] |
      | name | Existing observer document |
    Then the response should have a status code 200
    And fetch field "(FILE_UUID)ocs.data.uuid" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/enable_observer_profile"
      | value | false |
    Then the response should have a status code 200
    When sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | signers | [{"displayName":"Signer Name","participantRole":"signer","identifyMethods":[{"method":"account","value":"signer1"}]},{"displayName":"Observer Name","participantRole":"observer","identifyMethods":[{"method":"account","value":"observer1"}]}] |
    Then the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"displayName":"Signer Name","participantRole":"signer","identifyMethods":[{"method":"account","value":"signer1"}]},{"displayName":"Observer Name","participantRole":"observer","identifyMethods":[{"method":"account","value":"observer1"}]}] |
      | name | New observer document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                  |
      | (jq).ocs.data.message | Observer participants are not enabled  |

  Scenario: Existing envelope observer requests keep the policy snapshot after the policy is disabled
    Given as user "admin"
    And user "signer1" exists
    And user "observer1" exists
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/enable_observer_profile"
      | value | true |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | files | [{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Contract.pdf"},{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Annex.pdf"}] |
      | signers | [{"displayName":"Signer Name","participantRole":"signer","identifyMethods":[{"method":"account","value":"signer1"}]},{"displayName":"Observer Name","participantRole":"observer","identifyMethods":[{"method":"account","value":"observer1"}]}] |
      | name | Existing observer envelope |
    Then the response should have a status code 200
    And fetch field "(FILE_UUID)ocs.data.uuid" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/enable_observer_profile"
      | value | false |
    Then the response should have a status code 200
    When sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | signers | [{"displayName":"Signer Name","participantRole":"signer","identifyMethods":[{"method":"account","value":"signer1"}]},{"displayName":"Observer Name","participantRole":"observer","identifyMethods":[{"method":"account","value":"observer1"}]}] |
    Then the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | files | [{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Contract.pdf"},{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Annex.pdf"}] |
      | signers | [{"displayName":"Signer Name","participantRole":"signer","identifyMethods":[{"method":"account","value":"signer1"}]},{"displayName":"Observer Name","participantRole":"observer","identifyMethods":[{"method":"account","value":"observer1"}]}] |
      | name | New observer envelope |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                  |
      | (jq).ocs.data.message | Observer participants are not enabled  |

  Scenario: Existing request can add observers after the policy is enabled
    Given as user "admin"
    And user "signer1" exists
    And user "observer1" exists
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/enable_observer_profile"
      | value | false |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"displayName":"Signer Name","participantRole":"signer","identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | Request created without observers |
    Then the response should have a status code 200
    And fetch field "(FILE_UUID)ocs.data.uuid" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/enable_observer_profile"
      | value | true |
    Then the response should have a status code 200
    When sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | signers | [{"displayName":"Signer Name","participantRole":"signer","identifyMethods":[{"method":"account","value":"signer1"}]},{"displayName":"Observer Name","participantRole":"observer","identifyMethods":[{"method":"account","value":"observer1"}]}] |
    Then the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/enable_observer_profile"
      | value | false |
    Then the response should have a status code 200
    When sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | signers | [{"displayName":"Signer Name","participantRole":"signer","identifyMethods":[{"method":"account","value":"signer1"}]},{"displayName":"Observer Name","participantRole":"observer","identifyMethods":[{"method":"account","value":"observer1"}]}] |
    Then the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"displayName":"Signer Name","participantRole":"signer","identifyMethods":[{"method":"account","value":"signer1"}]},{"displayName":"Observer Name","participantRole":"observer","identifyMethods":[{"method":"account","value":"observer1"}]}] |
      | name | New observer after disable |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                  |
      | (jq).ocs.data.message | Observer participants are not enabled  |

  Scenario: Mixed account signer and email observer creates the observer with a notification
    Given as user "admin"
    And user "signer1" exists
    And set the email of user "signer1" to ""
    And my inbox is empty
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/enable_observer_profile"
      | value | true |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/make_validation_url_private"
      | value | false |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"can_create_account":false,"factors":[{"name":"account","enabled":true,"requirement":"optional","signatureMethods":{"clickToSign":{"enabled":true}}},{"name":"email","enabled":true,"requirement":"optional","signatureMethods":{"clickToSign":{"enabled":true}}}]} |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"displayName":"Signer Name","participantRole":"signer","identifyMethods":[{"method":"account","value":"signer1"}]},{"displayName":"Observer Name","participantRole":"observer","identifyMethods":[{"method":"email","value":"observer@domain.test"}],"description":"Please review the annex."}] |
      | name | Mixed identify methods observer |
    Then the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And the response should be a JSON array with the following mandatory values
      | key                                              | value            |
      | (jq).ocs.data.data[0].name                       | Mixed identify methods observer |
      | (jq).ocs.data.data[0].signers\|length            | 2                |
      | (jq).ocs.data.data[0].signers[0].displayName     | Observer Name    |
      | (jq).ocs.data.data[0].signers[0].participantRole | observer         |
      | (jq).ocs.data.data[0].signers[1].displayName     | Signer Name      |
      | (jq).ocs.data.data[0].signers[1].participantRole | signer           |
    And there should be 1 emails in my inbox
    When I open the latest email to "observer@domain.test" with subject "LibreSign: A document is ready for signature"
    Then I should see "Please review the annex" in the opened email
    And I should see "A document is ready for signature" in the opened email

  Scenario: Visible signature elements are rejected for observers and accepted for signers
    Given as user "admin"
    And user "signer1" exists
    And user "observer1" exists
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/enable_observer_profile"
      | value | true |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"factors":[{"name":"account","enabled":true,"requirement":"required","signatureMethods":{"clickToSign":{"enabled":true}}}]} |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"displayName":"Signer Name","participantRole":"signer","identifyMethods":[{"method":"account","value":"signer1"}]},{"displayName":"Observer Name","participantRole":"observer","identifyMethods":[{"method":"account","value":"observer1"}]}] |
      | name | Visible element observer document |
      | status | 0 |
    Then the response should have a status code 200
    And fetch field "(FILE_UUID)ocs.data.uuid" from previous JSON response
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(OBSERVER_SIGN_REQUEST_ID)ocs.data.data.0.signers.0.signRequestId" from previous JSON response
    And fetch field "(SIGNER_SIGN_REQUEST_ID)ocs.data.data.0.signers.1.signRequestId" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/file-element/<FILE_UUID>"
      | signRequestId | <OBSERVER_SIGN_REQUEST_ID> |
      | type          | signature                  |
    Then the response should have a status code 404
    And the response should be a JSON array with the following mandatory values
      | key                             | value                                         |
      | (jq).ocs.data.errors[0].message | Observers cannot have visible signature elements |
    When sending "post" to ocs "/apps/libresign/api/v1/file-element/<FILE_UUID>"
      | signRequestId | <SIGNER_SIGN_REQUEST_ID> |
      | type          | signature                |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                               | value  |
      | (jq).ocs.meta.message             | OK     |
      | (jq).ocs.data.fileElementId\|type | number |

  Scenario: Sending a notification to an observer uses the validation email
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/enable_observer_profile"
      | value | true |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"can_create_account":false,"factors":[{"name":"email","enabled":true,"requirement":"required"}]} |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | name | Observer notification document |
      | signers | [{"displayName":"Signer Name","participantRole":"signer","identifyMethods":[{"method":"email","value":"signer@domain.test"}]},{"displayName":"Observer Name","participantRole":"observer","identifyMethods":[{"method":"email","value":"observer@domain.test"}],"description":"Please review the annex."}] |
    Then the response should have a status code 200
    And fetch field "(FILE_ID)ocs.data.id" from previous JSON response
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(OBSERVER_SIGN_REQUEST_ID)ocs.data.data.0.signers.0.signRequestId" from previous JSON response
    And my inbox is empty
    When sending "post" to ocs "/apps/libresign/api/v1/notify/signer"
      | fileId | <FILE_ID> |
      | signRequestId | <OBSERVER_SIGN_REQUEST_ID> |
    Then the response should have a status code 200
    And there should be 1 emails in my inbox
    When I open the latest email to "observer@domain.test" with subject "LibreSign: Changes were made to a document"
    Then I should see "Please review the annex" in the opened email
    And I should see "Changes were made to a document. Open the link below:" in the opened email
