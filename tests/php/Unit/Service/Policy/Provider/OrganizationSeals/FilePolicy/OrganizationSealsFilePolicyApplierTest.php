<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\OrganizationSeals\FilePolicy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\OrganizationSeal\OrganizationSealStore;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\OrganizationSeals\FilePolicy\OrganizationSealsFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\OrganizationSeals\OrganizationSealsPolicy;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;

final class OrganizationSealsFilePolicyApplierTest extends TestCase {
	private PolicyService&MockObject $policyService;
	private FileService&MockObject $fileService;
	private IL10N&MockObject $l10n;
	private OrganizationSealStore&MockObject $sealStore;

	public function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->policyService->method('getRequestLifecycle')->willReturn(PolicySpec::LIFECYCLE_REQUEST_SNAPSHOT);
		$this->fileService = $this->createMock(FileService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->sealStore = $this->createMock(OrganizationSealStore::class);
		$this->sealStore->method('exists')->willReturnCallback(static fn (string $id): bool => in_array($id, ['first', 'second'], true));
	}

	private function getApplier(): OrganizationSealsFilePolicyApplier {
		$applier = new OrganizationSealsFilePolicyApplier(
			$this->policyService,
			$this->fileService,
			$this->l10n,
			$this->createMock(FileMapper::class),
		);
		$applier->setSealStore($this->sealStore);
		return $applier;
	}

	private function resolvedPolicy(string $value, string $sourceScope, bool $canOverride = true): ResolvedPolicy {
		return (new ResolvedPolicy())
			->setPolicyKey(OrganizationSealsPolicy::KEY)
			->setEffectiveValue($value)
			->setSourceScope($sourceScope)
			->setCanUseAsRequestOverride($canOverride);
	}

	public function testApplyStoresTheRequestedSeals(): void {
		$file = new FileEntity();
		$this->policyService->expects($this->once())
			->method('resolveForUser')
			->with(OrganizationSealsPolicy::KEY, null, [OrganizationSealsPolicy::KEY => '["second","first"]'])
			->willReturn($this->resolvedPolicy('["second","first"]', 'request'));

		$this->getApplier()->apply($file, ['policyOverrides' => [OrganizationSealsPolicy::KEY => ['second', 'first']]]);

		$this->assertSame([
			'effectiveValue' => '["second","first"]',
			'sourceScope' => 'request',
		], $file->getMetadata()['policy_snapshot'][OrganizationSealsPolicy::KEY]);
	}

	public function testApplyWithoutChoiceStoresTheDefault(): void {
		$file = new FileEntity();
		$this->policyService->method('resolveForUser')->willReturn($this->resolvedPolicy('[]', 'system', false));

		$this->getApplier()->apply($file, []);

		$this->assertSame('[]', $file->getMetadata()['policy_snapshot'][OrganizationSealsPolicy::KEY]['effectiveValue']);
	}

	public function testApplyRejectsAnUnknownSeal(): void {
		$this->policyService->expects($this->never())->method('resolveForUser');

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Unknown organization seal.');
		$this->getApplier()->apply(new FileEntity(), ['policyOverrides' => [OrganizationSealsPolicy::KEY => ['first', 'unknown']]]);
	}

	public function testApplyRejectsAChoiceTheAdministratorDidNotAllow(): void {
		$this->policyService->method('resolveForUser')->willReturn($this->resolvedPolicy('[]', 'system', false));

		$this->expectException(LibresignException::class);
		$this->getApplier()->apply(new FileEntity(), ['policyOverrides' => [OrganizationSealsPolicy::KEY => ['first']]]);
	}

	public function testSyncCannotChangeTheSealsOfASentRequest(): void {
		$file = $this->sentRequestWithSeals('["first"]');
		$this->policyService->expects($this->never())->method('resolveForUserId');

		$this->expectException(LibresignException::class);
		$this->getApplier()->sync($file, ['policyOverrides' => [OrganizationSealsPolicy::KEY => ['second']]]);
	}

	public function testSyncAcceptsTheStoredSealsOfASentRequest(): void {
		$file = $this->sentRequestWithSeals('["first"]');
		$this->policyService->expects($this->never())->method('resolveForUserId');
		$this->fileService->expects($this->never())->method('update');

		$this->getApplier()->sync($file, ['policyOverrides' => [OrganizationSealsPolicy::KEY => ['first']]]);
		$this->getApplier()->sync($file, []);

		$this->assertSame('["first"]', $file->getMetadata()['policy_snapshot'][OrganizationSealsPolicy::KEY]['effectiveValue']);
	}

	public function testSyncUpdatesADraft(): void {
		$file = new FileEntity();
		$file->setUserId('john');
		$file->setStatus(FileStatus::DRAFT->value);
		$this->policyService->method('resolveForUserId')->willReturn($this->resolvedPolicy('["second"]', 'request'));
		$this->fileService->expects($this->once())->method('update')->with($this->identicalTo($file));

		$this->getApplier()->sync($file, ['policyOverrides' => [OrganizationSealsPolicy::KEY => ['second']]]);

		$this->assertSame('["second"]', $file->getMetadata()['policy_snapshot'][OrganizationSealsPolicy::KEY]['effectiveValue']);
	}

	private function sentRequestWithSeals(string $seals): FileEntity {
		$file = new FileEntity();
		$file->setUserId('john');
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$file->setMetadata([
			'policy_snapshot' => [
				OrganizationSealsPolicy::KEY => [
					'effectiveValue' => $seals,
					'sourceScope' => 'request',
				],
			],
		]);
		return $file;
	}
}
