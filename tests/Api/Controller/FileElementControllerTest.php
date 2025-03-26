<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Api\Controller;

use DateTime;
use OCA\Libresign\Tests\Api\ApiTestCase;

/**
 * @internal
 * @group DB
 */
final class FileElementControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testPostSuccess():array {
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
		$signers[0]->setSigned(new DateTime());

		$this->getMockAppConfig();
		$this->request
			->withPath('/api/v1/file-element/' . $file->getUuid())
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'coordinates' => [
					'top' => 188,
					'left' => 4,
					'width' => 370,
					'height' => 90,
					'page' => 1,
				],
				'type' => 'signature',
				'signRequestId' => $signers[0]->getId(),
			]);
		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		return [
			'file' => $file,
			'fileElementId' => $body['ocs']['data']['fileElementId'],
		];
	}

	/**
	 * @runInSeparateProcess
	 * @depends testPostSuccess
	 */
	public function testPatchSuccess($params):array {
		$this->createAccount('username', 'password');
		extract($params);
		$signers = $this->getSignersFromFileId($file->getId());
		$this->request
			->withPath('/api/v1/file-element/' . $file->getUuid() . '/' . $fileElementId)
			->withMethod('PATCH')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'coordinates' => [
					'top' => 189,
					'left' => 5,
					'width' => 371,
					'height' => 91,
					'page' => 1,
				],
				'type' => 'signature',
				'signRequestId' => $signers[0]->getId(),
			]);
		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		return [
			'file' => $file,
			'fileElementId' => $body['ocs']['data']['fileElementId'],
		];
	}

	/**
	 * @runInSeparateProcess
	 * @depends testPostSuccess
	 * @depends testPatchSuccess
	 */
	public function testDeleteSuccess($params):void {
		$this->createAccount('username', 'password');
		extract($params);
		$this->request
			->withPath('/api/v1/file-element/' . $file->getUuid() . '/' . $fileElementId)
			->withMethod('DELETE')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
			]);
		$this->assertRequest();
	}
}
