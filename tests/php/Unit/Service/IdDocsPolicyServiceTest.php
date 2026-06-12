<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\IdDocsPolicyService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\ApprovalGroups\ApprovalGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicy;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class IdDocsPolicyServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private PolicyService&MockObject $policyService;
	private IGroupManager&MockObject $groupManager;
	private IL10N&MockObject $l10n;
	private IdDocsMapper&MockObject $idDocsMapper;

	public function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->l10n = $this->createConfiguredMock(IL10N::class, [
			't' => 'You are not allowed to approve user profile documents.',
		]);
		$this->idDocsMapper = $this->createMock(IdDocsMapper::class);
	}

	private function getService(): IdDocsPolicyService {
		return new IdDocsPolicyService(
			$this->policyService,
			$this->groupManager,
			$this->l10n,
			$this->idDocsMapper,
		);
	}

	#[DataProvider('provideCanApproverSignIdDocScenarios')]
	public function testCanApproverSignIdDoc(
		array $identificationDocumentsValue,
		bool $userCanApprove,
		int $fileStatus,
		bool $idDocExists,
		bool $expectedResult,
	): void {
		$user = $this->createMock(IUser::class);
		$fileId = 123;

		$this->policyService
			->method('resolveForUser')
			->willReturnCallback(static function (string $policyKey, IUser $resolvedUser) use ($user, $identificationDocumentsValue, $userCanApprove): ResolvedPolicy {
				self::assertSame($user, $resolvedUser);

				if ($policyKey === IdentificationDocumentsPolicy::KEY) {
					return (new ResolvedPolicy())->setEffectiveValue($identificationDocumentsValue);
				}

				if ($policyKey === ApprovalGroupsPolicy::KEY) {
					return (new ResolvedPolicy())->setEffectiveValue($userCanApprove ? ['approvers'] : ['admin']);
				}

				self::fail('Unexpected policy key: ' . $policyKey);
			});

		$this->groupManager
			->method('getUserGroupIds')
			->with($user)
			->willReturn($userCanApprove ? ['approvers'] : ['users']);

		$identificationDocumentsEnabled = $identificationDocumentsValue['enabled'] ?? false;
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

	#[DataProvider('provideGetApproverGroupsScenarios')]
	public function testGetApproverGroups(
		array $identificationDocumentsValue,
		array $expectedApprovers,
	): void {
		$this->policyService
			->method('resolve')
			->with(IdentificationDocumentsPolicy::KEY)
			->willReturn(
				(new ResolvedPolicy())->setEffectiveValue($identificationDocumentsValue)
			);

		$result = $this->getService()->getApproverGroups();

		$this->assertSame($expectedApprovers, $result);
	}

	public static function provideCanApproverSignIdDocScenarios(): array {
		return [
			'feature disabled' => [
				'identificationDocumentsValue' => ['enabled' => false, 'approvers' => ['admin']],
				'userCanApprove' => true,
				'fileStatus' => FileStatus::ABLE_TO_SIGN->value,
				'idDocExists' => true,
				'expectedResult' => false,
			],
			'user cannot approve' => [
				'identificationDocumentsValue' => ['enabled' => true, 'approvers' => ['approvers']],
				'userCanApprove' => false,
				'fileStatus' => FileStatus::ABLE_TO_SIGN->value,
				'idDocExists' => true,
				'expectedResult' => false,
			],
			'file status is draft' => [
				'identificationDocumentsValue' => ['enabled' => true, 'approvers' => ['approvers']],
				'userCanApprove' => true,
				'fileStatus' => FileStatus::DRAFT->value,
				'idDocExists' => true,
				'expectedResult' => false,
			],
			'file status is deleted' => [
				'identificationDocumentsValue' => ['enabled' => true, 'approvers' => ['approvers']],
				'userCanApprove' => true,
				'fileStatus' => FileStatus::DELETED->value,
				'idDocExists' => true,
				'expectedResult' => false,
			],
			'file is not an id doc' => [
				'identificationDocumentsValue' => ['enabled' => true, 'approvers' => ['approvers']],
				'userCanApprove' => true,
				'fileStatus' => FileStatus::ABLE_TO_SIGN->value,
				'idDocExists' => false,
				'expectedResult' => false,
			],
			'all conditions met with ABLE_TO_SIGN' => [
				'identificationDocumentsValue' => ['enabled' => true, 'approvers' => ['approvers']],
				'userCanApprove' => true,
				'fileStatus' => FileStatus::ABLE_TO_SIGN->value,
				'idDocExists' => true,
				'expectedResult' => true,
			],
			'all conditions met with PARTIAL_SIGNED' => [
				'identificationDocumentsValue' => ['enabled' => true, 'approvers' => ['approvers']],
				'userCanApprove' => true,
				'fileStatus' => FileStatus::PARTIAL_SIGNED->value,
				'idDocExists' => true,
				'expectedResult' => true,
			],
			'file is already signed' => [
				'identificationDocumentsValue' => ['enabled' => true, 'approvers' => ['approvers']],
				'userCanApprove' => true,
				'fileStatus' => FileStatus::SIGNED->value,
				'idDocExists' => true,
				'expectedResult' => false,
			],
		];
	}

	public static function provideGetApproverGroupsScenarios(): array {
		return [
			'returns custom approvers' => [
				'identificationDocumentsValue' => ['enabled' => true, 'approvers' => ['group1', 'group2']],
				'expectedApprovers' => ['group1', 'group2'],
			],
			'returns default approvers' => [
				'identificationDocumentsValue' => ['enabled' => false, 'approvers' => ['admin']],
				'expectedApprovers' => ['admin'],
			],
		];
	}
}
