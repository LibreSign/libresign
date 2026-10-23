# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: secure footer template previews
  Scenario: Non-admin cannot write global footer settings through the preview endpoint
    Given as user "admin"
    And user "signer1" exists
    And as user "signer1"
    When sending "post" to ocs "/apps/libresign/api/v1/admin/footer-template/preview-pdf"
      | template | <div>Non-admin preview</div> |
      | width | 595 |
      | height | 100 |
    Then the response should have a status code 403

  Scenario: Admin may preview a Twig template without persisting it
    Given as user "admin"
    And sending "post" to ocs "/apps/libresign/api/v1/admin/footer-template"
      | template | <div>Persisted footer</div> |
      | width | 595 |
      | height | 100 |
    And the response should have a status code 200
    When sending "post" to ocs "/apps/libresign/api/v1/admin/footer-template/preview-pdf"
      | template | <div>Transient footer preview</div> |
      | width | 595 |
      | height | 100 |
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/libresign/api/v1/admin/footer-template"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key | value |
      | (jq).ocs.data.template | <div>Persisted footer</div> |
