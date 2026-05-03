Feature: admin/initial_state
  Scenario: Default identify methods are exposed in admin initial state
    Given as user "admin"
    And run the command "config:app:delete libresign identify_methods" with result code 0
    When sending "get" to "/settings/admin/libresign"
    Then the response should contain the initial state "libresign-identify_methods" json that match with:
      | key                                     | value                                                                                                            |
      | (jq)map(select(.name=="account"))      | (jq)length == 1 and .[0].enabled == true and .[0].mandatory == true and .[0].signatureMethods.password.enabled == true |
      | (jq)map(select(.name=="email"))        | (jq)length == 1 and .[0].enabled == false and .[0].mandatory == true and .[0].can_create_account == true and .[0].signatureMethods.emailToken.enabled == true |

  Scenario: Identify methods stored as invalid string can be normalized through the API contract
    Given as user "admin"
    And run the command "config:app:set libresign identify_methods --value=invalid --type=string" with result code 0
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","fake":null}] |
    And the response should have a status code 200
    When sending "get" to "/settings/admin/libresign"
    Then the response should contain the initial state "libresign-identify_methods" json that match with:
      | key                                     | value                                                                                                            |
      | (jq)map(select(.name=="account"))      | (jq)length == 1 and .[0].enabled == true and .[0].mandatory == true and .[0].signatureMethods.password.enabled == true |
      | (jq)map(select(.name=="email"))        | (jq)length == 1 and .[0].enabled == false and .[0].mandatory == true and .[0].can_create_account == true and .[0].signatureMethods.emailToken.enabled == true |

  Scenario Outline: Invalid identify methods updates preserve the default contract
    Given as user "admin"
    And run the command "config:app:set libresign identify_methods --value=[] --type=array" with result code 0
    When sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)<payload> |
    Then sending "get" to "/settings/admin/libresign"
    And the response should contain the initial state "libresign-identify_methods" json that match with:
      | key                                     | value                                                                                                            |
      | (jq)map(select(.name=="account"))      | (jq)length == 1 and .[0].enabled == true and .[0].mandatory == true and .[0].signatureMethods.password.enabled == true |
      | (jq)map(select(.name=="email"))        | (jq)length == 1 and .[0].enabled == false and .[0].mandatory == true and .[0].can_create_account == true and .[0].signatureMethods.emailToken.enabled == true |

    Examples:
      | payload                                 |
      | [{"name":"account","fake":null}]   |
      | [{"name":"account","enabled":"string"}] |
      | [{"name":"email","test_url":"immutable"}] |

  Scenario: Updated identify methods are exposed in admin initial state
    Given as user "admin"
    And run the command "config:app:set libresign identify_methods --value=[] --type=array" with result code 0
    When sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":true}}},{"name":"email","enabled":false,"mandatory":false}] |
    Then sending "get" to "/settings/admin/libresign"
    And the response should contain the initial state "libresign-identify_methods" json that match with:
      | key                                 | value                                                                                                       |
      | (jq)map(select(.name=="account"))  | (jq)length == 1 and .[0].signatureMethods.clickToSign.enabled == true and .[0].signatureMethods.password.enabled == false |
      | (jq)map(select(.name=="email"))    | (jq)length == 1 and .[0].mandatory == false and .[0].signatureMethods.emailToken.enabled == true           |
    And run the command "config:app:delete libresign identify_methods" with result code 0

  Scenario: Stable default admin initial states are exposed
    Given as user "admin"
    And run the command "user:setting admin libresign policy_workbench_catalog_compact_view --delete" with result code 0
    And run the command "user:setting admin libresign files_list_sorting_mode --delete" with result code 0
    And run the command "user:setting admin libresign files_list_sorting_direction --delete" with result code 0
    And run the command "user:setting admin libresign files_list_sorting_mode name" with result code 0
    And run the command "user:setting admin libresign files_list_sorting_direction asc" with result code 0
    And run the command "config:app:delete libresign signature_text_template" with result code 0
    And run the command "config:app:delete libresign footer_template" with result code 0
    And run the command "config:app:delete libresign config_path" with result code 0
    And run the command "config:app:delete libresign tsa_password" with result code 0
    And sending "delete" to ocs "/apps/libresign/api/v1/admin/tsa"
    And the response should have a status code 200
    And the following libresign app config is set
      | certificate_engine                | openssl                  |
      | certificate_policies_oid          |                          |
      | collect_metadata                  | false                    |
      | legal_information                 |                          |
      | signature_background_type         | default                  |
      | signature_font_size               | 20                       |
      | signature_height                  | 100                      |
      | signature_preview_zoom_level      | 100                      |
      | footer_preview_zoom_level         | 100                      |
      | footer_preview_width              | 595                      |
      | footer_preview_height             | 100                      |
      | signature_engine                  | JSignPdf                 |
      | signature_render_mode             | GRAPHIC_AND_DESCRIPTION  |
      | signature_width                   | 350                      |
      | template_font_size                | 10                       |
      | docmdp_level                      | 2                        |
      | policy.signature_flow.system      | none                     |
      | signing_mode                      | sync                     |
      | worker_type                       | local                    |
      | identification_documents          | false                    |
      | approval_group                    | ["admin"]                |
      | envelope_enabled                  | true                     |
      | parallel_workers                  | 4                        |
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
    And the response should contain the initial state "libresign-signature_available_variables" json that match with:
      | key                                     | value |
      | (jq)has("{{DocumentUUID}}")            | true  |
      | (jq)has("{{IssuerCommonName}}")        | true  |
      | (jq)has("{{ServerSignatureDate}}")     | true  |
      | (jq)has("{{SignerCommonName}}")        | true  |
      | (jq)has("{{SignerIP}}")                | false |
    And the response should contain the initial state "libresign-signature_preview_zoom_level" with the following values:
      """
      100
      """
    And the response should contain the initial state "libresign-footer_preview_zoom_level" with the following values:
      """
      100
      """
    And the response should contain the initial state "libresign-footer_preview_width" with the following values:
      """
      595
      """
    And the response should contain the initial state "libresign-footer_preview_height" with the following values:
      """
      100
      """
    And the response should contain the initial state "libresign-footer_template_variables" json that match with:
      | key                             | value                           |
      | (jq).direction.type             | string                          |
      | (jq).linkToSite.default         | https://libresign.coop          |
      | (jq).signedBy.default           | Digitally signed by LibreSign.  |
      | (jq).validateIn.default         | Validate in %s.                 |
      | (jq).signers.type               | array                           |
      | (jq).uuid.type                  | string                          |
    And the response should contain the initial state "libresign-footer_template_is_default" with the following values:
      """
      true
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
      | (jq).policies.identification_documents.effectiveValue       | false                                 |
      | (jq)(.policies.approval_group.effectiveValue \| fromjson \| .[0]) | admin                           |
      | (jq).policies.envelope_enabled.effectiveValue               | true                                  |
      | (jq).policies.show_confetti_after_signing.effectiveValue    | true                                  |
      | (jq).policies.crl_external_validation_enabled.effectiveValue | true                                 |
      | (jq).policies.tsa_settings.policyKey                                      | tsa_settings |
      | (jq).policies.tsa_settings.sourceScope                                    | system       |
      | (jq)(.policies.tsa_settings.effectiveValue \| fromjson).auth_type          | none         |
    And the response should contain the initial state "libresign-signing_mode" with the following values:
      """
      sync
      """
    And the response should contain the initial state "libresign-worker_type" with the following values:
      """
      local
      """
    And the response should contain the initial state "libresign-parallel_workers" with the following values:
      """
      "4"
      """
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
      | signature_preview_zoom_level      | 125                               |
      | footer_preview_zoom_level         | 85                                |
      | footer_preview_width              | 610                               |
      | footer_preview_height             | 80                                |
      | signature_engine                  | PhpNative                         |
      | signature_render_mode             | DESCRIPTION_ONLY                  |
      | signature_text_template           | Issuer: {{IssuerCommonName}}      |
      | signature_width                   | 420                               |
      | template_font_size                | 12.5                              |
      | tsa_password                      | topsecret                         |
      | docmdp_level                      | 0                                 |
      | policy.signature_flow.system      | ordered_numeric                   |
      | signing_mode                      | async                             |
      | worker_type                       | external                          |
      | identification_documents          | true                              |
      | approval_group                    | ["admin","staff"]               |
      | envelope_enabled                  | false                             |
      | parallel_workers                  | 9                                 |
      | show_confetti_after_signing       | false                             |
      | crl_external_validation_enabled   | false                             |
    And sending "post" to ocs "/apps/libresign/api/v1/admin/tsa"
      | tsa_url        | https://tsa.example.test/tsr |
      | tsa_policy_oid | 1.2.3                        |
      | tsa_auth_type  | basic                        |
      | tsa_username   | signer                       |
      | tsa_password   | topsecret                    |
    And the response should have a status code 200
    And sending "post" to ocs "/apps/libresign/api/v1/admin/footer-template"
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
    And the response should contain the initial state "libresign-signature_text_parsed" with the following values:
      """
      Issuer: Acme Cooperative
      """
    And the response should contain the initial state "libresign-signature_preview_zoom_level" with the following values:
      """
      125
      """
    And the response should contain the initial state "libresign-footer_preview_zoom_level" with the following values:
      """
      85
      """
    And the response should contain the initial state "libresign-footer_preview_width" with the following values:
      """
      610
      """
    And the response should contain the initial state "libresign-footer_preview_height" with the following values:
      """
      80
      """
    And the response should contain the initial state "libresign-footer_template" with the following values:
      """
      Custom footer for {{ uuid }}
      """
    And the response should contain the initial state "libresign-footer_template_is_default" with the following values:
      """
      false
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
      | (jq).policies.identification_documents.effectiveValue       | true                                  |
      | (jq)(.policies.approval_group.effectiveValue \| fromjson \| .[0]) | admin                           |
      | (jq)(.policies.approval_group.effectiveValue \| fromjson \| .[1]) | staff                           |
      | (jq).policies.envelope_enabled.effectiveValue               | false                                 |
      | (jq).policies.show_confetti_after_signing.effectiveValue    | false                                 |
      | (jq).policies.crl_external_validation_enabled.effectiveValue | false                                |
      | (jq).policies.tsa_settings.policyKey                                      | tsa_settings                 |
      | (jq).policies.tsa_settings.sourceScope                                    | global                       |
      | (jq)(.policies.tsa_settings.effectiveValue \| fromjson).url                | https://tsa.example.test/tsr |
      | (jq)(.policies.tsa_settings.effectiveValue \| fromjson).policy_oid         | 1.2.3                        |
      | (jq)(.policies.tsa_settings.effectiveValue \| fromjson).auth_type          | basic                        |
      | (jq)(.policies.tsa_settings.effectiveValue \| fromjson).username           | signer                       |
    And the response should contain the initial state "libresign-signing_mode" with the following values:
      """
      async
      """
    And the response should contain the initial state "libresign-worker_type" with the following values:
      """
      external
      """
    And the response should contain the initial state "libresign-parallel_workers" with the following values:
      """
      "9"
      """
    And the following libresign app config is set
      | certificate_engine                | openssl                  |
      | certificate_policies_oid          |                          |
      | collect_metadata                  | false                    |
      | legal_information                 |                          |
      | signature_background_type         | default                  |
      | signature_font_size               | 20                       |
      | signature_height                  | 100                      |
      | signature_preview_zoom_level      | 100                      |
      | footer_preview_zoom_level         | 100                      |
      | footer_preview_width              | 595                      |
      | footer_preview_height             | 100                      |
      | signature_engine                  | JSignPdf                 |
      | signature_render_mode             | GRAPHIC_AND_DESCRIPTION  |
      | signature_width                   | 350                      |
      | template_font_size                | 10                       |
      | docmdp_level                      | 2                        |
      | policy.signature_flow.system      | none                     |
      | signing_mode                      | sync                     |
      | worker_type                       | local                    |
      | identification_documents          | false                    |
      | approval_group                    | ["admin"]                |
      | envelope_enabled                  | true                     |
      | parallel_workers                  | 4                        |
      | show_confetti_after_signing       | true                     |
      | crl_external_validation_enabled   | true                     |
    And sending "delete" to ocs "/apps/libresign/api/v1/admin/tsa"
    And the response should have a status code 200
    And run the command "config:app:delete libresign signature_text_template" with result code 0
    And run the command "config:app:delete libresign footer_template" with result code 0
    And run the command "config:app:delete libresign config_path" with result code 0
    And run the command "config:app:delete libresign tsa_password" with result code 0

  Scenario: User preference is exposed in config initial state
    Given as user "admin"
    And run the command "user:setting admin libresign policy_workbench_catalog_compact_view --delete" with result code 0
    When run the command "user:setting admin libresign policy_workbench_catalog_compact_view 1" with result code 0
    Then sending "get" to "/settings/admin/libresign"
    And the response should contain the initial state "libresign-config" json that match with:
      | key                                        | value |
      | (jq).policy_workbench_catalog_compact_view | true  |
    And run the command "user:setting admin libresign policy_workbench_catalog_compact_view --delete" with result code 0
