Feature: envelope authorization
  Background:
    Given as user "admin"
    And user "requester" exists
    And run the bash command "php <nextcloudRootDir>/console.php group:delete requesters-envelope-authorization >/dev/null 2>&1 || true" with result code 0
    And run the command "group:add requesters-envelope-authorization" with result code 0
    And run the command "group:adduser requesters-envelope-authorization requester" with result code 0
    And run the command "config:app:set libresign groups_request_sign --value='[\"admin\",\"requesters-envelope-authorization\"]' --type=array" with result code 0

  Scenario: A requester cannot modify another requester's draft envelope
    Given sending "post" to ocs "/apps/libresign/api/v1/file"
      | files | [{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Contract.pdf"},{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Annex.pdf"}] |
      | name | Owner Envelope |
    And the response should have a status code 200
    And fetch field "(ENVELOPE_UUID)ocs.data.uuid" from previous JSON response
    When as user "requester"
    And sending "post" to ocs "/apps/libresign/api/v1/file/<ENVELOPE_UUID>/add-file"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                       |
      | (jq).ocs.data.message | You do not have permission for this action. |

  Scenario: The envelope owner reaches upload validation
    Given sending "post" to ocs "/apps/libresign/api/v1/file"
      | files | [{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Contract.pdf"},{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Annex.pdf"}] |
      | name | Owner Envelope |
    And the response should have a status code 200
    And fetch field "(ENVELOPE_UUID)ocs.data.uuid" from previous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/file/<ENVELOPE_UUID>/add-file"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value             |
      | (jq).ocs.data.message | No files uploaded |
