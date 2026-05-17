<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\FilePolicyApplier;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicy;
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

	public function testApplyAllAppliesAllRegisteredFilePolicies(): void {
		$file = new FileEntity();
		$identifyMethodsPolicyValue = [
			[
				'name' => 'email',
				'enabled' => true,
				'requirement' => 'required',
				'mandatory' => true,
				'minimumTotalVerifiedFactors' => 2,
			],
		];

		$this->policyService
			->expects($this->exactly(4))
			->method('resolveForUser')
			->willReturnCallback(function (string $policyKey) use ($identifyMethodsPolicyValue): ResolvedPolicy {
				return match ($policyKey) {
					SignatureFlowPolicy::KEY => $this->createResolvedPolicy(
						SignatureFlowPolicy::KEY,
						SignatureFlow::PARALLEL->value,
						'system',
					),
					DocMdpPolicy::KEY => $this->createResolvedPolicy(
						DocMdpPolicy::KEY,
						DocMdpLevel::CERTIFIED_FORM_FILLING->value,
						'group',
					),
					FooterPolicy::KEY => $this->createResolvedPolicy(
						FooterPolicy::KEY,
						'{"enabled":true}',
						'system',
					),
					IdentifyMethodsPolicy::KEY => $this->createResolvedPolicy(
						IdentifyMethodsPolicy::KEY,
						$identifyMethodsPolicyValue,
						'group',
					),
					default => throw new \RuntimeException('Unexpected policy key: ' . $policyKey),
				};
			});

		$this->getApplier()->applyAll($file, []);

		$this->assertSame(SignatureFlow::PARALLEL, $file->getSignatureFlowEnum());
		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING, $file->getDocmdpLevelEnum());
		$metadata = $file->getMetadata() ?? [];
		$this->assertArrayHasKey('policy_snapshot', $metadata);
		$this->assertSame([
			'effectiveValue' => SignatureFlow::PARALLEL->value,
			'sourceScope' => 'system',
		], $metadata['policy_snapshot'][SignatureFlowPolicy::KEY] ?? null);
		$this->assertSame([
			'effectiveValue' => DocMdpLevel::CERTIFIED_FORM_FILLING->value,
			'sourceScope' => 'group',
		], $metadata['policy_snapshot'][DocMdpPolicy::KEY] ?? null);
		$this->assertSame([
			'effectiveValue' => '{"enabled":true}',
			'sourceScope' => 'system',
		], $metadata['policy_snapshot'][FooterPolicy::KEY] ?? null);
		$this->assertSame([
			'effectiveValue' => $identifyMethodsPolicyValue,
			'sourceScope' => 'group',
		], $metadata['policy_snapshot'][IdentifyMethodsPolicy::KEY] ?? null);
	}

	public function testSyncCoreFlowPoliciesSkipsNonCoreProviders(): void {
		$file = new FileEntity();
		$file->setUserId('john');
		$file->setSignatureFlowEnum(SignatureFlow::NONE);
		$file->setDocmdpLevelEnum(DocMdpLevel::NOT_CERTIFIED);

		$this->policyService
			->expects($this->exactly(2))
			->method('resolveForUserId')
			->willReturnCallback(function (string $policyKey): ResolvedPolicy {
				return match ($policyKey) {
					SignatureFlowPolicy::KEY => $this->createResolvedPolicy(
						SignatureFlowPolicy::KEY,
						SignatureFlow::ORDERED_NUMERIC->value,
						'group',
					),
					DocMdpPolicy::KEY => $this->createResolvedPolicy(
						DocMdpPolicy::KEY,
						DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS->value,
						'system',
					),
					default => throw new \RuntimeException('Unexpected policy key: ' . $policyKey),
				};
			});

		$this->fileService
			->expects($this->exactly(2))
			->method('update')
			->with($this->identicalTo($file));

		$this->getApplier()->syncCoreFlowPolicies($file, []);

		$this->assertArrayNotHasKey(FooterPolicy::KEY, $file->getMetadata()['policy_snapshot']);
		$this->assertSame(SignatureFlow::ORDERED_NUMERIC, $file->getSignatureFlowEnum());
		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS, $file->getDocmdpLevelEnum());
	}

	public function testSyncAllPoliciesSyncsAllRegisteredProviders(): void {
		$file = new FileEntity();
		$file->setUserId('john');
		$file->setSignatureFlowEnum(SignatureFlow::PARALLEL);
		$file->setDocmdpLevelEnum(DocMdpLevel::CERTIFIED_FORM_FILLING);
		$file->setMetadata([
			'policy_snapshot' => [
				SignatureFlowPolicy::KEY => [
					'effectiveValue' => SignatureFlow::PARALLEL->value,
					'sourceScope' => 'system',
				],
				DocMdpPolicy::KEY => [
					'effectiveValue' => DocMdpLevel::CERTIFIED_FORM_FILLING->value,
					'sourceScope' => 'group',
				],
				FooterPolicy::KEY => [
					'effectiveValue' => '{"enabled":false}',
					'sourceScope' => 'system',
				],
				IdentifyMethodsPolicy::KEY => [
					'effectiveValue' => [
						[
							'name' => 'email',
							'enabled' => true,
							'requirement' => 'required',
							'mandatory' => true,
						],
					],
					'sourceScope' => 'system',
				],
			],
		]);

		$this->policyService
			->expects($this->exactly(4))
			->method('resolveForUserId')
			->willReturnCallback(function (string $policyKey): ResolvedPolicy {
				return match ($policyKey) {
					SignatureFlowPolicy::KEY => $this->createResolvedPolicy(
						SignatureFlowPolicy::KEY,
						SignatureFlow::PARALLEL->value,
						'system',
					),
					DocMdpPolicy::KEY => $this->createResolvedPolicy(
						DocMdpPolicy::KEY,
						DocMdpLevel::CERTIFIED_FORM_FILLING->value,
						'group',
					),
					FooterPolicy::KEY => $this->createResolvedPolicy(
						FooterPolicy::KEY,
						'{"enabled":false}',
						'system',
					),
					IdentifyMethodsPolicy::KEY => $this->createResolvedPolicy(
						IdentifyMethodsPolicy::KEY,
						[
							[
								'name' => 'email',
								'enabled' => true,
								'requirement' => 'required',
								'mandatory' => true,
							],
						],
						'system',
					),
					default => throw new \RuntimeException('Unexpected policy key: ' . $policyKey),
				};
			});

		$this->fileService
			->expects($this->never())
			->method('update');

		$this->getApplier()->syncAllPolicies($file, []);

		$this->assertSame(SignatureFlow::PARALLEL, $file->getSignatureFlowEnum());
		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING, $file->getDocmdpLevelEnum());
		$this->assertArrayHasKey(FooterPolicy::KEY, $file->getMetadata()['policy_snapshot']);
	}

	private function createResolvedPolicy(
		string $policyKey,
		mixed $effectiveValue,
		string $sourceScope,
	): ResolvedPolicy {
		return (new ResolvedPolicy())
			->setPolicyKey($policyKey)
			->setEffectiveValue($effectiveValue)
			->setSourceScope($sourceScope)
			->setVisible(true)
			->setEditableByCurrentActor(true)
			->setCanUseAsRequestOverride(true)
			->setCanSaveAsUserDefault(true);
	}
}
