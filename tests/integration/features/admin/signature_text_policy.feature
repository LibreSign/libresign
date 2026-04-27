Feature: Signature text policy layer
  Background:
    Given the app "libresign" is installed
    And user "admin" exists with default attributes

  Scenario: Admin can set system-level signature text template via policy
    When I am logged in as admin
    And I set the policy "signature_text_template" with system value "Welcome {{SignerCommonName}}" via policy service
    Then the system policy "signature_text_template" should be "Welcome {{SignerCommonName}}"
    And the effective policy "signature_text_template" should be "Welcome {{SignerCommonName}}"

  Scenario: Admin can set signature render mode via policy
    When I am logged in as admin
    And I set the policy "signature_render_mode" with system value "DESCRIPTION_ONLY" via policy service
    Then the system policy "signature_render_mode" should be "DESCRIPTION_ONLY"

  Scenario: Admin can set signature dimensions via policy
    When I am logged in as admin
    And I set the policy "signature_width" with system value "100" via policy service
    And I set the policy "signature_height" with system value "60" via policy service
    Then the system policy "signature_width" should be "100"
    And the system policy "signature_height" should be "60"

  Scenario: Admin can set signature font sizes via policy
    When I am logged in as admin
    And I set the policy "template_font_size" with system value "8.5" via policy service
    And I set the policy "signature_font_size" with system value "18" via policy service
    Then the system policy "template_font_size" should be "8.5"
    And the system policy "signature_font_size" should be "18"

  Scenario: Policy fallback to appconfig during migration
    When I am logged in as admin
    And appConfig key "signature_text_template" is set to "Legacy template {{SignerCommonName}}"
    And the policy service is unavailable
    Then the effective policy "signature_text_template" should return "Legacy template {{SignerCommonName}}" from appConfig fallback

  Scenario: User-level policy override for signature text
    Given user "signer" exists with default attributes
    When I am logged in as admin
    And I set the policy "signature_text_template" with user "signer" value "User-specific: {{SignerCommonName}}" via policy service
    Then the user "signer" effective policy "signature_text_template" should be "User-specific: {{SignerCommonName}}"

  Scenario: Group-level policy for signature text
    Given user "signer" exists with default attributes
    And group "signers" exists
    And user "signer" is member of group "signers"
    When I am logged in as admin
    And I set the policy "signature_render_mode" with group "signers" value "GRAPHIC_ONLY" via policy service
    Then the user "signer" effective policy "signature_render_mode" should be "GRAPHIC_ONLY"

  Scenario: Policy value normalization
    When I am logged in as admin
    And I set the policy "signature_width" with system value "350.75" via policy service
    Then the system policy "signature_width" should be normalized to float "350.75"

  Scenario: Invalid policy value rejection
    When I am logged in as admin
    And I try to set the policy "signature_render_mode" with system value "INVALID_MODE" via policy service
    Then the invalid policy value should be normalized to default "default"
