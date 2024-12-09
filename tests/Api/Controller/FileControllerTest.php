<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Api\Controller;

use OCA\Libresign\Tests\Api\ApiTestCase;

/**
 * @internal
 * @group DB
 */
final class FileControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testValidateUsignUuidWithInvalidData() {
		$this->mockAppConfig([]);

		$this->request
			->withPath('/api/v1/file/validate/uuid/invalid')
			->assertResponseCode(404);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Invalid data to validate file', $body['ocs']['data']['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testValidateUsignFileIdWithInvalidData() {
		$this->request
			->withPath('/api/v1/file/validate/file_id/171')
			->assertResponseCode(404);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Invalid data to validate file', $body['ocs']['data']['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testValidateWithSuccessUsingUnloggedUser() {
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

		$this->request
			->withPath('/api/v1/file/validate/uuid/' . $file->getUuid());

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertFalse($body['ocs']['data']['signers'][0]['me'], "It's me");
		$this->assertFalse($body['ocs']['data']['settings']['canRequestSign'], 'Can permission to request sign');
		$this->assertFalse($body['ocs']['data']['settings']['canSign'], 'Can permission to sign');
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testValidateWithSuccessUsingSigner() {
		$user = $this->createAccount('username', 'password');
		$user->setEMailAddress('person@test.coop');
		$this->mockAppConfig([
			'identify_methods' => [
				[
					'name' => 'email',
					'enabled' => 1,
				],
			],
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
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			])
			->withPath('/api/v1/file/validate/uuid/' . $file->getUuid());

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue($body['ocs']['data']['signers'][0]['me'], "It's me");
		$this->assertFalse($body['ocs']['data']['settings']['canRequestSign'], 'Can permission to request sign');
		$this->assertTrue($body['ocs']['data']['settings']['canSign'], 'Can permission to sign');
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testControllerListWithEmptyData() {
		$this->createAccount('username', 'password');
		$this->request
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			])
			->withPath('/api/v1/file/list');

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertCount(0, $body['ocs']['data']['data']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSendNewFile() {
		$this->createAccount('allowrequestsign', 'password');
		$this->mockAppConfig([
			'groups_request_sign' => '["admin","testGroup"]',
		]);
		$this->request
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password'),
				'Content-Type' => 'application/json',
			])
			->withPath('/api/v1/file')
			->withMethod('POST')
			->withRequestBody([
				'name' => 'test',
				'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			]);

		$this->assertRequest();
	}
}
