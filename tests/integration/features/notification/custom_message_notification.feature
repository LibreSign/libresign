Feature: Custom message for signers
  In order to provide personalized instructions to signers
  As a document owner
  I want to send custom messages to signers via email

  Background:
    Given as user "admin"
    And user "signer1" exists
    And set the email of user "signer1" to "signer1@test.com"
    And my inbox is empty
    And reset notifications of user "signer1"
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And run the command "config:app:set activity notify_email_libresign_file_to_sign --value=1" with result code 0
    And run the command "user:setting signer1 activity notify_email_libresign_file_to_sign 1" with result code 0

  Scenario: Account method - default message without custom description
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | name | Document without custom message |
      | users | [{"identify":{"account":"signer1"}}] |
    Then the response should have a status code 200
    And there should be 1 emails in my inbox
    When I open the latest email to "signer1@test.com" with subject "LibreSign: There is a file for you to sign"
    Then I should see "There is a document for you to sign" in the opened email

  Scenario: Account method - custom description in email
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | name | Document with custom message |
      | users | [{"identify":{"account":"signer1"},"description":"Please review section 3 and the appendix before signing."}] |
    Then the response should have a status code 200
    And there should be 1 emails in my inbox
    When I open the latest email to "signer1@test.com" with subject "LibreSign: There is a file for you to sign"
    Then I should see "Please review section 3 and the appendix before signing" in the opened email
    And I should see "There is a document for you to sign" in the opened email

  Scenario: Email method - default notification
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | name | Document for email method |
      | users | [{"identify":{"email":"external@domain.test"},"displayName":"External Signer"}] |
    Then the response should have a status code 200

  Scenario: Email method - custom description via reminder
    Given sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | name | Document for email with description |
      | users | [{"identify":{"email":"external@domain.test"},"displayName":"External Signer","description":"Urgent: Please sign by end of day."}] |
    And the response should have a status code 200
    And fetch field "(FILE_ID)ocs.data.data.nodeId" from previous JSON response
    And fetch field "(SIGN_REQUEST_ID)ocs.data.data.signers.0.signRequestId" from previous JSON response
    And my inbox is empty
    When sending "post" to ocs "/apps/libresign/api/v1/notify/signer"
      | fileId | <FILE_ID> |
      | signRequestId | <SIGN_REQUEST_ID> |
    Then the response should have a status code 200
    And there should be 1 emails in my inbox
    When I open the latest email to "external@domain.test"
    Then I should see "Urgent: Please sign by end of day" in the opened email
