Feature: admin/certificate
  Scenario: Generate root cert with success
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"Common Name","names":{"C": {"value":"BR"}}} |
    And the response should have a status code 200
    Then sending "get" to ocs "/apps/libresign/api/v1/admin/certificate"
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key        | value                                                               |
      | rootCert   | {"commonName":"Common Name","names":{"C":{"id":"C","value":"BR"}} } |
      | generated  | true                                                                |
