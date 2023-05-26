<?php

use Behat\Gherkin\Node\TableNode;
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
	public static function beforeSuite(): void {
		$console = realpath(__DIR__ . '/../../../../../../console.php');
		$owner = posix_getpwuid(fileowner($console));
		$command = 'runuser -u ' . $owner['name'] . ' -- php ' . $console . ' libresign:developer:reset --all';
		exec($command, $output);
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
			$this->file['uuid'] ?? null,
		];
		$text = preg_replace($patterns, $replacements, $text);
		return $text;
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
			Assert::assertEquals($value['value'], $actual);
		}
	}

	/**
	 * @Then follow the link on opened email
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
