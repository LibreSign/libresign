Feature: page/validate

  Scenario: Unauthenticated user can see sign page
    Given sending "delete" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/make_validation_url_private"
    When sending "get" to "/apps/libresign/p/validation"
    Then 
    # When sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
    #   | value | (string)[{"name":"email","test_url":"immutable"}] |
