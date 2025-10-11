Feature: signed
  Scenario: Sign file using password
    Given as user "admin"
    And user "signer1" exists
    And run the command "libresign:install --use-local-cert --java" with result code 0
    And run the command "libresign:install --use-local-cert --jsignpdf" with result code 0
    And run the command "libresign:install --use-local-cert --pdftk" with result code 0
    And run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company --l=City\ Name --ou=Organization\ Unit" with result code 0
    And run the command "config:app:set libresign add_footer --value=true --type=boolean" with result code 0
    And run the command "config:app:set libresign write_qrcode_on_footer --value=true --type=boolean" with result code 0
    And run the command "config:app:set libresign tsa_url --value=https://freetsa.org/tsr --type=string" with result code 0
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"mandatory":true,"signatureMethods":{"password":{"name":"password","enabled":true}},"signatureMethodEnabled":"password"}] |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"displayName": "Signer Name","description": "Please, sign this document","identify": {"account": "signer1"}}] |
      | name | Document Name |
    And the response should have a status code 200
    And as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    Then the response should be a JSON array with the following mandatory values
      | key                        | value         |
      | (jq).ocs.data.data[0].name | Document Name |
    And fetch field "(SIGN_URL)ocs.data.data.0.url" from previous JSON response
    And fetch field "(SIGN_UUID)ocs.data.data.0.signers.0.sign_uuid" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    And sending "post" to ocs "/apps/libresign/api/v1/account/signature"
      | signPassword | TheComplexPfxPasswordHere |
    And sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID>"
      | method | password |
      | token | TheComplexPfxPasswordHere |
    And the response should have a status code 200
    Then the response should be a JSON array with the following mandatory values
      | key                     | value       |
      | (jq).ocs.data.message   | File signed |
      | (jq).ocs.data.file.uuid | <FILE_UUID> |
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    Then the response should be a JSON array with the following mandatory values
      | key  | value                                 |
      | (jq).ocs.data.data[0].name   | Document Name |
      | (jq).ocs.data.data[0].status | 3             |

  Scenario: Signing a file sends an email and notification
    Given as user "admin"
    And user "signer1" exists
    And set the email of user "signer1" to "signer@domain.test"
    And set the email of user "admin" to "admin@email.tld"
    And run the command "config:app:set activity notify_notification_libresign_file_to_sign --value=1" with result code 0
    And run the command "config:app:set activity notify_email_libresign_file_to_sign --value=1" with result code 0
    And run the command "config:app:set activity notify_notification_libresign_file_signed --value=1" with result code 0
    And run the command "config:app:set activity notify_email_libresign_file_signed --value=1" with result code 0
    And run the command "user:setting signer1 activity notify_email_libresign_file_to_sign 1" with result code 0
    And run the command "user:setting signer1 activity notify_notification_libresign_file_to_sign 1" with result code 0
    And run the command "user:setting admin activity notify_notification_libresign_file_signed 1" with result code 0
    And run the command "user:setting admin activity notify_email_libresign_file_signed 1" with result code 0
    And run the command "libresign:install --use-local-cert --java" with result code 0
    And run the command "libresign:install --use-local-cert --jsignpdf" with result code 0
    And run the command "libresign:install --use-local-cert --pdftk" with result code 0
    And run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company --l=City\ Name --ou=Organization\ Unit" with result code 0
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":true}}}] |
    And the response should have a status code 200
    And my inbox is empty
    And reset notifications of user "signer1"
    And reset notifications of user "admin"
    And reset activity of user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"displayName": "Signer Name","identify": {"account": "signer1"}},{"displayName": "Admin","identify": {"account": "admin"}}] |
      | name | Document Name |
    And the response should have a status code 200
    And as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    Then the response should be a JSON array with the following mandatory values
      | key                        | value         |
      | (jq).ocs.data.data[0].name | Document Name |
    And fetch field "(SIGN_UUID)ocs.data.data.0.signers.0.sign_uuid" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    And sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID>"
      | method | clickToSign |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                     | value       |
      | (jq).ocs.data.message   | File signed |
      | (jq).ocs.data.file.uuid | <FILE_UUID> |
    # broking at GitHub Action but worked fine locally
    # Then I open the latest email to "signer@domain.test" with subject "LibreSign: There is a file for you to sign"
    Then I open the latest email to "admin@email.tld" with subject "LibreSign: There is a file for you to sign"
    Then I open the latest email to "admin@email.tld" with subject "LibreSign: A file has been signed"
    When sending "get" to ocs "/apps/notifications/api/v2/notifications"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value                                                         |
      | ocs | (jq).data\|any(.subject == "admin requested your signature on Document Name")|
    When as user "admin"
    And sending "get" to ocs "/apps/notifications/api/v2/notifications"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value                                                         |
      | ocs | (jq).data\|any(.subject == "Signer Name signed Document Name")|
    When sending "get" to ocs "/apps/activity/api/v2/activity/libresign?since=0"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value                                                          |
      | ocs | (jq).data\|any(.subject == "Signer Name signed Document Name") |

  Scenario: Signing a file doesn't send an email or notification
    Given as user "admin"
    And user "signer1" exists
    And set the email of user "signer1" to "signer@domain.test"
    And set the email of user "admin" to "admin@email.tld"
    And run the command "config:app:set activity notify_notification_libresign_file_to_sign --value=0" with result code 0
    And run the command "config:app:set activity notify_email_libresign_file_to_sign --value=0" with result code 0
    And run the command "config:app:set activity notify_notification_libresign_file_signed --value=0" with result code 0
    And run the command "config:app:set activity notify_email_libresign_file_signed --value=0" with result code 0
    And run the command "user:setting signer1 activity notify_email_libresign_file_to_sign 0" with result code 0
    And run the command "user:setting signer1 activity notify_notification_libresign_file_to_sign 0" with result code 0
    And run the command "user:setting admin activity notify_notification_libresign_file_signed 0" with result code 0
    And run the command "user:setting admin activity notify_email_libresign_file_signed 0" with result code 0
    And run the command "libresign:install --use-local-cert --java" with result code 0
    And run the command "libresign:install --use-local-cert --jsignpdf" with result code 0
    And run the command "libresign:install --use-local-cert --pdftk" with result code 0
    And run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company --l=City\ Name --ou=Organization\ Unit" with result code 0
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"account","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":true}}}] |
    And the response should have a status code 200
    And my inbox is empty
    And reset notifications of user "signer1"
    And reset notifications of user "admin"
    And reset activity of user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"displayName": "Signer Name","identify": {"account": "signer1"}},{"displayName": "Admin","identify": {"account": "admin"}}] |
      | name | Document Name |
    And the response should have a status code 200
    And as user "signer1"
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    Then the response should be a JSON array with the following mandatory values
      | key                        | value         |
      | (jq).ocs.data.data[0].name | Document Name |
    And fetch field "(SIGN_UUID)ocs.data.data.0.signers.0.sign_uuid" from previous JSON response
    And fetch field "(FILE_UUID)ocs.data.data.0.uuid" from previous JSON response
    And sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID>"
      | method | clickToSign |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                     | value       |
      | (jq).ocs.data.message   | File signed |
      | (jq).ocs.data.file.uuid | <FILE_UUID> |
    Then there should be 0 email in my inbox
    When sending "get" to ocs "/apps/notifications/api/v2/notifications"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key           | value |
      | (jq).ocs.data | []    |
    When as user "admin"
    And sending "get" to ocs "/apps/notifications/api/v2/notifications"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key           | value |
      | (jq).ocs.data | []    |
    When sending "get" to ocs "/apps/activity/api/v2/activity/libresign?since=0"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value                                                          |
      | ocs | (jq).data\|any(.subject == "Signer Name signed Document Name") |

  Scenario: Signing a file using unauthenticatd signer with click to sign
    Given as user "admin"
    And run the command "config:app:set activity notify_notification_libresign_file_to_sign --value=1" with result code 0
    And run the command "config:app:set activity notify_email_libresign_file_to_sign --value=1" with result code 0
    And run the command "config:app:set activity notify_notification_libresign_file_signed --value=1" with result code 0
    And run the command "config:app:set activity notify_email_libresign_file_signed --value=1" with result code 0
    And run the command "user:setting admin activity notify_notification_libresign_file_signed 1" with result code 0
    And run the command "user:setting admin activity notify_email_libresign_file_signed 1" with result code 0
    And run the command "libresign:install --use-local-cert --java" with result code 0
    And run the command "libresign:install --use-local-cert --jsignpdf" with result code 0
    And run the command "libresign:install --use-local-cert --pdftk" with result code 0
    And run the command "libresign:configure:openssl --cn=Common\ Name --c=BR --o=Organization --st=State\ of\ Company --l=City\ Name --ou=Organization\ Unit" with result code 0
    And sending "post" to ocs "/apps/provisioning_api/api/v1/config/apps/libresign/identify_methods"
      | value | (string)[{"name":"email","enabled":true,"mandatory":true,"signatureMethods":{"clickToSign":{"enabled":true}},"can_create_account":false}] |
    And my inbox is empty
    And reset notifications of user "admin"
    And reset activity of user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"url":"<BASE_URL>/apps/libresign/develop/pdf"} |
      | users | [{"displayName": "Signer Name","identify": {"email": "unauthenticated@email.tld"}}] |
      | name | Document Name |
    And the response should have a status code 200
    And I open the latest email to "unauthenticated@email.tld" with subject "LibreSign: There is a file for you to sign"
    And I fetch the signer UUID from opened email
    And as user ""
    And sending "post" to ocs "/apps/libresign/api/v1/sign/uuid/<SIGN_UUID>"
      | method | clickToSign |
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                     | value       |
      | (jq).ocs.data.message   | File signed |
    When as user "admin"
    And sending "get" to ocs "/apps/notifications/api/v2/notifications"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value                                                         |
      | ocs | (jq).data\|any(.subject == "Signer Name signed Document Name")|
    When sending "get" to ocs "/apps/activity/api/v2/activity/libresign?since=0"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value                                                          |
      | ocs | (jq).data\|any(.subject == "Signer Name signed Document Name") |
