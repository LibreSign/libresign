Feature: policies/footer_template_preview
  Scenario: Saving footer template returns a non-empty PDF preview
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/footer-template"
      | template | <table><tr><td>Preview from Behat</td></tr></table> |
      | width    | 595                                                 |
      | height   | 120                                                 |
    Then the response should have a status code 200
    And the response header "Content-Type" should contain "application/pdf"
    And the response body should not be empty
    And the response body should match the regular expression "^%PDF"

  Scenario: Non-admin footer template access follows allow and deny policy states
    Given as user "admin"
    And user "signer1" exists
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/add_footer"
      | value              | true |
      | allowChildOverride | true |
    And the response should have a status code 200
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/signer1/add_footer"
    And the response should have a status code 200
    And as user "signer1"
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/add_footer"
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/footer-template"
      | template | <p>Signer allowed flow</p> |
      | width    | 595 |
      | height   | 100 |
    Then the response should have a status code 200
    And the response header "Content-Type" should contain "application/pdf"
    And the response body should not be empty
    And the response body should match the regular expression "^%PDF"
    When sending "get" to ocs "/apps/libresign/api/v1/footer-template"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.template\|type | string |
    When sending "post" to ocs "/apps/libresign/api/v1/footer-template/preview-pdf"
      | template | <p>Signer preview allowed</p> |
      | width    | 595 |
      | height   | 100 |
    Then the response should have a status code 200
    And the response header "Content-Type" should contain "application/pdf"
    And the response body should not be empty
    And the response body should match the regular expression "^%PDF"
    And sending "put" to ocs "/apps/libresign/api/v1/policies/user/add_footer"
      | value | false |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/footer-template/preview-pdf"
      | template | <p>Signer preview denied</p> |
      | width    | 595 |
      | height   | 100 |
    Then the response should have a status code 403
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/add_footer"
    And the response should have a status code 200

  Scenario: Footer preview endpoint returns non-empty PDF with a minimal template
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/footer-template/preview-pdf"
      | template | <p>Preview endpoint</p> |
      | width    | 595 |
      | height   | 100 |
    Then the response should have a status code 200
    And the response header "Content-Type" should contain "application/pdf"
    And the response body should not be empty
    And the response body should match the regular expression "^%PDF"

  Scenario: Admin cannot preview footer template while add_footer policy is disabled
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/add_footer"
      | value | false |
      | allowChildOverride | true |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/footer-template/preview-pdf"
      | template | <p>Admin preview bypass</p> |
      | width    | 595 |
      | height   | 100 |
    Then the response should have a status code 403
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/add_footer"
      | value | true |
      | allowChildOverride | true |
    And the response should have a status code 200

  Scenario: Reset footer template clears customization and allows a new template
    Given as user "admin"
    And run the command "config:app:delete libresign footer_template" with result code 0
    When sending "post" to ocs "/apps/libresign/api/v1/footer-template"
      | template | <div>BEHAT_FOOTER_TEMPLATE_A</div> |
      | width    | 595 |
      | height   | 100 |
    Then the response should have a status code 200

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policies.add_footer.policyKey | add_footer |
      | (jq).ocs.data.policies.add_footer.effectiveValue\|fromjson\|.customizeFooterTemplate | true |
      | (jq).ocs.data.policies.add_footer.effectiveValue\|fromjson\|.footerTemplate | <div>BEHAT_FOOTER_TEMPLATE_A</div> |

    # API contract: reset is performed by sending an empty template
    When sending "post" to ocs "/apps/libresign/api/v1/footer-template"
      | template | |
      | width    | 595 |
      | height   | 100 |
    Then the response should have a status code 200

    When sending "get" to ocs "/apps/libresign/api/v1/footer-template"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.isDefault | true |
      | (jq).ocs.data.template\|contains("signedBy") | true |

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policies.add_footer.policyKey | add_footer |
      | (jq).ocs.data.policies.add_footer.effectiveValue\|fromjson\|.customizeFooterTemplate | false |
      | (jq).ocs.data.policies.add_footer.effectiveValue\|fromjson\|.footerTemplate | |

    When sending "post" to ocs "/apps/libresign/api/v1/footer-template"
      | template | <div>BEHAT_FOOTER_TEMPLATE_B</div> |
      | width    | 595 |
      | height   | 100 |
    Then the response should have a status code 200

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policies.add_footer.policyKey | add_footer |
      | (jq).ocs.data.policies.add_footer.effectiveValue\|fromjson\|.customizeFooterTemplate | true |
      | (jq).ocs.data.policies.add_footer.effectiveValue\|fromjson\|.footerTemplate | <div>BEHAT_FOOTER_TEMPLATE_B</div> |

  Scenario: User reset falls back to group footer template customized by admin
    Given as user "admin"
    And user "signer1" exists
    And run the command "group:add libresign_footer_reset_flow_group" with result code 0
    And run the command "group:adduser libresign_footer_reset_flow_group signer1" with result code 0

    # Cleanup any previous overrides to keep this scenario deterministic
    When sending "delete" to ocs "/apps/libresign/api/v1/policies/user/signer1/add_footer"
    Then the response should have a status code 200
    Given as user "signer1"
    When sending "delete" to ocs "/apps/libresign/api/v1/policies/user/add_footer"
    Then the response should have a status code 200
    Given as user "admin"
    When sending "delete" to ocs "/apps/libresign/api/v1/policies/group/libresign_footer_reset_flow_group/add_footer"
    Then the response should have a status code 200

    # Admin customizes footer template at group level
    When sending "put" to ocs "/apps/libresign/api/v1/policies/group/libresign_footer_reset_flow_group/add_footer"
      | value | (string){"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"<div>BEHAT_GROUP_TEMPLATE</div>","previewWidth":595,"previewHeight":100,"previewZoom":100} |
      | allowChildOverride | true |
    Then the response should have a status code 200

    # Group member sees group template as effective value
    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policies.add_footer.policyKey | add_footer |
      | (jq).ocs.data.policies.add_footer.sourceScope | group |
      | (jq).ocs.data.policies.add_footer.effectiveValue\|fromjson\|.customizeFooterTemplate | true |
      | (jq).ocs.data.policies.add_footer.effectiveValue\|fromjson\|.footerTemplate | <div>BEHAT_GROUP_TEMPLATE</div> |

    # Group member customizes their own template and reset falls back to group template
    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/add_footer"
      | value | (string){"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"<div>BEHAT_SIGNER1_TEMPLATE</div>","previewWidth":595,"previewHeight":100,"previewZoom":100} |
    Then the response should have a status code 200

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policies.add_footer.policyKey | add_footer |
      | (jq).ocs.data.policies.add_footer.sourceScope | user |
      | (jq).ocs.data.policies.add_footer.effectiveValue\|fromjson\|.customizeFooterTemplate | true |
      | (jq).ocs.data.policies.add_footer.effectiveValue\|fromjson\|.footerTemplate | <div>BEHAT_SIGNER1_TEMPLATE</div> |

    When sending "delete" to ocs "/apps/libresign/api/v1/policies/user/add_footer"
    Then the response should have a status code 200

    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policies.add_footer.policyKey | add_footer |
      | (jq).ocs.data.policies.add_footer.sourceScope | group |
      | (jq).ocs.data.policies.add_footer.effectiveValue\|fromjson\|.customizeFooterTemplate | true |
      | (jq).ocs.data.policies.add_footer.effectiveValue\|fromjson\|.footerTemplate | <div>BEHAT_GROUP_TEMPLATE</div> |

    # Admin simulates an explicit user policy override
    Given as user "admin"
    When sending "put" to ocs "/apps/libresign/api/v1/policies/user/signer1/add_footer"
      | value | (string){"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"<div>BEHAT_USER_TEMPLATE</div>","previewWidth":595,"previewHeight":100,"previewZoom":100} |
    Then the response should have a status code 200

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policies.add_footer.policyKey | add_footer |
      | (jq).ocs.data.policies.add_footer.sourceScope | user_policy |
      | (jq).ocs.data.policies.add_footer.effectiveValue\|fromjson\|.customizeFooterTemplate | true |
      | (jq).ocs.data.policies.add_footer.effectiveValue\|fromjson\|.footerTemplate | <div>BEHAT_USER_TEMPLATE</div> |

    # User reset (clear user-level override) must fall back to group template
    Given as user "admin"
    When sending "delete" to ocs "/apps/libresign/api/v1/policies/user/signer1/add_footer"
    Then the response should have a status code 200

    Given as user "signer1"
    When sending "get" to ocs "/apps/libresign/api/v1/policies/effective"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.policies.add_footer.policyKey | add_footer |
      | (jq).ocs.data.policies.add_footer.sourceScope | group |
      | (jq).ocs.data.policies.add_footer.effectiveValue\|fromjson\|.customizeFooterTemplate | true |
      | (jq).ocs.data.policies.add_footer.effectiveValue\|fromjson\|.footerTemplate | <div>BEHAT_GROUP_TEMPLATE</div> |

    Given as user "admin"
    And run the command "group:removeuser libresign_footer_reset_flow_group signer1" with result code 0
    And run the command "group:delete libresign_footer_reset_flow_group" with result code 0
