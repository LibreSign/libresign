<?php

namespace OCA\Libresign\Tests\Api\Controller;

use OCA\Libresign\Tests\Api\ApiTestCase;

/**
 * @group DB
 */
final class AccountControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testAccountCreateWithInvalidUuid() {
		$this->createUser('username', 'password');

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'email' => 'testuser01@test.coop',
				'password' => 'secret',
				'signPassword' => 'secretToSign'
			])
			->withPath('/account/create/1234564789')
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Invalid UUID', $body['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testAccountCreateWithSuccess() {
		$this->mockConfig([
			'libresign' => [
				'cfssl_bin' => '',
				'rootCert' => json_encode([
					'commonName' => 'LibreCode',
					'names' => [
						'C' => ['value' => 'BR'],
					]
				]),
			],
		]);

		$user = $this->createUser('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'identify' => [
						'email' => 'guest-user@test.coop',
					],
				],
			],
			'userManager' => $user
		]);
		$this->deleteUserIfExists('guest-user@test.coop');

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'email' => 'guest-user@test.coop',
				'password' => 'secret',
				'signPassword' => 'secretToSign'
			])
			->withPath('/account/create/' . $file['users'][0]->getUuid());
		$this->markUserExists('guest-user@test.coop');

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPostProfileFilesWithInvalidData() {
		$this->createUser('username', 'password');

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'files' => [
					[
						'type' => 'INVALID',
						'file' => [
							'base64' => 'invalid'
						]
					]
				]
			])
			->withPath('/account/files')
			->assertResponseCode(401);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPostAccountAddFilesWithSuccess() {
		$this->createUser('username', 'password');

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'files' => [
					[
						'type' => 'IDENTIFICATION',
						'file' => [
							'base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))
						]
					]
				]
			])
			->withPath('/account/files');

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testMeWithoutAuthenticatedUser() {
		$this->request
			->withPath('/account/me')
			->assertResponseCode(404);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testMeWithAuthenticatedUser() {
		$this->createUser('username', 'password');
		$this->request
			->withPath('/account/me')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			]);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testApprovalListWithSuccess() {
		$this->createUser('username', 'password');

		$this->mockConfig([
			'libresign' => [
				'approval_group' => '["testGroup"]'
			]
		]);

		$this->request
			->withPath('/account/files/approval/list')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			]);

		$this->assertRequest();
	}
}
