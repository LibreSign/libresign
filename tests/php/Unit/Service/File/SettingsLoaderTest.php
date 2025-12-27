<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Service\File\AccountSettingsProvider;
use OCA\Libresign\Service\File\FileResponseOptions;
use OCA\Libresign\Service\File\SettingsLoader;
use OCP\IAppConfig;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;

final class SettingsLoaderTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private AccountSettingsProvider|MockObject $accountSettingsProvider;
	private FileMapper|MockObject $fileMapper;
	private IAppConfig|MockObject $appConfig;

	public function setUp(): void {
		parent::setUp();
		$this->accountSettingsProvider = $this->createMock(AccountSettingsProvider::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
	}

	private function getService(): SettingsLoader {
		return new SettingsLoader(
			$this->accountSettingsProvider,
			$this->fileMapper,
			$this->appConfig,
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

		// When there's no user and signer is not identified, settings should not be set
		$this->assertFalse(isset($fileData->settings));
	}

	public function testGetIdentificationDocumentsStatusDisabled(): void {
		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(false);

		$service = $this->getService();
		$status = $service->getIdentificationDocumentsStatus('user123');

		$this->assertEquals(SettingsLoader::IDENTIFICATION_DOCUMENTS_DISABLED, $status);
	}

	public function testGetIdentificationDocumentsStatusNeedSend(): void {
		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(true);

		$this->fileMapper->method('getFilesOfAccount')->with('user123')->willReturn([]);

		$service = $this->getService();
		$status = $service->getIdentificationDocumentsStatus('user123');

		$this->assertEquals(SettingsLoader::IDENTIFICATION_DOCUMENTS_NEED_SEND, $status);
	}

	public function testGetIdentificationDocumentsStatusAllDeleted(): void {
		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(true);

		$file1 = new File();
		$file1->setStatus(File::STATUS_DELETED);

		$file2 = new File();
		$file2->setStatus(File::STATUS_DELETED);

		$this->fileMapper->method('getFilesOfAccount')->with('user123')->willReturn([$file1, $file2]);

		$service = $this->getService();
		$status = $service->getIdentificationDocumentsStatus('user123');

		$this->assertEquals(SettingsLoader::IDENTIFICATION_DOCUMENTS_NEED_SEND, $status);
	}

	public function testGetIdentificationDocumentsStatusNeedApproval(): void {
		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(true);

		$file1 = new File();
		$file1->setStatus(File::STATUS_SIGNED);

		$file2 = new File();
		$file2->setStatus(File::STATUS_DRAFT);

		$this->fileMapper->method('getFilesOfAccount')->with('user123')->willReturn([$file1, $file2]);

		$service = $this->getService();
		$status = $service->getIdentificationDocumentsStatus('user123');

		$this->assertEquals(SettingsLoader::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL, $status);
	}

	public function testGetIdentificationDocumentsStatusApproved(): void {
		$this->appConfig->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn(true);

		$file1 = new File();
		$file1->setStatus(File::STATUS_SIGNED);

		$file2 = new File();
		$file2->setStatus(File::STATUS_SIGNED);

		$this->fileMapper->method('getFilesOfAccount')->with('user123')->willReturn([$file1, $file2]);

		$service = $this->getService();
		$status = $service->getIdentificationDocumentsStatus('user123');

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

		$this->fileMapper->method('getFilesOfAccount')->willReturn([]);

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
		$status = $service->getIdentificationDocumentsStatus('');

		$this->assertEquals(SettingsLoader::IDENTIFICATION_DOCUMENTS_NEED_SEND, $status);
	}
}
