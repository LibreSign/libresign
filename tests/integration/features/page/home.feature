Feature: page/sign_identify_default

  Scenario: Open sign file with all data valid
    Given user "signer1" exists
    When sending "get" to "/apps/libresign/"
    Then the response should have a status code 200
    And the response should contain the initial state "libresign-identify_methods" with the following values:
      """
      {
        "action": 250,
        "sign": {
          "uuid": "<FILE_UUID>",
          "filename": "document",
          "description": null,
          "pdf": {
            "url": "/index.php/apps/libresign/pdf/user/<SIGN_UUID>"
          }
        },
        "user": {
          "name": ""
        },
        "settings": {
          "identifyMethods": [
            {
              "mandatory": 1,
              "identifiedAtDate": null,
              "method": "account"
            }
          ],
          "identificationDocumentsFlow": false,
          "certificateOk": false,
          "hasSignatureFile": false,
          "phoneNumber": "",
          "isApprover": false
        }
      }
      """
