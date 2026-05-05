Feature: settings/user-config
  Background: Create users
    Given user "signer1" exists

  Scenario: Unauthenticated user cannot save config
    Given as user ""
    And sending "put" to ocs "/apps/libresign/api/v1/account/config/files_list_grid_view"
      | value | 1 |
    Then the response should have a status code 401

  Scenario: Batch CRUD for all user config keys
    Given as user "signer1"

    # Save all configs and validate returned value
    And sending "put" to ocs "/apps/libresign/api/v1/account/config/files_list_grid_view"
      | value | 1 |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key               | value                |
      | (jq).ocs.data.key | files_list_grid_view |
      | (jq).ocs.data.value | 1                  |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/files_list_signer_identify_tab"
      | value | cpf_cnpj |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                        |
      | (jq).ocs.data.key   | files_list_signer_identify_tab |
      | (jq).ocs.data.value | cpf_cnpj                     |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/files_list_sorting_mode"
      | value | date |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                   |
      | (jq).ocs.data.key   | files_list_sorting_mode |
      | (jq).ocs.data.value | date                    |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/files_list_sorting_direction"
      | value | desc |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                        |
      | (jq).ocs.data.key   | files_list_sorting_direction |
      | (jq).ocs.data.value | desc                         |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/policy_workbench_catalog_compact_view"
      | value | 1 |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                                 |
      | (jq).ocs.data.key   | policy_workbench_catalog_compact_view |
      | (jq).ocs.data.value | 1                                     |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/policy_workbench_catalog_collapsed"
      | value | 1 |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                              |
      | (jq).ocs.data.key   | policy_workbench_catalog_collapsed |
      | (jq).ocs.data.value | 1                                  |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/policy_workbench_category_collapsed_state"
      | value | {"advanced":true,"signatures":false,"terms":true} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                                             |
      | (jq).ocs.data.key   | policy_workbench_category_collapsed_state         |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/crl_filters"
      | value | {"status":"active","url":""} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                        |
      | (jq).ocs.data.key   | crl_filters                  |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/crl_sort"
      | value | {"field":"date","direction":"asc"} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                              |
      | (jq).ocs.data.key   | crl_sort                           |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/id_docs_filters"
      | value | {"type":"national_id","status":""} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                              |
      | (jq).ocs.data.key   | id_docs_filters                    |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/id_docs_sort"
      | value | {"field":"date","direction":"desc"} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                               |
      | (jq).ocs.data.key   | id_docs_sort                        |

    # Update all configs and validate returned value
    And sending "put" to ocs "/apps/libresign/api/v1/account/config/files_list_grid_view"
      | value | 0 |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                |
      | (jq).ocs.data.key   | files_list_grid_view |
      | (jq).ocs.data.value | 0                    |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/files_list_signer_identify_tab"
      | value | email_tab |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                        |
      | (jq).ocs.data.key   | files_list_signer_identify_tab |
      | (jq).ocs.data.value | email_tab                    |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/files_list_sorting_mode"
      | value | name |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                   |
      | (jq).ocs.data.key   | files_list_sorting_mode |
      | (jq).ocs.data.value | name                    |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/files_list_sorting_direction"
      | value | asc |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                        |
      | (jq).ocs.data.key   | files_list_sorting_direction |
      | (jq).ocs.data.value | asc                          |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/policy_workbench_catalog_compact_view"
      | value | 0 |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                                 |
      | (jq).ocs.data.key   | policy_workbench_catalog_compact_view |
      | (jq).ocs.data.value | 0                                     |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/policy_workbench_catalog_collapsed"
      | value | 0 |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                              |
      | (jq).ocs.data.key   | policy_workbench_catalog_collapsed |
      | (jq).ocs.data.value | 0                                  |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/policy_workbench_category_collapsed_state"
      | value | {"advanced":false,"signatures":true,"terms":false} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                                              |
      | (jq).ocs.data.key   | policy_workbench_category_collapsed_state          |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/crl_filters"
      | value | {"status":"inactive","url":"https://example.com"} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                                             |
      | (jq).ocs.data.key   | crl_filters                                       |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/crl_sort"
      | value | {"field":"name","direction":"desc"} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                               |
      | (jq).ocs.data.key   | crl_sort                            |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/id_docs_filters"
      | value | {"type":"passport","status":"pending"} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                                  |
      | (jq).ocs.data.key   | id_docs_filters                        |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/id_docs_sort"
      | value | {"field":"name","direction":"asc"} |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                              |
      | (jq).ocs.data.key   | id_docs_sort                       |

    # Delete all configs (empty string) and validate returned value
    And sending "put" to ocs "/apps/libresign/api/v1/account/config/files_list_grid_view"
      | value | |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                |
      | (jq).ocs.data.key   | files_list_grid_view |
      | (jq).ocs.data.value |                      |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/files_list_signer_identify_tab"
      | value | |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                        |
      | (jq).ocs.data.key   | files_list_signer_identify_tab |
      | (jq).ocs.data.value |                               |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/files_list_sorting_mode"
      | value | |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                   |
      | (jq).ocs.data.key   | files_list_sorting_mode |
      | (jq).ocs.data.value |                         |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/files_list_sorting_direction"
      | value | |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                        |
      | (jq).ocs.data.key   | files_list_sorting_direction |
      | (jq).ocs.data.value |                              |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/policy_workbench_catalog_compact_view"
      | value | |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                                 |
      | (jq).ocs.data.key   | policy_workbench_catalog_compact_view |
      | (jq).ocs.data.value |                                       |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/policy_workbench_catalog_collapsed"
      | value | |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                              |
      | (jq).ocs.data.key   | policy_workbench_catalog_collapsed |
      | (jq).ocs.data.value |                                    |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/policy_workbench_category_collapsed_state"
      | value | |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value                                     |
      | (jq).ocs.data.key   | policy_workbench_category_collapsed_state |
      | (jq).ocs.data.value |                                           |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/crl_filters"
      | value | |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value       |
      | (jq).ocs.data.key   | crl_filters |
      | (jq).ocs.data.value |             |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/crl_sort"
      | value | |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value    |
      | (jq).ocs.data.key   | crl_sort |
      | (jq).ocs.data.value |          |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/id_docs_filters"
      | value | |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value          |
      | (jq).ocs.data.key   | id_docs_filters |
      | (jq).ocs.data.value |                |

    And sending "put" to ocs "/apps/libresign/api/v1/account/config/id_docs_sort"
      | value | |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                 | value       |
      | (jq).ocs.data.key   | id_docs_sort |
      | (jq).ocs.data.value |             |
