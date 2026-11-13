<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Api\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use OCA\Libresign\Service\RequestSignatureService;
use OCA\Libresign\Tests\Api\ApiTestCase;
use OCP\IDBConnection;

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
						'value' => 'external-signer@test.coop',
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

	/**
	 * @runInSeparateProcess
	 */
	public function testUuidPatchKeepsTheFrozenDocMdpAfterTheAdminChangesIt(): void {
		$user = $this->createAccount('allowrequestsign', 'password', 'testGroup');
		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueString(Application::APP_ID, 'groups_request_sign', '{"allowGroups":["admin","testGroup"],"denyGroups":[]}');
		$appConfig->setValueBool(Application::APP_ID, 'notifyUnsignedUser', false);
		$appConfig->setValueInt(Application::APP_ID, 'docmdp_level', DocMdpLevel::CERTIFIED_FORM_FILLING->value);
		$signers = [[
			'identifyMethods' => [[
				'method' => 'email',
				'requirement' => 'optional',
				'value' => 'external-signer@test.coop',
			]],
		]];
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))],
			'name' => 'sent',
			'signers' => $signers,
			'userManager' => $user,
		]);
		$fileMapper = \OCP\Server::get(FileMapper::class);
		$before = $fileMapper->getById($file->getId());
		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING->value, $before->getMetadata()['policy_snapshot']['docmdp']['effectiveValue']);

		$appConfig->setValueInt(Application::APP_ID, 'docmdp_level', DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS->value);
		$this->request
			->withMethod('PATCH')
			->withPath('/api/v1/request-signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password'),
				'Content-Type' => 'application/json',
			])
			->withRequestBody([
				'uuid' => $file->getUuid(),
				'name' => 'renamed',
				'signers' => $signers,
			])
			->expectStatus(200);

		$this->assertRequest();
		$after = $fileMapper->getById($file->getId());
		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING, $after->getDocmdpLevelEnum());
		$this->assertSame($before->getMetadata()['policy_snapshot']['docmdp'], $after->getMetadata()['policy_snapshot']['docmdp']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testUuidPatchRejectsAnotherDocMdpOnceTheRequestWasSent(): void {
		$user = $this->createAccount('allowrequestsign', 'password', 'testGroup');
		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueString(Application::APP_ID, 'groups_request_sign', '{"allowGroups":["admin","testGroup"],"denyGroups":[]}');
		$appConfig->setValueBool(Application::APP_ID, 'notifyUnsignedUser', false);
		$appConfig->setValueInt(Application::APP_ID, 'docmdp_level', DocMdpLevel::CERTIFIED_FORM_FILLING->value);
		$appConfig->setValueString(Application::APP_ID, 'docmdp_level.allow_child_override', '1');
		$signers = [[
			'identifyMethods' => [[
				'method' => 'email',
				'requirement' => 'optional',
				'value' => 'external-signer@test.coop',
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
				'policy' => ['overrides' => ['docmdp' => DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS->value]],
			])
			->expectStatus(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertSame('The PDF certification cannot be changed after the signing flow has started.', $body['ocs']['data']['message']);
		$this->assertSame($before['policy_snapshot'], $fileMapper->getById($file->getId())->getMetadata()['policy_snapshot']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testUuidPatchRefreshesDraftDocMdpBeforeFreezing(): void {
		$user = $this->createAccount('allowrequestsign', 'password', 'testGroup');
		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueString(Application::APP_ID, 'groups_request_sign', '{"allowGroups":["admin","testGroup"],"denyGroups":[]}');
		$appConfig->setValueBool(Application::APP_ID, 'notifyUnsignedUser', false);
		$appConfig->setValueInt(Application::APP_ID, 'docmdp_level', DocMdpLevel::CERTIFIED_FORM_FILLING->value);

		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))],
			'name' => 'draft',
			'status' => FileStatus::DRAFT->value,
			'signers' => [],
			'userManager' => $user,
		]);
		$fileMapper = \OCP\Server::get(FileMapper::class);
		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING, $fileMapper->getById($file->getId())->getDocmdpLevelEnum());

		$appConfig->setValueInt(Application::APP_ID, 'docmdp_level', DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS->value);
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
						'value' => 'external-signer@test.coop',
					]],
				]],
			])
			->expectStatus(200);

		$this->assertRequest();
		$sent = $fileMapper->getById($file->getId());
		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS, $sent->getDocmdpLevelEnum());
		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS->value, $sent->getMetadata()['policy_snapshot']['docmdp']['effectiveValue']);
		$this->assertArrayHasKey('policy_snapshot_frozen_at', $sent->getMetadata());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPostRejectsTwoSignersWhenTheRequestOverridesDocMdpToNoChanges(): void {
		$this->createAccount('allowrequestsign', 'password', 'testGroup');
		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueString(Application::APP_ID, 'groups_request_sign', '{"allowGroups":["admin","testGroup"],"denyGroups":[]}');
		$appConfig->setValueBool(Application::APP_ID, 'notifyUnsignedUser', false);
		$appConfig->setValueInt(Application::APP_ID, 'docmdp_level', DocMdpLevel::CERTIFIED_FORM_FILLING->value);
		$appConfig->setValueString(Application::APP_ID, 'docmdp_level.allow_child_override', '1');
		$policy = ['overrides' => ['docmdp' => DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED->value]];

		$body = $this->postDocMdpRequest(['one@test.coop', 'two@test.coop'], 422, $policy);
		$this->assertSame('This document has been certified with no changes allowed. You cannot add more signers to this document.', $body['ocs']['data']['message']);
		$this->assertSame(0, $this->countFilesOf('allowrequestsign'));
	}

	/**
	 * The override the previous test relies on is authorized: with a single
	 * signer it is stored as the request's level.
	 *
	 * @runInSeparateProcess
	 */
	public function testPostStoresTheDocMdpOverrideWithASingleSigner(): void {
		$this->createAccount('allowrequestsign', 'password', 'testGroup');
		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueString(Application::APP_ID, 'groups_request_sign', '{"allowGroups":["admin","testGroup"],"denyGroups":[]}');
		$appConfig->setValueBool(Application::APP_ID, 'notifyUnsignedUser', false);
		$appConfig->setValueInt(Application::APP_ID, 'docmdp_level', DocMdpLevel::CERTIFIED_FORM_FILLING->value);
		$appConfig->setValueString(Application::APP_ID, 'docmdp_level.allow_child_override', '1');

		$body = $this->postDocMdpRequest(['one@test.coop'], 200, ['overrides' => ['docmdp' => DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED->value]]);
		$file = \OCP\Server::get(FileMapper::class)->getByUuid($body['ocs']['data']['uuid']);
		$this->assertSame(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED, $file->getDocmdpLevelEnum());
		$this->assertSame(
			['effectiveValue' => DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED->value, 'sourceScope' => 'request'],
			$file->getMetadata()['policy_snapshot']['docmdp'],
		);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPostAcceptsTwoSignersWhenDocMdpAllowsFormFilling(): void {
		$this->createAccount('allowrequestsign', 'password', 'testGroup');
		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueString(Application::APP_ID, 'groups_request_sign', '{"allowGroups":["admin","testGroup"],"denyGroups":[]}');
		$appConfig->setValueBool(Application::APP_ID, 'notifyUnsignedUser', false);
		$appConfig->setValueInt(Application::APP_ID, 'docmdp_level', DocMdpLevel::CERTIFIED_FORM_FILLING->value);

		$body = $this->postDocMdpRequest(['one@test.coop', 'two@test.coop'], 200);
		$file = \OCP\Server::get(FileMapper::class)->getByUuid($body['ocs']['data']['uuid']);
		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING, $file->getDocmdpLevelEnum());
		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING->value, $file->getMetadata()['policy_snapshot']['docmdp']['effectiveValue']);
		$this->assertCount(2, $this->getSignersFromFileId($file->getId()));
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testUuidPatchAddsSignersToAFrozenUncertifiedRequestAfterTheAdminCertifies(): void {
		$user = $this->createAccount('allowrequestsign', 'password', 'testGroup');
		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueString(Application::APP_ID, 'groups_request_sign', '{"allowGroups":["admin","testGroup"],"denyGroups":[]}');
		$appConfig->setValueBool(Application::APP_ID, 'notifyUnsignedUser', false);
		$appConfig->setValueInt(Application::APP_ID, 'docmdp_level', DocMdpLevel::NOT_CERTIFIED->value);
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))],
			'name' => 'sent',
			'signers' => $this->buildEmailSigners(['one@test.coop']),
			'userManager' => $user,
		]);
		$fileMapper = \OCP\Server::get(FileMapper::class);
		$before = $fileMapper->getById($file->getId());
		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $before->getMetadata()['policy_snapshot']['docmdp']['effectiveValue']);

		$appConfig->setValueInt(Application::APP_ID, 'docmdp_level', DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED->value);
		$this->request
			->withMethod('PATCH')
			->withPath('/api/v1/request-signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password'),
				'Content-Type' => 'application/json',
			])
			->withRequestBody([
				'uuid' => $file->getUuid(),
				'signers' => $this->buildEmailSigners(['one@test.coop', 'two@test.coop']),
			])
			->expectStatus(200);

		$this->assertRequest();
		$after = $fileMapper->getById($file->getId());
		$this->assertSame(DocMdpLevel::NOT_CERTIFIED, $after->getDocmdpLevelEnum());
		$this->assertSame($before->getMetadata()['policy_snapshot']['docmdp'], $after->getMetadata()['policy_snapshot']['docmdp']);
		$this->assertCount(2, $this->getSignersFromFileId($file->getId()));
	}

	/**
	 * @param list<string> $emails
	 * @param array<string, mixed>|null $policy
	 * @return array<string, mixed>
	 */
	private function postDocMdpRequest(array $emails, int $expectedStatus, ?array $policy = null): array {
		$body = [
			'name' => 'docmdp',
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))],
			'signers' => $this->buildEmailSigners($emails),
		];
		if ($policy !== null) {
			$body['policy'] = $policy;
		}
		$this->request
			->withMethod('POST')
			->withPath('/api/v1/request-signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('allowrequestsign:password'),
				'Content-Type' => 'application/json',
			])
			->withRequestBody($body)
			->expectStatus($expectedStatus);

		$response = $this->assertRequest();
		return json_decode($response->getBody()->getContents(), true);
	}

	/**
	 * @param list<string> $emails
	 * @return list<array<string, mixed>>
	 */
	private function buildEmailSigners(array $emails): array {
		return array_map(static fn (string $email): array => [
			'identifyMethods' => [[
				'method' => 'email',
				'requirement' => 'optional',
				'value' => $email,
			]],
		], $emails);
	}

	private function countFilesOf(string $userId): int {
		$qb = \OCP\Server::get(IDBConnection::class)->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'total'))
			->from('libresign_file')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$result = $qb->executeQuery();
		$total = (int)$result->fetchOne();
		$result->closeCursor();
		return $total;
	}
}
