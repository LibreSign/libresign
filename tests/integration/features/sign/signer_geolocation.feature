Feature: sign/signer_geolocation
  Scenario: Required geolocation is enforced server-side and stored independently of collect_metadata
    Given as user "admin"
    And user "signer1" exists
    And run the command "config:app:set libresign signing_mode --value=sync --type=string" with result code 0
    And run the command "libresign:install --use-local-cert --java" with result code 0
    And run the command "libresign:install --use-local-cert --jsignpdf" with result code 0
    And run the command "libresign:install --use-local-cert --pdftk" with result code 0
    And run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company --l=City\ Name --ou=Organization\ Unit" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/collect_metadata"
      | value | false |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signer_geolocation"
      | value | {"mode":"required"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"factors":[{"name":"account","enabled":true,"requirement":"required","signatureMethods":{"clickToSign":{"enabled":true}}}]} |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"displayName":"Signer Name","identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | Geolocation document |
    And the response should have a status code 200
    And as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should be a JSON array with the following mandatory values
      | key                                                                 | value    |
      | (jq).ocs.data.data[0].name                                           | Geolocation document |
      | (jq).ocs.data.data[0].signers[0].metadata.geolocationRequirement    | required |
    And fetch field "(SIGN_REQUEST_UUID)ocs.data.data.0.signers.0.sign_request_uuid" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_REQUEST_UUID>"
      | method | clickToSign |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                             | value                                              |
      | (jq).ocs.data.errors[0].message | Geolocation is required to sign this document.     |
    When sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_REQUEST_UUID>"
      | method | clickToSign |
      | geolocation | {"status":"collected","latitude":-23.5505,"longitude":-46.6333,"accuracy":25,"timestamp":1700000000000} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                     | value       |
      | (jq).ocs.data.message   | File signed |
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should be a JSON array with the following mandatory values
      | key                                                              | value     |
      | (jq).ocs.data.data[0].signers[0].metadata.geolocation.status      | collected |
      | (jq).ocs.data.data[0].signers[0].metadata.geolocation.latitude   | -23.5505  |

  Scenario: Requester may require geolocation for selected signers when policy is optional
    Given as user "admin"
    And user "signer1" exists
    And user "signer2" exists
    And run the command "config:app:set libresign signing_mode --value=sync --type=string" with result code 0
    And run the command "libresign:install --use-local-cert --java" with result code 0
    And run the command "libresign:install --use-local-cert --jsignpdf" with result code 0
    And run the command "libresign:install --use-local-cert --pdftk" with result code 0
    And run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company --l=City\ Name --ou=Organization\ Unit" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signer_geolocation"
      | value | {"mode":"optional"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"factors":[{"name":"account","enabled":true,"requirement":"required","signatureMethods":{"clickToSign":{"enabled":true}}}]} |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"displayName":"Required signer","geolocationRequired":true,"identifyMethods":[{"method":"account","value":"signer1"}]},{"displayName":"Optional signer","geolocationRequired":false,"identifyMethods":[{"method":"account","value":"signer2"}]}] |
      | name | Mixed geolocation document |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should be a JSON array with the following mandatory values
      | key                                                                  | value    |
      | (jq).ocs.data.data[0].signers[0].metadata.geolocationRequirement     | required |
      | (jq).ocs.data.data[0].signers[1].metadata.geolocationRequirement     | disabled |
    And as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(SIGN_REQUEST_UUID)ocs.data.data.0.signers.0.sign_request_uuid" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_REQUEST_UUID>"
      | method | clickToSign |
    Then the response should have a status code 422
    And as user "signer2"
    When sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(SIGN_REQUEST_UUID)ocs.data.data.0.signers.1.sign_request_uuid" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_REQUEST_UUID>"
      | method | clickToSign |
    Then the response should have a status code 200

  Scenario: Frozen geolocation requirement survives later policy changes
    Given as user "admin"
    And user "signer1" exists
    And run the command "config:app:set libresign signing_mode --value=sync --type=string" with result code 0
    And run the command "libresign:install --use-local-cert --java" with result code 0
    And run the command "libresign:install --use-local-cert --jsignpdf" with result code 0
    And run the command "libresign:install --use-local-cert --pdftk" with result code 0
    And run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company --l=City\ Name --ou=Organization\ Unit" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signer_geolocation"
      | value | {"mode":"required"} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"factors":[{"name":"account","enabled":true,"requirement":"required","signatureMethods":{"clickToSign":{"enabled":true}}}]} |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"displayName":"Signer Name","identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | Frozen geolocation document |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/signer_geolocation"
      | value | {"mode":"disabled"} |
    And the response should have a status code 200
    And as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should be a JSON array with the following mandatory values
      | key                                                                 | value    |
      | (jq).ocs.data.data[0].signers[0].metadata.geolocationRequirement    | required |
    And fetch field "(SIGN_REQUEST_UUID)ocs.data.data.0.signers.0.sign_request_uuid" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_REQUEST_UUID>"
      | method | clickToSign |
    Then the response should have a status code 422
