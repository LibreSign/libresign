<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Api\Controller;

use DateTime;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Tests\Api\ApiTestCase;

/**
 * @group DB
 */
final class SignFileControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithInvalidFileToSign():void {
		$this->createAccount('allowrequestsign', 'password', 'testGroup');

		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueArray(Application::APP_ID, 'groups_request_sign', ['admin','testGroup']);
		$appConfig->setValueBool(Application::APP_ID, 'notifyUnsignedUser', false);
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/api/v1/sign/file_id/171')
			->withRequestBody([
				'identifyValue' => 'secretPassword',
				'method' => 'password',
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertCount(1, $body['ocs']['data']['errors']);
		$this->assertArrayHasKey(0, $body['ocs']['data']['errors']);
		$this->assertEquals('File not found', $body['ocs']['data']['errors'][0]['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithInvalidUuidToSign():void {
		$this->createAccount('username', 'password');
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/api/v1/sign/uuid/invalid')
			->withRequestBody([
				'identifyValue' => 'secretPassword',
				'method' => 'password',
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertCount(1, $body['ocs']['data']['errors']);
		$this->assertArrayHasKey(0, $body['ocs']['data']['errors']);
		$this->assertEquals('Invalid UUID', $body['ocs']['data']['errors'][0]['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithAlreadySignedFile():void {
		$user = $this->createAccount('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'identify' => [
						'account' => 'username',
					],
				],
			],
			'userManager' => $user,
		]);
		$signers = $this->getSignersFromFileId($file->getId());
		$signers[0]->setSigned(new DateTime());
		$signRequest = \OCP\Server::get(\OCA\Libresign\Db\SignRequestMapper::class);
		$signRequest->update($signers[0]);

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/api/v1/sign/uuid/' . $signers[0]->getUuid())
			->withRequestBody([
				'identifyValue' => 'secretPassword',
				'method' => 'password',
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertCount(1, $body['ocs']['data']['errors']);
		$this->assertArrayHasKey(0, $body['ocs']['data']['errors']);
		$this->assertEquals('File already signed.', $body['ocs']['data']['errors'][0]['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithNotFoundFile():void {
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
		$folderService = \OCP\Server::get(\OCA\Libresign\Service\FolderService::class);
		$libresignFolder = $folderService->getFolder();
		$libresignFolder->delete();

		$signers = $this->getSignersFromFileId($file->getId());
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/api/v1/sign/uuid/' . $signers[0]->getUuid())
			->withRequestBody([
				'identifyValue' => 'secretPassword',
				'method' => 'password',
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertCount(1, $body['ocs']['data']['errors']);
		$this->assertArrayHasKey(0, $body['ocs']['data']['errors']);
		$this->assertEquals('File not found', $body['ocs']['data']['errors'][0]['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithoutPfx():void {
		$this->markTestSkipped('Neet to assign visible elements to signrequest and not to nextcloud account');
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
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/api/v1/sign/uuid/' . $signers[0]->getUuid())
			->withRequestBody([
				'password' => ''
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals(200, $body['ocs']['data']['action']);
		$this->assertCount(1, $body['ocs']['data']['errors']);
		$this->assertArrayHasKey(0, $body['ocs']['data']['errors']);
		$this->assertEquals('Empty identify data.', $body['ocs']['data']['errors'][0]['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignWithCertificateButEmptyPassword():void {
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
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/api/v1/sign/uuid/' . $signers[0]->getUuid())
			->withRequestBody([
				'method' => 'password',
				'token' => '',
			])
			->assertResponseCode(422);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testAccountSignatureEndpointWithFailure():void {
		$this->createAccount('username', 'password');

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'signPassword' => ''
			])
			->withPath('/api/v1/account/signature')
			->assertResponseCode(401);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDeleteSignFileIdSignRequestIdWithSuccess():void {
		$user = $this->createAccount('allowrequestsign', 'password', 'testGroup');
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

		$this->getMockAppConfig()->setValueArray(Application::APP_ID, 'groups_request_sign', ['admin','testGroup']);

		$signers = $this->getSignersFromFileId($file->getId());
		$this->request
			->withMethod('DELETE')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password')
			])
			->withPath('/api/v1/sign/file_id/' . $file->getNodeId() . '/' . $signers[0]->getId());

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDeleteSignFileIdSignRequestIdWithError():void {
		$user = $this->createAccount('username', 'password');

		$this->request
			->withMethod('DELETE')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			])
			->withPath('/api/v1/sign/file_id/171/171')
			->assertResponseCode(422);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDeleteUsingSignFileIdWithSuccess():void {
		$user = $this->createAccount('allowrequestsign', 'password', 'testGroup');
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

		$this->getMockAppConfig()->setValueArray(Application::APP_ID, 'groups_request_sign', ['admin','testGroup']);

		$this->request
			->withMethod('DELETE')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password')
			])
			->withPath('/api/v1/sign/file_id/' . $file->getNodeId());

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDeleteUsingSignFileIdWithInvalidSignRequestGroup():void {
		$user = $this->createAccount('username', 'password');

		$this->request
			->withMethod('DELETE')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			])
			->withPath('/api/v1/sign/file_id/171')
			->assertResponseCode(422);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDeleteUsingSignFileIdWithInvalidFile():void {
		$user = $this->createAccount('username', 'password');
		$this->getMockAppConfig()->setValueArray(Application::APP_ID, 'groups_request_sign', ['admin','testGroup']);

		$this->request
			->withMethod('DELETE')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			])
			->withPath('/api/v1/sign/file_id/171')
			->assertResponseCode(401);

		$this->assertRequest();
	}
}
