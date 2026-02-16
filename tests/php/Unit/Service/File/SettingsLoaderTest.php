<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\File\AccountSettingsProvider;
use OCA\Libresign\Service\File\FileResponseOptions;
use OCA\Libresign\Service\File\SettingsLoader;
use OCA\Libresign\Service\IdDocsPolicyService;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;

final class SettingsLoaderTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private AccountSettingsProvider|MockObject $accountSettingsProvider;
	private IdDocsMapper|MockObject $idDocsMapper;
	private IdDocsPolicyService|MockObject $idDocsPolicyService;
	private IAppConfig|MockObject $appConfig;
	private IGroupManager|MockObject $groupManager;

	public function setUp(): void {
		parent::setUp();
		$this->accountSettingsProvider = $this->createMock(AccountSettingsProvider::class);
		$this->idDocsMapper = $this->createMock(IdDocsMapper::class);
		$this->idDocsPolicyService = $this->createMock(IdDocsPolicyService::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
	}

	private function getService(): SettingsLoader {
		return new SettingsLoader(
			$this->accountSettingsProvider,
			$this->idDocsPolicyService,
			$this->appConfig,
			$this->groupManager,
			$this->idDocsMapper,
			$this->createMock(\OCA\Libresign\Service\IdentifyMethodService::class),
		);
	}

	public function testLoadSettingsNotShown(): void {
		$fileData = new stdClass();
		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowSettings')->willReturn(false);

		$service = $this->getService();
		$service->loadSettings($fileData, $options);

		$this->assertFalse(property_exists($fileData, 'settings'));
	}

	public function testLoadSettingsWithUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');

		$fileData = new stdClass();
		$fileData->id = 1;
		$fileData->status = FileStatus::ABLE_TO_SIGN->value;
		$fileData->settings = [];
		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowSettings')->willReturn(true);
		$options->method('getMe')->willReturn($user);
		$options->method('isSignerIdentified')->willReturn(false);

		$this->accountSettingsProvider->method('getSettings')->with($user)->willReturn([
			'canSign' => true,
			'canRequestSign' => false,
		]);
		$this->accountSettingsProvider->method('getPhoneNumber')->with($user)->willReturn('123456789');

		$this->appConfig->method('getValueBool')->willReturn(false);

		$service = $this->getService();
		$service->loadSettings($fileData, $options);

		$this->assertTrue(isset($fileData->settings));
		$this->assertEquals('123456789', $fileData->settings['phoneNumber']);
		$this->assertTrue($fileData->settings['canSign']);
	}

	public function testLoadSettingsWithoutUser(): void {
		$fileData = new stdClass();
		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowSettings')->willReturn(true);
		$options->method('getMe')->willReturn(null);
		$options->method('isSignerIdentified')->willReturn(false);

		$this->appConfig->method('getValueBool')->willReturn(false);

		$service = $this->getService();
		$service->loadSettings($fileData, $options);

		$this->assertTrue(isset($fileData->settings));
		$this->assertFalse($fileData->settings['canSign']);
		$this->assertFalse($fileData->settings['canRequestSign']);
		$this->assertFalse($fileData->settings['hasSignatureFile']);
	}

	public function testLoadSettingsApproverCanSignIdDoc(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('approver');

		$fileData = new stdClass();
		$fileData->id = 10;
		$fileData->status = FileStatus::ABLE_TO_SIGN->value;

		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowSettings')->willReturn(true);
		$options->method('getMe')->willReturn($user);
		$options->method('isSignerIdentified')->willReturn(false);

		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(false);
		$this->accountSettingsProvider->method('getSettings')
			->with($user)
			->willReturn([
				'canSign' => false,
				'canRequestSign' => false,
				'hasSignatureFile' => false,
				'isApprover' => true,
			]);
		$this->accountSettingsProvider->method('getPhoneNumber')->with($user)->willReturn('');
		$this->idDocsPolicyService->method('canApproverSignIdDoc')
			->with($user, 10, FileStatus::ABLE_TO_SIGN->value)
			->willReturn(true);

		$service = $this->getService();
		$service->loadSettings($fileData, $options);

		$this->assertTrue($fileData->settings['canSign']);
	}

	public static function providerGetIdentificationDocumentsStatus(): array {
		return [
			'disabled returns DISABLED' => [
				'idDocsEnabled' => false,
				'hasUser' => true,
				'userGroups' => [],
				'approvalGroups' => [],
				'signRequestId' => null,
				'fileStatuses' => [],
				'expected' => SettingsLoader::IDENTIFICATION_DOCUMENTS_DISABLED,
			],
			'no files returns NEED_SEND' => [
				'idDocsEnabled' => true,
				'hasUser' => true,
				'userGroups' => ['users'],
				'approvalGroups' => ['admin'],
				'signRequestId' => null,
				'fileStatuses' => [],
				'expected' => SettingsLoader::IDENTIFICATION_DOCUMENTS_NEED_SEND,
			],
			'all files deleted returns NEED_SEND' => [
				'idDocsEnabled' => true,
				'hasUser' => true,
				'userGroups' => ['users'],
				'approvalGroups' => ['admin'],
				'signRequestId' => null,
				'fileStatuses' => [FileStatus::DELETED->value, FileStatus::DELETED->value],
				'expected' => SettingsLoader::IDENTIFICATION_DOCUMENTS_NEED_SEND,
			],
			'mixed signed and draft returns NEED_APPROVAL' => [
				'idDocsEnabled' => true,
				'hasUser' => true,
				'userGroups' => ['users'],
				'approvalGroups' => ['admin'],
				'signRequestId' => null,
				'fileStatuses' => [FileStatus::SIGNED->value, FileStatus::DRAFT->value],
				'expected' => SettingsLoader::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL,
			],
			'all files signed returns APPROVED' => [
				'idDocsEnabled' => true,
				'hasUser' => true,
				'userGroups' => ['users'],
				'approvalGroups' => ['admin'],
				'signRequestId' => null,
				'fileStatuses' => [FileStatus::SIGNED->value, FileStatus::SIGNED->value],
				'expected' => SettingsLoader::IDENTIFICATION_DOCUMENTS_APPROVED,
			],
			'user in approval group bypasses to APPROVED' => [
				'idDocsEnabled' => true,
				'hasUser' => true,
				'userGroups' => ['approvers'],
				'approvalGroups' => ['admin', 'approvers'],
				'signRequestId' => null,
				'fileStatuses' => [],
				'expected' => SettingsLoader::IDENTIFICATION_DOCUMENTS_APPROVED,
			],
			'signRequest with draft file returns NEED_APPROVAL' => [
				'idDocsEnabled' => true,
				'hasUser' => false,
				'userGroups' => [],
				'approvalGroups' => ['admin'],
				'signRequestId' => 42,
				'fileStatuses' => [FileStatus::DRAFT->value],
				'expected' => SettingsLoader::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL,
			],
			'signRequest with no files returns NEED_SEND' => [
				'idDocsEnabled' => true,
				'hasUser' => false,
				'userGroups' => [],
				'approvalGroups' => ['admin'],
				'signRequestId' => 99,
				'fileStatuses' => [],
				'expected' => SettingsLoader::IDENTIFICATION_DOCUMENTS_NEED_SEND,
			],
			'signRequest with all signed returns APPROVED' => [
				'idDocsEnabled' => true,
				'hasUser' => false,
				'userGroups' => [],
				'approvalGroups' => ['admin'],
				'signRequestId' => 50,
				'fileStatuses' => [FileStatus::SIGNED->value],
				'expected' => SettingsLoader::IDENTIFICATION_DOCUMENTS_APPROVED,
			],
			'null user and null signRequest returns NEED_SEND' => [
				'idDocsEnabled' => true,
				'hasUser' => false,
				'userGroups' => [],
				'approvalGroups' => ['admin'],
				'signRequestId' => null,
				'fileStatuses' => [],
				'expected' => SettingsLoader::IDENTIFICATION_DOCUMENTS_NEED_SEND,
			],
			'single ABLE_TO_SIGN file returns NEED_APPROVAL' => [
				'idDocsEnabled' => true,
				'hasUser' => true,
				'userGroups' => ['users'],
				'approvalGroups' => ['admin'],
				'signRequestId' => null,
				'fileStatuses' => [FileStatus::ABLE_TO_SIGN->value],
				'expected' => SettingsLoader::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL,
			],
			'mixed deleted and signed returns NEED_APPROVAL' => [
				'idDocsEnabled' => true,
				'hasUser' => true,
				'userGroups' => ['users'],
				'approvalGroups' => ['admin'],
				'signRequestId' => null,
				'fileStatuses' => [FileStatus::DELETED->value, FileStatus::SIGNED->value],
				'expected' => SettingsLoader::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL,
			],
		];
	}

	#[DataProvider('providerGetIdentificationDocumentsStatus')]
	public function testGetIdentificationDocumentsStatus(
		bool $idDocsEnabled,
		bool $hasUser,
		array $userGroups,
		array $approvalGroups,
		?int $signRequestId,
		array $fileStatuses,
		int $expected,
	): void {
		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn($idDocsEnabled);

		if ($idDocsEnabled) {
			$this->appConfig->method('getValueArray')
				->with(Application::APP_ID, 'approval_group', ['admin'])
				->willReturn($approvalGroups);
		}

		$user = null;
		$signRequest = null;
		$files = array_map(function (int $status) {
			$file = new File();
			$file->setStatus($status);
			return $file;
		}, $fileStatuses);

		if ($hasUser) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('testuser');
			$this->groupManager->method('getUserGroupIds')->with($user)->willReturn($userGroups);
			$this->idDocsMapper->method('getFilesOfAccount')->with('testuser')->willReturn($files);
		}

		if ($signRequestId !== null) {
			$signRequest = new SignRequest();
			$signRequest->setId($signRequestId);
			$this->idDocsMapper->method('getFilesOfSignRequest')->with($signRequestId)->willReturn($files);
		}

		$service = $this->getService();
		$status = $service->getIdentificationDocumentsStatus($user, $signRequest);

		$this->assertEquals($expected, $status);
	}

	public function testLoadSettingsWithSignerIdentifiedNeedSend(): void {
		$fileData = new stdClass();
		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowSettings')->willReturn(true);
		$options->method('getMe')->willReturn(null);
		$options->method('isSignerIdentified')->willReturn(true);

		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(true);

		$this->idDocsMapper->method('getFilesOfAccount')->willReturn([]);

		$service = $this->getService();
		$service->loadSettings($fileData, $options);

		$this->assertTrue($fileData->settings['needIdentificationDocuments']);
		$this->assertFalse($fileData->settings['identificationDocumentsWaitingApproval']);
	}

	public function testLoadSettingsWithSignerIdentifiedNeedApproval(): void {
		$fileData = new stdClass();
		$fileData->settings = []; // Initialize settings array
		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowSettings')->willReturn(true);
		$options->method('getMe')->willReturn(null);
		$options->method('isSignerIdentified')->willReturn(true);

		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(true);

		// When getMe() returns null and isSignerIdentified() is true,
		// getIdentificationDocumentsStatus is called with empty string
		// which returns NEED_SEND (not NEED_APPROVAL)
		$service = $this->getService();
		$service->loadSettings($fileData, $options);

		$this->assertTrue($fileData->settings['needIdentificationDocuments']);
		$this->assertFalse($fileData->settings['identificationDocumentsWaitingApproval']);
	}

	public function testLoadSettingsEmptyUserIdReturnsNeedSend(): void {
		$fileData = new stdClass();
		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowSettings')->willReturn(true);
		$options->method('getMe')->willReturn(null);
		$options->method('isSignerIdentified')->willReturn(true);

		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(true);

		$service = $this->getService();
		$status = $service->getIdentificationDocumentsStatus(null);

		$this->assertEquals(SettingsLoader::IDENTIFICATION_DOCUMENTS_NEED_SEND, $status);
	}

	public static function providerGetUserIdentificationSettings(): array {
		return [
			'disabled → no identification needed' => [
				'idDocsEnabled' => false,
				'hasUser' => true,
				'userGroups' => [],
				'approvalGroups' => [],
				'signRequestId' => null,
				'fileStatuses' => [],
				'expectedNeedDocs' => false,
				'expectedWaitingApproval' => false,
			],
			'need send → needs documents, not waiting' => [
				'idDocsEnabled' => true,
				'hasUser' => true,
				'userGroups' => ['users'],
				'approvalGroups' => ['admin'],
				'signRequestId' => null,
				'fileStatuses' => [],
				'expectedNeedDocs' => true,
				'expectedWaitingApproval' => false,
			],
			'need approval → needs documents, waiting' => [
				'idDocsEnabled' => true,
				'hasUser' => true,
				'userGroups' => ['users'],
				'approvalGroups' => ['admin'],
				'signRequestId' => null,
				'fileStatuses' => [FileStatus::DRAFT->value],
				'expectedNeedDocs' => true,
				'expectedWaitingApproval' => true,
			],
			'approved → no identification needed' => [
				'idDocsEnabled' => true,
				'hasUser' => true,
				'userGroups' => ['users'],
				'approvalGroups' => ['admin'],
				'signRequestId' => null,
				'fileStatuses' => [FileStatus::SIGNED->value, FileStatus::SIGNED->value],
				'expectedNeedDocs' => false,
				'expectedWaitingApproval' => false,
			],
			'signRequest all signed → no identification needed' => [
				'idDocsEnabled' => true,
				'hasUser' => false,
				'userGroups' => [],
				'approvalGroups' => ['admin'],
				'signRequestId' => 50,
				'fileStatuses' => [FileStatus::SIGNED->value],
				'expectedNeedDocs' => false,
				'expectedWaitingApproval' => false,
			],
			'signRequest with draft → needs documents, waiting' => [
				'idDocsEnabled' => true,
				'hasUser' => false,
				'userGroups' => [],
				'approvalGroups' => ['admin'],
				'signRequestId' => 42,
				'fileStatuses' => [FileStatus::DRAFT->value],
				'expectedNeedDocs' => true,
				'expectedWaitingApproval' => true,
			],
			'null user without signRequest → needs documents, not waiting' => [
				'idDocsEnabled' => true,
				'hasUser' => false,
				'userGroups' => [],
				'approvalGroups' => ['admin'],
				'signRequestId' => null,
				'fileStatuses' => [],
				'expectedNeedDocs' => true,
				'expectedWaitingApproval' => false,
			],
		];
	}

	#[DataProvider('providerGetUserIdentificationSettings')]
	public function testGetUserIdentificationSettings(
		bool $idDocsEnabled,
		bool $hasUser,
		array $userGroups,
		array $approvalGroups,
		?int $signRequestId,
		array $fileStatuses,
		bool $expectedNeedDocs,
		bool $expectedWaitingApproval,
	): void {
		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn($idDocsEnabled);

		if ($idDocsEnabled) {
			$this->appConfig->method('getValueArray')
				->with(Application::APP_ID, 'approval_group', ['admin'])
				->willReturn($approvalGroups);
		}

		$user = null;
		$signRequest = null;
		$files = array_map(function (int $status) {
			$file = new File();
			$file->setStatus($status);
			return $file;
		}, $fileStatuses);

		if ($hasUser) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('testuser');
			$this->groupManager->method('getUserGroupIds')->with($user)->willReturn($userGroups);
			$this->idDocsMapper->method('getFilesOfAccount')->with('testuser')->willReturn($files);
		}

		if ($signRequestId !== null) {
			$signRequest = new SignRequest();
			$signRequest->setId($signRequestId);
			$this->idDocsMapper->method('getFilesOfSignRequest')->with($signRequestId)->willReturn($files);
		}

		$service = $this->getService();
		$result = $service->getUserIdentificationSettings($user, $signRequest);

		$this->assertEquals($expectedNeedDocs, $result['needIdentificationDocuments']);
		$this->assertEquals($expectedWaitingApproval, $result['identificationDocumentsWaitingApproval']);
	}
}
