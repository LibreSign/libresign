<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeSuite;
use Behat\Step\Given;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Libresign\NextcloudBehat\NextcloudApiContext;
use PHPUnit\Framework\Assert;
use rpkamp\Behat\MailhogExtension\Context\OpenedEmailStorageAwareContext;
use rpkamp\Behat\MailhogExtension\Service\OpenedEmailStorage;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends NextcloudApiContext implements OpenedEmailStorageAwareContext {
	private OpenedEmailStorage $openedEmailStorage;

	#[BeforeSuite()]
	public static function beforeSuite(BeforeSuiteScope $scope):void {
		parent::beforeSuite($scope);
		self::runCommand('config:system:set debug --value true --type boolean');
		self::runCommand('app:enable --force notifications');
	}

	#[BeforeScenario()]
	public static function beforeScenario(): void {
		parent::beforeScenario();
		self::runCommand('libresign:developer:reset --all');
	}

	public function setOpenedEmailStorage(OpenedEmailStorage $storage): void {
		$this->openedEmailStorage = $storage;
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

	#[Given('I fetch the signer UUID from opened email')]
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

	#[Given('I send a file to be signed')]
	public function iSendAFileToBeSigned(TableNode $body): void {
		$this->sendOCSRequest('post', '/apps/libresign/api/v1/request-signature', $body);
	}

	#[Given('follow the link on opened email')]
	public function followTheLinkOnOpenedEmail(): void {
		if (!$this->openedEmailStorage->hasOpenedEmail()) {
			throw new RuntimeException('No email opened, unable to do something!');
		}

		/** @var \rpkamp\Mailhog\Message\Message $openedEmail */
		$openedEmail = $this->openedEmailStorage->getOpenedEmail();
		preg_match('/p\/sign\/(?<uuid>[\w-]+)"/', $openedEmail->body, $matches);

		$this->sendRequest('get', '/apps/libresign/p/sign/' . $matches['uuid']);
	}

	#[Given('reset :type of user :user')]
	public function resetNotifications($type, $user): void {
		self::runCommand('libresign:developer:reset --' . $type . '=' . $user);
	}

	#[Given('user :user has the following notifications')]
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

	#[Given('I fetch the signer UUID from notification')]
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

	#[Given('user :user uploads file :source to :path')]
	public function userUploadsFileTo(string $user, string $source, string $path): void {
		$this->setCurrentUser($user);
		$filePath = __DIR__ . '/../assets/' . $source;
		$content = file_exists($filePath) ? file_get_contents($filePath) : "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n0000000052 00000 n\n0000000101 00000 n\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n178\n%%EOF";
		$this->davRequest($user, 'PUT', $path, $content);
		Assert::assertContains($this->response->getStatusCode(), [201, 204], 'Failed to upload file');
	}

	#[Given('user :user gets WebDAV properties for :path')]
	public function userGetsWebDavPropertiesFor(string $user, string $path): void {
		$this->setCurrentUser($user);
		$body = <<<XML
			<?xml version="1.0"?>
			<d:propfind xmlns:d="DAV:" xmlns:nc="http://nextcloud.org/ns" xmlns:oc="http://owncloud.org/ns">
			  <d:prop>
			    <nc:fileid/>
			    <nc:libresign-signature-status/>
			    <nc:libresign-signed-node-id/>
			  </d:prop>
			</d:propfind>
			XML;
		$this->davRequest($user, 'PROPFIND', $path, $body, ['Depth' => '0']);
	}

	#[Given('the WebDAV response should contain property :property with value :value')]
	public function theWebDavResponseShouldContainPropertyWithValue(string $property, string $value): void {
		$result = $this->parseXml()->xpath("//nc:$property");
		Assert::assertNotEmpty($result, "Property nc:$property not found in WebDAV response");
		Assert::assertEquals($this->parseText($value), (string)$result[0], "Property nc:$property has unexpected value");
	}

	#[Given('fetch WebDAV property :property to :alias')]
	public function fetchWebDavPropertyTo(string $property, string $alias): void {
		$result = $this->parseXml()->xpath("//nc:$property");
		Assert::assertNotEmpty($result, "Property nc:$property not found in WebDAV response");
		$this->fields[$alias] = (string)$result[0];
	}

	private function davRequest(string $user, string $method, string $path, ?string $body = null, array $headers = []): void {
		$client = new \GuzzleHttp\Client();
		try {
			$this->response = $client->request($method, $this->baseUrl . '/remote.php/dav/files/' . $user . '/' . $path, [
				'auth' => [$user === 'admin' ? 'admin' : $user, $user === 'admin' ? $this->adminPassword : $this->testPassword],
				'headers' => $headers,
				'body' => $body,
			]);
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			$this->response = $ex->getResponse();
		}
	}

	private function parseXml(): \SimpleXMLElement {
		Assert::assertEquals(207, $this->response->getStatusCode(), 'Expected HTTP 207 Multi-Status');
		$this->response->getBody()->rewind();
		$xml = simplexml_load_string($this->response->getBody()->getContents());
		Assert::assertNotFalse($xml, 'Failed to parse XML response');
		$xml->registerXPathNamespace('nc', 'http://nextcloud.org/ns');
		return $xml;
	}
}
