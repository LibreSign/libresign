Feature: page/sign_identify_account
  Background: Make setup ok
    Given run the command "libresign:configure:openssl --cn test" with result code 0

  Scenario: Open sign file with invalid account data
    Given user "signer1" exists
    And as user "admin"
    And set the email of user "signer1" to "signer1@domain.test"
    And my inbox is empty
    And reset notifications of user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":"data:application/pdf;base64,JVBERi0xLjYKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURlY29kZT4+CnN0cmVhbQp4nDPQM1Qo5ypUMFAw0DMwslAwtTTVMzIxV7AwMdSzMDNUKErlCtdSyOMyVADBonQuA4iUhaVCLheKYqBIDlw7xLAcuLEgFlwVVwZXmhZXoAIAI+sZGAplbmRzdHJlYW0KZW5kb2JqCgozIDAgb2JqCjg2CmVuZG9iagoKNSAwIG9iago8PAo+PgplbmRvYmoKCjYgMCBvYmoKPDwvRm9udCA1IDAgUgovUHJvY1NldFsvUERGL1RleHRdCj4+CmVuZG9iagoKMSAwIG9iago8PC9UeXBlL1BhZ2UvUGFyZW50IDQgMCBSL1Jlc291cmNlcyA2IDAgUi9NZWRpYUJveFswIDAgNTk1LjI3NTU5MDU1MTE4MSA4NDEuODg5NzYzNzc5NTI4XS9Hcm91cDw8L1MvVHJhbnNwYXJlbmN5L0NTL0RldmljZVJHQi9JIHRydWU+Pi9Db250ZW50cyAyIDAgUj4+CmVuZG9iagoKNCAwIG9iago8PC9UeXBlL1BhZ2VzCi9SZXNvdXJjZXMgNiAwIFIKL01lZGlhQm94WyAwIDAgNTk1IDg0MSBdCi9LaWRzWyAxIDAgUiBdCi9Db3VudCAxPj4KZW5kb2JqCgo3IDAgb2JqCjw8L1R5cGUvQ2F0YWxvZy9QYWdlcyA0IDAgUgovT3BlbkFjdGlvblsxIDAgUiAvWFlaIG51bGwgbnVsbCAwXQo+PgplbmRvYmoKCjggMCBvYmoKPDwvQ3JlYXRvcjxGRUZGMDA0NDAwNzIwMDYxMDA3Nz4KL1Byb2R1Y2VyPEZFRkYwMDRDMDA2OTAwNjIwMDcyMDA2NTAwNEYwMDY2MDA2NjAwNjkwMDYzMDA2NTAwMjAwMDM3MDAyRTAwMzA+Ci9DcmVhdGlvbkRhdGUoRDoyMDIxMDIyMzExMDgwOS0wMycwMCcpPj4KZW5kb2JqCgp4cmVmCjAgOQowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAyNzAgMDAwMDAgbiAKMDAwMDAwMDAxOSAwMDAwMCBuIAowMDAwMDAwMTc2IDAwMDAwIG4gCjAwMDAwMDA0MzggMDAwMDAgbiAKMDAwMDAwMDE5NSAwMDAwMCBuIAowMDAwMDAwMjE3IDAwMDAwIG4gCjAwMDAwMDA1MzYgMDAwMDAgbiAKMDAwMDAwMDYxOSAwMDAwMCBuIAp0cmFpbGVyCjw8L1NpemUgOS9Sb290IDcgMCBSCi9JbmZvIDggMCBSCi9JRCBbIDw1RkQ4MDlEMTdFODMwQUU5OTRDODkxNDVBMTMwNUQyQz4KPDVGRDgwOUQxN0U4MzBBRTk5NEM4OTE0NUExMzA1RDJDPiBdCi9Eb2NDaGVja3N1bSAvRDZBQThGQTBBQjMwODg2QkQ5ODU0QzYyMTg5QjI2NDQKPj4Kc3RhcnR4cmVmCjc4NQolJUVPRgo="} |
      | users | [{"identify":{"account":"signer1"}}] |
      | name | document |
    And the response should have a status code 200
    And fetch field "(FILE_UUID)ocs.data.data.uuid" from prevous JSON response
    When as user "signer1"
    And sending "get" to ocs "/apps/notifications/api/v2/notifications"
    Then the response should be a JSON array with the following mandatory values
      | key | value                                                         |
      | ocs | (jq).data\|.[].subject == "admin requested your signature on document"|
    When sending "get" to ocs "/apps/libresign/api/v1/file/list"
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                        | value                   |
      | (jq).ocs.data.data\|length                                 | 1                       |
      | (jq).ocs.data.data[0].statusText                           | available for signature |
      | (jq).ocs.data.data[0].signers\|length                      | 1                       |
      | (jq).ocs.data.data[0].signers[0].me                        | true                    |
      | (jq).ocs.data.data[0].signers[0].identifyMethods\|length   | 1                       |
      | (jq).ocs.data.data[0].signers[0].identifyMethods[0].method | account                 |
      | (jq).ocs.data.data[0].signers[0].identifyMethods[0].value  | signer1                 |
    And fetch field "(SIGN_UUID)ocs.data.data.0.signers.0.sign_uuid" from prevous JSON response
    # invalid UUID, need to be the signer UUID
    When as user "signer1"
    And sending "get" to "/apps/libresign/p/sign/<FILE_UUID>"
    Then the response should have a status code 404
    And the response should be a JSON array with the following mandatory values
      | key      | value             |
      | action | 2000 |
      | errors | [{"message":"Invalid UUID"}] |
    # invalid user
    When as user "admin"
    And sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value             |
      | action | 2000 |
      | errors | [{"message":"Invalid user"}] |
    # unauthenticated user
    When as user ""
    And sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key    | value                                     |
      | action | 1000                                      |
      | errors | ["You are not logged in. Please log in."] |

  Scenario: Open sign file with all data valid
    Given user "signer1" exists
    And as user "admin"
    And set the email of user "signer1" to "signer1@domain.test"
    And my inbox is empty
    And reset notifications of user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":"data:application/pdf;base64,JVBERi0xLjYKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURlY29kZT4+CnN0cmVhbQp4nDPQM1Qo5ypUMFAw0DMwslAwtTTVMzIxV7AwMdSzMDNUKErlCtdSyOMyVADBonQuA4iUhaVCLheKYqBIDlw7xLAcuLEgFlwVVwZXmhZXoAIAI+sZGAplbmRzdHJlYW0KZW5kb2JqCgozIDAgb2JqCjg2CmVuZG9iagoKNSAwIG9iago8PAo+PgplbmRvYmoKCjYgMCBvYmoKPDwvRm9udCA1IDAgUgovUHJvY1NldFsvUERGL1RleHRdCj4+CmVuZG9iagoKMSAwIG9iago8PC9UeXBlL1BhZ2UvUGFyZW50IDQgMCBSL1Jlc291cmNlcyA2IDAgUi9NZWRpYUJveFswIDAgNTk1LjI3NTU5MDU1MTE4MSA4NDEuODg5NzYzNzc5NTI4XS9Hcm91cDw8L1MvVHJhbnNwYXJlbmN5L0NTL0RldmljZVJHQi9JIHRydWU+Pi9Db250ZW50cyAyIDAgUj4+CmVuZG9iagoKNCAwIG9iago8PC9UeXBlL1BhZ2VzCi9SZXNvdXJjZXMgNiAwIFIKL01lZGlhQm94WyAwIDAgNTk1IDg0MSBdCi9LaWRzWyAxIDAgUiBdCi9Db3VudCAxPj4KZW5kb2JqCgo3IDAgb2JqCjw8L1R5cGUvQ2F0YWxvZy9QYWdlcyA0IDAgUgovT3BlbkFjdGlvblsxIDAgUiAvWFlaIG51bGwgbnVsbCAwXQo+PgplbmRvYmoKCjggMCBvYmoKPDwvQ3JlYXRvcjxGRUZGMDA0NDAwNzIwMDYxMDA3Nz4KL1Byb2R1Y2VyPEZFRkYwMDRDMDA2OTAwNjIwMDcyMDA2NTAwNEYwMDY2MDA2NjAwNjkwMDYzMDA2NTAwMjAwMDM3MDAyRTAwMzA+Ci9DcmVhdGlvbkRhdGUoRDoyMDIxMDIyMzExMDgwOS0wMycwMCcpPj4KZW5kb2JqCgp4cmVmCjAgOQowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAyNzAgMDAwMDAgbiAKMDAwMDAwMDAxOSAwMDAwMCBuIAowMDAwMDAwMTc2IDAwMDAwIG4gCjAwMDAwMDA0MzggMDAwMDAgbiAKMDAwMDAwMDE5NSAwMDAwMCBuIAowMDAwMDAwMjE3IDAwMDAwIG4gCjAwMDAwMDA1MzYgMDAwMDAgbiAKMDAwMDAwMDYxOSAwMDAwMCBuIAp0cmFpbGVyCjw8L1NpemUgOS9Sb290IDcgMCBSCi9JbmZvIDggMCBSCi9JRCBbIDw1RkQ4MDlEMTdFODMwQUU5OTRDODkxNDVBMTMwNUQyQz4KPDVGRDgwOUQxN0U4MzBBRTk5NEM4OTE0NUExMzA1RDJDPiBdCi9Eb2NDaGVja3N1bSAvRDZBQThGQTBBQjMwODg2QkQ5ODU0QzYyMTg5QjI2NDQKPj4Kc3RhcnR4cmVmCjc4NQolJUVPRgo="} |
      | users | [{"identify":{"account":"signer1"}}] |
      | name | document |
    And the response should have a status code 200
    When as user "signer1"
    And sending "get" to ocs "/apps/notifications/api/v2/notifications"
    Then the response should be a JSON array with the following mandatory values
      | key | value                                                         |
      | ocs | (jq).data\|.[].subject == "admin requested your signature on document"|
    When sending "get" to ocs "/apps/libresign/api/v1/file/list"
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                        | value                   |
      | (jq).ocs.data.data\|length                                 | 1                       |
      | (jq).ocs.data.data[0].statusText                           | available for signature |
      | (jq).ocs.data.data[0].signers\|length                      | 1                       |
      | (jq).ocs.data.data[0].signers[0].me                        | true                    |
      | (jq).ocs.data.data[0].signers[0].identifyMethods\|length   | 1                       |
      | (jq).ocs.data.data[0].signers[0].identifyMethods[0].method | account                 |
      | (jq).ocs.data.data[0].signers[0].identifyMethods[0].value  | signer1                 |
    And fetch field "(SIGN_UUID)ocs.data.data.0.signers.0.sign_uuid" from prevous JSON response
    When as user "signer1"
    And sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    And the response should contain the initial state "libresign-action" with the following values:
      """
      2500
      """
    And the response should contain the initial state "libresign-pdf" with the following values:
      """
      {
        "url": "/index.php/apps/libresign/pdf/<SIGN_UUID>"
      }
      """
    And the response should contain the initial state "libresign-filename" with the following values:
      """
      document
      """
