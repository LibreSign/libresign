Feature: file-list
  Background: Create users
    Given user "signer1" exists
    Given user "signer2" exists

  Scenario: Return a list with two files
    Given as user "admin"
    And set the email of user "signer1" to "signer1@domain.test"
    And set the email of user "signer2" to ""
    And sending "post" to ocs "/apps/libresign/api/v1/request-signature"
      | file | {"base64":"data:application/pdf;base64,JVBERi0xLjYKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURlY29kZT4+CnN0cmVhbQp4nDPQM1Qo5ypUMFAw0DMwslAwtTTVMzIxV7AwMdSzMDNUKErlCtdSyOMyVADBonQuA4iUhaVCLheKYqBIDlw7xLAcuLEgFlwVVwZXmhZXoAIAI+sZGAplbmRzdHJlYW0KZW5kb2JqCgozIDAgb2JqCjg2CmVuZG9iagoKNSAwIG9iago8PAo+PgplbmRvYmoKCjYgMCBvYmoKPDwvRm9udCA1IDAgUgovUHJvY1NldFsvUERGL1RleHRdCj4+CmVuZG9iagoKMSAwIG9iago8PC9UeXBlL1BhZ2UvUGFyZW50IDQgMCBSL1Jlc291cmNlcyA2IDAgUi9NZWRpYUJveFswIDAgNTk1LjI3NTU5MDU1MTE4MSA4NDEuODg5NzYzNzc5NTI4XS9Hcm91cDw8L1MvVHJhbnNwYXJlbmN5L0NTL0RldmljZVJHQi9JIHRydWU+Pi9Db250ZW50cyAyIDAgUj4+CmVuZG9iagoKNCAwIG9iago8PC9UeXBlL1BhZ2VzCi9SZXNvdXJjZXMgNiAwIFIKL01lZGlhQm94WyAwIDAgNTk1IDg0MSBdCi9LaWRzWyAxIDAgUiBdCi9Db3VudCAxPj4KZW5kb2JqCgo3IDAgb2JqCjw8L1R5cGUvQ2F0YWxvZy9QYWdlcyA0IDAgUgovT3BlbkFjdGlvblsxIDAgUiAvWFlaIG51bGwgbnVsbCAwXQo+PgplbmRvYmoKCjggMCBvYmoKPDwvQ3JlYXRvcjxGRUZGMDA0NDAwNzIwMDYxMDA3Nz4KL1Byb2R1Y2VyPEZFRkYwMDRDMDA2OTAwNjIwMDcyMDA2NTAwNEYwMDY2MDA2NjAwNjkwMDYzMDA2NTAwMjAwMDM3MDAyRTAwMzA+Ci9DcmVhdGlvbkRhdGUoRDoyMDIxMDIyMzExMDgwOS0wMycwMCcpPj4KZW5kb2JqCgp4cmVmCjAgOQowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAyNzAgMDAwMDAgbiAKMDAwMDAwMDAxOSAwMDAwMCBuIAowMDAwMDAwMTc2IDAwMDAwIG4gCjAwMDAwMDA0MzggMDAwMDAgbiAKMDAwMDAwMDE5NSAwMDAwMCBuIAowMDAwMDAwMjE3IDAwMDAwIG4gCjAwMDAwMDA1MzYgMDAwMDAgbiAKMDAwMDAwMDYxOSAwMDAwMCBuIAp0cmFpbGVyCjw8L1NpemUgOS9Sb290IDcgMCBSCi9JbmZvIDggMCBSCi9JRCBbIDw1RkQ4MDlEMTdFODMwQUU5OTRDODkxNDVBMTMwNUQyQz4KPDVGRDgwOUQxN0U4MzBBRTk5NEM4OTE0NUExMzA1RDJDPiBdCi9Eb2NDaGVja3N1bSAvRDZBQThGQTBBQjMwODg2QkQ5ODU0QzYyMTg5QjI2NDQKPj4Kc3RhcnR4cmVmCjc4NQolJUVPRgo="} |
      | users | [{"identify":{"email":"signer1@domain.test"}},{"identify":{"account":"signer2"}}] |
      | name | document |
    And the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/file/list"
    Then the response of file list match with:
      """
      {
        "data": [
          {
            "uuid": "<IGNORED>",
            "name": "document",
            "callback": null,
            "request_date": "<IGNORED>",
            "status_date": null,
            "requested_by": {
              "uid": "admin",
              "displayName": null
            },
            "type": "pdf",
            "url": "\/index.php\/apps\/libresign\/pdf\/user\/<IGNORED>",
            "nodeId": "<IGNORED>",
            "signers": [
              {
                "email": "signer1@domain.test",
                "description": null,
                "displayName": "signer1@domain.test",
                "request_sign_date": "<IGNORED>",
                "sign_date": null,
                "uid": "",
                "signRequestId": "<IGNORED>",
                "me": false,
                "identifyMethods": [
                  {
                    "method": "email",
                    "mandatory": 1,
                    "identifiedAtDate": null
                  }
                ]
              },
              {
                "email": "",
                "description": null,
                "displayName": "signer2",
                "request_sign_date": "<IGNORED>",
                "sign_date": null,
                "uid": "signer2",
                "signRequestId": "<IGNORED>",
                "me": false,
                "identifyMethods": [
                  {
                    "method": "account",
                    "mandatory": 1,
                    "identifiedAtDate": null
                  }
                ]
              }
            ],
            "status": 2,
            "statusText": "pending"
          }
        ],
        "pagination": {
          "total": 1,
          "current": null,
          "next": null,
          "prev": null,
          "last": null,
          "first": null
        }
      }
      """
    When delete signer 1 from file 1 of previous listing
    And sending "get" to ocs "/apps/libresign/api/v1/file/list"
    Then the response of file list match with:
      """
      {
        "data": [
          {
            "uuid": "<IGNORED>",
            "name": "document",
            "callback": null,
            "request_date": "<IGNORED>",
            "status_date": null,
            "requested_by": {
              "uid": "admin",
              "displayName": null
            },
            "type": "pdf",
            "url": "\/index.php\/apps\/libresign\/pdf\/user\/<IGNORED>",
            "nodeId": "<IGNORED>",
            "signers": [
              {
                "email": "",
                "description": null,
                "displayName": "signer2",
                "request_sign_date": "<IGNORED>",
                "sign_date": null,
                "uid": "signer2",
                "signRequestId": "<IGNORED>",
                "me": false,
                "identifyMethods": [
                  {
                    "method": "account",
                    "mandatory": 1,
                    "identifiedAtDate": null
                  }
                ]
              }
            ],
            "status": 2,
            "statusText": "pending"
          }
        ],
        "pagination": {
          "total": 1,
          "current": null,
          "next": null,
          "prev": null,
          "last": null,
          "first": null
        }
      }
      """
