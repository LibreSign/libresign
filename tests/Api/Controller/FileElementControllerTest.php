<?php

namespace OCA\Libresign\Tests\Api\Controller;

use OCA\Libresign\Tests\Api\ApiTestCase;

/**
 * @internal
 * @group DB
 */
final class FileElementControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testPostSuccess() {
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
		$file['users'][0]->setSigned(time());

		$this->mockConfig(['libresign' => []]);
		$this->request
			->withPath('/file-element/' . $file['uuid'])
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
				'fileUserId' => $file['users'][0]->getId(),
			]);
		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		return [
			'file' => $file,
			'fileElementId' => $body['fileElementId'],
		];
	}

	/**
	 * @runInSeparateProcess
	 * @depends testPostSuccess
	 */
	public function testPatchSuccess($params) {
		$this->createUser('username', 'password');
		extract($params);
		$this->request
			->withPath('/file-element/' . $file['uuid'] . '/' . $fileElementId)
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
				'fileUserId' => $file['users'][0]->getId(),
			]);
		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		return [
			'file' => $file,
			'fileElementId' => $body['fileElementId'],
		];
	}

	/**
	 * @runInSeparateProcess
	 * @depends testPostSuccess
	 * @depends testPatchSuccess
	 */
	public function testDeleteSuccess($params) {
		$this->createUser('username', 'password');
		extract($params);
		$this->request
			->withPath('/file-element/' . $file['uuid'] . '/' . $fileElementId)
			->withMethod('DELETE')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
			]);
		$this->assertRequest();
	}
}
