Feature: account/signature
  Background: Create users
    Given user "signer1" exists
    And set the email of user "signer1" to "signer@domain.test"
    And as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"Common Name"} |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/admin/certificate"
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key        | value                                   |
      | rootCert   | {"commonName":"Common Name","names":[]} |
      | generated  | true                                    |

  Scenario: Create pfx with success
    Given as user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/account/signature"
      | signPassword | password |
    Then the response should have a status code 200
