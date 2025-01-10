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
final class NotifyControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testNotifySignersWithError():void {
		$this->createAccount('username', 'password');
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'fileId' => 171,
				'signers' => [
					[
						'email' => 'invalid@test.coop'
					]
				]
			])
			->withPath('/api/v1/notify/signers')
			->assertResponseCode(401);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testNotifySignersWithSuccess():void {
		$user = $this->createAccount('allowrequestsign', 'password', 'testGroup');
		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueArray(Application::APP_ID, 'groups_request_sign', ['admin','testGroup']);
		$appConfig->setValueBool(Application::APP_ID, 'notifyUnsignedUser', true);
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
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'fileId' => $file->getNodeId(),
				'signers' => [
					[
						'email' => 'person@test.coop'
					]
				]
			])
			->withPath('/api/v1/notify/signers');

		$this->assertRequest();
	}
}
