Feature: validate
  Scenario: Validation assigns visible elements only to their signer
    Given as user "admin"
    And user "signer1" exists
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"test"} |
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"admin"}]},{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | status | 0 |
      | name | Visible elements validation |
    Then the response should have a status code 200
    And fetch field "(FILE_UUID)ocs.data.uuid" from previous JSON response
    When sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<FILE_UUID>"
    Then the response should have a status code 200
    And fetch field "(SIGN_REQUEST_ID)ocs.data.signers.0.signRequestId" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/file-element/<FILE_UUID>"
      | signRequestId | <SIGN_REQUEST_ID> |
      | type | signature |
      | coordinates | {"page":1,"llx":10,"lly":10,"urx":110,"ury":60} |
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<FILE_UUID>"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.visibleElements \| length | 1 |
      | (jq)(.ocs.data.signers[0].visibleElements == .ocs.data.visibleElements) | true |
      | (jq).ocs.data.signers[1].visibleElements | [] |

  Scenario: Envelope validation keeps visible elements scoped to each document signer
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"test"} |
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | files | [{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"First.pdf"},{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Second.pdf"}] |
      | signers | [{"identifyMethods":[{"method":"account","value":"admin"}]}] |
      | status | 0 |
      | name | Envelope visible elements |
    Then the response should have a status code 200
    And fetch field "(ENVELOPE_UUID)ocs.data.uuid" from previous JSON response
    When sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<ENVELOPE_UUID>"
    Then the response should have a status code 200
    And fetch field "(CHILD_UUID)ocs.data.files.0.uuid" from previous JSON response
    And fetch field "(SIGN_REQUEST_ID)ocs.data.files.0.signers.0.signRequestId" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/file-element/<CHILD_UUID>"
      | signRequestId | <SIGN_REQUEST_ID> |
      | type | signature |
      | coordinates | {"page":1,"llx":10,"lly":10,"urx":110,"ury":60} |
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<ENVELOPE_UUID>"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.visibleElements \| length | 1 |
      | (jq).ocs.data.files[0].visibleElements \| length | 1 |
      | (jq)(.ocs.data.files[0].signers[0].visibleElements == .ocs.data.files[0].visibleElements) | true |
      | (jq).ocs.data.files[1].visibleElements | [] |
      | (jq).ocs.data.files[1].signers[0].visibleElements | [] |

  Scenario: Sign with account, delete the account and validate
    Given as user "admin"
    And run the command "config:app:set libresign signing_mode --value=sync --type=string" with result code 0
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
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | Document Name |
    Then the response should have a status code 200
    And as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should be a JSON array with the following mandatory values
      | key                        | value         |
      | (jq).ocs.data.data[0].name | Document Name |
    And fetch field "(SIGN_REQUEST_UUID)ocs.data.data.0.signers.0.sign_request_uuid" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_REQUEST_UUID>"
      | key    | value       |
      | method | clickToSign |
    Then the response should have a status code 200
    When run the command "user:delete signer1" with result code 0
    And as user ""
    And sending "get" to ocs "/apps/libresign/api/v1/file/validate/uuid/<FILE_UUID>"
    Then the response should have a status code 200
    Then the response should be a JSON array with the following mandatory values
      | key                                           | value                                                                                    |
      | (jq).ocs.data.signers[0].me                   | false                                                                                    |
      | (jq).ocs.data.signers[0].identifyMethods      | [{"method": "account","value": "signer1","mandatory": 1}]                                |
      | (jq).ocs.data.signers[0]                      | (jq).name \|test("/C=BR")                                                                |
      | (jq).ocs.data.signers[0]                      | (jq).name \|test("/ST=State of Company")                                                 |
      | (jq).ocs.data.signers[0]                      | (jq).name \|test("/L=City Name")                                                         |
      | (jq).ocs.data.signers[0]                      | (jq).name \|test("/O=Organization")                                                      |
      | (jq).ocs.data.signers[0]                      | (jq).name \|test("/OU=Organization Unit, libresign-ca-id:[a-z0-9]+_g:[0-9]+_e:[oc]?") |
      | (jq).ocs.data.signers[0]                      | (jq).name \|test("/CN=signer1-displayname")                                              |
      | (jq).ocs.data.signers[0].subject.CN           | signer1-displayname                                                                      |
      | (jq).ocs.data.signers[0].subject.C            | BR                                                                                       |
      | (jq).ocs.data.signers[0].subject.ST           | State of Company                                                                         |
      | (jq).ocs.data.signers[0].subject.L            | City Name                                                                                |
      | (jq).ocs.data.signers[0].subject.O            | Organization                                                                             |
      | (jq).ocs.data.signers[0].signature_validation | {"id":1,"label":"Signature is valid.","isValid":true}                                    |
      | (jq).ocs.data.signers[0].signatureTypeSN      | RSA-SHA256                                                                               |
      | (jq)(.ocs.data.signers[0].valid_from \| test("^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}[+-][0-9]{2}:[0-9]{2}$")) | true   |
      | (jq)(.ocs.data.signers[0].valid_to \| test("^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}[+-][0-9]{2}:[0-9]{2}$"))   | true   |

  Scenario Outline: Unauthenticated user can fetch the validation ednpoint
    Given as user "admin"
    And sending "delete" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/make_validation_url_private"

    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"admin"}]}] |
      | name | document |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    When sending "<method>" to ocs "<url>"
    And the response should have a status code <statusCode>

    Examples:
      | url                                                   | method | statusCode |
      | /apps/libresign/api/v1/file/validate/uuid/<FILE_UUID> | get    | 200        |
      | /apps/libresign/api/v1/file/validate/file_id/171      | get    | 404        |
      | /apps/libresign/api/v1/file/validate/                 | post   | 404        |

  Scenario Outline: Unauthenticated user can not fetch the validation ednpoint
    Given as user "admin"
    Given sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/make_validation_url_private"
      | value | true |
    And as user ""
    When sending "<method>" to ocs "<url>"
    Then the response should be a JSON array with the following mandatory values
      | key                    | value                                     |
      | (jq).ocs.data.errors   | ["You are not logged in. Please log in."] |
      | (jq).ocs.data.action   | 1000                                      |
      | (jq).ocs.data.redirect | /index.php/login?redirect_url=<url>       |
    And the response should have a status code 401

    Examples:
      | url                                                                            | method |
      | /apps/libresign/api/v1/file/validate/uuid/fakeuuid-6037-47be-9d9e-3d90b9d0a3ea | get    |
      | /apps/libresign/api/v1/file/validate/file_id/171                               | get    |
      | /apps/libresign/api/v1/file/validate/                                          | post   |
