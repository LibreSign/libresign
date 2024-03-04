Feature: request-signature
  Background: Create users
    Given user "signer1" exists

  Scenario: Get error when try to request to sign isn't manager
    Given as user "signer1"
    And run the command "libresign:configure:openssl --cn test"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":""} |
      | users | [{"identify":{"account":"signer1"}}] |
      | name | document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key    | value                                      |
      | action | 2000                                       |
      | errors | ["You are not allowed to request signing"] |

  Scenario: Get error when try to request to sign without file name
    Given as user "admin"
    And run the command "libresign:configure:openssl --cn test"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"invalid":""} |
      | users | [{"identify":{"account":"signer1"}}] |
      | name | |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value             |
      | message  | Name is mandatory |

  Scenario: Request to sign with error using different authenticated account
    Given as user "admin"
    And run the command "libresign:configure:openssl --cn test"
    And set the email of user "signer1" to "signer1@domain.test"
    And reset notifications of user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer1"}}] |
      | name | document |
    And the response should have a status code 200
    And as user "signer1"
    And I fetch the signer UUID from notification
    And user "signer2" exists
    And as user "signer2"
    When sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key    | value            |
      | action | 2000             |
      | errors | ["Invalid user"] |

  Scenario: Request to sign with error when the user is not authenticated
    Given as user "admin"
    And run the command "libresign:configure:openssl --cn test"
    And reset notifications of user "signer1"
    And my inbox is empty
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer1"}}] |
      | name | document |
    Then the response should have a status code 200
    And as user "signer1"
    And I fetch the signer UUID from notification
    And as user ""
    When sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key    | value                                     |
      | action | 1000                                      |
      | errors | ["You are not logged in. Please log in."] |

  Scenario: Request to sign with error when the authenticated user have an email different of signer
    Given as user "admin"
    And run the command "libresign:configure:openssl --cn test"
    And reset notifications of user "signer1"
    And set the email of user "signer1" to "signer1@domain.test"
    And my inbox is empty
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"signer1@domain.test"}}] |
      | name | document |
    Then the response should have a status code 200
    And there should be 1 emails in my inbox
    And I open the latest email to "signer1@domain.test" with subject "LibreSign: There is a file for you to sign"
    And I fetch the signer UUID from opened email
    And user "signer2" exists
    And set the email of user "signer2" to "signer2@domain.test"
    And as user "signer2"
    When sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key    | value                                  |
      | action | 1000                                   |
      | errors | ["User already exists. Please login."] |

  Scenario: Request to sign with error when the link was expired
    Given as user "admin"
    And my inbox is empty
    And run the command "libresign:developer:reset --all"
    And run the command "libresign:configure:openssl --cn test"
    And run the command "config:app:set libresign maximum_validity --value 1"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"signer2@domain.test"}}] |
      | name | document |
    Then the response should have a status code 200
    And there should be 1 emails in my inbox
    And I open the latest email to "signer2@domain.test" with subject "LibreSign: There is a file for you to sign"
    And I fetch the signer UUID from opened email
    And as user ""
    When wait for 2 second
    And sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key    | value             |
      | action | 2000              |
      | errors | ["Link expired."] |

  Scenario: Request to sign with success when is necessary to renew the link
    Given as user "admin"
    And my inbox is empty
    And run the command "libresign:developer:reset --all"
    And run the command "libresign:configure:openssl --cn test"
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"mandatory":true,"can_create_account":false}] |
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"signer2@domain.test"}}] |
      | name | document |
    And the response should have a status code 200
    And there should be 1 emails in my inbox
    And I open the latest email to "signer2@domain.test" with subject "LibreSign: There is a file for you to sign"
    And I fetch the signer UUID from opened email
    And as user ""
    And run the command "config:app:set libresign maximum_validity --value 300"
    And run the command "config:app:set libresign renewal_interval --value 1"
    Given wait for 2 second
    When sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key    | value       |
      | action | 4500        |
      | title | Link expired |
    Given my inbox is empty
    When sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID>/renew/email"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key     | value                                        |
      | message | Renewed with success. Access the link again. |
    And I open the latest email to "signer2@domain.test" with subject "LibreSign: Changes into a file for you to sign"
    And I fetch the signer UUID from opened email
    And as user ""
    # setting the renewal interval to 2 and making 3 requests, one by second,
    # the 3rd don't will fail because on each valid request, the renewal
    # interval is renewed.
    And run the command "config:app:set libresign renewal_interval --value 2"
    Given wait for 1 second
    When sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    And the response should have a status code 200
    Given wait for 1 second
    When sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should have a status code 200
    Given wait for 1 second
    When sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should have a status code 200
    And the response should contain the initial state "libresign-action" with the following values:
      """
      2500
      """

  Scenario: Request to sign with error using account as identifier when the user don't exists
    Given as user "admin"
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","enabled":true}] |
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer2"}}] |
      | name | document |
    Then the response should be a JSON array with the following mandatory values
      | key     | value           |
      | message | User not found. |

  Scenario: Request to sign with success using account as identifier
    Given as user "admin"
    And run the command "libresign:configure:openssl --cn test"
    And set the email of user "signer1" to "signer1@domain.test"
    And reset notifications of user "signer1"
    And my inbox is empty
    And run the command "user:setting signer1 activity notify_email_libresign 1"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer1"}}] |
      | name | document |
    Then the response should have a status code 200
    And user signer1 has the following notifications
      | app       | object_type | object_id | subject                         |
      | libresign | sign        | document  | There is a file for you to sign |
    And wait for 1 second
    And run the command "activity:send-mails"
    And there should be 1 emails in my inbox
    And I open the latest email to "signer1@domain.test" with subject "Activity at Nextcloud"

  Scenario: Request to sign with error using account as identifier with invalid email
    Given as user "admin"
    And run the command "libresign:developer:reset --all"
    And run the command "libresign:configure:openssl --cn test"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"invaliddomain.test"}}] |
      | name | document |
    Then the response should have a status code 422
    Then the response should be a JSON array with the following mandatory values
      | key     | value           |
      | message | User not found. |

  Scenario: Request to sign with error using email as account identifier
    Given as user "admin"
    And run the command "libresign:configure:openssl --cn test"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer3@domain.test"}}] |
      | name | document |
    Then the response should have a status code 422
    Then the response should be a JSON array with the following mandatory values
      | key     | value           |
      | message | User not found. |

  Scenario: Request to sign with success using email as identifier and URL as file
    Given as user "admin"
    And run the command "libresign:configure:openssl --cn test"
    And my inbox is empty
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"signer2@domain.test"}}] |
      | name | document |
    Then the response should have a status code 200
    And there should be 1 emails in my inbox
    And I open the latest email to "signer2@domain.test" with subject "LibreSign: There is a file for you to sign"

  Scenario: Request to sign with success using account as identifier and URL as file
    Given as user "admin"
    And run the command "libresign:configure:openssl --cn test"
    And set the email of user "signer1" to "signer1@domain.test"
    And reset notifications of user "signer1"
    And my inbox is empty
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer1"}}] |
      | name | document |
    Then the response should have a status code 200
    And user signer1 has the following notifications
      | app       | object_type | object_id | subject                         |
      | libresign | sign        | document  | There is a file for you to sign |
    And there should be 0 emails in my inbox

  Scenario: Request to sign with success using email as identifier
    Given as user "admin"
    And run the command "libresign:configure:openssl --cn test"
    And set the email of user "signer1" to "signer1@domain.test"
    And my inbox is empty
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"signer1@domain.test"}}] |
      | name | document |
    Then the response should have a status code 200
    And there should be 1 emails in my inbox
    And I open the latest email to "signer1@domain.test" with subject "LibreSign: There is a file for you to sign"

  Scenario: Request to sign using email as identifier and when is necessary to use visible elements
    Given as user "admin"
    And run the command "libresign:configure:openssl --cn test"
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"mandatory":true,"can_create_account":false}] |
    And I send a file to be signed
      | file   | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users  | [{"identify":{"email":"signer1@domain.test"}}]  |
      | status | 0                                               |
      | name   | document                                        |
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    And fetch field "data.0.signers.0.signRequestId" from prevous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/file-element/<FILE_UUID>"
      | signRequestId | <data.0.signers.0.signRequestId> |
      | type | signature |
    Then the response should have a status code 404
    And the response should be a JSON array with the following mandatory values
      | key    | value                                           |
      | errors | ["You do not have permission for this action."] |
    When sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"mandatory":true,"can_create_account":true}] |
    And sending "post" to ocs "/apps/libresign/api/v1/file-element/<FILE_UUID>"
      | signRequestId | <data.0.signers.0.signRequestId> |
      | type | signature |
    Then the response should have a status code 200

  Scenario: Request to sign with success using multiple users
    Given as user "admin"
    And run the command "libresign:configure:openssl --cn test"
    And set the email of user "signer1" to "signer1@domain.test"
    And my inbox is empty
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":"data:application/pdf;base64,JVBERi0xLjYKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURlY29kZT4+CnN0cmVhbQp4nDPQM1Qo5ypUMFAw0DMwslAwtTTVMzIxV7AwMdSzMDNUKErlCtdSyOMyVADBonQuA4iUhaVCLheKYqBIDlw7xLAcuLEgFlwVVwZXmhZXoAIAI+sZGAplbmRzdHJlYW0KZW5kb2JqCgozIDAgb2JqCjg2CmVuZG9iagoKNSAwIG9iago8PAo+PgplbmRvYmoKCjYgMCBvYmoKPDwvRm9udCA1IDAgUgovUHJvY1NldFsvUERGL1RleHRdCj4+CmVuZG9iagoKMSAwIG9iago8PC9UeXBlL1BhZ2UvUGFyZW50IDQgMCBSL1Jlc291cmNlcyA2IDAgUi9NZWRpYUJveFswIDAgNTk1LjI3NTU5MDU1MTE4MSA4NDEuODg5NzYzNzc5NTI4XS9Hcm91cDw8L1MvVHJhbnNwYXJlbmN5L0NTL0RldmljZVJHQi9JIHRydWU+Pi9Db250ZW50cyAyIDAgUj4+CmVuZG9iagoKNCAwIG9iago8PC9UeXBlL1BhZ2VzCi9SZXNvdXJjZXMgNiAwIFIKL01lZGlhQm94WyAwIDAgNTk1IDg0MSBdCi9LaWRzWyAxIDAgUiBdCi9Db3VudCAxPj4KZW5kb2JqCgo3IDAgb2JqCjw8L1R5cGUvQ2F0YWxvZy9QYWdlcyA0IDAgUgovT3BlbkFjdGlvblsxIDAgUiAvWFlaIG51bGwgbnVsbCAwXQo+PgplbmRvYmoKCjggMCBvYmoKPDwvQ3JlYXRvcjxGRUZGMDA0NDAwNzIwMDYxMDA3Nz4KL1Byb2R1Y2VyPEZFRkYwMDRDMDA2OTAwNjIwMDcyMDA2NTAwNEYwMDY2MDA2NjAwNjkwMDYzMDA2NTAwMjAwMDM3MDAyRTAwMzA+Ci9DcmVhdGlvbkRhdGUoRDoyMDIxMDIyMzExMDgwOS0wMycwMCcpPj4KZW5kb2JqCgp4cmVmCjAgOQowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAyNzAgMDAwMDAgbiAKMDAwMDAwMDAxOSAwMDAwMCBuIAowMDAwMDAwMTc2IDAwMDAwIG4gCjAwMDAwMDA0MzggMDAwMDAgbiAKMDAwMDAwMDE5NSAwMDAwMCBuIAowMDAwMDAwMjE3IDAwMDAwIG4gCjAwMDAwMDA1MzYgMDAwMDAgbiAKMDAwMDAwMDYxOSAwMDAwMCBuIAp0cmFpbGVyCjw8L1NpemUgOS9Sb290IDcgMCBSCi9JbmZvIDggMCBSCi9JRCBbIDw1RkQ4MDlEMTdFODMwQUU5OTRDODkxNDVBMTMwNUQyQz4KPDVGRDgwOUQxN0U4MzBBRTk5NEM4OTE0NUExMzA1RDJDPiBdCi9Eb2NDaGVja3N1bSAvRDZBQThGQTBBQjMwODg2QkQ5ODU0QzYyMTg5QjI2NDQKPj4Kc3RhcnR4cmVmCjc4NQolJUVPRgo="} |
      | users | [{"identify":{"email":"signer1@domain.test"}},{"identify":{"account":"signer1"}}] |
      | name | document |
    Then the response should have a status code 200
    And user signer1 has the following notifications
      | app       | object_type | object_id | subject                         |
      | libresign | sign        | document  | There is a file for you to sign |
    And there should be 1 emails in my inbox
    And I open the latest email to "signer1@domain.test" with subject "LibreSign: There is a file for you to sign"

  Scenario: CRUD of identify methods
    Given run the command "libresign:developer:reset --all"
    And run the command "libresign:configure:openssl --cn test"
    And as user "admin"
    When I send a file to be signed
      | file | {"base64":"data:application/pdf;base64,JVBERi0xLjYKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURlY29kZT4+CnN0cmVhbQp4nDPQM1Qo5ypUMFAw0DMwslAwtTTVMzIxV7AwMdSzMDNUKErlCtdSyOMyVADBonQuA4iUhaVCLheKYqBIDlw7xLAcuLEgFlwVVwZXmhZXoAIAI+sZGAplbmRzdHJlYW0KZW5kb2JqCgozIDAgb2JqCjg2CmVuZG9iagoKNSAwIG9iago8PAo+PgplbmRvYmoKCjYgMCBvYmoKPDwvRm9udCA1IDAgUgovUHJvY1NldFsvUERGL1RleHRdCj4+CmVuZG9iagoKMSAwIG9iago8PC9UeXBlL1BhZ2UvUGFyZW50IDQgMCBSL1Jlc291cmNlcyA2IDAgUi9NZWRpYUJveFswIDAgNTk1LjI3NTU5MDU1MTE4MSA4NDEuODg5NzYzNzc5NTI4XS9Hcm91cDw8L1MvVHJhbnNwYXJlbmN5L0NTL0RldmljZVJHQi9JIHRydWU+Pi9Db250ZW50cyAyIDAgUj4+CmVuZG9iagoKNCAwIG9iago8PC9UeXBlL1BhZ2VzCi9SZXNvdXJjZXMgNiAwIFIKL01lZGlhQm94WyAwIDAgNTk1IDg0MSBdCi9LaWRzWyAxIDAgUiBdCi9Db3VudCAxPj4KZW5kb2JqCgo3IDAgb2JqCjw8L1R5cGUvQ2F0YWxvZy9QYWdlcyA0IDAgUgovT3BlbkFjdGlvblsxIDAgUiAvWFlaIG51bGwgbnVsbCAwXQo+PgplbmRvYmoKCjggMCBvYmoKPDwvQ3JlYXRvcjxGRUZGMDA0NDAwNzIwMDYxMDA3Nz4KL1Byb2R1Y2VyPEZFRkYwMDRDMDA2OTAwNjIwMDcyMDA2NTAwNEYwMDY2MDA2NjAwNjkwMDYzMDA2NTAwMjAwMDM3MDAyRTAwMzA+Ci9DcmVhdGlvbkRhdGUoRDoyMDIxMDIyMzExMDgwOS0wMycwMCcpPj4KZW5kb2JqCgp4cmVmCjAgOQowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAyNzAgMDAwMDAgbiAKMDAwMDAwMDAxOSAwMDAwMCBuIAowMDAwMDAwMTc2IDAwMDAwIG4gCjAwMDAwMDA0MzggMDAwMDAgbiAKMDAwMDAwMDE5NSAwMDAwMCBuIAowMDAwMDAwMjE3IDAwMDAwIG4gCjAwMDAwMDA1MzYgMDAwMDAgbiAKMDAwMDAwMDYxOSAwMDAwMCBuIAp0cmFpbGVyCjw8L1NpemUgOS9Sb290IDcgMCBSCi9JbmZvIDggMCBSCi9JRCBbIDw1RkQ4MDlEMTdFODMwQUU5OTRDODkxNDVBMTMwNUQyQz4KPDVGRDgwOUQxN0U4MzBBRTk5NEM4OTE0NUExMzA1RDJDPiBdCi9Eb2NDaGVja3N1bSAvRDZBQThGQTBBQjMwODg2QkQ5ODU0QzYyMTg5QjI2NDQKPj4Kc3RhcnR4cmVmCjc4NQolJUVPRgo="} |
      | users | [{"identify":{"email":"signer1@domain.test"}},{"identify":{"account":"signer1"}}] |
      | name | document |
    And the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/file/list"
    Then the response of file list match with:
      """
      {
        "data": [
          {
            "uuid": "<IGNORED>",
            "name": "document",
            "callback": null,
            "request_date": "<IGNORED>",
            "file": {
              "type": "pdf",
              "url": "\/index.php\/apps\/libresign\/pdf\/user\/<IGNORED>",
              "nodeId": "<IGNORED>"
            },
            "signers": [
              {
                "email": "signer1@domain.test",
                "description": null,
                "displayName": "signer1@domain.test",
                "request_sign_date": "<IGNORED>",
                "sign_date": null,
                "uid": "",
                "signRequestId": "<IGNORED>",
                "me": false,
                "identifyMethods": [
                  {
                    "method": "email",
                    "mandatory": 1,
                    "identifiedAtDate": null
                  }
                ]
              },
              {
                "email": "",
                "description": null,
                "displayName": "signer1",
                "request_sign_date": "<IGNORED>",
                "sign_date": null,
                "uid": "signer1",
                "signRequestId": "<IGNORED>",
                "me": false,
                "identifyMethods": [
                  {
                    "method": "account",
                    "mandatory": 1,
                    "identifiedAtDate": null
                  }
                ]
              }
            ],
            "status": 2,
            "statusText": "pending"
          }
        ],
        "pagination": {
          "total": 1,
          "current": null,
          "next": null,
          "prev": null,
          "last": null,
          "first": null
        }
      }
      """
    And I change the file
      | uuid | <FILE_UUID> |
      | users | [{"identify":{"email":"signer1@domain.test"}}] |
      | name | document |
    And the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/file/list"
    Then the response of file list match with:
      """
      {
        "data": [
          {
            "uuid": "<IGNORED>",
            "name": "document",
            "callback": null,
            "request_date": "<IGNORED>",
            "file": {
              "type": "pdf",
              "url": "\/index.php\/apps\/libresign\/pdf\/user\/<IGNORED>",
              "nodeId": "<IGNORED>"
            },
            "signers": [
              {
                "email": "signer1@domain.test",
                "description": null,
                "displayName": "signer1@domain.test",
                "request_sign_date": "<IGNORED>",
                "sign_date": null,
                "uid": "",
                "signRequestId": "<IGNORED>",
                "me": false,
                "identifyMethods": [
                  {
                    "method": "email",
                    "mandatory": 1,
                    "identifiedAtDate": null
                  }
                ]
              }
            ],
            "status": 2,
            "statusText": "pending"
          }
        ],
        "pagination": {
          "total": 1,
          "current": null,
          "next": null,
          "prev": null,
          "last": null,
          "first": null
        }
      }
      """
