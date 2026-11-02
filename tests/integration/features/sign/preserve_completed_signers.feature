Feature: Preserve completed signer records

  Background:
    Given as user "admin"
    And user "signer1" exists
    And user "signer2" exists
    And run the command "config:app:set libresign signing_mode --value=sync --type=string" with result code 0
    And run the command "libresign:install --use-local-cert --java" with result code 0
    And run the command "libresign:install --use-local-cert --jsignpdf" with result code 0
    And run the command "libresign:install --use-local-cert --pdftk" with result code 0
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string){"factors":[{"name":"account","enabled":true,"requirement":"required","signatureMethods":{"clickToSign":{"enabled":true}}}]} |
    And the response should have a status code 200

  Scenario: A completed signer and document state survive an unsupported removal
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]}] |
      | name | Completed record |
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(SIGN_REQUEST_ID)ocs.data.data.0.signers.0.signRequestId" from previous JSON response
    When as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(SIGN_REQUEST_UUID)ocs.data.data.0.signers.0.sign_request_uuid" from previous JSON response
    And sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_REQUEST_UUID>"
      | method | clickToSign |
    Then the response should have a status code 200
    When as user "admin"
    And sending "delete" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/<SIGN_REQUEST_ID>"
    Then the response should have a status code 422
    When sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should be a JSON array with the following mandatory values
      | key                                  | value |
      | (jq).ocs.data.data[0].status        | 3     |
      | (jq).ocs.data.data[0].signers \| length | 1 |
      | (jq).ocs.data.data[0].signers[0].signed != null | true |

  Scenario: Signer management cannot change a partially signed workflow
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":"<SMALL_VALID_PDF_BASE64>"} |
      | signers | [{"identifyMethods":[{"method":"account","value":"signer1"}]},{"identifyMethods":[{"method":"account","value":"signer2"}]}] |
      | name | Partial signature |
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(FILE_ID)ocs.data.data.0.id" from previous JSON response
    And fetch field "(COMPLETED_SIGNER_ID)ocs.data.data.0.signers.0.signRequestId" from previous JSON response
    And fetch field "(PENDING_SIGNER_ID)ocs.data.data.0.signers.1.signRequestId" from previous JSON response
    When as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    And fetch field "(SIGN_REQUEST_UUID)ocs.data.data.0.signers.0.sign_request_uuid" from previous JSON response
    And sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_REQUEST_UUID>"
      | method | clickToSign |
    Then the response should have a status code 200
    When as user "admin"
    And sending "delete" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/<COMPLETED_SIGNER_ID>"
    Then the response should have a status code 422
    When sending "delete" to ocs "/apps/libresign/api/v1/sign/file_id/<FILE_ID>/<PENDING_SIGNER_ID>"
    Then the response should have a status code 422
    When sending "get" to ocs "/apps/libresign/api/v1/file/list?details=1"
    Then the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.data[0].status | 2 |
      | (jq).ocs.data.data[0].signers \| length | 2 |
      | (jq).ocs.data.data[0].signers[0].signed != null | true |
