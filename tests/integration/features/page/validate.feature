Feature: page/validate
  Background: Make setup ok
    Given run the command "config:app:set libresign authkey --value=dummy" with result code 0
    And run the command "libresign:configure:openssl --cn test" with result code 0

  Scenario: Unauthenticated user can see sign page
    Given as user "admin"
    And sending "delete" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/make_validation_url_private"
    And as user ""
    When sending "get" to "/apps/libresign/p/validation"
    And the response should have a status code 200

  Scenario: Unauthenticated user can not see sign page
    Given as user "admin"
    Given sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/make_validation_url_private"
      | value | true |
    And as user ""
    When sending "get" to "/apps/libresign/p/validation"
    And the response should be a JSON array with the following mandatory values
      | key      | value                                                                |
      | errors   | ["You are not logged in. Please log in."]                            |
      | action   | 1000                                                                 |
      | redirect | /index.php/login?redirect_url=/index.php/apps/libresign/p/validation |
