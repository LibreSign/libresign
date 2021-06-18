<?php

namespace OCA\Libresign\Tests\Unit;

/**
 * @group DB
 */
final class WebhookControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testPostRegisterWithValidationFailure() {
		$this->createUser('username', 'password');
		$this->request
			->withMethod('POST')
			->withPath('/webhook/register')
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
		$this->assertEquals('You are not allowed to request signing', $body['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPostRegisterWithSuccess() {
		$this->createUser('username', 'password');

		$this->mockConfig([
			'libresign' => [
				'webhook_authorized' => '["admin","testGroup"]',
				'notifyUnsignedUser' => 0
			]
		]);

		$this->request
			->withMethod('POST')
			->withPath('/webhook/register')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'name' => 'filename',
				'file' => [
					'base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))
				],
				'users' => [
					[
						'email' => 'user@test.coop'
					]
				]
			]);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$body['data']['users'][] = ['email' => 'user@test.coop'];
		$this->addFile($body['data']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testMeWithoutAuthenticatedUser() {
		$this->request
			->withPath('/webhook/me')
			->assertResponseCode(404);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testMeWithAuthenticatedUser() {
		$this->createUser('username', 'password');
		$this->request
			->withPath('/webhook/me')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			]);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPatchRegisterWithValidationFailure() {
		$this->createUser('username', 'password');
		$this->request
			->withMethod('PATCH')
			->withPath('/webhook/register')
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
		$this->assertEquals('You are not allowed to request signing', $body['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPatchRegisterWithSuccess() {
		$user = $this->createUser('username', 'password');

		$this->mockConfig([
			'libresign' => [
				'webhook_authorized' => '["admin","testGroup"]',
				'notifyUnsignedUser' => 0
			]
		]);

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'email' => 'person@test.coop'
				]
			],
			'userManager' => $user
		]);

		$this->request
			->withMethod('PATCH')
			->withPath('/webhook/register')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'uuid' => $file['uuid'],
				'users' => [
					[
						'email' => 'user@test.coop'
					]
				]
			]);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$body['data']['users'][] = ['email' => 'user@test.coop'];
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDeleteRegisterWithValidationFailure() {
		$user = $this->createUser('username', 'password');

		$this->request
			->withMethod('DELETE')
			->withPath('/webhook/register/signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'uuid' => 'invalid',
				'users' => []
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('You are not allowed to request signing', $body['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDeleteRegisterWithSuccess() {
		$user = $this->createUser('username', 'password');

		$this->mockConfig([
			'libresign' => [
				'webhook_authorized' => '["admin","testGroup"]',
				'notifyUnsignedUser' => 0
			]
		]);

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'email' => 'user01@test.coop'
				]
			],
			'userManager' => $user
		]);

		$this->request
			->withMethod('DELETE')
			->withPath('/webhook/register/signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'uuid' => $file['uuid'],
				'users' => [
					[
						'email' => 'user01@test.coop'
					]
				]
			]);

		$this->assertRequest();
	}
}
