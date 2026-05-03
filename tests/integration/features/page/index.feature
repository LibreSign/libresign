Feature: page/sign_identify_default
  Background: Make setup ok
    Given run the command "libresign:configure:openssl --cn test" with result code 0

  Scenario: Open sign file with all data valid
    Given as user "admin"
    And sending "delete" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
    When sending "get" to "/apps/libresign/f/"
    Then the response should have a status code 200
    And the response should contain the initial state "libresign-effective_policies" json that match with:
      | key                                         | value            |
      | (jq).policies.identify_methods.policyKey   | identify_methods |
