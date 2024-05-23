Feature: admin/initial_state
  Scenario: Validate default initial state
    Given as user "admin"
    And sending "delete" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
    When sending "get" to "/settings/admin/libresign"
    Then the response should contain the initial state "libresign-identify_methods" with the following values:
      """
      [
        {"name":"account","friendly_name":"Account","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":false,"label":"Click to sign","name":"clickToSign"},"emailToken":{"enabled":false,"label":"Email token","name":"emailToken"},"password":{"enabled":true,"label":"Certificate with password","name":"password"}}},
        {"name":"email","friendly_name":"Email","enabled":false,"mandatory":true,"can_create_account":true,"test_url":"/index.php/settings/admin/mailtest","signatureMethods":{"clickToSign":{"enabled":false,"label":"Click to sign","name":"clickToSign"},"emailToken":{"enabled":true,"label":"Email token","name":"emailToken"}}}
      ]
      """

  Scenario: Update identify methods with string
    Given as user "admin"
    And sending "delete" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
    When sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | "invalid" |
    Then sending "get" to "/settings/admin/libresign"
    And the response should contain the initial state "libresign-identify_methods" with the following values:
      """
      [
        {"name":"account","friendly_name":"Account","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":false,"label":"Click to sign","name":"clickToSign"},"emailToken":{"enabled":false,"label":"Email token","name":"emailToken"},"password":{"enabled":true,"label":"Certificate with password","name":"password"}}},
        {"name":"email","friendly_name":"Email","enabled":false,"mandatory":true,"can_create_account":true,"test_url":"/index.php/settings/admin/mailtest","signatureMethods":{"clickToSign":{"enabled":false,"label":"Click to sign","name":"clickToSign"},"emailToken":{"enabled":true,"label":"Email token","name":"emailToken"}}}
      ]
      """

  Scenario: Update identify methods with invalid keys
    Given as user "admin"
    And sending "delete" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
    When sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","fake":null}] |
    Then sending "get" to "/settings/admin/libresign"
    And the response should contain the initial state "libresign-identify_methods" with the following values:
      """
      [
        {"name":"account","friendly_name":"Account","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":false,"label":"Click to sign","name":"clickToSign"},"emailToken":{"enabled":false,"label":"Email token","name":"emailToken"},"password":{"enabled":true,"label":"Certificate with password","name":"password"}}},
        {"name":"email","friendly_name":"Email","enabled":false,"mandatory":true,"can_create_account":true,"test_url":"/index.php/settings/admin/mailtest","signatureMethods":{"clickToSign":{"enabled":false,"label":"Click to sign","name":"clickToSign"},"emailToken":{"enabled":true,"label":"Email token","name":"emailToken"}}}
      ]
      """

  Scenario: Update identify methods with inalid type as value
    Given as user "admin"
    And sending "delete" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
    When sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","enabled":"string"}] |
    Then sending "get" to "/settings/admin/libresign"
    And the response should contain the initial state "libresign-identify_methods" with the following values:
      """
      [
        {"name":"account","friendly_name":"Account","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":false,"label":"Click to sign","name":"clickToSign"},"emailToken":{"enabled":false,"label":"Email token","name":"emailToken"},"password":{"enabled":true,"label":"Certificate with password","name":"password"}}},
        {"name":"email","friendly_name":"Email","enabled":false,"mandatory":true,"can_create_account":true,"test_url":"/index.php/settings/admin/mailtest","signatureMethods":{"clickToSign":{"enabled":false,"label":"Click to sign","name":"clickToSign"},"emailToken":{"enabled":true,"label":"Email token","name":"emailToken"}}}
      ]
      """

  Scenario: Update identify methods with property that can't be changed
    Given as user "admin"
    And sending "delete" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
    When sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","test_url":"immutable"}] |
    Then sending "get" to "/settings/admin/libresign"
    And the response should contain the initial state "libresign-identify_methods" with the following values:
      """
      [
        {"name":"account","friendly_name":"Account","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":false,"label":"Click to sign","name":"clickToSign"},"emailToken":{"enabled":false,"label":"Email token","name":"emailToken"},"password":{"enabled":true,"label":"Certificate with password","name":"password"}}},
        {"name":"email","friendly_name":"Email","enabled":false,"mandatory":true,"can_create_account":true,"test_url":"/index.php/settings/admin/mailtest","signatureMethods":{"clickToSign":{"enabled":false,"label":"Click to sign","name":"clickToSign"},"emailToken":{"enabled":true,"label":"Email token","name":"emailToken"}}}
      ]
      """

  Scenario: Update identify methods and retrieve with success as initial state
    Given as user "admin"
    When sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":true}}},{"name":"email","enabled":false,"mandatory":false}] |
    Then sending "get" to "/settings/admin/libresign"
    And the response should contain the initial state "libresign-identify_methods" with the following values:
      """
      [
        {"name":"account","friendly_name":"Account","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":true,"label":"Click to sign","name":"clickToSign"},"emailToken":{"enabled":false,"label":"Email token","name":"emailToken"},"password":{"enabled":false,"label":"Certificate with password","name":"password"}}},
        {"name":"email","friendly_name":"Email","enabled":false,"mandatory":false,"can_create_account":true,"test_url":"/index.php/settings/admin/mailtest","signatureMethods":{"clickToSign":{"enabled":false,"label":"Click to sign","name":"clickToSign"},"emailToken":{"enabled":true,"label":"Email token","name":"emailToken"}}}
      ]
      """
