<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy;

use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\FilePolicyApplier;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;

final class FilePolicyApplierTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private PolicyService&MockObject $policyService;
	private FileService&MockObject $fileService;
	private IL10N&MockObject $l10n;

	public function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->fileService = $this->createMock(FileService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
	}

	private function getApplier(): FilePolicyApplier {
		return new FilePolicyApplier(
			$this->policyService,
			$this->fileService,
			$this->l10n,
		);
	}

	// -------------------------------------------------------------------------
	// applySignatureFlow (was setSignatureFlow in RequestSignatureService)
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider applySignatureFlowCasesProvider
	 * @param array<string, mixed> $data
	 */
	public function testApplySignatureFlowResolvesExpectedValue(array $data, string $resolvedValue, SignatureFlow $expected): void {
		$file = new \OCA\Libresign\Db\File();
		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(
				SignatureFlowPolicy::KEY,
				null,
				isset($data['policyOverrides'][SignatureFlowPolicy::KEY])
					? [SignatureFlowPolicy::KEY => $data['policyOverrides'][SignatureFlowPolicy::KEY]]
					: [],
			)
			->willReturn($this->createResolvedPolicy($resolvedValue));

		self::invokePrivate($this->getApplier(), 'applySignatureFlow', [
			$file,
			$data,
		]);

		$this->assertSame($expected, $file->getSignatureFlowEnum());
	}

	/** @return array<string, array{0: array<string, mixed>, 1: string, 2: SignatureFlow}> */
	public static function applySignatureFlowCasesProvider(): array {
		return [
			'payload_override_has_priority' => [
				['policyOverrides' => [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value]],
				SignatureFlow::PARALLEL->value,
				SignatureFlow::PARALLEL,
			],
			'global_policy_used_when_payload_missing' => [
				[],
				SignatureFlow::ORDERED_NUMERIC->value,
				SignatureFlow::ORDERED_NUMERIC,
			],
			'default_none_when_no_payload_or_forced_global' => [
				[],
				SignatureFlow::NONE->value,
				SignatureFlow::NONE,
			],
		];
	}

	public function testApplySignatureFlowThrowsWhenRequestOverrideIsBlocked(): void {
		$file = new \OCA\Libresign\Db\File();
		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(SignatureFlowPolicy::KEY, null, [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value])
			->willReturn($this->createResolvedPolicy(
				SignatureFlow::ORDERED_NUMERIC->value,
				sourceScope: 'group',
				canUseAsRequestOverride: false,
				blockedBy: 'group',
			));

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);

		self::invokePrivate($this->getApplier(), 'applySignatureFlow', [
			$file,
			['policyOverrides' => [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value]],
		]);
	}

	public function testApplySignatureFlowStoresResolvedPolicySnapshotInMetadata(): void {
		$file = new \OCA\Libresign\Db\File();
		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(SignatureFlowPolicy::KEY, null, [])
			->willReturn($this->createResolvedPolicy(
				SignatureFlow::ORDERED_NUMERIC->value,
				sourceScope: 'group',
			));

		self::invokePrivate($this->getApplier(), 'applySignatureFlow', [
			$file,
			[],
		]);

		$this->assertSame(SignatureFlow::ORDERED_NUMERIC, $file->getSignatureFlowEnum());
		$this->assertSame([
			'policy_snapshot' => [
				'signature_flow' => [
					'effectiveValue' => SignatureFlow::ORDERED_NUMERIC->value,
					'sourceScope' => 'group',
				],
			],
		], $file->getMetadata());
	}

	// -------------------------------------------------------------------------
	// syncSignatureFlow (was updateSignatureFlowIfAllowed in RequestSignatureService)
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider syncSignatureFlowUpdateCasesProvider
	 */
	public function testSyncSignatureFlowUpdatesWhenResolvedFlowDiffers(
		SignatureFlow $initialFlow,
		array $data,
		string $resolvedValue,
		SignatureFlow $expectedFlow,
	): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setSignatureFlowEnum($initialFlow);
		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(
				SignatureFlowPolicy::KEY,
				'john',
				isset($data['policyOverrides'][SignatureFlowPolicy::KEY])
					? [SignatureFlowPolicy::KEY => $data['policyOverrides'][SignatureFlowPolicy::KEY]]
					: [],
			)
			->willReturn($this->createResolvedPolicy($resolvedValue));

		$this->fileService
			->expects($this->once())
			->method('update')
			->with($this->identicalTo($file));

		self::invokePrivate($this->getApplier(), 'syncSignatureFlow', [
			$file,
			$data,
		]);

		$this->assertSame($expectedFlow, $file->getSignatureFlowEnum());
	}

	/** @return array<string, array{0: SignatureFlow, 1: array<string, mixed>, 2: string, 3: SignatureFlow}> */
	public static function syncSignatureFlowUpdateCasesProvider(): array {
		return [
			'global_forces_flow_change' => [
				SignatureFlow::PARALLEL,
				['policyOverrides' => [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value]],
				SignatureFlow::ORDERED_NUMERIC->value,
				SignatureFlow::ORDERED_NUMERIC,
			],
			'payload_resolved_flow_applied' => [
				SignatureFlow::NONE,
				['policyOverrides' => [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value]],
				SignatureFlow::PARALLEL->value,
				SignatureFlow::PARALLEL,
			],
		];
	}

	public function testSyncSignatureFlowThrowsWhenRequestOverrideIsBlocked(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setSignatureFlowEnum(SignatureFlow::NONE);
		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(SignatureFlowPolicy::KEY, 'john', [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value])
			->willReturn($this->createResolvedPolicy(
				SignatureFlow::ORDERED_NUMERIC->value,
				sourceScope: 'group',
				canUseAsRequestOverride: false,
				blockedBy: 'group',
			));

		$this->fileService
			->expects($this->never())
			->method('update');

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);

		self::invokePrivate($this->getApplier(), 'syncSignatureFlow', [
			$file,
			['policyOverrides' => [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value]],
		]);
	}

	public function testSyncSignatureFlowKeepsCurrentValueWithoutPayloadOrForcedGlobal(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setSignatureFlowEnum(SignatureFlow::PARALLEL);
		$file->setMetadata([
			'policy_snapshot' => [
				'signature_flow' => [
					'effectiveValue' => SignatureFlow::PARALLEL->value,
					'sourceScope' => 'system',
				],
			],
		]);
		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(SignatureFlowPolicy::KEY, 'john', [])
			->willReturn($this->createResolvedPolicy(SignatureFlow::PARALLEL->value));

		$this->fileService
			->expects($this->never())
			->method('update');

		self::invokePrivate($this->getApplier(), 'syncSignatureFlow', [
			$file,
			[],
		]);

		$this->assertSame(SignatureFlow::PARALLEL, $file->getSignatureFlowEnum());
	}

	public function testSyncSignatureFlowStoresResolvedPolicySnapshotWhenMissing(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setSignatureFlowEnum(SignatureFlow::PARALLEL);
		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(SignatureFlowPolicy::KEY, 'john', [])
			->willReturn($this->createResolvedPolicy(
				SignatureFlow::PARALLEL->value,
				sourceScope: 'group',
			));

		$this->fileService
			->expects($this->once())
			->method('update')
			->with($this->identicalTo($file));

		self::invokePrivate($this->getApplier(), 'syncSignatureFlow', [
			$file,
			[],
		]);

		$this->assertSame([
			'policy_snapshot' => [
				'signature_flow' => [
					'effectiveValue' => SignatureFlow::PARALLEL->value,
					'sourceScope' => 'group',
				],
			],
		], $file->getMetadata());
	}

	// -------------------------------------------------------------------------
	// applyDocMdpLevel (was setDocMdpLevelFromPolicy in RequestSignatureService)
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider applyDocMdpCasesProvider
	 * @param array<string, mixed> $data
	 */
	public function testApplyDocMdpLevelUsesResolvedPolicyValueAndSnapshot(
		array $data,
		int $resolvedValue,
		DocMdpLevel $expectedLevel,
		string $expectedSourceScope,
	): void {
		$file = new \OCA\Libresign\Db\File();
		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(
				DocMdpPolicy::KEY,
				null,
				isset($data['policyOverrides'][DocMdpPolicy::KEY])
					? [DocMdpPolicy::KEY => $data['policyOverrides'][DocMdpPolicy::KEY]]
					: [],
			)
			->willReturn($this->createResolvedPolicy(
				$resolvedValue,
				sourceScope: $expectedSourceScope,
				policyKey: DocMdpPolicy::KEY,
			));

		self::invokePrivate($this->getApplier(), 'applyDocMdpLevel', [
			$file,
			$data,
		]);

		$this->assertSame($expectedLevel, $file->getDocmdpLevelEnum());

		$this->assertSame([
			'policy_snapshot' => [
				'docmdp' => [
					'effectiveValue' => $resolvedValue,
					'sourceScope' => $expectedSourceScope,
				],
			],
		], $file->getMetadata());
	}

	/** @return array<string, array{0: array<string, mixed>, 1: int, 2: DocMdpLevel, 3: string}> */
	public static function applyDocMdpCasesProvider(): array {
		return [
			'default_system_value' => [
				[],
				2,
				DocMdpLevel::CERTIFIED_FORM_FILLING,
				'system',
			],
			'payload_override_group_value' => [
				['policyOverrides' => [DocMdpPolicy::KEY => 3]],
				3,
				DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS,
				'group',
			],
		];
	}

	// -------------------------------------------------------------------------
	// syncDocMdpLevel (was updateDocMdpLevelFromPolicy in RequestSignatureService)
	// -------------------------------------------------------------------------

	public function testSyncDocMdpLevelUpdatesFileWhenEffectiveValueChanges(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setDocmdpLevelEnum(DocMdpLevel::NOT_CERTIFIED);

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(DocMdpPolicy::KEY, 'john', [DocMdpPolicy::KEY => 1])
			->willReturn($this->createResolvedPolicy(
				1,
				policyKey: DocMdpPolicy::KEY,
			));

		$this->fileService
			->expects($this->once())
			->method('update')
			->with($this->identicalTo($file));

		self::invokePrivate($this->getApplier(), 'syncDocMdpLevel', [
			$file,
			['policyOverrides' => [DocMdpPolicy::KEY => 1]],
		]);

		$this->assertSame(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED, $file->getDocmdpLevelEnum());
	}

	public function testSyncDocMdpLevelDoesNotPersistWhenNothingChangedAndSnapshotExists(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setDocmdpLevelEnum(DocMdpLevel::CERTIFIED_FORM_FILLING);
		$file->setMetadata([
			'policy_snapshot' => [
				'docmdp' => [
					'effectiveValue' => 2,
					'sourceScope' => 'system',
				],
			],
		]);

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(DocMdpPolicy::KEY, 'john', [])
			->willReturn($this->createResolvedPolicy(
				2,
				policyKey: DocMdpPolicy::KEY,
			));

		$this->fileService
			->expects($this->never())
			->method('update');

		self::invokePrivate($this->getApplier(), 'syncDocMdpLevel', [
			$file,
			[],
		]);
	}

	// -------------------------------------------------------------------------
	// applyFooterPolicy (was setFooterPolicyFromPolicy in RequestSignatureService)
	// -------------------------------------------------------------------------

	public function testApplyFooterPolicyStoresResolvedPolicySnapshotInMetadata(): void {
		$file = new \OCA\Libresign\Db\File();
		$footerPolicyValue = '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"<p>Group footer</p>","previewWidth":595,"previewHeight":100,"previewZoom":100}';

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(FooterPolicy::KEY, null, [FooterPolicy::KEY => $footerPolicyValue])
			->willReturn($this->createResolvedPolicy(
				$footerPolicyValue,
				sourceScope: 'group',
				policyKey: FooterPolicy::KEY,
			));

		self::invokePrivate($this->getApplier(), 'applyFooterPolicy', [
			$file,
			['policyOverrides' => [FooterPolicy::KEY => $footerPolicyValue]],
		]);

		$this->assertSame([
			'policy_snapshot' => [
				'add_footer' => [
					'effectiveValue' => $footerPolicyValue,
					'sourceScope' => 'group',
				],
			],
		], $file->getMetadata());
	}

	public function testApplyFooterPolicyThrowsWhenRequestOverrideIsBlocked(): void {
		$file = new \OCA\Libresign\Db\File();
		$footerPolicyValue = '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"<p>User footer</p>","previewWidth":595,"previewHeight":100,"previewZoom":100}';

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(FooterPolicy::KEY, null, [FooterPolicy::KEY => $footerPolicyValue])
			->willReturn($this->createResolvedPolicy(
				$footerPolicyValue,
				sourceScope: 'group',
				canUseAsRequestOverride: false,
				blockedBy: 'group',
				policyKey: FooterPolicy::KEY,
			));

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);

		self::invokePrivate($this->getApplier(), 'applyFooterPolicy', [
			$file,
			['policyOverrides' => [FooterPolicy::KEY => $footerPolicyValue]],
		]);
	}

	// -------------------------------------------------------------------------
	// syncFooterPolicy (was updateFooterPolicyFromPolicy in RequestSignatureService)
	// -------------------------------------------------------------------------

	public function testSyncFooterPolicyPersistsWhenSnapshotChanges(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$footerPolicyValue = '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"<p>Request footer</p>","previewWidth":595,"previewHeight":100,"previewZoom":100}';

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(FooterPolicy::KEY, 'john', [FooterPolicy::KEY => $footerPolicyValue])
			->willReturn($this->createResolvedPolicy(
				$footerPolicyValue,
				sourceScope: 'request',
				policyKey: FooterPolicy::KEY,
			));

		$this->fileService
			->expects($this->once())
			->method('update')
			->with($this->identicalTo($file));

		self::invokePrivate($this->getApplier(), 'syncFooterPolicy', [
			$file,
			['policyOverrides' => [FooterPolicy::KEY => $footerPolicyValue]],
		]);

		$this->assertSame([
			'policy_snapshot' => [
				'add_footer' => [
					'effectiveValue' => $footerPolicyValue,
					'sourceScope' => 'request',
				],
			],
		], $file->getMetadata());
	}

	// -------------------------------------------------------------------------
	// Shared helper
	// -------------------------------------------------------------------------

	private function createResolvedPolicy(
		mixed $effectiveValue,
		string $sourceScope = 'system',
		bool $canUseAsRequestOverride = true,
		?string $blockedBy = null,
		string $policyKey = 'signature_flow',
	): ResolvedPolicy {
		return (new ResolvedPolicy())
			->setPolicyKey($policyKey)
			->setEffectiveValue($effectiveValue)
			->setSourceScope($sourceScope)
			->setCanUseAsRequestOverride($canUseAsRequestOverride)
			->setBlockedBy($blockedBy);
	}
}
