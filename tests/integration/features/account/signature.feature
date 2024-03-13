Feature: account/signature
  Scenario: Create root certificate using API
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"Common Name"} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/admin/certificate"
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key        | value                                   |
      | rootCert   | {"commonName":"Common Name","names":[]} |
      | generated  | true                                    |

  Scenario: Create pfx with success with CFSSL
    Given user "signer1" exists
    And set the email of user "signer1" to "signer@domain.test"
    And as user "signer1"
    And run the command "config:app:set libresign certificate_engine --value cfssl"
    And run the command "libresign:install --cfssl"
    And run the command "libresign:configure:cfssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company"
    And sending "post" to ocs "/apps/libresign/api/v1/account/signature"
      | signPassword | password |
    And the response should have a status code 200
    When sending "Post" to ocs "/apps/libresign/api/v1/account/pfx/read"
      | key      | value    |
      | password | password |
    Then the response should be a JSON array with the following mandatory values
      | key              | value |
      | name             | /C=BR/ST=State of Company/O=Organization/CN=signer1-displayname |
      | subject          | {"CN": "signer1-displayname","C": "BR","ST": "State of Company","O": "Organization"} |
      | subjectAltName   | DNS:signer1 |
      | issuer           | {"CN": "Common Name","C": "BR","ST": "State of Company","O": "Organization"} |
      | issuerInfoAccess | |

  Scenario: Create pfx with success with OpenSSL
    Given user "signer1" exists
    And set the email of user "signer1" to "signer@domain.test"
    And as user "signer1"
    And run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company"
    And sending "post" to ocs "/apps/libresign/api/v1/account/signature"
      | signPassword | password |
    And the response should have a status code 200
    When sending "Post" to ocs "/apps/libresign/api/v1/account/pfx/read"
      | key      | value    |
      | password | password |
    Then the response should be a JSON array with the following mandatory values
      | key              | value |
      | name             | /CN=Common Name/O=Organization/C=BR/ST=State of Company |
      | subject          | {"CN": "Common Name","C": "BR","ST": "State of Company","O": "Organization"} |
      | subjectAltName   | |
      | issuer           | {"CN": "Common Name","C": "BR","ST": "State of Company","O": "Organization"} |
      | issuerInfoAccess | |

  Scenario: Upload PFX file with error
    Given run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company"
    And user "signer1" exists
    And as user "signer1"
    When sending "post" to ocs "/apps/libresign/api/v1/account/pfx"
    Then the response should have a status code 400
    And the response should be a JSON array with the following mandatory values
      | key     | value                        |
      | message | No certificate file provided |

  Scenario: Change pfx password with success
    Given run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company"
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
    Given run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company"
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
