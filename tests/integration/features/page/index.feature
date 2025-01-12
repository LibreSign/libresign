Feature: page/sign_identify_default
  Background: Make setup ok
    Given run the command "config:app:set libresign authkey --value=dummy" with result code 0

  Scenario: Open sign file with all data valid
    Given as user "admin"
    And sending "delete" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
    When sending "get" to "/apps/libresign/f/"
    Then the response should have a status code 200
    And the response should contain the initial state "libresign-identify_methods" with the following values:
      """
      [
        {"name":"account","friendly_name":"Account","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":false,"label":"Click to sign","name":"clickToSign"},"emailToken":{"enabled":false,"label":"Email token","name":"emailToken"},"password":{"enabled":true,"label":"Certificate with password","name":"password"}}},
        {"name":"email","friendly_name":"Email","enabled":false,"mandatory":true,"can_create_account":true,"test_url":"/index.php/settings/admin/mailtest","signatureMethods":{"clickToSign":{"enabled":false,"label":"Click to sign","name":"clickToSign"},"emailToken":{"enabled":true,"label":"Email token","name":"emailToken"}}}
      ]
      """
