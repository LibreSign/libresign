<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\File\AccountSettingsProvider;
use OCA\Libresign\Service\File\FileResponseOptions;
use OCA\Libresign\Service\File\SettingsLoader;
use OCA\Libresign\Service\IdDocsPolicyService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

final class SettingsLoaderApproverTest extends TestCase {
	public function testApproverGetsSignatureMethodsOfOwnAccount(): void {
		$accountSettingsProvider = $this->createMock(AccountSettingsProvider::class);
		$idDocsPolicyService = $this->createMock(IdDocsPolicyService::class);
		$appConfig = $this->createMock(IAppConfig::class);
		$groupManager = $this->createMock(IGroupManager::class);
		$idDocsMapper = $this->createMock(IdDocsMapper::class);
		$identifyMethodService = $this->createMock(IdentifyMethodService::class);

		$approver = $this->createMock(IUser::class);
		$approver->method('getUID')->willReturn('approver');

		$fileData = new \stdClass();
		$fileData->id = 10;
		$fileData->status = FileStatus::ABLE_TO_SIGN->value;

		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowSettings')->willReturn(true);
		$options->method('getMe')->willReturn($approver);
		$options->method('getSignRequest')->willReturn(null);

		$appConfig->method('getValueBool')->willReturn(false);
		$accountSettingsProvider->method('getSettings')->with($approver)->willReturn([]);
		$accountSettingsProvider->method('getPhoneNumber')->with($approver)->willReturn('');
		$idDocsPolicyService->method('canApproverSignIdDoc')
			->with($approver, 10, FileStatus::ABLE_TO_SIGN->value)
			->willReturn(true);

		$approverMethods = [
			'emailToken' => [
				'identifyMethod' => 'account',
				'blurredEmail' => 'a*****@example.coop',
				'needCode' => true,
				'hasConfirmCode' => false,
			],
		];
		$identifyMethodService->expects($this->once())
			->method('getSignMethodsOfAccount')
			->with('approver')
			->willReturn($approverMethods);
		$identifyMethodService->expects($this->never())
			->method('getSignMethodsOfIdentifiedFactors');
		$idDocsMapper->expects($this->never())->method('getByFileId');

		$service = new SettingsLoader(
			$accountSettingsProvider,
			$idDocsPolicyService,
			$appConfig,
			$groupManager,
			$idDocsMapper,
			$identifyMethodService,
		);
		$service->loadSettings($fileData, $options);

		$this->assertTrue($fileData->settings['isApprover']);
		$this->assertSame($approverMethods, $fileData->settings['signatureMethods']);
	}
}
