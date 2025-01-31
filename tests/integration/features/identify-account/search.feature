Feature: search
  Background: Create users
    Given user "search-signer1" exists
    Given user "search-signer2" exists

  Scenario: Search account by specific user
    Given as user "admin"
    When sending "get" to ocs "/apps/libresign/api/v1/identify-account/search?search=search-signer1"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                          | value                      |
      | (jq).ocs.data\|length        | 1                          |
      | (jq).ocs.data[0].id          | search-signer1             |
      | (jq).ocs.data[0].isNoUser    | false                      |
      | (jq).ocs.data[0].displayName | search-signer1-displayname |
      | (jq).ocs.data[0].subname     | search-signer1             |
      | (jq).ocs.data[0].icon        | icon-user                  |
      | (jq).ocs.data[0].shareType   | 0                          |

  Scenario: Search account by multiple users
    Given as user "admin"
    When sending "get" to ocs "/apps/libresign/api/v1/identify-account/search?search=search-signer"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                          | value                      |
      | (jq).ocs.data\|length        | 2                          |
      | (jq).ocs.data[0].id          | search-signer1             |
      | (jq).ocs.data[0].isNoUser    | false                      |
      | (jq).ocs.data[0].displayName | search-signer1-displayname |
      | (jq).ocs.data[0].subname     | search-signer1             |
      | (jq).ocs.data[0].icon        | icon-user                  |
      | (jq).ocs.data[0].shareType   | 0                          |
      | (jq).ocs.data[1].id          | search-signer2             |
      | (jq).ocs.data[1].isNoUser    | false                      |
      | (jq).ocs.data[1].displayName | search-signer2-displayname |
      | (jq).ocs.data[1].subname     | search-signer2             |
      | (jq).ocs.data[1].icon        | icon-user                  |
      | (jq).ocs.data[1].shareType   | 0                          |


  Scenario: Search account by herself with partial name search
    Given as user "admin"
    And set the email of user "admin" to "admin@email.tld"
    When sending "get" to ocs "/apps/libresign/api/v1/identify-account/search?search=adm"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                          | value           |
      | (jq).ocs.data\|length        | 1               |
      | (jq).ocs.data[0].id          | admin           |
      | (jq).ocs.data[0].isNoUser    | false           |
      | (jq).ocs.data[0].displayName | admin           |
      | (jq).ocs.data[0].subname     | admin@email.tld |
      | (jq).ocs.data[0].icon        | icon-user       |
      | (jq).ocs.data[0].shareType   | 0               |

  Scenario: Search account by herself without permission to identify by account
    Given as user "admin"
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","enabled":true}] |
    And set the display name of user "admin" to "Temporary Name"
    And set the email of user "admin" to "my@email.tld"
    And sending "get" to ocs "/apps/libresign/api/v1/identify-account/search?search=admin"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                   | value |
      | (jq).ocs.data\|length | 0     |
    And set the email of user "admin" to "admin@email.tld"
    And set the display name of user "admin" to "admin"

  Scenario: Search account by herself with permission to identify by account
    Given as user "admin"
    And set the email of user "admin" to "admin@email.tld"
    And set the display name of user "admin" to "admin"
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","enabled":true}] |
    When sending "get" to ocs "/apps/libresign/api/v1/identify-account/search?search=admin"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                          | value           |
      | (jq).ocs.data\|length        | 1               |
      | (jq).ocs.data[0].id          | admin           |
      | (jq).ocs.data[0].isNoUser    | false           |
      | (jq).ocs.data[0].displayName | admin           |
      | (jq).ocs.data[0].subname     | admin@email.tld |
      | (jq).ocs.data[0].icon        | icon-user       |
      | (jq).ocs.data[0].shareType   | 0               |

  Scenario: Search account by herself without permission to identify by email
    Given as user "admin"
    And set the email of user "admin" to "admin@email.tld"
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","enabled":true}] |
    When sending "get" to ocs "/apps/libresign/api/v1/identify-account/search?search=admin@email.tld"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                   | value |
      | (jq).ocs.data\|length | 0     |

  Scenario: Search account by herself with permission to identify by email
    Given as user "admin"
    And set the email of user "admin" to "admin@email.tld"
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","enabled":true}] |
    When sending "get" to ocs "/apps/libresign/api/v1/identify-account/search?search=admin@email.tld"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                          | value           |
      | (jq).ocs.data\|length        | 1               |
      | (jq).ocs.data[0].id          | admin@email.tld |
      | (jq).ocs.data[0].isNoUser    | true            |
      | (jq).ocs.data[0].displayName | admin           |
      | (jq).ocs.data[0].subname     | admin@email.tld |
      | (jq).ocs.data[0].icon        | icon-mail       |
      | (jq).ocs.data[0].shareType   | 4               |
