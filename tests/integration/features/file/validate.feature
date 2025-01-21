Feature: validate
  Scenario: Sign with account, delete the account and validate
    Given as user "admin"
    And run the command "libresign:install --use-local-cert --java" with result code 0
    And run the command "libresign:install --use-local-cert --jsignpdf" with result code 0
    And run the command "libresign:install --use-local-cert --pdftk" with result code 0
    And run the command "config:app:set libresign certificate_engine --value=openssl" with result code 0
    And run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company --l=City\ Name --ou=Organization\ Unit" with result code 0
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":true}}}] |
    And user "signer1" exists
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer1"}}] |
      | name | Document Name |
    Then the response should have a status code 200
    And as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    Then the response should be a JSON array with the following mandatory values
      | key                        | value         |
      | (jq).ocs.data.data[0].name | Document Name |
    And fetch field "(SIGN_URL)ocs.data.data.0.url" from prevous JSON response
    And fetch field "(SIGN_UUID)ocs.data.data.0.signers.0.sign_uuid" from prevous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from prevous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID>"
      | key    | value       |
      | method | clickToSign |
    Then the response should have a status code 200
    When run the command "user:delete signer1" with result code 0
    And as user ""
    And sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<FILE_UUID>"
    Then the response should have a status code 200
    Then the response should be a JSON array with the following mandatory values
      | key                                           | value                                                                                                                |
      | (jq).ocs.data.signers[0].me                   | false                                                                                                                |
      | (jq).ocs.data.signers[0].identifyMethods      | [{"method": "account","value": "signer1","mandatory": 1}]                                                            |
      | (jq).ocs.data.signers[0].subject              | /C=BR/ST=State of Company/L=City Name/O=Organization/OU=Organization Unit/UID=account:signer1/CN=signer1-displayname |
      | (jq).ocs.data.signers[0].signature_validation | {"id":1,"label":"Signature is valid."}                                                                               |
      | (jq).ocs.data.signers[0].hash_algorithm       | RSA-SHA1                                                                                                             |
