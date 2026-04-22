Feature: page/validate
  Background: Make setup ok
    Given run the command "libresign:configure:openssl --cn test" with result code 0

  Scenario Outline: Unauthenticated user can see sign page
    Given as user "admin"
    And sending "delete" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/make_validation_url_private"

    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"admin"}]}] |
      | name | document |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(SIGN_REQUEST_UUID)ocs.data.data.0.signers.0.sign_request_uuid" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    When sending "get" to "<url>"
    And the response should have a status code 200

    Examples:
      | url                                    |
      | /apps/libresign/p/sign/<SIGN_REQUEST_UUID>     |
      | /apps/libresign/validation/<FILE_UUID> |
      | /apps/libresign/p/validation           |
      | /apps/libresign/pdf/<SIGN_REQUEST_UUID>        |
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

  Scenario: Authenticated signer can fetch PDF using sign request UUID
    Given user "validate-signer" exists
    And as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file    | {"base64":"data:application/pdf;base64,JVBERi0xLjYKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURlY29kZT4+CnN0cmVhbQp4nDPQM1Qo5ypUMFAw0DMwslAwtTTVMzIxV7AwMdSzMDNUKErlCtdSyOMyVADBonQuA4iUhaVCLheKYqBIDlw7xLAcuLEgFlwVVwZXmhZXoAIAI+sZGAplbmRzdHJlYW0KZW5kb2JqCgozIDAgb2JqCjg2CmVuZG9iagoKNSAwIG9iago8PAo+PgplbmRvYmoKCjYgMCBvYmoKPDwvRm9udCA1IDAgUgovUHJvY1NldFsvUERGL1RleHRdCj4+CmVuZG9iagoKMSAwIG9iago8PC9UeXBlL1BhZ2UvUGFyZW50IDQgMCBSL1Jlc291cmNlcyA2IDAgUi9NZWRpYUJveFswIDAgNTk1LjI3NTU5MDU1MTE4MSA4NDEuODg5NzYzNzc5NTI4XS9Hcm91cDw8L1MvVHJhbnNwYXJlbmN5L0NTL0RldmljZVJHQi9JIHRydWU+Pi9Db250ZW50cyAyIDAgUj4+CmVuZG9iagoKNCAwIG9iago8PC9UeXBlL1BhZ2VzCi9SZXNvdXJjZXMgNiAwIFIKL01lZGlhQm94WyAwIDAgNTk1IDg0MSBdCi9LaWRzWyAxIDAgUiBdCi9Db3VudCAxPj4KZW5kb2JqCgo3IDAgb2JqCjw8L1R5cGUvQ2F0YWxvZy9QYWdlcyA0IDAgUgovT3BlbkFjdGlvblsxIDAgUiAvWFlaIG51bGwgbnVsbCAwXQo+PgplbmRvYmoKCjggMCBvYmoKPDwvQ3JlYXRvcjxGRUZGMDA0NDAwNzIwMDYxMDA3Nz4KL1Byb2R1Y2VyPEZFRkYwMDRDMDA2OTAwNjIwMDcyMDA2NTAwNEYwMDY2MDA2NjAwNjkwMDYzMDA2NTAwMjAwMDM3MDAyRTAwMzA+Ci9DcmVhdGlvbkRhdGUoRDoyMDIxMDIyMzExMDgwOS0wMycwMCcpPj4KZW5kb2JqCgp4cmVmCjAgOQowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAyNzAgMDAwMDAgbiAKMDAwMDAwMDAxOSAwMDAwMCBuIAowMDAwMDAwMTc2IDAwMDAwIG4gCjAwMDAwMDA0MzggMDAwMDAgbiAKMDAwMDAwMDE5NSAwMDAwMCBuIAowMDAwMDAwMjE3IDAwMDAwIG4gCjAwMDAwMDA1MzYgMDAwMDAgbiAKMDAwMDAwMDYxOSAwMDAwMCBuIAp0cmFpbGVyCjw8L1NpemUgOS9Sb290IDcgMCBSCi9JbmZvIDggMCBSCi9JRCBbIDw1RkQ4MDlEMTdFODMwQUU5OTRDODkxNDVBMTMwNUQyQz4KPDVGRDgwOUQxN0U4MzBBRTk5NEM4OTE0NUExMzA1RDJDPiBdCi9Eb2NDaGVja3N1bSAvRDZBQThGQTBBQjMwODg2QkQ5ODU0QzYyMTg5QjI2NDQKPj4Kc3RhcnR4cmVmCjc4NQolJUVPRgo="} |
      | signers | [{"identifyMethods":[{"method":"account","value":"validate-signer"}]}] |
      | name    | signer-pdf                                                       |
    And the response should have a status code 200
    And as user "validate-signer"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(SIGN_REQUEST_UUID)ocs.data.data.0.signers.0.sign_request_uuid" from previous JSON response
    When sending "get" to "/apps/libresign/pdf/<SIGN_REQUEST_UUID>"
    Then the response should have a status code 200

  Scenario: Missing sign request UUID returns controlled File not found response
    Given user "validate-signer-2" exists
    And as user "validate-signer-2"
    And sending "get" to "/apps/libresign/pdf/fakeuuid-6037-47be-9d9e-3d90b9d0a3ea"
    Then the response should have a status code 404
    And the response should be a JSON array with the following mandatory values
      | key    | value                        |
      | action | 2000                         |
      | errors | [{"message":"Invalid UUID"}] |

  Scenario: Unauthenticated email signer can fetch PDF and gets controlled error after source deletion
    Given as user "admin"
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":true}},"can_create_account":false}] |
    And my inbox is empty
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file    | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | signers | [{"displayName":"External Signer","identifyMethods":[{"method":"email","value":"external@domain.test"}]}] |
      | name    | external-email-pdf |
      | settings | {"folderName":"rm-target-folder"} |
    Then the response should have a status code 200
    And I open the latest email to "external@domain.test" with subject "LibreSign: There is a file for you to sign"
    And I fetch the signer UUID from opened email
    And as user ""
    When sending "get" to "/apps/libresign/pdf/<SIGN_REQUEST_UUID>"
    Then the response should have a status code 200
    And as user "admin"
    And user "admin" sends WebDAV "DELETE" to "LibreSign/rm-target-folder/external-email-pdf.pdf"
    And the response should have a status code 204
    And as user ""
    When sending "get" to "/apps/libresign/pdf/<SIGN_REQUEST_UUID>"
    Then the response should have a status code 404
    And the response should be a JSON array with the following mandatory values
      | key    | value                          |
      | action | 2000                           |
      | errors | [{"message":"File not found"}] |
