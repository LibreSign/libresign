Feature: search
  Background: Create users
    Given user "search-signer1" exists
    Given user "search-signer2" exists

  Scenario: Search account by specific user
    Given as user "admin"
    When sending "get" to ocs "/apps/libresign/api/v1/identify-account/search?search=search-signer1"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key      | value             |
      | ocs | {"meta":{"status":"ok","statuscode":200,"message":"OK"},"data":[{"id":"search-signer1","isNoUser":false,"displayName":"search-signer1-displayname","subname":"search-signer1","icon":"icon-user"}]} |

  Scenario: Search account by multiple users
    Given as user "admin"
    When sending "get" to ocs "/apps/libresign/api/v1/identify-account/search?search=search-signer"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key      | value             |
      | ocs | {"meta":{"status":"ok","statuscode":200,"message":"OK"},"data":[{"id":"search-signer1","isNoUser":false,"displayName":"search-signer1-displayname","subname":"search-signer1","icon":"icon-user"},{"id":"search-signer2","isNoUser":false,"displayName":"search-signer2-displayname","subname":"search-signer2","icon":"icon-user"}]} |


  Scenario: Search account by herself with partial name search
    Given as user "admin"
    And set the email of user "admin" to "admin@email.tld"
    When sending "get" to ocs "/apps/libresign/api/v1/identify-account/search?search=adm"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key      | value             |
      | ocs | {"meta":{"status":"ok","statuscode":200,"message":"OK"},"data":[{"id":"admin","isNoUser":false,"displayName":"admin","subname":"admin@email.tld","icon":"icon-user"}]} |

  Scenario: Search account by herself with full name search
    Given as user "admin"
    And set the email of user "admin" to "admin@email.tld"
    When sending "get" to ocs "/apps/libresign/api/v1/identify-account/search?search=admin"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key      | value             |
      | ocs | {"meta":{"status":"ok","statuscode":200,"message":"OK"},"data":[{"id":"admin","isNoUser":false,"displayName":"admin","subname":"admin@email.tld","icon":"icon-user"}]} |
