Feature: account/create_to_sign
  Background:
    Given as user "admin"
    And my inbox is empty
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":"data:application/pdf;base64,JVBERi0xLjYKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURlY29kZT4+CnN0cmVhbQp4nDPQM1Qo5ypUMFAw0DMwslAwtTTVMzIxV7AwMdSzMDNUKErlCtdSyOMyVADBonQuA4iUhaVCLheKYqBIDlw7xLAcuLEgFlwVVwZXmhZXoAIAI+sZGAplbmRzdHJlYW0KZW5kb2JqCgozIDAgb2JqCjg2CmVuZG9iagoKNSAwIG9iago8PAo+PgplbmRvYmoKCjYgMCBvYmoKPDwvRm9udCA1IDAgUgovUHJvY1NldFsvUERGL1RleHRdCj4+CmVuZG9iagoKMSAwIG9iago8PC9UeXBlL1BhZ2UvUGFyZW50IDQgMCBSL1Jlc291cmNlcyA2IDAgUi9NZWRpYUJveFswIDAgNTk1LjI3NTU5MDU1MTE4MSA4NDEuODg5NzYzNzc5NTI4XS9Hcm91cDw8L1MvVHJhbnNwYXJlbmN5L0NTL0RldmljZVJHQi9JIHRydWU+Pi9Db250ZW50cyAyIDAgUj4+CmVuZG9iagoKNCAwIG9iago8PC9UeXBlL1BhZ2VzCi9SZXNvdXJjZXMgNiAwIFIKL01lZGlhQm94WyAwIDAgNTk1IDg0MSBdCi9LaWRzWyAxIDAgUiBdCi9Db3VudCAxPj4KZW5kb2JqCgo3IDAgb2JqCjw8L1R5cGUvQ2F0YWxvZy9QYWdlcyA0IDAgUgovT3BlbkFjdGlvblsxIDAgUiAvWFlaIG51bGwgbnVsbCAwXQo+PgplbmRvYmoKCjggMCBvYmoKPDwvQ3JlYXRvcjxGRUZGMDA0NDAwNzIwMDYxMDA3Nz4KL1Byb2R1Y2VyPEZFRkYwMDRDMDA2OTAwNjIwMDcyMDA2NTAwNEYwMDY2MDA2NjAwNjkwMDYzMDA2NTAwMjAwMDM3MDAyRTAwMzA+Ci9DcmVhdGlvbkRhdGUoRDoyMDIxMDIyMzExMDgwOS0wMycwMCcpPj4KZW5kb2JqCgp4cmVmCjAgOQowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAyNzAgMDAwMDAgbiAKMDAwMDAwMDAxOSAwMDAwMCBuIAowMDAwMDAwMTc2IDAwMDAwIG4gCjAwMDAwMDA0MzggMDAwMDAgbiAKMDAwMDAwMDE5NSAwMDAwMCBuIAowMDAwMDAwMjE3IDAwMDAwIG4gCjAwMDAwMDA1MzYgMDAwMDAgbiAKMDAwMDAwMDYxOSAwMDAwMCBuIAp0cmFpbGVyCjw8L1NpemUgOS9Sb290IDcgMCBSCi9JbmZvIDggMCBSCi9JRCBbIDw1RkQ4MDlEMTdFODMwQUU5OTRDODkxNDVBMTMwNUQyQz4KPDVGRDgwOUQxN0U4MzBBRTk5NEM4OTE0NUExMzA1RDJDPiBdCi9Eb2NDaGVja3N1bSAvRDZBQThGQTBBQjMwODg2QkQ5ODU0QzYyMTg5QjI2NDQKPj4Kc3RhcnR4cmVmCjc4NQolJUVPRgo="} |
      | users | [{"identify":{"email":"signer1@domain.test"}}] |
      | name | document |
    Then the response should have a status code 200
    And there should be 1 emails in my inbox

  Scenario: Try to create with invalid data
    Given as user ""
    And run the command "user:delete signer1@domain.test"
    And I open the latest email to "signer1@domain.test" with subject "LibreSign: There is a file for you to sign"
    And I fetch the signer UUID from opened email
    And follow the link on opened email
    And the response should have a status code 200
    And the response should contain the initial state "libresign-config" with the following values:
      """
      {
        "action": 250,
        "sign": {
          "uuid": "<FILE_UUID>",
          "filename": "document",
          "description": null,
          "pdf": {
            "url": "/index.php/apps/libresign/pdf/user/<SIGN_UUID>"
          }
        },
        "user": {
          "name": ""
        },
        "settings": {
          "identifyMethods": [
            {
              "mandatory": 0,
              "identifiedAtDate": null,
              "method": "email"
            }
          ],
          "identificationDocumentsFlow": false,
          "certificateOk": false,
          "hasSignatureFile": false,
          "phoneNumber": "",
          "isApprover": false
        }
      }
      """
    Then sending "post" to ocs "/apps/libresign/api/v1/account/create/<SIGN_UUID>"
      | uuid     | <SIGN_UUID>         |
      | email    | invalid@domain.test |
      | password | 123456              |
    And the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key     | value                 |
      | success | false                 |
      | message | This is not your file |
      | action  | 200                   |


  Scenario: Create with valid data
    Given as user ""
    And run the command "user:delete signer1@domain.test"
    And I open the latest email to "signer1@domain.test" with subject "LibreSign: There is a file for you to sign"
    And I fetch the signer UUID from opened email
    And follow the link on opened email
    And the response should have a status code 200
    And the response should contain the initial state "libresign-config" with the following values:
      """
      {
        "action": 250,
        "sign": {
          "uuid": "<FILE_UUID>",
          "filename": "document",
          "description": null,
          "pdf": {
            "url": "/index.php/apps/libresign/pdf/user/<SIGN_UUID>"
          }
        },
        "user": {
          "name": ""
        },
        "settings": {
          "identifyMethods": [
            {
              "mandatory": 0,
              "identifiedAtDate": null,
              "method": "email"
            }
          ],
          "identificationDocumentsFlow": false,
          "certificateOk": false,
          "hasSignatureFile": false,
          "phoneNumber": "",
          "isApprover": false
        }
      }
      """
    Then sending "post" to ocs "/apps/libresign/api/v1/account/create/<SIGN_UUID>"
      | uuid | <SIGN_UUID> |
      | email | signer1@domain.test |
      | password | 123456 |
    And the response should have a status code 200
