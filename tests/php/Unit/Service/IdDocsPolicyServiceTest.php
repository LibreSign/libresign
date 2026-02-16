<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\IdDocsPolicyService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IAppConfig;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class IdDocsPolicyServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IAppConfig&MockObject $appConfig;
	private ValidateHelper&MockObject $validateHelper;
	private IdDocsMapper&MockObject $idDocsMapper;

	public function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->validateHelper = $this->createMock(ValidateHelper::class);
		$this->idDocsMapper = $this->createMock(IdDocsMapper::class);
	}

	private function getService(): IdDocsPolicyService {
		return new IdDocsPolicyService(
			$this->appConfig,
			$this->validateHelper,
			$this->idDocsMapper,
		);
	}

	#[DataProvider('provideCanApproverSignIdDocScenarios')]
	public function testCanApproverSignIdDoc(
		bool $identificationDocumentsEnabled,
		bool $userCanApprove,
		int $fileStatus,
		bool $idDocExists,
		bool $expectedResult,
	): void {
		$user = $this->createMock(IUser::class);
		$fileId = 123;

		$this->appConfig
			->method('getValueBool')
			->with(Application::APP_ID, 'identification_documents', false)
			->willReturn($identificationDocumentsEnabled);

		$this->validateHelper
			->method('userCanApproveValidationDocuments')
			->with($user, false)
			->willReturn($userCanApprove);

		if ($identificationDocumentsEnabled && $userCanApprove && in_array($fileStatus, [FileStatus::ABLE_TO_SIGN->value, FileStatus::PARTIAL_SIGNED->value])) {
			if ($idDocExists) {
				$this->idDocsMapper
					->method('getByFileId')
					->with($fileId)
					->willReturn($this->createMock(\OCA\Libresign\Db\IdDocs::class));
			} else {
				$this->idDocsMapper
					->method('getByFileId')
					->with($fileId)
					->willThrowException(new DoesNotExistException(''));
			}
		}

		$result = $this->getService()->canApproverSignIdDoc($user, $fileId, $fileStatus);

		$this->assertSame($expectedResult, $result);
	}

	public static function provideCanApproverSignIdDocScenarios(): array {
		return [
			'feature disabled' => [
				'identificationDocumentsEnabled' => false,
				'userCanApprove' => true,
				'fileStatus' => FileStatus::ABLE_TO_SIGN->value,
				'idDocExists' => true,
				'expectedResult' => false,
			],
			'user cannot approve' => [
				'identificationDocumentsEnabled' => true,
				'userCanApprove' => false,
				'fileStatus' => FileStatus::ABLE_TO_SIGN->value,
				'idDocExists' => true,
				'expectedResult' => false,
			],
			'file status is draft' => [
				'identificationDocumentsEnabled' => true,
				'userCanApprove' => true,
				'fileStatus' => FileStatus::DRAFT->value,
				'idDocExists' => true,
				'expectedResult' => false,
			],
			'file status is deleted' => [
				'identificationDocumentsEnabled' => true,
				'userCanApprove' => true,
				'fileStatus' => FileStatus::DELETED->value,
				'idDocExists' => true,
				'expectedResult' => false,
			],
			'file is not an id doc' => [
				'identificationDocumentsEnabled' => true,
				'userCanApprove' => true,
				'fileStatus' => FileStatus::ABLE_TO_SIGN->value,
				'idDocExists' => false,
				'expectedResult' => false,
			],
			'all conditions met with ABLE_TO_SIGN' => [
				'identificationDocumentsEnabled' => true,
				'userCanApprove' => true,
				'fileStatus' => FileStatus::ABLE_TO_SIGN->value,
				'idDocExists' => true,
				'expectedResult' => true,
			],
			'all conditions met with PARTIAL_SIGNED' => [
				'identificationDocumentsEnabled' => true,
				'userCanApprove' => true,
				'fileStatus' => FileStatus::PARTIAL_SIGNED->value,
				'idDocExists' => true,
				'expectedResult' => true,
			],
			'file is already signed' => [
				'identificationDocumentsEnabled' => true,
				'userCanApprove' => true,
				'fileStatus' => FileStatus::SIGNED->value,
				'idDocExists' => true,
				'expectedResult' => false,
			],
		];
	}
}
