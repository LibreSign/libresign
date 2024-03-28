Feature: account/me
  Background: Create users
    Given user "signer1" exists

  Scenario: who a me with fail because need an authenticated user
    Given as user ""
    And sending "get" to ocs "/apps/libresign/api/v1/account/me"
    Then the response should have a status code 404

  Scenario: who a me with success
    Given as user "signer1"
    And set the email of user "signer1" to ""
    And sending "get" to ocs "/apps/libresign/api/v1/account/me"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key      | value                                                                             |
      | account  | {"uid":"signer1","emailAddress":"","displayName":"signer1-displayname"}           |
      | settings | {"canRequestSign":false,"hasSignatureFile":false} |
