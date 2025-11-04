Feature: admin/certificate_openssl
  Scenario: Generate root cert with success using only required values
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"Common Name"} |
    And the response should have a status code 200
    Then sending "get" to ocs "/apps/libresign/api/v1/admin/certificate"
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                   | value                                               |
      | (jq).ocs.data.rootCert.commonName     | Common Name                                         |
      | (jq).ocs.data.rootCert.names\|length  | 1                                                   |
      | (jq).ocs.data.rootCert.names[0].id    | OU                                                  |
      | (jq).ocs.data.rootCert.names[0].value | (jq) .[] \|test("^libresign-ca-uuid:[a-z0-9]{10}$") |
      | (jq).ocs.data.generated               | true                                                |

  Scenario: Generate root cert with fail without CN
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":""} |
    Then the response should have a status code 401
    And the response should be a JSON array with the following mandatory values
      | key                   | value                       |
      | (jq).ocs.data.message | Parameter 'CN' is required! |

  Scenario: Generate root cert with a big CN
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"0123456789012345678901234567890123456789012345678901234567890123456789"} |
    Then the response should have a status code 401
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                       |
      | (jq).ocs.data.message | Parameter 'CN' should be betweeen 1 and 64. |

  Scenario: Generate root cert with success using optional names values
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"Common Name","names":[{"id": "C","value":"BR"}]} |
    And the response should have a status code 200
    Then sending "get" to ocs "/apps/libresign/api/v1/admin/certificate"
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                   | value                                               |
      | (jq).ocs.data.rootCert.commonName     | Common Name                                         |
      | (jq).ocs.data.rootCert.names\|length  | 2                                                   |
      | (jq).ocs.data.rootCert.names[0].id    | C                                                   |
      | (jq).ocs.data.rootCert.names[0].value | BR                                                  |
      | (jq).ocs.data.rootCert.names[1].id    | OU                                                  |
      | (jq).ocs.data.rootCert.names[1].value | (jq) .[] \|test("^libresign-ca-uuid:[a-z0-9]{10}$") |
      | (jq).ocs.data.generated               | true                                                |

  Scenario: Generate root cert with fail when country have less then 2 characters
      Given as user "admin"
      When sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
        | rootCert | {"commonName":"Common Name","names":[{"id": "C","value":"B"}]} |
      Then the response should have a status code 401
      And the response should be a JSON array with the following mandatory values
        | key                   | value                                     |
        | (jq).ocs.data.message | Parameter 'C' should be betweeen 2 and 2. |

  Scenario: Generate root cert with fail when country have more then 2 characters
      Given as user "admin"
      When sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
        | rootCert | {"commonName":"Common Name","names":[{"id": "C","value":"BRA"}]} |
      Then the response should have a status code 401
      And the response should be a JSON array with the following mandatory values
        | key                   | value                                     |
        | (jq).ocs.data.message | Parameter 'C' should be betweeen 2 and 2. |
