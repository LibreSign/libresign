<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Api\Controller;

use OCA\Libresign\Tests\Api\ApiTestCase;

/**
 * @group DB
 */
final class RequestSignatureControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testPostRegisterWithValidationFailure():void {
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
		$this->assertEquals('You are not allowed to request signing', $body['ocs']['data']['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPostRegisterWithSuccess():void {
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
		$body['ocs']['data']['data']['users'][] = ['email' => 'user@test.coop'];
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPatchRegisterWithValidationFailure():void {
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
		$this->assertEquals('You are not allowed to request signing', $body['ocs']['data']['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPatchRegisterWithSuccess():void {
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
		$body['ocs']['data']['data']['users'][] = ['email' => 'user@test.coop'];
	}
}
