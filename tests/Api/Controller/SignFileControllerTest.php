<?php

namespace OCA\Libresign\Tests\Api\Controller;

use donatj\MockWebServer\Response;
use Jeidison\JSignPDF\JSignPDF;
use OCA\Libresign\Tests\Api\ApiTestCase;

/**
 * @group DB
 */
final class SignFileControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithInvalidFileToSign() {
		$this->createAccount('allowrequestsign', 'password', 'testGroup');
		$this->mockAppConfig([
			'groups_request_sign' => '["admin","testGroup"]',
			'notifyUnsignedUser' => 0,
		]);
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/sign/file_id/171')
			->withRequestBody([
				'identifyValue' => 'secretPassword',
				'method' => 'password',
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('File not found', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithInvalidUuidToSign() {
		$this->createAccount('username', 'password');
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/sign/uuid/invalid')
			->withRequestBody([
				'identifyValue' => 'secretPassword',
				'method' => 'password',
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Invalid UUID', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithAlreadySignedFile() {
		$user = $this->createAccount('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'identify' => [
						'account' => 'username',
					],
				],
			],
			'userManager' => $user,
		]);
		$signers = $this->getSignersFromFileId($file->getId());
		$signers[0]->setSigned(time());
		$signRequest = \OC::$server->get(\OCA\Libresign\Db\SignRequestMapper::class);
		$signRequest->update($signers[0]);

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/sign/uuid/' . $signers[0]->getUuid())
			->withRequestBody([
				'identifyValue' => 'secretPassword',
				'method' => 'password',
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('File already signed.', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithNotFoundFile() {
		$this->markTestSkipped('Neet to assign visible elements to signrequest and not to nextcloud account');
		$user = $this->createAccount('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'identify' => [
						'email' => 'person@test.coop',
					],
				],
			],
			'userManager' => $user,
		]);
		$folderService = \OC::$server->get(\OCA\Libresign\Service\FolderService::class);
		$libresignFolder = $folderService->getFolder();
		$libresignFolder->delete();

		$signers = $this->getSignersFromFileId($file->getId());
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/sign/uuid/' . $signers[0]->getUuid())
			->withRequestBody([
				'identifyValue' => 'secretPassword',
				'method' => 'password',
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('File not found', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithoutPfx() {
		$this->markTestSkipped('Neet to assign visible elements to signrequest and not to nextcloud account');
		$user = $this->createAccount('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'identify' => [
						'email' => 'person@test.coop',
					],
				],
			],
			'userManager' => $user,
		]);

		$signers = $this->getSignersFromFileId($file->getId());
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/sign/uuid/' . $signers[0]->getUuid())
			->withRequestBody([
				'password' => ''
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals(200, $body['action']);
		$this->assertEquals('Empty identify data.', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithEmptyCertificatePassword() {
		$this->markTestSkipped('Neet to assign visible elements to signrequest and not to nextcloud account');
		$this->mockAppConfig([
			'cfssl_bin' => '',
			'java_path' => __FILE__,
			'rootCert' => json_encode([
				'commonName' => 'LibreCode',
				'names' => [
					'C' => ['value' => 'BR'],
				]
			]),
		]);

		$user = $this->createAccount('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'identify' => [
						'email' => 'person@test.coop',
					],
				],
			],
			'userManager' => $user,
		]);
		$pkcs12Handler = \OC::$server->get(\OCA\Libresign\Handler\Pkcs12Handler::class);
		$certificate = $pkcs12Handler->generateCertificate(
			[
				'host' => 'person@test.coop',
				'name' => 'John Doe',
			],
			'secretPassword',
			'username'
		);
		$pkcs12Handler->savePfx('person@test.coop', $certificate);

		$signers = $this->getSignersFromFileId($file->getId());
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/sign/uuid/' . $signers[0]->getUuid())
			->withRequestBody([
				'password' => ''
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Empty identify data.', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithSuccess() {
		$this->markTestSkipped('Neet to assign visible elements to signrequest and not to nextcloud account');
		$this->mockAppConfig([
			'cfssl_bin' => '',
			'java_path' => __FILE__,
			'rootCert' => json_encode([
				'commonName' => 'LibreCode',
				'names' => [
					'C' => ['value' => 'BR'],
				]
			]),
		]);

		$user = $this->createAccount('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'identify' => [
						'email' => 'person@test.coop',
					],
				],
			],
			'userManager' => $user,
		]);
		$pkcs12Handler = \OC::$server->get(\OCA\Libresign\Handler\Pkcs12Handler::class);
		$certificate = $pkcs12Handler->generateCertificate(
			[
				'host' => 'person@test.coop',
				'name' => 'John Doe',
			],
			'secretPassword',
			'username'
		);
		$pkcs12Handler->savePfx('person@test.coop', $certificate);

		$mock = $this->createMock(JSignPDF::class);
		$mock->method('sign')->willReturn('content');
		$jsignHandler = \OC::$server->get(\OCA\Libresign\Handler\JSignPdfHandler::class);
		$jsignHandler->setJSignPdf($mock);
		\OC::$server->registerService(\OCA\Libresign\Handler\JSignPdfHandler::class, function () use ($jsignHandler) {
			return $jsignHandler;
		});

		$signers = $this->getSignersFromFileId($file->getId());
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/sign/uuid/' . $signers[0]->getUuid())
			->withRequestBody([
				'identifyValue' => 'secretPassword',
				'method' => 'password',
			]);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPostRegisterWithValidationFailure() {
		$this->createAccount('username', 'password');
		$this->request
			->withMethod('POST')
			->withPath('/request-signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'name' => 'filename',
				'file' => [],
				'users' => []
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('You are not allowed to request signing', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPostRegisterWithSuccess() {
		$this->createAccount('allowrequestsign', 'password', 'testGroup');

		$this->mockAppConfig([
			'groups_request_sign' => '["admin","testGroup"]',
			'notifyUnsignedUser' => 0,
		]);

		$this->request
			->withMethod('POST')
			->withPath('/request-signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'name' => 'filename',
				'file' => [
					'base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))
				],
				'users' => [
					[
						'identify' => [
							'email' => 'user@test.coop',
						],
					],
				],
			]);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$body['data']['users'][] = ['email' => 'user@test.coop'];
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPatchRegisterWithValidationFailure() {
		$this->createAccount('username', 'password');
		$this->request
			->withMethod('PATCH')
			->withPath('/request-signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'uuid' => '12345678-1234-1234-1234-123456789012',
				'users' => []
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('You are not allowed to request signing', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPatchRegisterWithSuccess() {
		$user = $this->createAccount('allowrequestsign', 'password', 'testGroup');

		$this->mockAppConfig([
			'groups_request_sign' => '["admin","testGroup"]',
			'notifyUnsignedUser' => 0,
		]);

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'identify' => [
						'email' => 'person@test.coop',
					],
				],
			],
			'userManager' => $user,
		]);

		$this->request
			->withMethod('PATCH')
			->withPath('/request-signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'uuid' => $file->getUuid(),
				'users' => [
					[
						'identify' => [
							'email' => 'user@test.coop',
						],
					],
				],
			]);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$body['data']['users'][] = ['email' => 'user@test.coop'];
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testAccountSignatureEndpointWithSuccess() {
		$this->markTestSkipped('Need to reimplement this test, stated to failure');
		$user = $this->createAccount('username', 'password');
		$user->setEMailAddress('person@test.coop');

		self::$server->setResponseOfPath('/api/v1/cfssl/newcert', new Response(
			file_get_contents(__DIR__ . '/../../fixtures/cfssl/newcert-with-success.json')
		));

		$this->mockAppConfig([
			'notifyUnsignedUser' => 0,
			'rootCert' => json_encode([
				'commonName' => 'LibreCode',
				'names' => [
					'C' => ['value' => 'BR'],
					'ST' => ['value' => 'RJ'],
					'L' => ['value' => 'Rio de Janeiro'],
					'O' => ['value' => 'LibreCode Coop'],
					'OU' => ['value' => 'LibreSign']
				]
			]),
			'cfsslUri' => self::$server->getServerRoot() . '/api/v1/cfssl/',
			'cfssl_bin' => '',
		]);

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'signPassword' => 'password'
			])
			->withPath('/account/signature');

		$home = $user->getHome();
		$this->assertFileDoesNotExist($home . '/files/LibreSign/signature.pfx');
		$this->assertRequest();
		$this->assertFileExists($home . '/files/LibreSign/signature.pfx');
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testAccountSignatureEndpointWithFailure() {
		$this->createAccount('username', 'password');

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'signPassword' => ''
			])
			->withPath('/account/signature')
			->assertResponseCode(401);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDeleteSignFileIdSignRequestIdWithSuccess() {
		$user = $this->createAccount('allowrequestsign', 'password', 'testGroup');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'identify' => [
						'email' => 'person@test.coop',
					],
				],
			],
			'userManager' => $user,
		]);

		$this->mockAppConfig([
			'groups_request_sign' => '["admin","testGroup"]',
		]);

		$signers = $this->getSignersFromFileId($file->getId());
		$this->request
			->withMethod('DELETE')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password')
			])
			->withPath('/sign/file_id/' . $file->getNodeId() . '/' . $signers[0]->getId());

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDeleteSignFileIdSignRequestIdWithError() {
		$user = $this->createAccount('username', 'password');

		$this->request
			->withMethod('DELETE')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			])
			->withPath('/sign/file_id/171/171')
			->assertResponseCode(422);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDeleteUsingSignFileIdWithSuccess() {
		$user = $this->createAccount('allowrequestsign', 'password', 'testGroup');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'identify' => [
						'email' => 'person@test.coop',
					],
				],
			],
			'userManager' => $user,
		]);

		$this->mockAppConfig([
			'groups_request_sign' => '["admin","testGroup"]',
		]);

		$this->request
			->withMethod('DELETE')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password')
			])
			->withPath('/sign/file_id/' . $file->getNodeId());

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDeleteUsingSignFileIdWithError() {
		$user = $this->createAccount('username', 'password');

		$this->request
			->withMethod('DELETE')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			])
			->withPath('/sign/file_id/171')
			->assertResponseCode(422);

		$this->assertRequest();
	}
}
