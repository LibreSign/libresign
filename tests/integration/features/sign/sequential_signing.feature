Feature: sequential-signing
  Background:
    Given as user "admin"
    And run the command "libresign:install --use-local-cert --java" with result code 0
    And run the command "libresign:install --use-local-cert --jsignpdf" with result code 0
    And run the command "libresign:install --use-local-cert --pdftk" with result code 0
    And run the command "libresign:configure:openssl --cn=Common\ Name" with result code 0
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":true}}}] |
    And the response should have a status code 200

  Scenario: Parallel signing - all signers can sign immediately
    Given user "signer1" exists
    And user "signer2" exists
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer1"}},{"identify":{"account":"signer2"}}] |
      | name | Parallel Document |
    Then the response should have a status code 200
    And as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    And the response should have a status code 200
    And fetch field "(SIGN_UUID_1)ocs.data.data.0.signers.0.sign_uuid" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID_1>"
      | method | clickToSign |
    Then the response should have a status code 200
    And as user "signer2"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    And the response should have a status code 200
    And fetch field "(SIGN_UUID_2)ocs.data.data.0.signers.1.sign_uuid" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID_2>"
      | method | clickToSign |
    Then the response should have a status code 200

  Scenario: Sequential signing - only first signer can sign initially
    Given user "signer1" exists
    And user "signer2" exists
    And sending "post" to ocs "/apps/libresign/api/v1/admin/signature-flow"
      | flow | ordered_numeric |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer1"},"signingOrder":1},{"identify":{"account":"signer2"},"signingOrder":2}] |
      | name | Sequential Document |
    Then the response should have a status code 200
    And as user "signer2"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    And the response should have a status code 200
    And fetch field "(SIGN_UUID_2)ocs.data.data.0.signers.1.sign_uuid" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID_2>"
      | method | clickToSign |
    Then the response should have a status code 422
    And as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    And the response should have a status code 200
    And fetch field "(SIGN_UUID_1)ocs.data.data.0.signers.0.sign_uuid" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID_1>"
      | method | clickToSign |
    Then the response should have a status code 200
    And as user "signer2"
    When sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID_2>"
      | method | clickToSign |
    Then the response should have a status code 200
