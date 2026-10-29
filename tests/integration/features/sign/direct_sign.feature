Feature: Direct PDF signing API
  The direct endpoint must enforce the authenticated account identity, signing
  policies and input restrictions, and return a signed PDF instead of OCS JSON.

  Scenario: Unauthenticated users cannot sign PDFs
    Given as user ""
    When sending "post" to ocs "/apps/libresign/api/v1/sign/direct"
      | file | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | name | unauthenticated.pdf |
    Then the response should have a status code 401

  Scenario: A user without permission to request signatures cannot sign PDFs
    Given user "signer1" exists
    And as user "signer1"
    When sending "post" to ocs "/apps/libresign/api/v1/sign/direct"
      | file | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | name | forbidden.pdf |
    Then the response should have a status code 422

  Scenario Outline: Invalid direct-sign payloads are rejected before signing
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/sign/direct"
      | file   | <file>   |
      | name   | <name>   |
      | method | <method> |
      | token  | <token>  |
    Then the response should have a status code 400

    Examples:
      | file                                                               | name         | method      | token |
      | {"base64":"<SMALL_VALID_PDF_BASE64>"}                             | invalid.txt  | clickToSign |       |
      | {}                                                                 | missing.pdf  | clickToSign |       |
      | {"url":"https://example.invalid/document.pdf"}                    | remote.pdf   | clickToSign |       |
      | {"base64":"<SMALL_VALID_PDF_BASE64>","path":"/other.pdf"}            | two.pdf      | clickToSign |       |
      | {"base64":"<SMALL_VALID_PDF_BASE64>"}                             | method.pdf   | emailToken  |       |
      | {"base64":"<SMALL_VALID_PDF_BASE64>"}                             | password.pdf | password    |       |

  Scenario Outline: A direct request cannot sign for another user or customize the signature
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/sign/direct"
      | file  | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | name  | unsupported.pdf |
      | <key> | <value> |
    Then the response should have a status code 400

    Examples:
      | key             | value                                         |
      | signers         | [{"identifyMethods":[{"method":"account","value":"other"}]}] |
      | visibleElements | []                                            |
      | files           | []                                            |
      | callback        | https://example.invalid/callback             |
      | identifyValue   | other-user                                    |
      | uuid            | existing-document                             |

  Scenario: A disabled account signature method is refused
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"factors":[{"name":"account","enabled":true,"requirement":"required","signatureMethods":{"clickToSign":{"enabled":false}}}]} |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/sign/direct"
      | file | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | name | policy-restricted.pdf |
    Then the response should have a status code 403

  Scenario: The authenticated account signs a local PDF and receives the binary signed PDF
    Given as user "admin"
    And run the command "config:app:set libresign signing_mode --value=sync --type=string" with result code 0
    And run the command "libresign:install --use-local-cert --java" with result code 0
    And run the command "libresign:install --use-local-cert --jsignpdf" with result code 0
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"factors":[{"name":"account","enabled":true,"requirement":"required","signatureMethods":{"clickToSign":{"enabled":true}}}]} |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/sign/direct"
      | file | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | name | directly-signed.pdf |
      | method | clickToSign |
    Then the response should have a status code 200
    And the direct-sign response should contain a signed PDF named "directly-signed.pdf"
    When sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                | value |
      | (jq).ocs.data.data[0].status      | 3     |
      | (jq).ocs.data.data[0].signers[0].identifyMethods[0].method | account |

  Scenario: Direct signing accepts an existing PDF by its user-relative path
    Given as user "admin"
    And run the command "config:app:set libresign signing_mode --value=sync --type=string" with result code 0
    And run the command "libresign:install --use-local-cert --java" with result code 0
    And run the command "libresign:install --use-local-cert --jsignpdf" with result code 0
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And user "admin" uploads the valid PDF fixture to "direct-source.pdf"
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"factors":[{"name":"account","enabled":true,"requirement":"required","signatureMethods":{"clickToSign":{"enabled":true}}}]} |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/sign/direct"
      | file | {"path":"/direct-source.pdf"} |
      | name | source-document.pdf |
      | method | clickToSign |
    Then the response should have a status code 200
    And the direct-sign response should contain a signed PDF named "direct-source.pdf"

  Scenario: Direct signing accepts an existing PDF by its Nextcloud node ID
    Given as user "admin"
    And run the command "config:app:set libresign signing_mode --value=sync --type=string" with result code 0
    And run the command "libresign:install --use-local-cert --java" with result code 0
    And run the command "libresign:install --use-local-cert --jsignpdf" with result code 0
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And user "admin" uploads the valid PDF fixture to "direct-source.pdf"
    And I retrieve the Nextcloud node ID for "direct-source.pdf" as "SOURCE_NODE_ID"
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"factors":[{"name":"account","enabled":true,"requirement":"required","signatureMethods":{"clickToSign":{"enabled":true}}}]} |
    And the response should have a status code 200
    When I directly sign PDF "source-document.pdf" using the stored WebDAV node ID "SOURCE_NODE_ID"
    Then the response should have a status code 200
    And the direct-sign response should contain a signed PDF named "direct-source.pdf"
