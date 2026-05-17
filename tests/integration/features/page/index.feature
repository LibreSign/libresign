Feature: page/sign_identify_default
  Background: Make setup ok
    Given run the command "libresign:configure:openssl --cn test" with result code 0

  Scenario: Open sign file with all data valid
    Given as user "admin"
    And run the command "config:app:set libresign identify_methods --value=[] --type=array" with result code 0
    And sending "post" to ocs "/apps/libresign/api/v1/policies/system/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"requirement":"required"},{"name":"email","enabled":true,"requirement":"optional"}] |
    When sending "get" to "/apps/libresign/f/"
    Then the response should have a status code 200
    And the response should contain the initial state "libresign-effective_policies" json that match with:
      | key                                                                                                              | value            |
      | (jq).policies.identify_methods.policyKey                                                                         | identify_methods |
      | (jq)(.policies.identify_methods.sourceScope \| test("^(system\|global)$"))                                    | true             |
      | (jq).policies.identify_methods.effectiveValue \| type                                                            | object           |
      | (jq).policies.identify_methods.effectiveValue.factors \| length                                                  | 2                |
      | (jq).policies.identify_methods.effectiveValue.factors \| map(select(.name == "account")) \| .[0].enabled       | true             |
      | (jq).policies.identify_methods.effectiveValue.factors \| map(select(.name == "account")) \| .[0].requirement   | required         |
      | (jq).policies.identify_methods.effectiveValue.factors \| map(select(.name == "email")) \| .[0].enabled         | true             |
      | (jq).policies.identify_methods.effectiveValue.factors \| map(select(.name == "email")) \| .[0].requirement     | optional         |
