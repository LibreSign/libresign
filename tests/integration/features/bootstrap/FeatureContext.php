<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Behat\Gherkin\Node\TableNode;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Libresign\NextcloudBehat\NextcloudApiContext;
use PHPUnit\Framework\Assert;
use rpkamp\Behat\MailhogExtension\Context\OpenedEmailStorageAwareContext;
use rpkamp\Behat\MailhogExtension\Service\OpenedEmailStorage;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends NextcloudApiContext implements OpenedEmailStorageAwareContext {
	private array $signer = [];
	private array $file = [];
	private static array $environments = [];
	private array $customHeaders = [];
	private OpenedEmailStorage $openedEmailStorage;

	/**
	 * @BeforeSuite
	 */
	public static function beforeSuite(BeforeSuiteScope $scope):void {
		if (get_current_user() !== exec('whoami')) {
			throw new Exception(sprintf("Have files that %s is the owner and the user that is running this test is %s, is necessary to be the same user.\n You should prefix your command to run the behat test with 'runuser -u %s'", get_current_user(), exec('whoami'), get_current_user()));
		}
		self::runCommand('config:system:set debug --value true --type boolean');
		self::runCommand('app:enable --force notifications');
	}

	/**
	 * @BeforeScenario
	 */
	public static function BeforeScenario(): void {
		self::$environments = [];
		self::runCommand('libresign:developer:reset --all');
	}

	/**
	 * @When /^run the command "(?P<command>(?:[^"]|\\")*)"$/
	 */
	public static function runCommand(string $command): array {
		$console = realpath(__DIR__ . '/../../../../../../console.php');
		$owner = posix_getpwuid(fileowner($console));
		$fullCommand = 'php ' . $console . ' ' . $command;
		if (posix_getuid() !== $owner['uid']) {
			$fullCommand = 'runuser -u ' . $owner['name'] . ' -- ' . $fullCommand;
		}
		if (!empty(self::$environments)) {
			$fullCommand = http_build_query(self::$environments, '', ' ') . ' ' . $fullCommand;
		}
		$fullCommand .= '  2>&1';
		return self::runBashCommand($fullCommand);
	}

	private static function runBashCommand(string $command): array {
		$command = str_replace('\"', '"', $command);
		$patterns = [];
		$replacements = [];
		$fields['libresignRootDir'] = realpath(__DIR__ . '/../../../../');
		foreach ($fields as $key => $value) {
			$patterns[] = '/<' . $key . '>/';
			$replacements[] = $value;
		}
		$command = preg_replace($patterns, $replacements, $command);

		exec($command, $output, $resultCode);
		return [
			'command' => $command,
			'output' => $output,
			'resultCode' => $resultCode,
		];
	}

	/**
	 * @When /^run the command "(?P<command>(?:[^"]|\\")*)" with result code (\d+)$/
	 */
	public static function runCommandWithResultCode(string $command, int $resultCode = 0): void {
		$return = self::runCommand($command);
		Assert::assertEquals($resultCode, $return['resultCode'], print_r($return, true));
	}

	/**
	 * @When /^run the bash command "(?P<command>(?:[^"]|\\")*)" with result code (\d+)$/
	 */
	public static function runBashCommandWithResultCode(string $command, int $resultCode = 0): void {
		$return = self::runBashCommand($command);
		Assert::assertEquals($resultCode, $return['resultCode'], print_r($return, true));
	}

	/**
	 * @Given create an environment :name with value :value to be used by occ command
	 */
	public static function createAnEnvironmentWithValueToBeUsedByOccCommand($name, $value):void {
		self::$environments[$name] = $value;
	}

	/**
	 * @When guest :guest exists
	 */
	public function assureGuestExists(string $guest): void {
		$response = $this->userExists($guest);
		if ($response->getStatusCode() !== 200) {
			$this->createAnEnvironmentWithValueToBeUsedByOccCommand('OC_PASS', '123456');
			$this->runCommandWithResultCode('guests:add admin ' . $guest . ' --password-from-env', 0);
			// Set a display name different than the user ID to be able to
			// ensure in the tests that the right value was returned.
			$this->setUserDisplayName($guest);
			$this->createdUsers[] = $guest;
		}
	}

	public function setOpenedEmailStorage(OpenedEmailStorage $storage): void {
		$this->openedEmailStorage = $storage;
	}

	public function sendRequest(string $verb, string $url, $body = null, array $headers = [], array $options = []): void {
		if (!is_null($this->currentUser)) {
			$options = array_merge(
				['cookies' => $this->getUserCookieJar($this->currentUser)],
				$options
			);
		}
		$headers = array_merge($headers, $this->customHeaders);
		parent::sendRequest($verb, $url, $body, $headers, $options);
	}

	/**
	 * @Given /^set the custom http header "([^"]*)" with "([^"]*)" as value to next request$/
	 */
	public function setTheCustomHttpHeaderAsValueToNextRequest(string $header, string $value):void {
		if (empty($value)) {
			unset($this->customHeaders[$header]);
			return;
		}
		$this->customHeaders[$header] = $this->parseText($value);
	}

	protected function beforeRequest(string $fullUrl, array $options): array {
		[$fullUrl, $options] = parent::beforeRequest($fullUrl, $options);
		$options = $this->parseFormParams($options);
		$fullUrl = $this->parseText($fullUrl);
		return [$fullUrl, $options];
	}

	protected function parseText(string $text): string {
		$fields = $this->fields;
		$fields['BASE_URL'] = $this->baseUrl . '/index.php';
		foreach ($fields as $key => $value) {
			$patterns[] = '/<' . $key . '>/';
			$replacements[] = $value;
		}
		$text = preg_replace($patterns, $replacements, $text);
		$text = parent::parseText($text);
		return $text;
	}

	/**
	 * @When I fetch the signer UUID from opened email
	 */
	public function iFetchTheLinkOnOpenedEmail(): void {
		if (!$this->openedEmailStorage->hasOpenedEmail()) {
			throw new RuntimeException('No email opened, unable to do something!');
		}

		/** @var \rpkamp\Mailhog\Message\Message $openedEmail */
		$openedEmail = $this->openedEmailStorage->getOpenedEmail();
		preg_match('/p\/sign\/(?<uuid>[\w-]+)"/', $openedEmail->body, $matches);
		Assert::assertArrayHasKey('uuid', $matches, 'UUID not found on email');
		$this->fields['SIGN_UUID'] = $matches['uuid'];
	}

	/**
	 * @when I send a file to be signed
	 */
	public function iSendAFileToBeSigned(TableNode $body): void {
		$this->sendOCSRequest('post', '/apps/libresign/api/v1/request-signature', $body);
	}

	/**
	 * @When follow the link on opened email
	 */
	public function followTheLinkOnOpenedEmail(): void {
		if (!$this->openedEmailStorage->hasOpenedEmail()) {
			throw new RuntimeException('No email opened, unable to do something!');
		}

		/** @var \rpkamp\Mailhog\Message\Message $openedEmail */
		$openedEmail = $this->openedEmailStorage->getOpenedEmail();
		preg_match('/p\/sign\/(?<uuid>[\w-]+)"/', $openedEmail->body, $matches);

		$this->sendRequest('get', '/apps/libresign/p/sign/' . $matches['uuid']);
	}

	/**
	 * @When reset :type of user :user
	 */
	public function resetNotifications($type, $user): void {
		self::runCommand('libresign:developer:reset --' . $type . '=' . $user);
	}

	/**
	 * @When /^wait for ([0-9]+) (second|seconds)$/
	 */
	public function waitForXSecond(int $seconds): void {
		sleep($seconds);
	}

	/**
	 * @When user :user has the following notifications
	 */
	public function userNotifications(string $user, ?TableNode $body = null): void {
		$this->setCurrentUser($user);
		$this->sendOCSRequest(
			'GET', '/apps/notifications/api/v2/notifications'
		);

		$jsonBody = json_decode($this->response->getBody()->getContents(), true);
		if ($this->response->getStatusCode() === 500) {
			throw new Exception('Internal failure when access notifications endpoint');
		}
		$data = $jsonBody['ocs']['data'];

		if ($body === null) {
			Assert::assertCount(0, $data);
			return;
		}
		$expectedValues = $body->getColumnsHash();
		foreach ($expectedValues as $expected) {
			ksort($expected);
			$found = false;
			foreach ($data as $actual) {
				$actualIntersect = array_filter(
					$actual,
					function ($k) use ($expected):bool {
						return isset($expected[$k]);
					},
					ARRAY_FILTER_USE_KEY,
				);
				ksort($actualIntersect);
				if ($expected === $actualIntersect) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				throw new Exception('Notification not found: ' . json_encode($expected));
			}
		}
	}

	/**
	 * @When I fetch the signer UUID from notification
	 */
	public function iFetchTheSignerUuidFromNotification(): void {
		$this->sendOCSRequest(
			'GET', '/apps/notifications/api/v2/notifications'
		);

		$jsonBody = json_decode($this->response->getBody()->getContents(), true);
		$data = $jsonBody['ocs']['data'];

		$found = array_filter(
			$data,
			function ($notification):bool {
				return $notification['subject'] === 'admin requested your signature on document';
			}
		);
		if (empty($found)) {
			throw new Exception('Notification with the subject [admin requested your signature on document] not found');
		}
		$found = current($found);


		preg_match('/f\/sign\/(?<uuid>[\w-]+)\/pdf$/', $found['link'], $matches);
		Assert::assertArrayHasKey('uuid', $matches, 'UUID not found on email');
		$this->fields['SIGN_UUID'] = $matches['uuid'];
	}
}
