Feature: page/validate
  Background: Make setup ok
    Given run the command "config:app:set libresign authkey --value=dummy" with result code 0
    And run the command "libresign:configure:openssl --cn test" with result code 0

  Scenario Outline: Unauthenticated user can see sign page
    Given as user "admin"
    And sending "delete" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/make_validation_url_private"

    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"admin"}}] |
      | name | document |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    And fetch field "(SIGN_UUID)ocs.data.data.0.signers.0.sign_uuid" from prevous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from prevous JSON response
    When sending "get" to "<url>"
    And the response should have a status code 200

    Examples:
      | url                                    |
      | /apps/libresign/p/sign/<SIGN_UUID>     |
      | /apps/libresign/validation/<SIGN_UUID> |
      | /apps/libresign/p/validation           |
      | /apps/libresign/pdf/<SIGN_UUID>        |
      | /apps/libresign/p/pdf/<FILE_UUID>      |

  Scenario Outline: Unauthenticated user can not see sign page
    Given as user "admin"
    Given sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/make_validation_url_private"
      | value | true |
    And as user ""
    When sending "get" to "<url>"
    Then the response should be a JSON array with the following mandatory values
      | key      | value                                     |
      | errors   | ["You are not logged in. Please log in."] |
      | action   | 1000                                      |
      | redirect | /index.php/login?redirect_url=<url>       |

    Examples:
      | url                                                             |
      | /apps/libresign/p/sign/fakeuuid-6037-47be-9d9e-3d90b9d0a3ea     |
      | /apps/libresign/validation/fakeuuid-6037-47be-9d9e-3d90b9d0a3ea |
      | /apps/libresign/p/validation                                    |
      | /apps/libresign/pdf/fakeuuid-6037-47be-9d9e-3d90b9d0a3ea        |
      | /apps/libresign/p/pdf/fakeuuid-6037-47be-9d9e-3d90b9d0a3ea      |
