Feature: admin/signature_stamp_preview
  Scenario: Signature stamp PDF preview requires policy editor permissions
    Given as user "admin"

    When sending "post" to ocs "/apps/libresign/api/v1/signature-stamp/preview-pdf"
      | template | Preview as admin {{SignerCommonName}} |
      | templateFontSize | 9 |
      | signatureFontSize | 9 |
      | signatureWidth | 90 |
      | signatureHeight | 60 |
      | renderMode | default |
      | backgroundType | default |
    Then the response should have a status code 200
    And the response header "Content-Type" should contain "application/pdf"
    And the response body should not be empty
    And the response body should match the regular expression "^%PDF"

    Given as user "signer1"
    When sending "post" to ocs "/apps/libresign/api/v1/signature-stamp/preview-pdf"
      | template | Preview as signer {{SignerCommonName}} |
      | templateFontSize | 9 |
      | signatureFontSize | 9 |
      | signatureWidth | 90 |
      | signatureHeight | 60 |
      | renderMode | default |
      | backgroundType | default |
    Then the response should have a status code 403
