Feature: file-thumbnail
  Scenario Outline: Thumbnail endpoint access by file_id should enforce signer authorization
    Given as user "admin"
    And user "signer1" exists
    And user "nonsigner" exists
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"mandatory":true}] |
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | document |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And as user "<user>"
    # x=0 triggers a deterministic 400 in controller for authorized users,
    # while unauthorized users are blocked earlier by middleware with 403.
    When sending "get" to ocs "/apps/libresign/api/v1/file/thumbnail/file_id/<FILE_ID>?x=0"
    Then the response should have a status code <statusCode>

    Examples:
      | user      | statusCode |
      | admin     | 400        |
      | signer1   | 400        |
      | nonsigner | 403        |
