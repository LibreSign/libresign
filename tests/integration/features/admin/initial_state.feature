Feature: admin/initial_state
  Scenario: Validate default initial state
    Given as user "admin"
    And sending "delete" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
    When sending "get" to "/settings/admin/libresign"
    Then the response should contain the initial state "libresign-identify_methods" with the following values:
      """
      [
        {"name":"account","enabled":true,"mandatory":true,"can_create_account":true,"signature_method":"password","allowed_signature_methods":["password"],"can_be_used":true},
        {"name":"email","enabled":true,"mandatory":true,"can_be_used":true,"test_url":"/index.php/settings/admin/mailtest"}
      ]
      """

  Scenario: Update identify methods and retrieve with success as initial state
    Given as user "admin"
    When sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | [{"name":"account","enabled":true,"mandatory":true,"can_create_account":false,"signature_method":"password","allowed_signature_methods":["password"],"can_be_used":true},{"name":"email","enabled":false,"mandatory":false,"can_be_used":false}] |
    Then sending "get" to "/settings/admin/libresign"
    And the response should contain the initial state "libresign-identify_methods" with the following values:
      """
      [
        {"name":"account","enabled":true,"mandatory":true,"can_create_account":false,"signature_method":"password","allowed_signature_methods":["password"],"can_be_used":true},
        {"name":"email","enabled":false,"mandatory":false,"can_be_used":false,"test_url":"/index.php/settings/admin/mailtest"}
      ]
      """
