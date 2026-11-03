<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Api\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\RequestSignatureService;
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
			->withPath('/api/v1/request-signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'name' => 'filename',
				'file' => [],
				'signers' => []
			])
			->expectStatus(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertCount(1, $body['ocs']['data']['errors']);
		$this->assertArrayHasKey(0, $body['ocs']['data']['errors']);
		$this->assertEquals('You are not allowed to create signature requests', $body['ocs']['data']['errors'][0]['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPostRegisterWithSuccess():void {
		$this->createAccount('allowrequestsign', 'password', 'testGroup');

		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueString(Application::APP_ID, 'groups_request_sign', '{"allowGroups":["admin","testGroup"],"denyGroups":[]}');
		$appConfig->setValueBool(Application::APP_ID, 'notifyUnsignedUser', false);

		$this->request
			->withMethod('POST')
			->withPath('/api/v1/request-signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'name' => 'filename',
				'file' => [
					'base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))
				],
				'signers' => [
					[
						'identifyMethods' => [[
							'method' => 'email',
							'requirement' => 'optional',
							'value' => 'user@test.coop',
						]],
					],
				],
			]);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$body['ocs']['data']['data']['signers'][] = ['email' => 'user@test.coop'];
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPatchRegisterWithValidationFailure():void {
		$this->createAccount('username', 'password');
		$this->request
			->withMethod('PATCH')
			->withPath('/api/v1/request-signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'uuid' => '12345678-1234-1234-1234-123456789012',
				'signers' => []
			])
			->expectStatus(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertCount(1, $body['ocs']['data']['errors']);
		$this->assertArrayHasKey(0, $body['ocs']['data']['errors']);
		$this->assertEquals('You are not allowed to create signature requests', $body['ocs']['data']['errors'][0]['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPatchRegisterWithSuccess():void {
		$user = $this->createAccount('allowrequestsign', 'password', 'testGroup');

		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueString(Application::APP_ID, 'groups_request_sign', '{"allowGroups":["admin","testGroup"],"denyGroups":[]}');
		$appConfig->setValueBool(Application::APP_ID, 'notifyUnsignedUser', false);

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))],
			'name' => 'test',
			'signers' => [
				[
					'identifyMethods' => [[
						'method' => 'email',
						'requirement' => 'optional',
						'value' => 'person@test.coop',
					]],
				],
			],
			'userManager' => $user,
		]);

		$this->request
			->withMethod('PATCH')
			->withPath('/api/v1/request-signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'uuid' => $file->getUuid(),
				'signers' => [
					[
						'identifyMethods' => [[
							'method' => 'email',
							'requirement' => 'optional',
							'value' => 'user@test.coop',
						]],
					],
				],
			]);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$body['ocs']['data']['data']['signers'][] = ['email' => 'user@test.coop'];
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPatchRefusesAnotherSignatureFlowOnceTheRequestWasSent():void {
		$user = $this->createAccount('allowrequestsign', 'password', 'testGroup');

		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueString(Application::APP_ID, 'groups_request_sign', '{"allowGroups":["admin","testGroup"],"denyGroups":[]}');
		$appConfig->setValueBool(Application::APP_ID, 'notifyUnsignedUser', false);

		$user->setEMailAddress('person@test.coop');
		$signers = [
			[
				'identifyMethods' => [[
					'method' => 'email',
					'requirement' => 'optional',
					'value' => 'person@test.coop',
				]],
			],
		];
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))],
			'name' => 'test',
			'signers' => $signers,
			'userManager' => $user,
		]);

		$this->request
			->withMethod('PATCH')
			->withPath('/api/v1/request-signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'uuid' => $file->getUuid(),
				'signers' => $signers,
				'policy' => ['overrides' => ['signature_flow' => 'ordered_numeric']],
			])
			->expectStatus(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertSame('The signing order cannot be changed after the signing flow has started.', $body['ocs']['data']['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPatchRefusesAnotherFooterOnceTheRequestWasSent():void {
		$user = $this->createAccount('allowrequestsign', 'password', 'testGroup');

		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueString(Application::APP_ID, 'groups_request_sign', '{"allowGroups":["admin","testGroup"],"denyGroups":[]}');
		$appConfig->setValueBool(Application::APP_ID, 'notifyUnsignedUser', false);

		$user->setEMailAddress('person@test.coop');
		$signers = [
			[
				'identifyMethods' => [[
					'method' => 'email',
					'requirement' => 'optional',
					'value' => 'person@test.coop',
				]],
			],
		];
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))],
			'name' => 'test',
			'signers' => $signers,
			'userManager' => $user,
		]);
		// Without signers the request returns to draft, and the document can be sent again by node id.
		foreach (\OCP\Server::get(SignRequestMapper::class)->getByFileId($file->getId()) as $signRequest) {
			\OCP\Server::get(RequestSignatureService::class)->unassociateToUser($file->getId(), $signRequest->getId());
		}

		$this->request
			->withMethod('PATCH')
			->withPath('/api/v1/request-signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'file' => ['nodeId' => $file->getNodeId()],
				'name' => 'test',
				'signers' => $signers,
				'policy' => ['overrides' => ['add_footer' => '{"enabled":false}']],
			])
			->expectStatus(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertSame('The footer cannot be changed after the signing flow has started.', $body['ocs']['data']['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testUuidPatchRefreshesDraftFooterBeforeFreezing(): void {
		$user = $this->createAccount('allowrequestsign', 'password', 'testGroup');
		$user->setEMailAddress('person@test.coop');
		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueString(Application::APP_ID, 'groups_request_sign', '{"allowGroups":["admin","testGroup"],"denyGroups":[]}');
		$appConfig->setValueBool(Application::APP_ID, 'notifyUnsignedUser', false);
		$initialFooter = FooterPolicyValue::encode(['enabled' => false]);
		$updatedFooter = FooterPolicyValue::encode(['enabled' => true, 'validationSite' => 'https://updated.example/']);
		$appConfig->setValueString(Application::APP_ID, 'add_footer', $initialFooter);
		$appConfig->setValueString(Application::APP_ID, 'add_footer.allow_child_override', '0');

		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))],
			'name' => 'draft',
			'status' => FileStatus::DRAFT->value,
			'signers' => [],
			'userManager' => $user,
		]);
		$fileMapper = \OCP\Server::get(FileMapper::class);
		$draft = $fileMapper->getById($file->getId())->getMetadata();
		$this->assertSame($initialFooter, $draft['policy_snapshot']['add_footer']['effectiveValue']);

		$appConfig->setValueString(Application::APP_ID, 'add_footer', $updatedFooter);
		$this->request
			->withMethod('PATCH')
			->withPath('/api/v1/request-signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password'),
				'Content-Type' => 'application/json',
			])
			->withRequestBody([
				'uuid' => $file->getUuid(),
				'status' => FileStatus::ABLE_TO_SIGN->value,
				'signers' => [[
					'identifyMethods' => [[
						'method' => 'email',
						'requirement' => 'optional',
						'value' => 'person@test.coop',
					]],
				]],
			])
			->expectStatus(200);

		$this->assertRequest();
		$sent = $fileMapper->getById($file->getId())->getMetadata();
		$this->assertSame($updatedFooter, $sent['policy_snapshot']['add_footer']['effectiveValue']);
		$this->assertArrayHasKey('policy_snapshot_frozen_at', $sent);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testUuidPatchRejectsChangedFrozenFooterWithoutMutatingSnapshot(): void {
		$user = $this->createAccount('allowrequestsign', 'password', 'testGroup');
		$user->setEMailAddress('person@test.coop');
		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueString(Application::APP_ID, 'groups_request_sign', '{"allowGroups":["admin","testGroup"],"denyGroups":[]}');
		$appConfig->setValueBool(Application::APP_ID, 'notifyUnsignedUser', false);
		$storedFooter = FooterPolicyValue::encode(['enabled' => true, 'customizeFooterTemplate' => true, 'footerTemplate' => '<p>Stored</p>']);
		$appConfig->setValueString(Application::APP_ID, 'add_footer', $storedFooter);
		$appConfig->setValueString(Application::APP_ID, 'add_footer.allow_child_override', '1');
		$signers = [[
			'identifyMethods' => [[
				'method' => 'email',
				'requirement' => 'optional',
				'value' => 'person@test.coop',
			]],
		]];
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))],
			'name' => 'sent',
			'signers' => $signers,
			'userManager' => $user,
		]);
		$fileMapper = \OCP\Server::get(FileMapper::class);
		$before = $fileMapper->getById($file->getId())->getMetadata();
		$this->assertSame($storedFooter, $before['policy_snapshot']['add_footer']['effectiveValue']);

		$this->request
			->withMethod('PATCH')
			->withPath('/api/v1/request-signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password'),
				'Content-Type' => 'application/json',
			])
			->withRequestBody([
				'uuid' => $file->getUuid(),
				'signers' => $signers,
				'policy' => ['overrides' => ['add_footer' => FooterPolicyValue::encode(['enabled' => false])]],
			])
			->expectStatus(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertSame('The footer cannot be changed after the signing flow has started.', $body['ocs']['data']['message']);
		$this->assertSame($before['policy_snapshot'], $fileMapper->getById($file->getId())->getMetadata()['policy_snapshot']);
	}
}
