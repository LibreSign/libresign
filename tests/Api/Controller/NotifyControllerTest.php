<?php

namespace OCA\Libresign\Tests\Api\Controller;

use OCA\Libresign\Tests\Api\ApiTestCase;

/**
 * @group DB
 */
final class NotifyControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testNotifySignersWithError() {
		$this->createUser('username', 'password');
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
			->withPath('/notify/signers')
			->assertResponseCode(401);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testNotifySignersWithSuccess() {
		$user = $this->createUser('allowrequestsign', 'password', 'testGroup');
		$this->mockAppConfig([
			'groups_request_sign' => '["admin","testGroup"]',
			'notifyUnsignedUser' => 0,
		]);
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
				'fileId' => $file['nodeId'],
				'signers' => [
					[
						'email' => 'person@test.coop'
					]
				]
			])
			->withPath('/notify/signers');

		$this->assertRequest();
	}
}
