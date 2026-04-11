Feature: admin/footer_template_preview
  Scenario: Saving footer template returns a non-empty PDF preview
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/admin/footer-template"
      | template | <table><tr><td>Preview from Behat</td></tr></table> |
      | width    | 595                                                 |
      | height   | 120                                                 |
    Then the response should have a status code 200
    And the response header "Content-Type" should contain "application/pdf"
    And the response body should not be empty
    And the response body should match the regular expression "^%PDF"

  Scenario: Footer preview endpoint returns non-empty PDF with a minimal template
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/admin/footer-template/preview-pdf"
      | template | <p>Preview endpoint</p> |
      | width    | 595 |
      | height   | 100 |
    Then the response should have a status code 200
    And the response header "Content-Type" should contain "application/pdf"
    And the response body should not be empty
    And the response body should match the regular expression "^%PDF"
