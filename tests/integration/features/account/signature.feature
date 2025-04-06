Feature: account/signature
  Scenario: Create root certificate with OpenSSL engine using API
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"Common Name","names":{"C":{"id":"C","value":"BR"},"ST":{"id":"ST","value":"State of Company"},"L":{"id":"L","value":"City name"},"O":{"id":"O","value":"Organization"},"OU":{"id":"OU","value":"Organizational Unit"}}} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/admin/certificate"
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                  | value                                     |
      | (jq).ocs.data.rootCert.commonName    | Common Name                               |
      | (jq).ocs.data.rootCert.names\|length | 5                                         |
      | (jq).ocs.data.rootCert.names[0]      | {"id":"C","value":"BR"}                   |
      | (jq).ocs.data.rootCert.names[1]      | {"id":"ST","value":"State of Company"}    |
      | (jq).ocs.data.rootCert.names[2]      | {"id":"L","value":"City name"}            |
      | (jq).ocs.data.rootCert.names[3]      | {"id":"O","value":"Organization"}         |
      | (jq).ocs.data.rootCert.names[4]      | {"id":"OU","value":"Organizational Unit"} |
      | (jq).ocs.data.generated              | true                                      |

  Scenario: Create root certificate with CFSSL engine using API
    Given as user "admin"
    And run the command "config:app:set libresign certificate_engine --value=cfssl" with result code 0
    And run the command "libresign:install --use-local-cert --cfssl" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/cfssl"
      | rootCert | {"commonName":"Common Name","names":{"C":{"id":"C","value":"BR"},"ST":{"id":"ST","value":"State of Company"},"L":{"id":"L","value":"City name"},"O":{"id":"O","value":"Organization"},"OU":{"id":"OU","value":"Organizational Unit"}}} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/admin/certificate"
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                  | value                                     |
      | (jq).ocs.data.rootCert.commonName    | Common Name                               |
      | (jq).ocs.data.rootCert.names\|length | 5                                         |
      | (jq).ocs.data.rootCert.names[0]      | {"id":"C","value":"BR"}                   |
      | (jq).ocs.data.rootCert.names[1]      | {"id":"ST","value":"State of Company"}    |
      | (jq).ocs.data.rootCert.names[2]      | {"id":"L","value":"City name"}            |
      | (jq).ocs.data.rootCert.names[3]      | {"id":"O","value":"Organization"}         |
      | (jq).ocs.data.rootCert.names[4]      | {"id":"OU","value":"Organizational Unit"} |
      | (jq).ocs.data.generated              | true                                      |

  Scenario: Create pfx with success using CFSSL
    Given user "signer1" exists
    And as user "signer1"
    And set the email of user "signer1" to "signer@domain.test"
    And run the command "config:app:set libresign certificate_engine --value=cfssl" with result code 0
    And run the command "libresign:install --use-local-cert --cfssl" with result code 0
    And run the command "libresign:configure:cfssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company --l=City\ Name --ou=Organization\ Unit" with result code 0
    When sending "delete" to ocs "/apps/libresign/api/v1/account/pfx"
    Then the response should have a status code 202
    When sending "post" to ocs "/apps/libresign/api/v1/account/pfx/read"
      | key      | value   |
      | password | invalid |
    Then the response should be a JSON array with the following mandatory values
      | key                   | value                                                    |
      | (jq).ocs.data.message | Password to sign not defined. Create a password to sign. |
    And sending "post" to ocs "/apps/libresign/api/v1/account/signature"
      | signPassword | password |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/account/pfx/read"
      | key      | value   |
      | password | invalid |
    And the response should have a status code 400
    Then the response should be a JSON array with the following mandatory values
      | key                   | value                    |
      | (jq).ocs.data.message | Invalid user or password |
    When sending "post" to ocs "/apps/libresign/api/v1/account/pfx/read"
      | key      | value    |
      | password | password |
    Then the response should be a JSON array with the following mandatory values
      | key                                            | value                                                 |
      | (jq).ocs.data.name                             | /C=BR/ST=State of Company/L=City Name/O=Organization/OU=Organization Unit/CN=account:signer1, signer1-displayname |
      | (jq).ocs.data.issuer\|length                   | 6                                                     |
      | (jq).ocs.data.issuer.CN                        | Common Name                                           |
      | (jq).ocs.data.issuer.C                         | BR                                                    |
      | (jq).ocs.data.issuer.ST                        | State of Company                                      |
      | (jq).ocs.data.issuer.L                         | City Name                                             |
      | (jq).ocs.data.issuer.O                         | Organization                                          |
      | (jq).ocs.data.issuer.OU                        | Organization Unit                                     |
      | (jq).ocs.data.subject\|length                  | 6                                                     |
      | (jq).ocs.data.subject.CN                       | account:signer1, signer1-displayname                  |
      | (jq).ocs.data.subject.C                        | BR                                                    |
      | (jq).ocs.data.subject.ST                       | State of Company                                      |
      | (jq).ocs.data.subject.L                        | City Name                                             |
      | (jq).ocs.data.subject.O                        | Organization                                          |
      | (jq).ocs.data.subject.OU                       | Organization Unit                                     |
      | (jq).ocs.data.extensions.basicConstraints      | CA:FALSE                                              |
      | (jq).ocs.data.extensions.subjectAltName        | email:signer@domain.test                              |
      | (jq).ocs.data.extensions.keyUsage              | Digital Signature, Key Encipherment, Certificate Sign |
      | (jq).ocs.data.extensions.extendedKeyUsage      | TLS Web Client Authentication, E-mail Protection      |
      | (jq).ocs.data.extensions                       | (jq).authorityKeyIdentifier \| test("([0-9A-F]{2}:)+[0-9A-F]{2}") |
      | (jq).ocs.data.extensions                       | (jq).subjectKeyIdentifier != ""                       |

  Scenario: Create pfx with success using OpenSSL
    Given user "signer1" exists
    And as user "signer1"
    And set the email of user "signer1" to "signer@domain.test"
    And run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company --l=City\ Name --ou=Organization\ Unit" with result code 0
    When sending "delete" to ocs "/apps/libresign/api/v1/account/pfx"
    Then the response should have a status code 202
    When sending "post" to ocs "/apps/libresign/api/v1/account/pfx/read"
      | key      | value   |
      | password | invalid |
    And the response should have a status code 400
    Then the response should be a JSON array with the following mandatory values
      | key                   | value                                                    |
      | (jq).ocs.data.message | Password to sign not defined. Create a password to sign. |
    And sending "post" to ocs "/apps/libresign/api/v1/account/signature"
      | signPassword | password |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/account/pfx/read"
      | key      | value   |
      | password | invalid |
    And the response should have a status code 400
    Then the response should be a JSON array with the following mandatory values
      | key                   | value                    |
      | (jq).ocs.data.message | Invalid user or password |
    When sending "post" to ocs "/apps/libresign/api/v1/account/pfx/read"
      | key      | value    |
      | password | password |
    Then the response should be a JSON array with the following mandatory values
      | key                                            | value                                                 |
      | (jq).ocs.data.name                             | /C=BR/ST=State of Company/L=City Name/O=Organization/OU=Organization Unit/UID=account:signer1/CN=signer1-displayname |
      | (jq).ocs.data.issuer\|length                   | 6                                                     |
      | (jq).ocs.data.issuer.CN                        | Common Name                                           |
      | (jq).ocs.data.issuer.C                         | BR                                                    |
      | (jq).ocs.data.issuer.ST                        | State of Company                                      |
      | (jq).ocs.data.issuer.L                         | City Name                                             |
      | (jq).ocs.data.issuer.O                         | Organization                                          |
      | (jq).ocs.data.issuer.OU                        | Organization Unit                                     |
      | (jq).ocs.data.subject\|length                  | 7                                                     |
      | (jq).ocs.data.subject.CN                       | signer1-displayname                                   |
      | (jq).ocs.data.subject.C                        | BR                                                    |
      | (jq).ocs.data.subject.ST                       | State of Company                                      |
      | (jq).ocs.data.subject.L                        | City Name                                             |
      | (jq).ocs.data.subject.O                        | Organization                                          |
      | (jq).ocs.data.subject.OU                       | Organization Unit                                     |
      | (jq).ocs.data.subject.UID                      | account:signer1                                       |
      | (jq).ocs.data.extensions.basicConstraints      | CA:FALSE                                              |
      | (jq).ocs.data.extensions.subjectAltName        | email:signer@domain.test                              |
      | (jq).ocs.data.extensions.keyUsage              | Digital Signature, Key Encipherment, Certificate Sign |
      | (jq).ocs.data.extensions.extendedKeyUsage      | TLS Web Client Authentication, E-mail Protection      |
      | (jq).ocs.data.extensions                       | (jq).authorityKeyIdentifier \| test("([0-9A-F]{2}:)+[0-9A-F]{2}") |
      | (jq).ocs.data.extensions                       | (jq).subjectKeyIdentifier != ""                       |

  Scenario: Upload PFX file with error
    Given run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company --l=City\ Name" with result code 0
    And user "signer1" exists
    And as user "signer1"
    When sending "post" to ocs "/apps/libresign/api/v1/account/pfx"
    Then the response should have a status code 400
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                  |
      | (jq).ocs.data.message | No certificate file provided |

  Scenario: Change pfx password with success
    Given run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company --l=City\ Name" with result code 0
    And user "signer1" exists
    And as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/account/signature"
      | signPassword | password |
    Then the response should have a status code 200
    Given sending "patch" to ocs "/apps/libresign/api/v1/account/pfx"
      | current | password |
      | new | new |
    Then the response should have a status code 202
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                  |
      | (jq).ocs.data.message | New password to sign documents has been created |
    Given sending "patch" to ocs "/apps/libresign/api/v1/account/pfx"
      | current | new |
      | new | anotherpassword |
    Then the response should have a status code 202
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                  |
      | (jq).ocs.data.message | New password to sign documents has been created |

  Scenario: Delete pfx password with success
    Given run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company --l=City\ Name" with result code 0
    And user "signer1" exists
    And as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/account/signature"
      | signPassword | password |
    Then the response should have a status code 200
    Given sending "delete" to ocs "/apps/libresign/api/v1/account/pfx"
    Then the response should have a status code 202
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                  |
      | (jq).ocs.data.message | Certificate file deleted with success. |

  Scenario: Create password to guest
    Given guest "guest@test.coop" exists
    And run the command "config:app:set guests whitelist --value=libresign" with result code 0
    And as user "guest@test.coop"
    And sending "post" to ocs "/apps/libresign/api/v1/account/signature"
      | signPassword | password |
    Then the response should have a status code 200

  Scenario: CRUD of signature element to guest
    Given guest "guest@test.coop" exists
    And run the command "config:app:set guests whitelist --value=libresign" with result code 0
    And as user "guest@test.coop"
    When sending "post" to ocs "/apps/libresign/api/v1/signature/elements"
      | elements | [{"type":"signature","file":{"base64":"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII="}}] |
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/signature/elements"
    Then the response should be a JSON array with the following mandatory values
      | key      | value                         |
      | (jq).ocs.data.elements\|length | 1         |
      | (jq).ocs.data.elements[0].type | signature |
    And fetch field "(NODE_ID)ocs.data.elements.0.file.nodeId" from prevous JSON response
    When sending "delete" to ocs "/apps/libresign/api/v1/signature/elements/<NODE_ID>"
    Then the response should have a status code 200

  Scenario: CRUD of signature element to signer by email without account
    Given run the command "config:app:set guests whitelist --value=libresign" with result code 0
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And as user "admin"
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"mandatory":true,"can_create_account":false}] |
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"signer@test.coop"}}] |
      | name | document |
    When as user ""
    And I open the latest email to "signer@test.coop" with subject "LibreSign: There is a file for you to sign"
    And I fetch the signer UUID from opened email
    And set the custom http header "LibreSign-sign-request-uuid" with "<SIGN_UUID>" as value to next request
    When sending "post" to ocs "/apps/libresign/api/v1/signature/elements"
      | elements | [{"type":"signature","file":{"base64":"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII="}}] |
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/signature/elements"
    Then the response should be a JSON array with the following mandatory values
      | key                            | value     |
      | (jq).ocs.data.elements\|length | 1         |
      | (jq).ocs.data.elements[0].type | signature |
    And fetch field "(NODE_ID)ocs.data.elements.0.file.nodeId" from prevous JSON response
    When sending "delete" to ocs "/apps/libresign/api/v1/signature/elements/<NODE_ID>"
    Then the response should have a status code 200
