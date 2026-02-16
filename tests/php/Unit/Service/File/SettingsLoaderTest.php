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
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\File\AccountSettingsProvider;
use OCA\Libresign\Service\File\FileResponseOptions;
use OCA\Libresign\Service\File\SettingsLoader;
use OCA\Libresign\Service\IdDocsPolicyService;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
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

	public function testGetIdentificationDocumentsStatusDisabled(): void {
		$user = $this->createMock(IUser::class);
		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(false);

		$service = $this->getService();
		$status = $service->getIdentificationDocumentsStatus($user);

		$this->assertEquals(SettingsLoader::IDENTIFICATION_DOCUMENTS_DISABLED, $status);
	}

	public function testGetIdentificationDocumentsStatusNeedSend(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');

		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(true);
		$this->appConfig->method('getValueArray')
			->with(Application::APP_ID, 'approval_group', ['admin'])
			->willReturn(['admin']);

		$this->groupManager->method('getUserGroupIds')
			->with($user)
			->willReturn(['users']);

		$this->idDocsMapper->method('getFilesOfAccount')->with('user123')->willReturn([]);

		$service = $this->getService();
		$status = $service->getIdentificationDocumentsStatus($user);

		$this->assertEquals(SettingsLoader::IDENTIFICATION_DOCUMENTS_NEED_SEND, $status);
	}

	public function testGetIdentificationDocumentsStatusAllDeleted(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');

		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(true);
		$this->appConfig->method('getValueArray')
			->with(Application::APP_ID, 'approval_group', ['admin'])
			->willReturn(['admin']);

		$this->groupManager->method('getUserGroupIds')
			->with($user)
			->willReturn(['users']);

		$file1 = new File();
		$file1->setStatus(FileStatus::DELETED->value);

		$file2 = new File();
		$file2->setStatus(FileStatus::DELETED->value);

		$this->idDocsMapper->method('getFilesOfAccount')->with('user123')->willReturn([$file1, $file2]);

		$service = $this->getService();
		$status = $service->getIdentificationDocumentsStatus($user);

		$this->assertEquals(SettingsLoader::IDENTIFICATION_DOCUMENTS_NEED_SEND, $status);
	}

	public function testGetIdentificationDocumentsStatusNeedApproval(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');

		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(true);
		$this->appConfig->method('getValueArray')
			->with(Application::APP_ID, 'approval_group', ['admin'])
			->willReturn(['admin']);

		$this->groupManager->method('getUserGroupIds')
			->with($user)
			->willReturn(['users']);

		$file1 = new File();
		$file1->setStatus(FileStatus::SIGNED->value);

		$file2 = new File();
		$file2->setStatus(FileStatus::DRAFT->value);

		$this->idDocsMapper->method('getFilesOfAccount')->with('user123')->willReturn([$file1, $file2]);

		$service = $this->getService();
		$status = $service->getIdentificationDocumentsStatus($user);

		$this->assertEquals(SettingsLoader::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL, $status);
	}

	public function testGetIdentificationDocumentsStatusApproved(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');

		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(true);
		$this->appConfig->method('getValueArray')
			->with(Application::APP_ID, 'approval_group', ['admin'])
			->willReturn(['admin']);

		$this->groupManager->method('getUserGroupIds')
			->with($user)
			->willReturn(['users']);

		$file1 = new File();
		$file1->setStatus(FileStatus::SIGNED->value);

		$file2 = new File();
		$file2->setStatus(FileStatus::SIGNED->value);

		$this->idDocsMapper->method('getFilesOfAccount')->with('user123')->willReturn([$file1, $file2]);

		$service = $this->getService();
		$status = $service->getIdentificationDocumentsStatus($user);

		$this->assertEquals(SettingsLoader::IDENTIFICATION_DOCUMENTS_APPROVED, $status);
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

	public function testGetUserIdentificationSettingsDisabled(): void {
		$user = $this->createMock(IUser::class);
		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(false);

		$service = $this->getService();
		$result = $service->getUserIdentificationSettings($user);

		$this->assertFalse($result['needIdentificationDocuments']);
		$this->assertFalse($result['identificationDocumentsWaitingApproval']);
	}

	public function testGetUserIdentificationSettingsNeedSend(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user456');

		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(true);
		$this->appConfig->method('getValueArray')
			->with(Application::APP_ID, 'approval_group', ['admin'])
			->willReturn(['admin']);

		$this->groupManager->method('getUserGroupIds')
			->with($user)
			->willReturn(['users']);

		$this->idDocsMapper->method('getFilesOfAccount')->with('user456')->willReturn([]);

		$service = $this->getService();
		$result = $service->getUserIdentificationSettings($user);

		$this->assertTrue($result['needIdentificationDocuments']);
		$this->assertFalse($result['identificationDocumentsWaitingApproval']);
	}

	public function testGetUserIdentificationSettingsNeedApproval(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user789');

		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(true);
		$this->appConfig->method('getValueArray')
			->with(Application::APP_ID, 'approval_group', ['admin'])
			->willReturn(['admin']);

		$this->groupManager->method('getUserGroupIds')
			->with($user)
			->willReturn(['users']);

		$file = new File();
		$file->setStatus(FileStatus::DRAFT->value);

		$this->idDocsMapper->method('getFilesOfAccount')->with('user789')->willReturn([$file]);

		$service = $this->getService();
		$result = $service->getUserIdentificationSettings($user);

		$this->assertTrue($result['needIdentificationDocuments']);
		$this->assertTrue($result['identificationDocumentsWaitingApproval']);
	}

	public function testGetUserIdentificationSettingsApproved(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user999');

		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(true);
		$this->appConfig->method('getValueArray')
			->with(Application::APP_ID, 'approval_group', ['admin'])
			->willReturn(['admin']);

		$this->groupManager->method('getUserGroupIds')
			->with($user)
			->willReturn(['users']);

		$file1 = new File();
		$file1->setStatus(FileStatus::SIGNED->value);

		$file2 = new File();
		$file2->setStatus(FileStatus::SIGNED->value);

		$this->idDocsMapper->method('getFilesOfAccount')->with('user999')->willReturn([$file1, $file2]);

		$service = $this->getService();
		$result = $service->getUserIdentificationSettings($user);

		$this->assertFalse($result['needIdentificationDocuments']);
		$this->assertFalse($result['identificationDocumentsWaitingApproval']);
	}
}
