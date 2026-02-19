Feature: envelope
  Scenario: Cannot save envelope when feature is disabled
    Given as user "admin"
    And the following "libresign" app config is set
      | envelope_enabled | false |
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"test"} |
    When sending "post" to ocs "/apps/libresign/api/v1/file"
      | files | [{"url":"<BASE_URL>/apps/libresign/develop/pdf"},{"url":"<BASE_URL>/apps/libresign/develop/pdf"}] |
      | name | Contract Package |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                         |
      | (jq).ocs.data.message | Envelope feature is disabled  |

  Scenario: Cannot save empty file and empty files array
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"test"} |
    When sending "post" to ocs "/apps/libresign/api/v1/file"
      | name | Test |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                 |
      | (jq).ocs.data.message | File or files parameter is required   |

  Scenario: Cannot save envelope with empty files array
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"test"} |
    When sending "post" to ocs "/apps/libresign/api/v1/file"
      | files | []            |
      | name  | Empty Package |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                               |
      | (jq).ocs.data.message | File or files parameter is required |

  Scenario: Cannot exceed maximum files per envelope
    Given as user "admin"
    And the following "libresign" app config is set
      | envelope_max_files | 2 |
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"test"} |
    When sending "post" to ocs "/apps/libresign/api/v1/file"
      | files | [{"url":"<BASE_URL>/apps/libresign/develop/pdf"},{"url":"<BASE_URL>/apps/libresign/develop/pdf"},{"url":"<BASE_URL>/apps/libresign/develop/pdf"}] |
      | name | Too Many Files |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                             |
      | (jq).ocs.data.message | Maximum number of files per envelope (2) exceeded |

  Scenario: Successfully save single file
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"test"} |
    When sending "post" to ocs "/apps/libresign/api/v1/file"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | name | Single Document |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                             | value                            |
      | (jq).ocs.data.message           | Success                          |
      | (jq).ocs.data.name              | Single Document                  |
      | (jq).ocs.data.status            | 0                                |
      | (jq).ocs.data.statusText        | Draft                            |
      | (jq).ocs.data.nodeType          | file                             |
      | (jq).ocs.data.files[0].name     | Single Document                  |
      | (jq).ocs.data.files \| length   | 1                                |

  Scenario: Successfully save envelope with multiple files
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"test"} |
    When sending "post" to ocs "/apps/libresign/api/v1/file"
      | files | [{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Contract.pdf"},{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Annex.pdf"}] |
      | name | Contract Package |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value                            |
      | (jq).ocs.data.message              | Success                          |
      | (jq).ocs.data.name                 | Contract Package                 |
      | (jq).ocs.data.status               | 0                                |
      | (jq).ocs.data.statusText           | Draft                            |
      | (jq).ocs.data.nodeType             | envelope                         |
      | (jq).ocs.data.files[0].name        | Contract                         |
      | (jq).ocs.data.files[1].name        | Annex                            |
      | (jq).ocs.data.files \| length      | 2                                |

  Scenario: Envelope files are linked to envelope
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"test"} |
    When sending "post" to ocs "/apps/libresign/api/v1/file"
      | files | [{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Doc1.pdf"},{"url":"<BASE_URL>/apps/libresign/develop/pdf","name":"Doc2.pdf"}] |
      | name | Package |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                               | value                         |
      | (jq).ocs.data.message             | Success                       |
      | (jq).ocs.data.name                | Package                       |
      | (jq).ocs.data.nodeType            | envelope                      |
      | (jq).ocs.data.files[0].name       | Doc1                          |
      | (jq).ocs.data.files[1].name       | Doc2                          |
      | (jq).ocs.data.files \| length     | 2                             |
