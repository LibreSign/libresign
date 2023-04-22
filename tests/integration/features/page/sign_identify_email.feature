Feature: page/sign_identify_token
  Background: Create users
    Given user "signer1" exists
    And as user "admin"
    And set the email of user "signer1" to "signer1@domain.test"
    And my inbox is empty
    And the following "libresign" app config is set
      | identify_method | email |
    And sending "post" to "/apps/libresign/api/0.1/request-signature"
      | file | {"base64":"data:application/pdf;base64,JVBERi0xLjYKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURlY29kZT4+CnN0cmVhbQp4nDPQM1Qo5ypUMFAw0DMwslAwtTTVMzIxV7AwMdSzMDNUKErlCtdSyOMyVADBonQuA4iUhaVCLheKYqBIDlw7xLAcuLEgFlwVVwZXmhZXoAIAI+sZGAplbmRzdHJlYW0KZW5kb2JqCgozIDAgb2JqCjg2CmVuZG9iagoKNSAwIG9iago8PAo+PgplbmRvYmoKCjYgMCBvYmoKPDwvRm9udCA1IDAgUgovUHJvY1NldFsvUERGL1RleHRdCj4+CmVuZG9iagoKMSAwIG9iago8PC9UeXBlL1BhZ2UvUGFyZW50IDQgMCBSL1Jlc291cmNlcyA2IDAgUi9NZWRpYUJveFswIDAgNTk1LjI3NTU5MDU1MTE4MSA4NDEuODg5NzYzNzc5NTI4XS9Hcm91cDw8L1MvVHJhbnNwYXJlbmN5L0NTL0RldmljZVJHQi9JIHRydWU+Pi9Db250ZW50cyAyIDAgUj4+CmVuZG9iagoKNCAwIG9iago8PC9UeXBlL1BhZ2VzCi9SZXNvdXJjZXMgNiAwIFIKL01lZGlhQm94WyAwIDAgNTk1IDg0MSBdCi9LaWRzWyAxIDAgUiBdCi9Db3VudCAxPj4KZW5kb2JqCgo3IDAgb2JqCjw8L1R5cGUvQ2F0YWxvZy9QYWdlcyA0IDAgUgovT3BlbkFjdGlvblsxIDAgUiAvWFlaIG51bGwgbnVsbCAwXQo+PgplbmRvYmoKCjggMCBvYmoKPDwvQ3JlYXRvcjxGRUZGMDA0NDAwNzIwMDYxMDA3Nz4KL1Byb2R1Y2VyPEZFRkYwMDRDMDA2OTAwNjIwMDcyMDA2NTAwNEYwMDY2MDA2NjAwNjkwMDYzMDA2NTAwMjAwMDM3MDAyRTAwMzA+Ci9DcmVhdGlvbkRhdGUoRDoyMDIxMDIyMzExMDgwOS0wMycwMCcpPj4KZW5kb2JqCgp4cmVmCjAgOQowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAyNzAgMDAwMDAgbiAKMDAwMDAwMDAxOSAwMDAwMCBuIAowMDAwMDAwMTc2IDAwMDAwIG4gCjAwMDAwMDA0MzggMDAwMDAgbiAKMDAwMDAwMDE5NSAwMDAwMCBuIAowMDAwMDAwMjE3IDAwMDAwIG4gCjAwMDAwMDA1MzYgMDAwMDAgbiAKMDAwMDAwMDYxOSAwMDAwMCBuIAp0cmFpbGVyCjw8L1NpemUgOS9Sb290IDcgMCBSCi9JbmZvIDggMCBSCi9JRCBbIDw1RkQ4MDlEMTdFODMwQUU5OTRDODkxNDVBMTMwNUQyQz4KPDVGRDgwOUQxN0U4MzBBRTk5NEM4OTE0NUExMzA1RDJDPiBdCi9Eb2NDaGVja3N1bSAvRDZBQThGQTBBQjMwODg2QkQ5ODU0QzYyMTg5QjI2NDQKPj4Kc3RhcnR4cmVmCjc4NQolJUVPRgo="} |
      | users | [{"identifyMethods":{"email":"signer1@domain.test"}}] |
      | name | document |
    And the response should have a status code 200
    And there should be 1 emails in my inbox
    And I open the latest email with subject "LibreSign: There is a file for you to sign"
    And I open the latest email with body "There is a document for you to sign. Access the link below"

  Scenario: Open sign file with invalid data
    # With invalid UUID, need to be the signer UUID
    When as user "signer1"
    And sending "get" to "/apps/libresign/p/sign/<FILE_UUID>"
    Then the response should contain the initial state "libresign-config" with the following values:
      | key | value |
      | action | 200 |
      | errors | ["Invalid UUID"] |
      | settings | {"identificationDocumentsFlow":false,"certificateOk":false,"hasSignatureFile":false,"phoneNumber":"","isApprover":false} |
    # With invalid user
    When as user "admin"
    And sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should contain the initial state "libresign-config" with the following values:
      | key | value |
      | action | 200 |
      | errors | ["Invalid user"] |
      | settings | {"identificationDocumentsFlow":false,"certificateOk":false,"hasSignatureFile":false,"phoneNumber":"","isApprover":true} |

  Scenario: Open sign file with all data valid
    When as user ""
    And sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should contain the initial state "libresign-config" with the following values:
      | key | value |
      | action | 250 |
      | settings | {"identificationDocumentsFlow":false,"certificateOk":false,"hasSignatureFile":false,"phoneNumber":"","identifyMethods":[{"method":"email","default":1,"identifiedAtDate":null}],"isApprover":false} |
      | user | {"name":""} |
