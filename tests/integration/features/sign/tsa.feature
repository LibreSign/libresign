Feature: TSA Integration - End-to-End Workflow

  Background:
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:install --use-local-cert --java" with result code 0
    And run the command "libresign:install --use-local-cert --jsignpdf" with result code 0
    And run the command "libresign:install --use-local-cert --pdftk" with result code 0
    And run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company --l=City\ Name --ou=Organization\ Unit" with result code 0

  Scenario: TSA workflow - Successfully signs document with timestamp
    Given run the command "config:app:set libresign tsa_url --value=https://freetsa.org/tsr --type=string" with result code 0
    And run the command "config:app:set libresign tsa_auth_type --value=none --type=string" with result code 0
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":true}},"signatureMethodEnabled":"clickToSign"}] |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file  | {"url":"<BASE_URL>/apps/libresign/develop/pdf"}                    |
      | users | [{"displayName": "TSA Signer","identify": {"account": "signer1"}}] |
      | name  | TSA Document Test                                                  |
    Then the response should have a status code 200
    And as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    Then the response should be a JSON array with the following mandatory values
      | key                        | value             |
      | (jq).ocs.data.data[0].name | TSA Document Test |
    And fetch field "(SIGN_UUID)ocs.data.data.0.signers.0.sign_uuid" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID>"
      | method | clickToSign |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                      | value       |
      | (jq).ocs.meta.status     | ok          |
      | (jq).ocs.meta.statuscode | 200         |
      | (jq).ocs.data.action     | 3500        |
      | (jq).ocs.data.message    | File signed |
    And as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<FILE_UUID>"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                           | value                                  |
      | (jq).ocs.data.signers[0].signature_validation | {"id":1,"label":"Signature is valid."} |
    And the response should be a JSON array with the following mandatory values
      | key                                       | value     |
      | (jq).ocs.data.signers[0].timestamp.policy | 1.2.3.4.1 |
    And the response should be a JSON array with the following mandatory values
      | key                                                                | value |
      | (jq).ocs.data.signers[0].timestamp.serialNumber \|test("^[0-9]+$") | true  |
    And the response should be a JSON array with the following mandatory values
      | key                                                                                                         | value |
      | (jq).ocs.data.signers[0].timestamp.genTime \|test("^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}") | true  |
    And the response should be a JSON array with the following mandatory values
      | key                                                    | value           |
      | (jq).ocs.data.signers[0].timestamp.cnHints.commonName  | www.freetsa.org |
      | (jq).ocs.data.signers[0].timestamp.cnHints.countryName | DE              |

  Scenario: TSA error handling - Invalid server
    Given run the command "config:app:set libresign tsa_url --value=https://invalid-tsa-server.example.com/tsr --type=string" with result code 0
    And run the command "config:app:set libresign tsa_auth_type --value=none --type=string" with result code 0
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":true}},"signatureMethodEnabled":"clickToSign"}] |
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file  | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify": {"account": "signer1"}}]          |
      | name  | TSA Error Test                                  |
    And as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    And fetch field "(SIGN_UUID)ocs.data.data.0.signers.0.sign_uuid" from previous JSON response
    And sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID>"
      | method | clickToSign |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                      | value   |
      | (jq).ocs.meta.status     | failure |
      | (jq).ocs.meta.statuscode | 422     |
    And the response should be a JSON array with the following mandatory values
      | key                  | value |
      | (jq).ocs.data.action | 2000  |

  Scenario: Clean up TSA configuration after tests
    Given run the command "config:app:delete libresign tsa_url" with result code 0
    And run the command "config:app:delete libresign tsa_policy_oid" with result code 0
    And run the command "config:app:delete libresign tsa_auth_type" with result code 0
