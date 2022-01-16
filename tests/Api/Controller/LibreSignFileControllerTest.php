<?php

namespace OCA\Libresign\Tests\Api\Controller;

use OCA\Libresign\Tests\Api\ApiTestCase;

/**
 * @internal
 * @group DB
 */
final class LibreSignFileControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testValidateUsignUuidWithInvalidData() {
		$this->mockConfig(['libresign' => []]);

		$this->request
			->withPath('/file/validate/uuid/invalid')
			->assertResponseCode(404);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Invalid data to validate file', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testValidateUsignFileIdWithInvalidData() {
		$this->request
			->withPath('/file/validate/file_id/171')
			->assertResponseCode(404);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Invalid data to validate file', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testValidateWithSuccessUsingUnloggedUser() {
		$user = $this->createUser('username', 'password');

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
			->withPath('/file/validate/uuid/' . $file['uuid']);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertFalse($body['signers'][0]['me'], "It's me");
		$this->assertFalse($body['settings']['canRequestSign'], 'Can permission to request sign');
		$this->assertFalse($body['settings']['canSign'], 'Can permission to sign');
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testValidateWithSuccessUsingSigner() {
		$user = $this->createUser('testValidateWithSuccessUsingSigner', 'password');

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
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('testValidateWithSuccessUsingSigner:password')
			])
			->withPath('/file/validate/uuid/' . $file['uuid']);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue($body['signers'][0]['me'], "It's me");
		$this->assertFalse($body['settings']['canRequestSign'], 'Can permission to request sign');
		$this->assertTrue($body['settings']['canSign'], 'Can permission to sign');
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testControllerListWithEmptyData() {
		$this->createUser('testControllerListWithEmptyData', 'password');
		$this->request
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('testControllerListWithEmptyData:password')
			])
			->withPath('/file/list');

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertCount(0, $body['data']);
	}
}
