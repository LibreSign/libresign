Feature: admin/initial_state
  Scenario: Default identify methods are exposed in admin initial state
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string)[] |
    And the response should have a status code 200
    When sending "get" to "/settings/admin/libresign"
    Then the response should contain the initial state "libresign-effective_policies" json that match with:
      | key | value |
      | (jq).policies.identify_methods.policyKey | identify_methods |
      | (jq).policies.identify_methods.effectiveValue.factors\|length | 0 |

  Scenario: Identify methods stored as invalid string can be normalized through the API contract
    Given as user "admin"
    And run the command "config:app:set libresign identify_methods --value=invalid --type=string" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string)[{"name":"account","fake":null}] |
    And the response should have a status code 200
    When sending "get" to "/settings/admin/libresign"
    Then the response should contain the initial state "libresign-effective_policies" json that match with:
      | key | value |
      | (jq).policies.identify_methods.policyKey | identify_methods |
      | (jq)(.policies.identify_methods.effectiveValue.factors \| map(select(.name == "account")) \| length) | 1 |

  Scenario Outline: Invalid identify methods updates preserve the default contract
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string)[] |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string)<payload> |
    Then sending "get" to "/settings/admin/libresign"
    And the response should contain the initial state "libresign-effective_policies" json that match with:
      | key | value |
      | (jq).policies.identify_methods.policyKey | identify_methods |
      | (jq).policies.identify_methods.effectiveValue.factors\|type | array |
      | (jq)(.policies.identify_methods.effectiveValue.factors \| map(select(.name == "<expected_factor_name>")) \| length) | 1 |

    Examples:
      | payload                                 | expected_factor_name |
      | [{"name":"account","fake":null}]   | account |
      | [{"name":"account","enabled":"string"}] | account |
      | [{"name":"email","test_url":"immutable"}] | email |

  Scenario: Updated identify methods are exposed in admin initial state
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string)[] |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"requirement":"required","signatureMethods":{"clickToSign":{"enabled":true}}},{"name":"email","enabled":false,"requirement":"optional"}] |
    Then sending "get" to "/settings/admin/libresign"
    And the response should contain the initial state "libresign-effective_policies" json that match with:
      | key | value |
      | (jq)(.policies.identify_methods.effectiveValue.factors \| map(select(.name == "account")) \| .[0].signatureMethods.clickToSign.enabled) | true |
      | (jq)(.policies.identify_methods.effectiveValue.factors \| map(select(.name == "account")) \| .[0].signatureMethods \| has("password")) | false |
      | (jq)(.policies.identify_methods.effectiveValue.factors \| map(select(.name == "account")) \| .[0].requirement) | required |
      | (jq)(.policies.identify_methods.effectiveValue.factors \| map(select(.name == "email")) \| .[0].enabled) | false |
      | (jq)(.policies.identify_methods.effectiveValue.factors \| map(select(.name == "email")) \| .[0].requirement) | optional |
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string)[] |
    And the response should have a status code 200

  Scenario: Stable default admin initial states are exposed
    Given as user "admin"
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/signature_flow"
    And the response should have a status code 200
    And sending "delete" to ocs "/apps/libresign/api/v1/policies/user/admin/signature_flow"
    And the response should have a status code 200
    And run the command "user:setting admin libresign policy_workbench_catalog_compact_view --delete" with result code 0
    And run the command "user:setting admin libresign files_list_sorting_mode --delete" with result code 0
    And run the command "user:setting admin libresign files_list_sorting_direction --delete" with result code 0
    And run the command "user:setting admin libresign files_list_sorting_mode name" with result code 0
    And run the command "user:setting admin libresign files_list_sorting_direction asc" with result code 0
    And run the command "config:app:delete libresign footer_template" with result code 0
    And run the command "config:app:delete libresign config_path" with result code 0
    And run the command "config:app:delete libresign policy.tsa_settings.password" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/tsa_settings"
      | value | (string){"url":"","policy_oid":"","auth_type":"none","username":""} |
    And the response should have a status code 200
    And the following libresign app config is set
      | certificate_engine                | openssl                  |
      | certificate_policies_oid          |                          |
      | collect_metadata                  | false                    |
      | legal_information                 |                          |
      | signature_background_type         | default                  |
      | signature_font_size               | 20                       |
      | signature_height                  | 100                      |
      | signature_engine                  | JSignPdf                 |
      | signature_render_mode             | GRAPHIC_AND_DESCRIPTION  |
      | signature_width                   | 350                      |
      | template_font_size                | 10                       |
      | docmdp_level                      | 2                        |
      | policy.signature_flow.system      | none                     |
      | identification_documents          | false                    |
      | approval_group                    | ["admin"]                |
      | envelope_enabled                  | true                     |
      | show_confetti_after_signing       | true                     |
      | crl_external_validation_enabled   | true                     |
    When sending "get" to "/settings/admin/libresign"
    Then the response should contain the initial state "libresign-config" json that match with:
      | key                                        | value |
      | (jq).files_list_sorting_mode               | name  |
      | (jq).files_list_sorting_direction          | asc   |
      | (jq).policy_workbench_catalog_compact_view | false |
      | (jq).identificationDocumentsFlow           | false |
    And the response should contain the initial state "libresign-certificate_engine" with the following values:
      """
      openssl
      """
    And the response should contain the initial state "libresign-certificate_policies_oid" with the following values:
      """

      """
    And the response should contain the initial state "libresign-certificate_policies_cps" with the following values:
      """

      """
    And the response should contain the initial state "libresign-config_path" with the following values:
      """

      """
    And the response should contain the initial state "libresign-signature_engine" with the following values:
      """
      JSignPdf
      """
    And the response should contain the initial state "libresign-effective_policies" json that match with:
      | key                                                         | value                                 |
      | (jq).policies.docmdp.effectiveValue                         | 2                                     |
      | (jq).policies.legal_information.effectiveValue              |                                       |
      | (jq).policies.signature_flow.policyKey                      | signature_flow                        |
      | (jq).policies.signature_flow.effectiveValue                 | none                                  |
      | (jq).policies.signature_flow.allowedValues                  | ["none","parallel","ordered_numeric"] |
      | (jq).policies.signature_background_type.effectiveValue      | default                               |
      | (jq).policies.identification_documents.effectiveValue.enabled       | false                        |
      | (jq).policies.identification_documents.effectiveValue.approvers[0]    | admin                        |
      | (jq).policies.envelope_enabled.effectiveValue               | true                                  |
      | (jq).policies.show_confetti_after_signing.effectiveValue    | true                                  |
      | (jq).policies.crl_external_validation_enabled.effectiveValue | true                                 |
      | (jq)(.policies.add_footer.effectiveValue \| fromjson).previewWidth | 595                           |
      | (jq)(.policies.add_footer.effectiveValue \| fromjson).previewHeight | 100                          |
      | (jq)(.policies.add_footer.effectiveValue \| fromjson).previewZoom | 100                             |
      | (jq)(.policies.add_footer.effectiveValue \| fromjson).customizeFooterTemplate | false                   |
      | (jq)(.policies.add_footer.effectiveValue \| fromjson).footerTemplate |                                  |
      | (jq).policies.tsa_settings.policyKey                                      | tsa_settings |
      | (jq).policies.tsa_settings.sourceScope                                    | system       |
      | (jq)(.policies.tsa_settings.effectiveValue \| fromjson).auth_type          | none         |
    And run the command "user:setting admin libresign files_list_sorting_mode --delete" with result code 0
    And run the command "user:setting admin libresign files_list_sorting_direction --delete" with result code 0

  Scenario: Custom admin initial states are exposed
    Given as user "admin"
    And the following libresign app config is set
      | certificate_engine                | openssl                           |
      | collect_metadata                  | false                             |
      | config_path                       | /tmp                              |
      | legal_information                 | Custom legal information          |
      | signature_background_type         | deleted                           |
      | signature_font_size               | 18.5                              |
      | signature_height                  | 140                               |
      | signature_engine                  | PhpNative                         |
      | signature_render_mode             | DESCRIPTION_ONLY                  |
      | signature_text_template           | Issuer: {{IssuerCommonName}}      |
      | signature_width                   | 420                               |
      | template_font_size                | 12.5                              |
      | policy.tsa_settings.password      | topsecret                         |
      | docmdp_level                      | 0                                 |
      | policy.signature_flow.system      | ordered_numeric                   |
      | identification_documents          | true                              |
      | approval_group                    | ["admin","staff"]               |
      | envelope_enabled                  | false                             |
      | show_confetti_after_signing       | false                             |
      | crl_external_validation_enabled   | false                             |
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identification_documents"
      | value | {"enabled":true,"approvers":["admin","staff"]} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/tsa_settings"
      | value | (string){"url":"https://tsa.example.test/tsr","policy_oid":"1.2.3","auth_type":"none","username":""} |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/footer-template"
      | template | Custom footer for {{ uuid }} |
      | width    | 610                          |
      | height   | 80                           |
    And the response should have a status code 200
    And run the command "config:app:set libresign certificate_policies_oid --value=1.2.3.4.5 --type=string" with result code 0
    When sending "get" to "/settings/admin/libresign"
    Then the response should contain the initial state "libresign-config" json that match with:
      | key                              | value |
      | (jq).identificationDocumentsFlow | true  |
    And the response should contain the initial state "libresign-certificate_engine" with the following values:
      """
      openssl
      """
    And the response should contain the initial state "libresign-certificate_policies_oid" with the following values:
      """
      1.2.3.4.5
      """
    And the response should contain the initial state "libresign-certificate_policies_cps" with the following values:
      """

      """
    And the response should contain the initial state "libresign-config_path" with the following values:
      """
      "/tmp"
      """
    And the response should contain the initial state "libresign-signature_engine" with the following values:
      """
      PhpNative
      """
    And the response should contain the initial state "libresign-effective_policies" json that match with:
      | key                                                         | value                                 |
      | (jq).policies.docmdp.effectiveValue                         | 0                                     |
      | (jq).policies.legal_information.effectiveValue              | Custom legal information              |
      | (jq).policies.signature_flow.policyKey                      | signature_flow                        |
      | (jq).policies.signature_flow.effectiveValue                 | ordered_numeric                       |
      | (jq).policies.signature_flow.allowedValues                  | ["ordered_numeric"]                  |
      | (jq).policies.signature_background_type.effectiveValue      | deleted                               |
      | (jq).policies.identification_documents.effectiveValue.enabled       | true                         |
      | (jq).policies.identification_documents.effectiveValue.approvers[0]    | admin                        |
      | (jq).policies.identification_documents.effectiveValue.approvers[1]    | staff                        |
      | (jq).policies.envelope_enabled.effectiveValue               | false                                 |
      | (jq).policies.show_confetti_after_signing.effectiveValue    | false                                 |
      | (jq).policies.crl_external_validation_enabled.effectiveValue | false                                |
      | (jq)(.policies.add_footer.effectiveValue \| fromjson).previewWidth | 610                           |
      | (jq)(.policies.add_footer.effectiveValue \| fromjson).previewHeight | 80                           |
      | (jq)(.policies.add_footer.effectiveValue \| fromjson).previewZoom | 100                            |
      | (jq)(.policies.add_footer.effectiveValue \| fromjson).customizeFooterTemplate | true                    |
      | (jq)(.policies.add_footer.effectiveValue \| fromjson).footerTemplate | Custom footer for {{ uuid }}     |
      | (jq).policies.tsa_settings.policyKey                                      | tsa_settings                 |
      | (jq).policies.tsa_settings.sourceScope                                    | global                       |
      | (jq)(.policies.tsa_settings.effectiveValue \| fromjson).url                | https://tsa.example.test/tsr |
      | (jq)(.policies.tsa_settings.effectiveValue \| fromjson).policy_oid         | 1.2.3                        |
      | (jq)(.policies.tsa_settings.effectiveValue \| fromjson).auth_type          | none                         |
    And the following libresign app config is set
      | certificate_engine                | openssl                  |
      | certificate_policies_oid          |                          |
      | collect_metadata                  | false                    |
      | legal_information                 |                          |
      | signature_background_type         | default                  |
      | signature_font_size               | 20                       |
      | signature_height                  | 100                      |
      | signature_engine                  | JSignPdf                 |
      | signature_render_mode             | GRAPHIC_AND_DESCRIPTION  |
      | signature_width                   | 350                      |
      | template_font_size                | 10                       |
      | docmdp_level                      | 2                        |
      | policy.signature_flow.system      | none                     |
      | identification_documents          | false                    |
      | approval_group                    | ["admin"]                |
      | envelope_enabled                  | true                     |
      | show_confetti_after_signing       | true                     |
      | crl_external_validation_enabled   | true                     |
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/tsa_settings"
      | value | (string){"url":"","policy_oid":"","auth_type":"none","username":""} |
    And the response should have a status code 200
    And run the command "config:app:delete libresign footer_template" with result code 0
    And run the command "config:app:delete libresign config_path" with result code 0
    And run the command "config:app:delete libresign policy.tsa_settings.password" with result code 0

  Scenario: User preference is exposed in config initial state
    Given as user "admin"
    And run the command "user:setting admin libresign policy_workbench_catalog_compact_view --delete" with result code 0
    When run the command "user:setting admin libresign policy_workbench_catalog_compact_view 1" with result code 0
    Then sending "get" to "/settings/admin/libresign"
    And the response should contain the initial state "libresign-config" json that match with:
      | key                                        | value |
      | (jq).policy_workbench_catalog_compact_view | true  |
    And run the command "user:setting admin libresign policy_workbench_catalog_compact_view --delete" with result code 0
