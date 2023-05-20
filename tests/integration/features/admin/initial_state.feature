Feature: admin/initial_state
  Scenario: Validate initial state
    Given as user "admin"
    When sending "get" to "/settings/admin/libresign"
    Then the response should contain the initial state "libresign-identify_methods" with the following values:
      """
      [
        {"name":"account","enabled":true,"mandatory":true,"can_create_account":true,"signature_method":"password","allowed_signature_methods":["password"],"can_be_used":true},
        {"name":"email","enabled":true,"mandatory":true,"can_be_used":true,"test_url":"/index.php/settings/admin/mailtest"}
      ]
      """
