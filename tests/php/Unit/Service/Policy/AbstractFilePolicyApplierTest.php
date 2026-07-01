<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AbstractFilePolicyApplierTestDouble extends \OCA\Libresign\Service\Policy\AbstractFilePolicyApplier {
	#[\Override]
	public function apply(FileEntity $file, array $data): void {
	}

	#[\Override]
	public function sync(FileEntity $file, array $data): void {
	}

	#[\Override]
	public function supportsCoreFlowSync(): bool {
		return false;
	}

	public function exposeExtractActiveContext(array $data): ?array {
		return $this->extractActiveContext($data);
	}

	public function exposeExtractSinglePolicyOverride(array $data, string $policyKey, ?callable $normalizer = null): array {
		return $this->extractSinglePolicyOverride($data, $policyKey, $normalizer);
	}

	public function exposeStorePolicySnapshot(FileEntity $file, ResolvedPolicy $resolvedPolicy, mixed $effectiveValue = null): void {
		$this->storePolicySnapshot($file, $resolvedPolicy, $effectiveValue);
	}

	public function exposeAssertRequestOverrideAllowed(array $requestOverrides, ResolvedPolicy $resolvedPolicy, string $message): void {
		$this->assertRequestOverrideAllowed($requestOverrides, $resolvedPolicy, $message);
	}
}

final class AbstractFilePolicyApplierTest extends TestCase {
	private PolicyService&MockObject $policyService;
	private FileService&MockObject $fileService;
	private IL10N&MockObject $l10n;

	protected function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->fileService = $this->createMock(FileService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(static fn (string $text, array $parameters = []): string => $parameters === []
			? $text
			: vsprintf($text, $parameters));
	}

	#[DataProvider('activeContextProvider')]
	public function testExtractActiveContext(array $data, ?array $expected): void {
		$applier = $this->createApplier();

		self::assertSame($expected, $applier->exposeExtractActiveContext($data));
	}

	public static function activeContextProvider(): array {
		return [
			'missing context' => [[], null],
			'non array context' => [['policyActiveContext' => 'invalid'], null],
			'missing type' => [['policyActiveContext' => ['id' => 'group-1']], null],
			'missing id' => [['policyActiveContext' => ['type' => 'group']], null],
			'empty values' => [['policyActiveContext' => ['type' => '', 'id' => '']], null],
			'valid context' => [
				['policyActiveContext' => ['type' => 'group', 'id' => 'group-1']],
				['type' => 'group', 'id' => 'group-1'],
			],
		];
	}

	#[DataProvider('singleOverrideProvider')]
	public function testExtractSinglePolicyOverride(array $data, string $policyKey, ?callable $normalizer, array $expected): void {
		$applier = $this->createApplier();

		self::assertSame($expected, $applier->exposeExtractSinglePolicyOverride($data, $policyKey, $normalizer));
	}

	public static function singleOverrideProvider(): array {
		return [
			'missing overrides' => [[], 'signature_flow', null, []],
			'other key only' => [['policyOverrides' => ['other' => 'value']], 'signature_flow', null, []],
			'raw string override' => [
				['policyOverrides' => ['signature_flow' => 'parallel']],
				'signature_flow',
				null,
				['signature_flow' => 'parallel'],
			],
			'normalized complex override' => [
				['policyOverrides' => ['identification_documents' => ['enabled' => 1, 'approvers' => ['legal']]]],
				'identification_documents',
				static fn (mixed $value): array => [
					'enabled' => (bool)($value['enabled'] ?? false),
					'approvers' => $value['approvers'] ?? [],
				],
				['identification_documents' => ['enabled' => true, 'approvers' => ['legal']]],
			],
		];
	}

	public function testStorePolicySnapshotMergesExistingMetadata(): void {
		$file = new FileEntity();
		$file->setMetadata([
			'existing' => 'value',
			'policy_snapshot' => [
				'other_policy' => [
					'effectiveValue' => 'keep-me',
					'sourceScope' => 'system',
				],
			],
		]);

		$applier = $this->createApplier();
		$applier->exposeStorePolicySnapshot(
			$file,
			$this->createResolvedPolicy('signature_flow', 'parallel', 'group'),
		);

		self::assertSame([
			'existing' => 'value',
			'policy_snapshot' => [
				'other_policy' => [
					'effectiveValue' => 'keep-me',
					'sourceScope' => 'system',
				],
				'signature_flow' => [
					'effectiveValue' => 'parallel',
					'sourceScope' => 'group',
				],
			],
		], $file->getMetadata());
	}

	public function testStorePolicySnapshotAllowsCustomSnapshotValue(): void {
		$file = new FileEntity();
		$applier = $this->createApplier();
		$applier->exposeStorePolicySnapshot(
			$file,
			$this->createResolvedPolicy('identify_methods', '[{"name":"email"}]', 'user'),
			[['name' => 'email', 'enabled' => true]],
		);

		self::assertSame([
			'policy_snapshot' => [
				'identify_methods' => [
					'effectiveValue' => [['name' => 'email', 'enabled' => true]],
					'sourceScope' => 'user',
				],
			],
		], $file->getMetadata());
	}

	public function testAssertRequestOverrideAllowedThrowsWhenPolicyBlocksOverride(): void {
		$applier = $this->createApplier();

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->expectExceptionMessage('Signature flow override is blocked by group.');

		$applier->exposeAssertRequestOverrideAllowed(
			['signature_flow' => 'parallel'],
			$this->createResolvedPolicy('signature_flow', 'ordered_numeric', 'group', false, 'group'),
			'Signature flow override is blocked by %s.',
		);
	}

	#[DataProvider('allowedOverrideProvider')]
	public function testAssertRequestOverrideAllowedAcceptsEmptyOrAuthorizedOverrides(array $requestOverrides, bool $canUseAsRequestOverride): void {
		$applier = $this->createApplier();
		$applier->exposeAssertRequestOverrideAllowed(
			$requestOverrides,
			$this->createResolvedPolicy('signature_flow', 'parallel', 'group', $canUseAsRequestOverride, null),
			'Signature flow override is blocked by %s.',
		);

		self::assertTrue(true);
	}

	public static function allowedOverrideProvider(): array {
		return [
			'no override requested' => [[], false],
			'override allowed explicitly' => [['signature_flow' => 'parallel'], true],
		];
	}

	private function createResolvedPolicy(
		string $policyKey,
		mixed $effectiveValue,
		string $sourceScope,
		bool $canUseAsRequestOverride = true,
		?string $blockedBy = null,
	): ResolvedPolicy {
		return (new ResolvedPolicy())
			->setPolicyKey($policyKey)
			->setEffectiveValue($effectiveValue)
			->setSourceScope($sourceScope)
			->setCanUseAsRequestOverride($canUseAsRequestOverride)
			->setBlockedBy($blockedBy);
	}

	private function createApplier(): AbstractFilePolicyApplierTestDouble {
		return new AbstractFilePolicyApplierTestDouble($this->policyService, $this->fileService, $this->l10n);
	}
}
