Feature: sign
  Scenario: Sign setup proccess with success
    And run the command "libresign:install --all --architecture=aarch64 --all-distros --use-local-cert" with result code 0
    And run the command "libresign:install --all --architecture=x86_64 --all-distros --use-local-cert" with result code 0
    And run the command "libresign:developer:sign-setup --privateKey=<appRootDir>/build/tools/certificates/local/libresign.key --certificate=<appRootDir>/build/tools/certificates/local/libresign.crt" with result code 0
    # Verify if have 10 files appinfo/*.json, if is different will throw an error
    # If the quantity of dependencies at setup proccess was changed, will be a number != than 10
    And run the bash command "php -r \"if (count(glob('<appRootDir>/appinfo/*.json')) !== 10) {echo count(glob('<appRootDir>/appinfo/*.json'));exit(1);}\"" with result code 0
