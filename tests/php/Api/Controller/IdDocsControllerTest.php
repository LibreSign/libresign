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
final class IdDocsControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testPostIdDocsWithInvalidData():void {
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
			->withPath('/api/v1/id-docs')
			->assertResponseCode(401);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPostIdDocsWithSuccess():void {
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
			->withPath('/api/v1/id-docs');

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testApprovalListWithSuccess():void {
		$this->createAccount('allowapprove', 'password', 'testGroup');

		$this->getMockAppConfig()->setValueArray(Application::APP_ID, 'approval_group', ['testGroup']);

		$this->request
			->withPath('/api/v1/id-docs/approval/list')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowapprove:password')
			]);

		$this->assertRequest();
	}
}
