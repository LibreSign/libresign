Feature: sign/register
  Background: Create users
    Given user "signer1" exists

  Scenario: Get error when try to request to sign with non admin user
    Given as user "signer1"
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1 |
      | file | {"base64":""} |
      | users | {"identify":"nextcloud"} |
      | name | document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value                                    |
      | message  | "You are not allowed to request signing" |

  Scenario: Get error when try to request to sign with invalid file
    Given as user "admin"
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1 |
      | file | {"invalid":""} |
      | users | {"identify":"nextcloud"} |
      | name | document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value                                                                      |
      | message  | "File type: document to sign. Specify a URL, a base64 string or a fileID." |
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1 |
      | file | {"base64":""} |
      | users | {"identify":"nextcloud"} |
      | name | document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value                                                                      |
      | message  | "File type: document to sign. Specify a URL, a base64 string or a fileID." |
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1 |
      | file | {"url":""} |
      | users | {"identify":"nextcloud"} |
      | name | document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value                                                                      |
      | message  | "File type: document to sign. Specify a URL, a base64 string or a fileID." |
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1 |
      | file | {"fileId":""} |
      | users | {"identify":"nextcloud"} |
      | name | document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value                                                                      |
      | message  | "File type: document to sign. Specify a URL, a base64 string or a fileID." |
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1                        |
      | file   | {"base64":"invalid"}     |
      | users  | {"identify":"nextcloud"} |
      | name   | document                 |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value                                               |
      | message  | "File type: document to sign. Invalid base64 file." |
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1                       |
      | file | {"base64":";;,invalid"}   |
      | users | {"identify":"nextcloud"} |
      | name | document                  |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value                                               |
      | message  | "File type: document to sign. Invalid base64 file." |
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1                                         |
      | file   | {"base64":"data:application/pdf,invalid"} |
      | users  | {"identify":"nextcloud"}                  |
      | name   | document                                  |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key     | value                                               |
      | message | "File type: document to sign. Invalid base64 file." |
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1                                          |
      | file   | {"base64":"data:application/pdf;,invalid"} |
      | users  | {"identify":"nextcloud"}                   |
      | name   | document                                   |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key     | value                                               |
      | message | "File type: document to sign. Invalid base64 file." |
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1                                              |
      | file   | {"base64":"data:application/pdf;text,invalid"} |
      | users  | {"identify":"nextcloud"}                       |
      | name   | document                                       |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key     | value                                               |
      | message | "File type: document to sign. Invalid base64 file." |
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1                                                |
      | file   | {"base64":"data:application/pdf;base64,invalid"} |
      | users  | {"identify":"nextcloud"}                         |
      | name   | document                                         |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key     | value                                               |
      | message | "File type: document to sign. Invalid base64 file." |
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1                                             |
      | file   | {"base64":"data:application/pdf;base64,MQ=="} |
      | users  | {"identify":"nextcloud"}                      |
      | name   | document                                      |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key     | value                                               |
      | message | "File type: document to sign. Invalid base64 file." |

  Scenario: Get error when try to request to sign using an user with invalid user data
    Given as user "admin"
    And set the email of user "signer1" to ""
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1 |
      | file | {"base64":"data:application/pdf;base64,JVBERi0xLjAKMSAwIG9iajw8L1BhZ2VzIDIgMCBSPj5lbmRvYmogMiAwIG9iajw8L0tpZHNbMyAwIFJdL0NvdW50IDE+PmVuZG9iaiAzIDAgb2JqPDwvTWVkaWFCb3hbMCAwIDMgM10+PmVuZG9iagp0cmFpbGVyPDwvUm9vdCAxIDAgUj4+Cg=="} |
      | users | [{"identify":"nextcloud"}] |
      | name | document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value            |
      | message  | "Email required" |
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1 |
      | file | {"base64":"data:application/pdf;base64,JVBERi0xLjAKMSAwIG9iajw8L1BhZ2VzIDIgMCBSPj5lbmRvYmogMiAwIG9iajw8L0tpZHNbMyAwIFJdL0NvdW50IDE+PmVuZG9iaiAzIDAgb2JqPDwvTWVkaWFCb3hbMCAwIDMgM10+PmVuZG9iagp0cmFpbGVyPDwvUm9vdCAxIDAgUj4+Cg=="} |
      | users | [{"email":""}] |
      | name | document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value            |
      | message  | "Email required" |
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1 |
      | file | {"base64":"data:application/pdf;base64,JVBERi0xLjAKMSAwIG9iajw8L1BhZ2VzIDIgMCBSPj5lbmRvYmogMiAwIG9iajw8L0tpZHNbMyAwIFJdL0NvdW50IDE+PmVuZG9iaiAzIDAgb2JqPDwvTWVkaWFCb3hbMCAwIDMgM10+PmVuZG9iagp0cmFpbGVyPDwvUm9vdCAxIDAgUj4+Cg=="} |
      | users | [{"uid":"invalid"}] |
      | name | document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value            |
      | message  | "User not found." |
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1 |
      | file | {"base64":"data:application/pdf;base64,JVBERi0xLjAKMSAwIG9iajw8L1BhZ2VzIDIgMCBSPj5lbmRvYmogMiAwIG9iajw8L0tpZHNbMyAwIFJdL0NvdW50IDE+PmVuZG9iaiAzIDAgb2JqPDwvTWVkaWFCb3hbMCAwIDMgM10+PmVuZG9iagp0cmFpbGVyPDwvUm9vdCAxIDAgUj4+Cg=="} |
      | users | [{"uid":"signer1"}] |
      | name | document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value            |
      | message  | "User signer1 has no email address." |

  Scenario: Get error when try to request to sign with duplicated user
    Given as user "admin"
    And set the email of user "signer1" to "signer1@domain.test"
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1 |
      | file | {"base64":"data:application/pdf;base64,JVBERi0xLjAKMSAwIG9iajw8L1BhZ2VzIDIgMCBSPj5lbmRvYmogMiAwIG9iajw8L0tpZHNbMyAwIFJdL0NvdW50IDE+PmVuZG9iaiAzIDAgb2JqPDwvTWVkaWFCb3hbMCAwIDMgM10+PmVuZG9iagp0cmFpbGVyPDwvUm9vdCAxIDAgUj4+Cg=="} |
      | users | [{"uid":"signer1"},{"uid":"signer1"}] |
      | name | document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value            |
      | message  | "Remove duplicated users, email address need to be unique" |

  Scenario: Get error when try to request to sign with invalid file to TCPDI
    Given as user "admin"
    And set the email of user "signer1" to "signer1@domain.test"
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1 |
      | file | {"base64":"data:application/pdf;base64,JVBERi0xLjAKMSAwIG9iajw8L1BhZ2VzIDIgMCBSPj5lbmRvYmogMiAwIG9iajw8L0tpZHNbMyAwIFJdL0NvdW50IDE+PmVuZG9iaiAzIDAgb2JqPDwvTWVkaWFCb3hbMCAwIDMgM10+PmVuZG9iagp0cmFpbGVyPDwvUm9vdCAxIDAgUj4+Cg=="} |
      | users | [{"uid":"signer1"}] |
      | name | document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value            |
      | message  | "Invalid PDF" |

  Scenario: Get error when try to request to sign with invalid status code
    Given as user "admin"
    And set the email of user "signer1" to "signer1@domain.test"
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      # Status signed
      | status | 3 |
      | file | {"base64":"data:application/pdf;base64,JVBERi0xLjYKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURlY29kZT4+CnN0cmVhbQp4nDPQM1Qo5ypUMFAw0DMwslAwtTTVMzIxV7AwMdSzMDNUKErlCtdSyOMyVADBonQuA4iUhaVCLheKYqBIDlw7xLAcuLEgFlwVVwZXmhZXoAIAI+sZGAplbmRzdHJlYW0KZW5kb2JqCgozIDAgb2JqCjg2CmVuZG9iagoKNSAwIG9iago8PAo+PgplbmRvYmoKCjYgMCBvYmoKPDwvRm9udCA1IDAgUgovUHJvY1NldFsvUERGL1RleHRdCj4+CmVuZG9iagoKMSAwIG9iago8PC9UeXBlL1BhZ2UvUGFyZW50IDQgMCBSL1Jlc291cmNlcyA2IDAgUi9NZWRpYUJveFswIDAgNTk1LjI3NTU5MDU1MTE4MSA4NDEuODg5NzYzNzc5NTI4XS9Hcm91cDw8L1MvVHJhbnNwYXJlbmN5L0NTL0RldmljZVJHQi9JIHRydWU+Pi9Db250ZW50cyAyIDAgUj4+CmVuZG9iagoKNCAwIG9iago8PC9UeXBlL1BhZ2VzCi9SZXNvdXJjZXMgNiAwIFIKL01lZGlhQm94WyAwIDAgNTk1IDg0MSBdCi9LaWRzWyAxIDAgUiBdCi9Db3VudCAxPj4KZW5kb2JqCgo3IDAgb2JqCjw8L1R5cGUvQ2F0YWxvZy9QYWdlcyA0IDAgUgovT3BlbkFjdGlvblsxIDAgUiAvWFlaIG51bGwgbnVsbCAwXQo+PgplbmRvYmoKCjggMCBvYmoKPDwvQ3JlYXRvcjxGRUZGMDA0NDAwNzIwMDYxMDA3Nz4KL1Byb2R1Y2VyPEZFRkYwMDRDMDA2OTAwNjIwMDcyMDA2NTAwNEYwMDY2MDA2NjAwNjkwMDYzMDA2NTAwMjAwMDM3MDAyRTAwMzA+Ci9DcmVhdGlvbkRhdGUoRDoyMDIxMDIyMzExMDgwOS0wMycwMCcpPj4KZW5kb2JqCgp4cmVmCjAgOQowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAyNzAgMDAwMDAgbiAKMDAwMDAwMDAxOSAwMDAwMCBuIAowMDAwMDAwMTc2IDAwMDAwIG4gCjAwMDAwMDA0MzggMDAwMDAgbiAKMDAwMDAwMDE5NSAwMDAwMCBuIAowMDAwMDAwMjE3IDAwMDAwIG4gCjAwMDAwMDA1MzYgMDAwMDAgbiAKMDAwMDAwMDYxOSAwMDAwMCBuIAp0cmFpbGVyCjw8L1NpemUgOS9Sb290IDcgMCBSCi9JbmZvIDggMCBSCi9JRCBbIDw1RkQ4MDlEMTdFODMwQUU5OTRDODkxNDVBMTMwNUQyQz4KPDVGRDgwOUQxN0U4MzBBRTk5NEM4OTE0NUExMzA1RDJDPiBdCi9Eb2NDaGVja3N1bSAvRDZBQThGQTBBQjMwODg2QkQ5ODU0QzYyMTg5QjI2NDQKPj4Kc3RhcnR4cmVmCjc4NQolJUVPRgo="} |
      | users | [{"uid":"signer1"}] |
      | name | document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value            |
      | message  | "Invalid status code for file." |
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      # Status dseleted
      | status | 4 |
      | file | {"base64":"data:application/pdf;base64,JVBERi0xLjYKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURlY29kZT4+CnN0cmVhbQp4nDPQM1Qo5ypUMFAw0DMwslAwtTTVMzIxV7AwMdSzMDNUKErlCtdSyOMyVADBonQuA4iUhaVCLheKYqBIDlw7xLAcuLEgFlwVVwZXmhZXoAIAI+sZGAplbmRzdHJlYW0KZW5kb2JqCgozIDAgb2JqCjg2CmVuZG9iagoKNSAwIG9iago8PAo+PgplbmRvYmoKCjYgMCBvYmoKPDwvRm9udCA1IDAgUgovUHJvY1NldFsvUERGL1RleHRdCj4+CmVuZG9iagoKMSAwIG9iago8PC9UeXBlL1BhZ2UvUGFyZW50IDQgMCBSL1Jlc291cmNlcyA2IDAgUi9NZWRpYUJveFswIDAgNTk1LjI3NTU5MDU1MTE4MSA4NDEuODg5NzYzNzc5NTI4XS9Hcm91cDw8L1MvVHJhbnNwYXJlbmN5L0NTL0RldmljZVJHQi9JIHRydWU+Pi9Db250ZW50cyAyIDAgUj4+CmVuZG9iagoKNCAwIG9iago8PC9UeXBlL1BhZ2VzCi9SZXNvdXJjZXMgNiAwIFIKL01lZGlhQm94WyAwIDAgNTk1IDg0MSBdCi9LaWRzWyAxIDAgUiBdCi9Db3VudCAxPj4KZW5kb2JqCgo3IDAgb2JqCjw8L1R5cGUvQ2F0YWxvZy9QYWdlcyA0IDAgUgovT3BlbkFjdGlvblsxIDAgUiAvWFlaIG51bGwgbnVsbCAwXQo+PgplbmRvYmoKCjggMCBvYmoKPDwvQ3JlYXRvcjxGRUZGMDA0NDAwNzIwMDYxMDA3Nz4KL1Byb2R1Y2VyPEZFRkYwMDRDMDA2OTAwNjIwMDcyMDA2NTAwNEYwMDY2MDA2NjAwNjkwMDYzMDA2NTAwMjAwMDM3MDAyRTAwMzA+Ci9DcmVhdGlvbkRhdGUoRDoyMDIxMDIyMzExMDgwOS0wMycwMCcpPj4KZW5kb2JqCgp4cmVmCjAgOQowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAyNzAgMDAwMDAgbiAKMDAwMDAwMDAxOSAwMDAwMCBuIAowMDAwMDAwMTc2IDAwMDAwIG4gCjAwMDAwMDA0MzggMDAwMDAgbiAKMDAwMDAwMDE5NSAwMDAwMCBuIAowMDAwMDAwMjE3IDAwMDAwIG4gCjAwMDAwMDA1MzYgMDAwMDAgbiAKMDAwMDAwMDYxOSAwMDAwMCBuIAp0cmFpbGVyCjw8L1NpemUgOS9Sb290IDcgMCBSCi9JbmZvIDggMCBSCi9JRCBbIDw1RkQ4MDlEMTdFODMwQUU5OTRDODkxNDVBMTMwNUQyQz4KPDVGRDgwOUQxN0U4MzBBRTk5NEM4OTE0NUExMzA1RDJDPiBdCi9Eb2NDaGVja3N1bSAvRDZBQThGQTBBQjMwODg2QkQ5ODU0QzYyMTg5QjI2NDQKPj4Kc3RhcnR4cmVmCjc4NQolJUVPRgo="} |
      | users | [{"uid":"signer1"}] |
      | name | document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value            |
      | message  | "Invalid status code for file." |

  Scenario: Request to sign with success
    Given as user "admin"
    And set the email of user "signer1" to "signer1@domain.test"
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | file | {"base64":"data:application/pdf;base64,JVBERi0xLjYKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURlY29kZT4+CnN0cmVhbQp4nDPQM1Qo5ypUMFAw0DMwslAwtTTVMzIxV7AwMdSzMDNUKErlCtdSyOMyVADBonQuA4iUhaVCLheKYqBIDlw7xLAcuLEgFlwVVwZXmhZXoAIAI+sZGAplbmRzdHJlYW0KZW5kb2JqCgozIDAgb2JqCjg2CmVuZG9iagoKNSAwIG9iago8PAo+PgplbmRvYmoKCjYgMCBvYmoKPDwvRm9udCA1IDAgUgovUHJvY1NldFsvUERGL1RleHRdCj4+CmVuZG9iagoKMSAwIG9iago8PC9UeXBlL1BhZ2UvUGFyZW50IDQgMCBSL1Jlc291cmNlcyA2IDAgUi9NZWRpYUJveFswIDAgNTk1LjI3NTU5MDU1MTE4MSA4NDEuODg5NzYzNzc5NTI4XS9Hcm91cDw8L1MvVHJhbnNwYXJlbmN5L0NTL0RldmljZVJHQi9JIHRydWU+Pi9Db250ZW50cyAyIDAgUj4+CmVuZG9iagoKNCAwIG9iago8PC9UeXBlL1BhZ2VzCi9SZXNvdXJjZXMgNiAwIFIKL01lZGlhQm94WyAwIDAgNTk1IDg0MSBdCi9LaWRzWyAxIDAgUiBdCi9Db3VudCAxPj4KZW5kb2JqCgo3IDAgb2JqCjw8L1R5cGUvQ2F0YWxvZy9QYWdlcyA0IDAgUgovT3BlbkFjdGlvblsxIDAgUiAvWFlaIG51bGwgbnVsbCAwXQo+PgplbmRvYmoKCjggMCBvYmoKPDwvQ3JlYXRvcjxGRUZGMDA0NDAwNzIwMDYxMDA3Nz4KL1Byb2R1Y2VyPEZFRkYwMDRDMDA2OTAwNjIwMDcyMDA2NTAwNEYwMDY2MDA2NjAwNjkwMDYzMDA2NTAwMjAwMDM3MDAyRTAwMzA+Ci9DcmVhdGlvbkRhdGUoRDoyMDIxMDIyMzExMDgwOS0wMycwMCcpPj4KZW5kb2JqCgp4cmVmCjAgOQowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAyNzAgMDAwMDAgbiAKMDAwMDAwMDAxOSAwMDAwMCBuIAowMDAwMDAwMTc2IDAwMDAwIG4gCjAwMDAwMDA0MzggMDAwMDAgbiAKMDAwMDAwMDE5NSAwMDAwMCBuIAowMDAwMDAwMjE3IDAwMDAwIG4gCjAwMDAwMDA1MzYgMDAwMDAgbiAKMDAwMDAwMDYxOSAwMDAwMCBuIAp0cmFpbGVyCjw8L1NpemUgOS9Sb290IDcgMCBSCi9JbmZvIDggMCBSCi9JRCBbIDw1RkQ4MDlEMTdFODMwQUU5OTRDODkxNDVBMTMwNUQyQz4KPDVGRDgwOUQxN0U4MzBBRTk5NEM4OTE0NUExMzA1RDJDPiBdCi9Eb2NDaGVja3N1bSAvRDZBQThGQTBBQjMwODg2QkQ5ODU0QzYyMTg5QjI2NDQKPj4Kc3RhcnR4cmVmCjc4NQolJUVPRgo="} |
      | users | [{"uid":"signer1"}] |
      | name | document |
    Then the response should have a status code 200
