<?php

use Behat\Gherkin\Node\PyStringNode;
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
	private OpenedEmailStorage $openedEmailStorage;

	/**
	 * @BeforeSuite
	 */
	public static function beforeSuite(BeforeSuiteScope $scope) {
		exec('php ../../../../occ config:system:set debug --value true --type boolean', $output);
	}

	/**
	 * @BeforeFeature
	 */
	public static function BeforeFeature(): void {
		self::runCommand('libresign:developer:reset --all');
		self::runCommand('app:enable --force notifications');
	}

	/**
	 * @When run the command :command
	 */
	public static function runCommand($command): void {
		$console = realpath(__DIR__ . '/../../../../../../console.php');
		$owner = posix_getpwuid(fileowner($console));
		$fullCommand = 'php ' . $console . ' ' . $command;
		if (get_current_user() !== $owner['name']) {
			$fullCommand = 'runuser -u ' . $owner['name'] . ' -- ' . $fullCommand;
		}
		exec($fullCommand, $output);
	}

	public function setOpenedEmailStorage(OpenedEmailStorage $storage): void {
		$this->openedEmailStorage = $storage;
	}

	/**
	 * @Then /^the signer "([^"]*)" have a file to sign$/
	 */
	public function theSignerHaveAFileToSign(string $signer): void {
		$this->setCurrentUser($signer);
		$this->sendOCSRequest('get', '/apps/libresign/api/v1/file/list');
		$response = json_decode($this->response->getBody()->getContents(), true);
		Assert::assertGreaterThan(0, $response['data'], 'Haven\'t files to sign');
		$this->signer = [];
		$this->file = [];
		foreach (array_reverse($response['data']) as $file) {
			$currentSigner = array_filter($file['signers'], function ($signer): bool {
				return $signer['me'];
			});
			if (count($currentSigner) === 1) {
				$this->signer = end($currentSigner);
				$this->file = $file;
				break;
			}
		}
		Assert::assertGreaterThan(1, $this->signer, $signer . ' don\'t will sign a file');
		Assert::assertGreaterThan(1, $this->file, 'The /file/list didn\'t returned a file assigned to ' . $signer);
	}

	/**
	 * @Then /^the file to sign contains$/
	 *
	 * @param string $name
	 */
	public function theFileToSignContains(TableNode $table): void {
		if (!$this->file) {
			$this->theSignerHaveAFileToSign($this->currentUser);
		}
		$expectedValues = $table->getColumnsHash();
		foreach ($expectedValues as $value) {
			Assert::assertArrayHasKey($value['key'], $this->file);
			if ($value['value'] === '<IGNORE>') {
				continue;
			}
			Assert::assertEquals($value['value'], $this->file[$value['key']]);
		}
	}

	protected function beforeRequest(string $fullUrl, array $options): array {
		list($fullUrl, $options) = parent::beforeRequest($fullUrl, $options);
		$fullUrl = $this->parseText($fullUrl);
		return [$fullUrl, $options];
	}

	protected function parseText(string $text): string {
		$patterns = [
			'/<SIGN_UUID>/',
			'/<FILE_UUID>/',
		];
		$replacements = [
			$this->signer['sign_uuid'] ?? null,
			$this->file['uuid'] ?? $this->getFileUuidFromText($text),
		];
		$text = preg_replace($patterns, $replacements, $text);
		return $text;
	}

	private function getFileUuidFromText(string $text): ?string {
		if (!$this->isJson($text)) {
			return '';
		}
		$json = json_decode($text, true);
		if (isset($json['sign']['uuid']) && $json['sign']['uuid']) {
			return $this->file['uuid'] = $json['sign']['uuid'];
		}
		return '';
	}

	/**
	 * @Given the signer contains
	 */
	public function theSignerContains(TableNode $table): void {
		if (!$this->signer) {
			$this->theSignerHaveAFileToSign($this->currentUser);
		}
		$expectedValues = $table->getColumnsHash();
		foreach ($expectedValues as $value) {
			Assert::assertArrayHasKey($value['key'], $this->signer);
			if ($value['value'] === '<IGNORE>') {
				continue;
			}
			$actual = $this->signer[$value['key']];
			if (is_array($this->signer[$value['key']]) || is_object($this->signer[$value['key']])) {
				$actual = json_encode($actual);
			}
			Assert::assertEquals($value['value'], $actual, sprintf('The actual value of key "%s" is different of expected', $value['key']));
		}
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
		Assert::arrayHasKey('uuid', $matches, 'UUID not found on email');
		$this->signer['sign_uuid'] = $matches['uuid'];
	}

	/**
	 * @When follow the link on opened email
	 */
	public function iDoSomethingWithTheOpenedEmail(): void {
		if (!$this->openedEmailStorage->hasOpenedEmail()) {
			throw new RuntimeException('No email opened, unable to do something!');
		}

		/** @var \rpkamp\Mailhog\Message\Message $openedEmail */
		$openedEmail = $this->openedEmailStorage->getOpenedEmail();
		preg_match('/p\/sign\/(?<uuid>[\w-]+)"/', $openedEmail->body, $matches);

		$this->sendRequest('get', '/apps/libresign/p/sign/' . $matches['uuid']);
	}

	/**
	 * @When reset notifications of user :user
	 */
	public function resetNotifications($user): void {
		self::runCommand('libresign:developer:reset --notifications=' . $user);
	}

	/**
	 * @When the response of file list match with:
	 */
	public function theResponseOfFileListMatchWith(PyStringNode $expected): void {
		$this->response->getBody()->seek(0);
		$realResponseArray = json_decode($this->response->getBody()->getContents(), true);
		$expectedArray = json_decode($expected, true);
		Assert::arrayHasKey($realResponseArray, 'pagination', 'The response have not pagination');
		Assert::assertJsonStringEqualsJsonString(json_encode($expectedArray['pagination']), json_encode($realResponseArray['pagination']));
		Assert::arrayHasKey($realResponseArray, 'data');
		Assert::assertCount(count($expectedArray['data']), $realResponseArray['data']);
		foreach ($expectedArray['data'] as $fileFey => $file) {
			Assert::assertCount(count($file['signers']), $realResponseArray['data'][$fileFey]['signers']);
			foreach ($file['signers'] as $signerKey => $signer) {
				Assert::assertCount(count($signer['identifyMethods']), $realResponseArray['data'][$fileFey]['signers'][$signerKey]['identifyMethods']);
			}
		}
	}

	/**
	 * @When delete signer :signer from file :file of previous listing
	 */
	public function deleteSignerFromFileOfPreviousListing(int $signerSequence, int $fileSequence): void {
		$this->response->getBody()->seek(0);
		$responseArray = json_decode($this->response->getBody()->getContents(), true);
		$fileId = $responseArray['data'][$fileSequence - 1]['file']['nodeId'];
		$fileUserId = $responseArray['data'][$fileSequence - 1]['signers'][$signerSequence - 1]['fileUserId'];
		$this->sendOCSRequest('delete', '/apps/libresign/api/v1/sign/file_id/' . $fileId . '/'. $fileUserId);
	}

	/**
	 * @When user :user has the following notifications
	 *
	 * @param string $user
	 * @param TableNode|null $body
	 */
	public function userNotifications(string $user, TableNode $body = null): void {
		$this->setCurrentUser($user);
		$this->sendOCSRequest(
			'GET', '/apps/notifications/api/v2/notifications'
		);

		$jsonBody = json_decode($this->response->getBody()->getContents(), true);
		$data = $jsonBody['ocs']['data'];

		if ($body === null) {
			Assert::assertCount(0, $data);
			return;
		}

		Assert::assertCount(count($data), $body, 'Notifications count does not match');
	}
}
