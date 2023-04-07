Feature: sign/register
  Background: Create users
    Given user "signer1" exists

  Scenario: Get error when try to sign with non admin user
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

  Scenario: Get error when try to sign with invalid file
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

  Scenario: Get error when try to sign using an user with invalid email
    Given as user "admin"
    When sending "post" to "/apps/libresign/api/0.1/sign/register"
      | status | 1 |
      | file | {"base64":"data:application/pdf;base64,JVBERi0xLjAKMSAwIG9iajw8L1BhZ2VzIDIgMCBSPj5lbmRvYmogMiAwIG9iajw8L0tpZHNbMyAwIFJdL0NvdW50IDE+PmVuZG9iaiAzIDAgb2JqPDwvTWVkaWFCb3hbMCAwIDMgM10+PmVuZG9iagp0cmFpbGVyPDwvUm9vdCAxIDAgUj4+Cg=="} |
      | users | [{"identify":"invalid"}] |
      | name | document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key      | value                                          |
      | message  | "Email required" |

