<?php

use Behat\Gherkin\Node\TableNode;
use Libresign\NextcloudBehat\NextcloudApiContext;
use PHPUnit\Framework\Assert;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends NextcloudApiContext {
	private array $signer = [];
	private array $file = [];

	/**
	 * @Then /^the signer "([^"]*)" have a file to sign$/
	 */
	public function theSignerHaveAFileToSign(string $signer): void {
		$this->setCurrentUser($signer);
		$this->sendRequest('get', '/apps/libresign/api/0.1/file/list');
		$response = json_decode($this->response->getBody()->getContents(), true);
		Assert::assertGreaterThan(0, $response['data'], 'Haven\'t files to sign');
		$this->signer = [];
		$this->file = [];
		foreach ($response['data'] as $file) {
			$currentSigner = array_filter($file['signers'], function ($signer): bool {
				return $signer['me'];
			});
			if (count($currentSigner) === 1) {
				$this->signer = current($currentSigner);
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
	 * @return void
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
}
