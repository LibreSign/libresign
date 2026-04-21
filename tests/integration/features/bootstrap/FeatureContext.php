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
use LibreSign\Behat\MailpitExtension\Context\OpenedEmailStorageAwareContext;
use LibreSign\Behat\MailpitExtension\Service\OpenedEmailStorage;
use Libresign\NextcloudBehat\NextcloudApiContext;
use PHPUnit\Framework\Assert;

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
		$fields['TSA_URL'] = getenv('LIBRESIGN_TSA_URL') ?: 'https://freetsa.org/tsr';
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
		$this->fields['SIGN_REQUEST_UUID'] = $matches['uuid'];
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

		/** @var \LibreSign\Mailpit\Message\Message $openedEmail */
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
		$this->fields['SIGN_REQUEST_UUID'] = $matches['uuid'];
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

	#[Given('user :user deletes file :path')]
	public function userDeletesFile(string $user, string $path): void {
		$this->setCurrentUser($user);
		$this->davRequest($user, 'DELETE', $path);
		Assert::assertContains($this->response->getStatusCode(), [204, 404], 'Failed to delete file');
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

	#[Given('I force sign request :signRequestUuid file node id to :nodeId')]
	public function iForceSignRequestFileNodeIdTo(string $signRequestUuid, int $nodeId): void {
		$uuid = $this->parseText($signRequestUuid);

		$nextcloudRootDir = self::findParentDirContainingFile('console.php');
		$CONFIG = [];
		require $nextcloudRootDir . '/config/config.php';
		/** @var array<string, mixed> $config */
		$config = $CONFIG;
		$tablePrefix = (string)($config['dbtableprefix'] ?? 'oc_');

		$dbType = (string)$config['dbtype'];
		if ($dbType === 'mysql') {
			$dsn = sprintf(
				'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
				(string)$config['dbhost'],
				(string)($config['dbport'] ?? 3306),
				(string)$config['dbname'],
			);
		} elseif ($dbType === 'pgsql') {
			$dsn = sprintf(
				'pgsql:host=%s;port=%s;dbname=%s',
				(string)$config['dbhost'],
				(string)($config['dbport'] ?? 5432),
				(string)$config['dbname'],
			);
		} else {
			throw new RuntimeException('Unsupported dbtype for this Behat step: ' . $dbType);
		}

		$pdo = new PDO(
			$dsn,
			(string)$config['dbuser'],
			(string)$config['dbpassword'],
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
		);

		$selectStmt = $pdo->prepare(sprintf(
			'SELECT file_id FROM %slibresign_sign_request WHERE uuid = :uuid LIMIT 1',
			$tablePrefix,
		));
		$selectStmt->execute(['uuid' => $uuid]);
		$row = $selectStmt->fetch(PDO::FETCH_ASSOC);
		Assert::assertNotFalse($row, "Sign request not found for UUID: $uuid");

		$updateStmt = $pdo->prepare(sprintf(
			'UPDATE %slibresign_file SET node_id = :node_id WHERE id = :id',
			$tablePrefix,
		));
		$updateStmt->execute([
			'node_id' => $nodeId,
			'id' => (int)$row['file_id'],
		]);

		$affectedRows = $updateStmt->rowCount();
		Assert::assertSame(1, $affectedRows, 'Expected exactly one file row to be updated');
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
