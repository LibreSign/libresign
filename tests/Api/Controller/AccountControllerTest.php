<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Api\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Tests\Api\ApiTestCase;

/**
 * @group DB
 */
final class AccountControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testAccountCreateWithInvalidUuid():void {
		$this->createAccount('username', 'password');

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
			->withPath('/api/v1/account/create/1234564789')
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Invalid UUID', $body['ocs']['data']['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testAccountCreateWithSuccess():void {
		$this->markTestSkipped('Need to reimplement this test, stated to failure after add multiple certificate engine');
		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueString(Application::APP_ID, 'cfssl_bin', '');
		$appConfig->setValueArray(Application::APP_ID, 'rootCert', [
			'commonName' => 'LibreCode',
			'names' => [
				'C' => ['value' => 'BR'],
			]
		]);
		$appConfig->setValueString(Application::APP_ID, 'certificate_engine', 'openssl');

		$user = $this->createAccount('username', 'password');

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
			'userManager' => $user,
		]);
		$this->deleteUserIfExists('guest-user@test.coop');

		$signers = $this->getSignersFromFileId($file->getId());
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
			->withPath('/api/v1/account/create/' . $signers[0]->getUuid());
		$this->markUserExists('guest-user@test.coop');

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPostProfileFilesWithInvalidData():void {
		$this->createAccount('username', 'password');

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
			->withPath('/api/v1/account/files')
			->assertResponseCode(401);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPostAccountAddFilesWithSuccess():void {
		$this->createAccount('username', 'password');

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
			->withPath('/api/v1/account/files');

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testMeWithoutAuthenticatedUser():void {
		$this->request
			->withPath('/api/v1/account/me')
			->assertResponseCode(404);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testMeWithAuthenticatedUser():void {
		$this->createAccount('username', 'password');
		$this->request
			->withPath('/api/v1/account/me')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			]);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testApprovalListWithSuccess():void {
		$this->createAccount('allowapprove', 'password', 'testGroup');

		$this->getMockAppConfig()->setValueArray(Application::APP_ID, 'approval_group', ['testGroup']);

		$this->request
			->withPath('/api/v1/account/files/approval/list')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowapprove:password')
			]);

		$this->assertRequest();
	}
}
