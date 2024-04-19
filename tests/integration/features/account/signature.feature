Feature: account/signature
  Scenario: Create root certificate with OpenSSL engine using API
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"Common Name","names":{"C":{"id":"C","value":"BR"},"ST":{"id":"ST","value":"State of Company"},"L":{"id":"L","value":"City name"},"O":{"id":"O","value":"Organization"},"OU":{"id":"OU","value":"Organizational Unit"}}} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/admin/certificate"
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                        | value                                   |
      | (jq).rootCert.commonName   | Common Name |
      | (jq).rootCert.names        | [{"id":"C","value":"BR"},{"id":"ST","value":"State of Company"},{"id":"L","value":"City name"},{"id":"O","value":"Organization"},{"id":"OU","value":"Organizational Unit"}] |
      | generated                  | true                                    |

  Scenario: Create root certificate with CFSSL engine using API
    Given as user "admin"
    And run the command "config:app:set libresign certificate_engine --value cfssl" with result code 0
    And run the command "libresign:install --cfssl" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/cfssl"
      | rootCert | {"commonName":"Common Name","names":{"C":{"id":"C","value":"BR"},"ST":{"id":"ST","value":"State of Company"},"L":{"id":"L","value":"City name"},"O":{"id":"O","value":"Organization"},"OU":{"id":"OU","value":"Organizational Unit"}}} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/admin/certificate"
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                        | value                                   |
      | (jq).rootCert.commonName   | Common Name |
      | (jq).rootCert.names        | [{"id":"C","value":"BR"},{"id":"ST","value":"State of Company"},{"id":"L","value":"City name"},{"id":"O","value":"Organization"},{"id":"OU","value":"Organizational Unit"}] |
      | generated                  | true                                    |

  Scenario: Create pfx with success using CFSSL
    Given user "signer1" exists
    And set the email of user "signer1" to "signer@domain.test"
    And as user "signer1"
    And run the command "config:app:set libresign certificate_engine --value cfssl" with result code 0
    And run the command "libresign:install --cfssl" with result code 0
    And run the command "libresign:configure:cfssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company --l=City\ Name --ou=Organization\ Unit" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/account/signature"
      | signPassword | password |
    And the response should have a status code 200
    When sending "Post" to ocs "/apps/libresign/api/v1/account/pfx/read"
      | key      | value    |
      | password | password |
    Then the response should be a JSON array with the following mandatory values
      | key                              | value |
      | name                             | /C=BR/ST=State of Company/L=City Name/O=Organization/OU=Organization Unit/CN=signer1-displayname |
      | issuer                           | {"CN": "Common Name","C": "BR","ST": "State of Company","L":"City Name","O": "Organization","OU":"Organization Unit"} |
      | subject                          | {"CN": "signer1-displayname","C": "BR","ST": "State of Company","L":"City Name","O": "Organization","OU":"Organization Unit"} |
      | (jq).extensions.basicConstraints | CA:FALSE |
      | (jq).extensions.subjectAltName   | email:signer@domain.test |
      | (jq).extensions.keyUsage         | Digital Signature, Key Encipherment, Certificate Sign |
      | (jq).extensions.extendedKeyUsage | TLS Web Client Authentication, E-mail Protection      |
      | (jq).extensions | (jq).authorityKeyIdentifier \| test("([0-9A-F]{2}:)+[0-9A-F]{2}") |
      | (jq).extensions | (jq).subjectKeyIdentifier != "" |

  Scenario: Create pfx with success using OpenSSL
    Given user "signer1" exists
    And set the email of user "signer1" to "signer@domain.test"
    And as user "signer1"
    And run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company --l=City\ Name --ou=Organization\ Unit" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/account/signature"
      | signPassword | password |
    And the response should have a status code 200
    When sending "Post" to ocs "/apps/libresign/api/v1/account/pfx/read"
      | key      | value    |
      | password | password |
    Then the response should be a JSON array with the following mandatory values
      | key                              | value |
      | name                             | /C=BR/ST=State of Company/L=City Name/O=Organization/OU=Organization Unit/CN=signer1-displayname |
      | issuer                           | {"CN": "Common Name","C": "BR","ST": "State of Company","L":"City Name","O": "Organization","OU":"Organization Unit"} |
      | subject                          | {"CN": "signer1-displayname","C": "BR","ST": "State of Company","L":"City Name","O": "Organization","OU":"Organization Unit"} |
      | (jq).extensions.basicConstraints | CA:FALSE |
      | (jq).extensions.subjectAltName   | email:signer@domain.test |
      | (jq).extensions.keyUsage         | Digital Signature, Key Encipherment, Certificate Sign |
      | (jq).extensions.extendedKeyUsage | TLS Web Client Authentication, E-mail Protection      |
      | (jq).extensions | (jq).authorityKeyIdentifier \| test("([0-9A-F]{2}:)+[0-9A-F]{2}") |
      | (jq).extensions | (jq).subjectKeyIdentifier != "" |

  Scenario: Upload PFX file with error
    Given run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company --l=City\ Name" with result code 0
    And user "signer1" exists
    And as user "signer1"
    When sending "post" to ocs "/apps/libresign/api/v1/account/pfx"
    Then the response should have a status code 400
    And the response should be a JSON array with the following mandatory values
      | key     | value                        |
      | message | No certificate file provided |

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
      | key     | value                                           |
      | message | New password to sign documents has been created |
    Given sending "patch" to ocs "/apps/libresign/api/v1/account/pfx"
      | current | new |
      | new | anotherpassword |
    Then the response should have a status code 202
    And the response should be a JSON array with the following mandatory values
      | key     | value                                           |
      | message | New password to sign documents has been created |

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
      | key     | value                                  |
      | message | Certificate file deleted with success. |

  Scenario: Create password to guest
    Given guest "guest@test.coop" exists
    And run the command "config:app:set guests whitelist --value libresign" with result code 0
    And as user "guest@test.coop"
    And sending "post" to ocs "/apps/libresign/api/v1/account/signature"
      | signPassword | password |
    Then the response should have a status code 200

  Scenario: CRUD of signature element to guest
    Given guest "guest@test.coop" exists
    And run the command "config:app:set guests whitelist --value libresign" with result code 0
    And as user "guest@test.coop"
    When sending "post" to ocs "/apps/libresign/api/v1/signature/elements"
      | elements | [{"type":"signature","file":{"base64":"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII="}}] |
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/signature/elements"
    Then the response should be a JSON array with the following mandatory values
      | key      | value                         |
      | elements | (jq).[]\|.type == "signature" |
    And fetch field "(NODE_ID)elements.0.file.nodeId" from prevous JSON response
    When sending "delete" to ocs "/apps/libresign/api/v1/signature/elements/<NODE_ID>"
    Then the response should have a status code 200

  Scenario: CRUD of signature element to signer by email without account
    Given guest "guest@test.coop" exists
    And run the command "config:app:set guests whitelist --value libresign" with result code 0
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And as user "admin"
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"mandatory":true,"can_create_account":false}] |
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"guest@test.coop"}}] |
      | name | document |
    And as user ""
    When sending "post" to ocs "/apps/libresign/api/v1/signature/elements"
      | elements | [{"type":"signature","file":{"base64":"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII="}}] |
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/signature/elements"
    Then the response should be a JSON array with the following mandatory values
      | key      | value                         |
      | elements | (jq).[]\|.type == "signature" |
    And fetch field "(NODE_ID)elements.0.file.nodeId" from prevous JSON response
    When sending "delete" to ocs "/apps/libresign/api/v1/signature/elements/<NODE_ID>"
    Then the response should have a status code 200
