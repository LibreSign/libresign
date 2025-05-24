Feature: request-signature
  Scenario: Get error when try to request to sign isn't manager
    Given user "signer1" exists
    And as user "signer1"
    And run the command "libresign:configure:openssl --cn test" with result code 0
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":""} |
      | users | [{"identify":{"account":"signer1"}}] |
      | name | document |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                             | value                                  |
      | (jq).ocs.data.action            | 2000                                   |
      | (jq).ocs.data.errors[0].message | You are not allowed to request signing |

  Scenario: Get error when try to request to sign without file name
    Given as user "admin"
    And run the command "libresign:configure:openssl --cn test" with result code 0
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"invalid":""} |
      | users | [{"identify":{"account":"signer1"}}] |
      | name | |
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key                   | value             |
      | (jq).ocs.data.message | Name is mandatory |

  Scenario: Request to sign with error using different authenticated account
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And set the email of user "signer1" to "signer1@domain.test"
    And reset notifications of user "signer1"
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer1"}}] |
      | name | document |
    And the response should have a status code 200
    And as user "signer1"
    And I fetch the signer UUID from notification
    And user "signer2" exists
    And as user "signer2"
    When sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key    | value            |
      | action | 2000             |
      | errors | [{"message":"Invalid user"}] |

  Scenario: Request to sign with error when the user is not authenticated
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And reset notifications of user "signer1"
    And my inbox is empty
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer1"}}] |
      | name | document |
    Then the response should have a status code 200
    And as user "signer1"
    And I fetch the signer UUID from notification
    And as user ""
    When sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key    | value                                     |
      | action | 1000                                      |
      | errors | ["You are not logged in. Please log in."] |

  Scenario: Request to sign with error when the authenticated user have an email different of signer
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And reset notifications of user "signer1"
    And set the email of user "signer1" to "signer1@domain.test"
    And my inbox is empty
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"signer1@domain.test"}}] |
      | name | document |
    Then the response should have a status code 200
    And there should be 1 emails in my inbox
    And I open the latest email to "signer1@domain.test" with subject "LibreSign: There is a file for you to sign"
    And I fetch the signer UUID from opened email
    And user "signer2" exists
    And set the email of user "signer2" to "signer2@domain.test"
    And as user "signer2"
    When sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key    | value                                                          |
      | action | 1000                                                           |
      | errors | ["User already exists. Please login.","This is not your file"] |

  Scenario: Request to sign with error when the link was expired
    Given as user "admin"
    And my inbox is empty
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And run the command "config:app:set libresign maximum_validity --value=1 --type=integer" with result code 0
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"signer2@domain.test"}}] |
      | name | document |
    Then the response should have a status code 200
    And there should be 1 emails in my inbox
    And I open the latest email to "signer2@domain.test" with subject "LibreSign: There is a file for you to sign"
    And I fetch the signer UUID from opened email
    And as user ""
    When wait for 2 second
    And sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key    | value             |
      | action | 2000              |
      | errors | [{"message":"Link expired."}] |

  Scenario: Request to sign with success when is necessary to renew the link
    Given as user "admin"
    And my inbox is empty
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"mandatory":true,"can_create_account":false}] |
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"signer2@domain.test"}}] |
      | name | document |
    And the response should have a status code 200
    And there should be 1 emails in my inbox
    And I open the latest email to "signer2@domain.test" with subject "LibreSign: There is a file for you to sign"
    And I fetch the signer UUID from opened email
    And as user ""
    And run the command "config:app:set libresign maximum_validity --value=300 --type=integer" with result code 0
    And run the command "config:app:set libresign renewal_interval --value=1 --type=integer" with result code 0
    Given wait for 2 second
    When sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should have a status code 422
    And the response should be a JSON array with the following mandatory values
      | key    | value        |
      | action | 4500         |
      | title  | Link expired |
    Given my inbox is empty
    When as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID>/renew/email"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                        |
      | (jq).ocs.data.message | Renewed with success. Access the link again. |
    And I open the latest email to "signer2@domain.test" with subject "LibreSign: Changes into a file for you to sign"
    And I fetch the signer UUID from opened email
    And as user ""
    # setting the renewal interval to 3 and making 4 requests, one by second,
    # the 4rd don't will fail because on each valid request, the renewal
    # interval is renewed.
    And run the command "config:app:set libresign renewal_interval --value=3 --type=integer" with result code 0
    When sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    And the response should have a status code 200
    Given wait for 1 second
    When sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should have a status code 200
    Given wait for 1 second
    When sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should have a status code 200
    Given wait for 1 second
    When sending "get" to "/apps/libresign/p/sign/<SIGN_UUID>"
    Then the response should have a status code 200
    And the response should contain the initial state "libresign-action" with the following values:
      """
      2500
      """

  Scenario: Request to sign with error using account as identifier when the user don't exists
    Given as user "admin"
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","enabled":true}] |
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer2"}}] |
      | name | document |
    Then the response should be a JSON array with the following mandatory values
      | key                   | value           |
      | (jq).ocs.data.message | User not found. |

  Scenario: Request to sign with success using account as identifier
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And set the email of user "signer1" to "signer1@domain.test"
    And reset notifications of user "signer1"
    And my inbox is empty
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer1"}}] |
      | name | document |
    Then the response should have a status code 200
    And fetch field "(FILE_UUID)ocs.data.data.uuid" from prevous JSON response
    When as user "signer1"
    Then sending "get" to ocs "/apps/notifications/api/v2/notifications"
    And the response should be a JSON array with the following mandatory values
      | key                      | value                                      |
      | (jq).ocs.data[0].subject | admin requested your signature on document |
      | (jq).ocs.data[0].message |                                            |
    When sending "get" to ocs "/apps/activity/api/v2/activity/libresign?since=0"
    Then the response should be a JSON array with the following mandatory values
      | key                      | value                                      |
      | (jq).ocs.data[0].subject | admin requested your signature on document |
    When as user "admin"
    And sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | users | [{"identify":{"account":"signer1"}}] |
    And the response should have a status code 200
    When as user "signer1"
    Then sending "get" to ocs "/apps/notifications/api/v2/notifications"
    And the response should be a JSON array with the following mandatory values
      | key                      | value                                                   |
      | (jq).ocs.data[0].subject | admin requested your signature on document              |
      | (jq).ocs.data[0].message | Changes have been made in a file that you have to sign. |
    When sending "get" to ocs "/apps/activity/api/v2/activity/libresign?since=0"
    Then the response should be a JSON array with the following mandatory values
      | key                      | value                          |
      | (jq).ocs.data[0].subject | admin made changes on document |

  Scenario: Request to sign with error using account as identifier with invalid email
    Given as user "admin"
    And run the command "libresign:configure:openssl --cn test" with result code 0
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"}  |
      | users | [{"identify":{"account":"invaliddomain.test"}}] |
      | name | document |
    Then the response should have a status code 422
    Then the response should be a JSON array with the following mandatory values
      | key                   | value           |
      | (jq).ocs.data.message | User not found. |

  Scenario: Request to sign with error using email as account identifier
    Given as user "admin"
    And run the command "libresign:configure:openssl --cn test" with result code 0
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer3@domain.test"}}] |
      | name | document |
    Then the response should have a status code 422
    Then the response should be a JSON array with the following mandatory values
      | key                   | value           |
      | (jq).ocs.data.message | User not found. |

  Scenario: Request to sign with success using email as identifier and URL as file
    Given as user "admin"
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And my inbox is empty
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"signer2@domain.test"}}] |
      | name | document |
    Then the response should have a status code 200
    And there should be 1 emails in my inbox
    And I open the latest email to "signer2@domain.test" with subject "LibreSign: There is a file for you to sign"

  Scenario: Request to sign with success using account as identifier and URL as file
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And set the email of user "signer1" to ""
    And reset notifications of user "signer1"
    And my inbox is empty
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"account":"signer1"}}] |
      | name | document |
    Then the response should have a status code 200
    When as user "signer1"
    And sending "get" to ocs "/apps/notifications/api/v2/notifications"
    Then the response should be a JSON array with the following mandatory values
      | key | value                                                         |
      | ocs | (jq).data\|.[].subject == "admin requested your signature on document"|
    And there should be 0 emails in my inbox

  Scenario: Request to sign with success using email as identifier
    Given as user "admin"
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And set the email of user "signer1" to "signer1@domain.test"
    And my inbox is empty
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"signer1@domain.test"}}] |
      | name | document |
    Then the response should have a status code 200
    And there should be 1 emails in my inbox
    And I open the latest email to "signer1@domain.test" with subject "LibreSign: There is a file for you to sign"

  Scenario: Request to sign using email as identifier and when is necessary to use visible elements
    Given as user "admin"
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"mandatory":true,"can_create_account":false}] |
    And I send a file to be signed
      | file   | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users  | [{"identify":{"email":"signer1@domain.test"}}]  |
      | status | 0                                               |
      | name   | document                                        |
    And fetch field "(FILE_UUID)ocs.data.data.uuid" from prevous JSON response
    And the response should have a status code 200
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    And fetch field "ocs.data.data.0.signers.0.signRequestId" from prevous JSON response
    When sending "post" to ocs "/apps/libresign/api/v1/file-element/<FILE_UUID>"
      | signRequestId | <ocs.data.data.0.signers.0.signRequestId> |
      | type          | signature                                 |
    And the response should be a JSON array with the following mandatory values
      | key                               | value  |
      | (jq).ocs.meta.message             | OK     |
      | (jq).ocs.data.fileElementId\|type | number |
    When sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"mandatory":true,"can_create_account":true}] |
    And sending "post" to ocs "/apps/libresign/api/v1/file-element/<FILE_UUID>"
      | signRequestId | <ocs.data.data.0.signers.0.signRequestId> |
      | type | signature |
    Then the response should have a status code 200

  Scenario: Request to sign with success using multiple users
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:configure:openssl --cn test" with result code 0
    And set the email of user "signer1" to ""
    And my inbox is empty
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"signer1@domain.test"}},{"identify":{"account":"signer1"}}] |
      | name | document |
    Then the response should have a status code 200
    When as user "signer1"
    And sending "get" to ocs "/apps/notifications/api/v2/notifications"
    Then the response should be a JSON array with the following mandatory values
      | key | value                                                         |
      | ocs | (jq).data\|.[].subject == "admin requested your signature on document"|
    And there should be 1 emails in my inbox
    And I open the latest email to "signer1@domain.test" with subject "LibreSign: There is a file for you to sign"

  Scenario: Request to sign with success using multiple emails
    Given run the command "libresign:configure:openssl --cn test" with result code 0
    And as user "admin"
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"mandatory":true,"signatureMethods":{"emailToken":{"enabled":true}},"can_create_account":false}] |
    And my inbox is empty
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"11111@domain.test"}},{"identify":{"email":"22222@domain.test"}}] |
      | name | document |
    Then the response should have a status code 200
    When I open the latest email to "11111@domain.test" with subject "LibreSign: There is a file for you to sign"
    And I fetch the signer UUID from opened email
    And as user ""
    And follow the link on opened email
    And the response should have a status code 200
    Then the response should contain the initial state "libresign-signers" json that match with:
      | key                                                                                         | value                            |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.label          | Email token                      |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.identifyMethod | email                            |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.needCode       | true                             |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.hasConfirmCode | false                            |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.blurredEmail   | 111***@***.test                  |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.hashOfEmail    | c8cb84220c4cf19b723390f29b83a0f8 |
    When I open the latest email to "22222@domain.test" with subject "LibreSign: There is a file for you to sign"
    And I fetch the signer UUID from opened email
    And as user ""
    And follow the link on opened email
    And the response should have a status code 200
    Then the response should contain the initial state "libresign-signers" json that match with:
      | key                                                                                         | value                            |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.label          | Email token                      |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.identifyMethod | email                            |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.needCode       | true                             |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.hasConfirmCode | false                            |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.blurredEmail   | 222***@***.test                  |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.hashOfEmail    | d3ab1426f412df8b8bbb9cb2405fb39d |

  Scenario: Failed to sign with invalid method
    Given run the command "libresign:configure:openssl --cn test" with result code 0
    And as user "admin"
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"mandatory":true,"signatureMethods":{"emailToken":{"enabled":true}},"can_create_account":false}] |
    And my inbox is empty
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"11111@domain.test"}}] |
      | name | document |
    Then the response should have a status code 200
    When I open the latest email to "11111@domain.test" with subject "LibreSign: There is a file for you to sign"
    And I fetch the signer UUID from opened email
    And as user ""
    And follow the link on opened email
    And the response should have a status code 200
    Then the response should contain the initial state "libresign-signers" json that match with:
      | key                                                                                         | value                            |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.label          | Email token                      |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.identifyMethod | email                            |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.needCode       | true                             |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.hasConfirmCode | false                            |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.blurredEmail   | 111***@***.test                  |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.hashOfEmail    | c8cb84220c4cf19b723390f29b83a0f8 |
    And sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID>"
      | method | clickToSign |
      | token |  |
    And the response should have a status code 422
    Then the response should be a JSON array with the following mandatory values
      | key                             | value         |
      | (jq).ocs.data.action            | 2000          |
      | (jq).ocs.data.errors[0].message | Invalid code. |
    And sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID>"
      | method | account |
      | token |  |
    And the response should have a status code 422
    Then the response should be a JSON array with the following mandatory values
      | key                             | value         |
      | (jq).ocs.data.action            | 2000          |
      | (jq).ocs.data.errors[0].message | Invalid code. |
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":true}},"can_create_account":false}] |
    And sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID>"
      | method | email |
      | token |  |
    And the response should have a status code 422
    Then the response should be a JSON array with the following mandatory values
      | key                             | value         |
      | (jq).ocs.data.action            | 2000          |
      | (jq).ocs.data.errors[0].message | Invalid code. |
    And sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID>"
      | method | account |
      | token |  |
    And the response should have a status code 422
    Then the response should be a JSON array with the following mandatory values
      | key                             | value         |
      | (jq).ocs.data.action            | 2000          |
      | (jq).ocs.data.errors[0].message | Invalid code. |

  Scenario: Failed to sign with invalid code
    Given run the command "libresign:configure:openssl --cn test" with result code 0
    And as user "admin"
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"mandatory":true,"signatureMethods":{"emailToken":{"enabled":true}},"can_create_account":false}] |
    And my inbox is empty
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"11111@domain.test"}}] |
      | name | document |
    Then the response should have a status code 200
    When I open the latest email to "11111@domain.test" with subject "LibreSign: There is a file for you to sign"
    And I fetch the signer UUID from opened email
    And as user ""
    And follow the link on opened email
    And the response should have a status code 200
    Then the response should contain the initial state "libresign-signers" json that match with:
      | key                                                                                         | value                            |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.label          | Email token                      |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.identifyMethod | email                            |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.needCode       | true                             |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.hasConfirmCode | false                            |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.blurredEmail   | 111***@***.test                  |
      | (jq).[] \| select(.signatureMethods != null) \| .signatureMethods.emailToken.hashOfEmail    | c8cb84220c4cf19b723390f29b83a0f8 |
    And sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID>"
      | method | email |
      | token |  |
    And the response should have a status code 422
    Then the response should be a JSON array with the following mandatory values
      | key                             | value         |
      | (jq).ocs.data.errors[0].message | Invalid code. |
    And sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID>"
      | method | email |
      | token | 123456789132456789 |
    And the response should have a status code 422
    Then the response should be a JSON array with the following mandatory values
      | key                             | value         |
      | (jq).ocs.data.errors[0].message | Invalid code. |

  Scenario: CRUD of identify methods
    Given run the command "libresign:configure:openssl --cn test" with result code 0
    And user "signer1" exists
    And as user "admin"
    When I send a file to be signed
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"signer1@domain.test"}},{"identify":{"account":"signer1"}}] |
      | name | document |
    And fetch field "(FILE_UUID)ocs.data.data.uuid" from prevous JSON response
    And the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/file/list"
    And the response should be a JSON array with the following mandatory values
      | key                                            | value                   |
      | (jq).ocs.data.data[0].name                     | document                |
      | (jq).ocs.data.data[0].status                   | 1                       |
      | (jq).ocs.data.data[0].statusText               | available for signature |
      | (jq).ocs.data.data[0].requested_by.userId      | admin                   |
      | (jq).ocs.data.data[0].signers\|length          | 2                       |
      | (jq).ocs.data.data[0].signers[0].email         | signer1@domain.test     |
      | (jq).ocs.data.data[0].signers[0].me            | false                   |
      | (jq).ocs.data.data[0].signers[1].email         |                         |
      | (jq).ocs.data.data[0].signers[1].me            | false                   |
    And sending "patch" to ocs "/apps/libresign/api/v1/request-signature"
      | uuid | <FILE_UUID> |
      | users | [{"identify":{"email":"signer1@domain.test"}}] |
    And the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/file/list"
    And the response should be a JSON array with the following mandatory values
      | key                                            | value                   |
      | (jq).ocs.data.data[0].name                     | document                |
      | (jq).ocs.data.data[0].status                   | 1                       |
      | (jq).ocs.data.data[0].statusText               | available for signature |
      | (jq).ocs.data.data[0].requested_by.userId      | admin                   |
      | (jq).ocs.data.data[0].signers\|length          | 1                       |
      | (jq).ocs.data.data[0].signers[0].email         | signer1@domain.test     |
      | (jq).ocs.data.data[0].signers[0].me            | false                   |

  Scenario: Not notify with status 0 and notify with status 1
    Given run the command "libresign:configure:openssl --cn test" with result code 0
    And user "signer1" exists
    And set the email of user "signer1" to "signer1@domain.test"
    And my inbox is empty
    And as user "admin"
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"mandatory":true,"signatureMethods":{"emailToken":{"enabled":true}}}] |
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"signer1@domain.test"}}] |
      | name | document |
      | status | 0 |
    And there should be 0 emails in my inbox
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"identify":{"email":"signer1@domain.test"}}] |
      | name | document |
      | status | 1 |
    And there should be 1 email in my inbox
