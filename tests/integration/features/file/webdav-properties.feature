Feature: webdav-properties
  Background:
    Given as user "admin"
    And user "signer1" exists
    And set the email of user "signer1" to "signer1@domain.test"

  Scenario: WebDAV PROPFIND returns LibreSign signature status properties
    Given user "admin" uploads file "test.pdf" to "test-document.pdf"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file  | {"path":"/test-document.pdf"}                  |
      | users | [{"identify":{"email":"signer1@domain.test"}}] |
      | name  | test-document                                  |
    Then the response should have a status code 200
    When user "admin" gets WebDAV properties for "test-document.pdf"
    And fetch WebDAV property "fileid" to "FILE_ID"
    Then the WebDAV response should contain property "libresign-signature-status" with value "1"
    And the WebDAV response should contain property "libresign-signed-node-id" with value "<FILE_ID>"
