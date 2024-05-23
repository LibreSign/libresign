<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
	public function testValidateUsignUuidWithInvalidData():void {
		$this->mockAppConfig([]);

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
	public function testValidateUsignFileIdWithInvalidData():void {
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
	public function testValidateWithSuccessUsingUnloggedUser():void {
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
			->withPath('/file/validate/uuid/' . $file->getUuid());

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertFalse($body['signers'][0]['me'], "It's me");
		$this->assertFalse($body['settings']['canRequestSign'], 'Can permission to request sign');
		$this->assertFalse($body['settings']['canSign'], 'Can permission to sign');
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testValidateWithSuccessUsingSigner():void {
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
			->withPath('/file/validate/uuid/' . $file->getUuid());

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue($body['signers'][0]['me'], "It's me");
		$this->assertFalse($body['settings']['canRequestSign'], 'Can permission to request sign');
		$this->assertTrue($body['settings']['canSign'], 'Can permission to sign');
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testControllerListWithEmptyData():void {
		$this->createAccount('username', 'password');
		$this->request
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			])
			->withPath('/file/list');

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertCount(0, $body['data']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSendNewFile():void {
		$this->createAccount('allowrequestsign', 'password');
		$this->mockAppConfig([
			'groups_request_sign' => '["admin","testGroup"]',
		]);
		$this->request
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password'),
				'Content-Type' => 'application/json',
			])
			->withPath('/file')
			->withMethod('POST')
			->withRequestBody([
				'name' => 'test',
				'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			]);

		$this->assertRequest();
	}
}
