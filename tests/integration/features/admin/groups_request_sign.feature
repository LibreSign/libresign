Feature: admin/groups_request_sign

  Scenario: Save ASCII group IDs via the endpoint
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/admin/groups-request-sign/config"
      | groups | ["admin","editors"] |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                   | value          |
      | (jq).ocs.data.message | Settings saved |
    When sending "get" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/groups_request_sign"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                          | value               |
      | (jq).ocs.data.data\|fromjson | ["admin","editors"] |

  Scenario: Save a non-ASCII group ID via the endpoint
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/admin/groups-request-sign/config"
      | groups | ["admin","S\u00d6"] |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                   | value          |
      | (jq).ocs.data.message | Settings saved |
    When sending "get" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/groups_request_sign"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                          | value          |
      | (jq).ocs.data.data\|fromjson | ["admin","SÖ"] |
