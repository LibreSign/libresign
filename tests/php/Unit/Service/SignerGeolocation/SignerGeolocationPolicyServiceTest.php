<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignerGeolocation;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\SignerGeolocationMode;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignerGeolocation\SignerGeolocationPolicy;
use OCA\Libresign\Service\SignerGeolocation\SignerGeolocationPolicyService;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignerGeolocationPolicyServiceTest extends TestCase {
	private PolicyService&MockObject $policyService;
	private FileMapper&MockObject $fileMapper;
	private SignRequestMapper&MockObject $signRequestMapper;
	private IL10N&MockObject $l10n;

	protected function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
	}

	private function getService(): SignerGeolocationPolicyService {
		return new SignerGeolocationPolicyService(
			$this->policyService,
			$this->fileMapper,
			$this->signRequestMapper,
			$this->l10n,
		);
	}

	#[DataProvider('provideEffectiveRequirementMatrix')]
	public function testResolveEffectiveRequirement(
		string $policyMode,
		bool $requesterRequiresGeolocation,
		SignerGeolocationMode $expected,
	): void {
		$file = $this->createFileWithSnapshot(['mode' => $policyMode]);

		$this->assertSame(
			$expected,
			$this->getService()->resolveEffectiveRequirement($file, $requesterRequiresGeolocation),
		);
	}

	/** @return iterable<string, array{0: string, 1: bool, 2: SignerGeolocationMode}> */
	public static function provideEffectiveRequirementMatrix(): iterable {
		yield 'disabled policy ignores requester choice' => ['disabled', false, SignerGeolocationMode::DISABLED];
		yield 'disabled policy rejects requester requirement' => ['disabled', true, SignerGeolocationMode::DISABLED];
		yield 'optional policy without requester requirement' => ['optional', false, SignerGeolocationMode::DISABLED];
		yield 'optional policy with requester requirement' => ['optional', true, SignerGeolocationMode::REQUIRED];
		yield 'required policy without requester requirement' => ['required', false, SignerGeolocationMode::REQUIRED];
		yield 'required policy with requester requirement' => ['required', true, SignerGeolocationMode::REQUIRED];
	}

	public function testValidateRequesterConfigurationRejectsWhenDisabled(): void {
		$file = $this->createFileWithSnapshot(['mode' => 'disabled']);

		$this->expectException(LibresignException::class);
		$this->getService()->validateRequesterConfiguration($file, true);
	}

	public function testValidateRequesterConfigurationAllowsOptionalMode(): void {
		$file = $this->createFileWithSnapshot(['mode' => 'optional']);

		$this->getService()->validateRequesterConfiguration($file, true);
		$this->addToAssertionCount(1);
	}

	public function testValidateRequesterConfigurationAllowsRequiredMode(): void {
		$file = $this->createFileWithSnapshot(['mode' => 'required']);

		$this->getService()->validateRequesterConfiguration($file, true);
		$this->addToAssertionCount(1);
	}

	public function testGetPolicyValueFallsBackToLivePolicy(): void {
		$file = new File();
		$this->policyService
			->expects($this->once())
			->method('resolve')
			->with(SignerGeolocationPolicy::KEY)
			->willReturn((new ResolvedPolicy())
				->setPolicyKey(SignerGeolocationPolicy::KEY)
				->setEffectiveValue(['mode' => 'required'])
				->setSourceScope('system'));

		$this->assertSame([
			'mode' => 'required',
		], $this->getService()->getPolicyValue($file));
	}

	public function testGetFrozenRequirementIgnoresLivePolicyChanges(): void {
		$signRequest = new SignRequest();
		$signRequest->setMetadata([
			SignerGeolocationPolicyService::METADATA_REQUIREMENT_KEY => SignerGeolocationMode::REQUIRED->value,
		]);

		$this->policyService
			->method('resolve')
			->willReturn((new ResolvedPolicy())
				->setPolicyKey(SignerGeolocationPolicy::KEY)
				->setEffectiveValue(['mode' => 'disabled'])
				->setSourceScope('system'));

		$this->assertSame(
			SignerGeolocationMode::REQUIRED,
			$this->getService()->getFrozenRequirement($signRequest),
		);
	}

	public function testGetFrozenRequirementNormalizesLegacyOptionalToDisabled(): void {
		$signRequest = new SignRequest();
		$signRequest->setMetadata([
			SignerGeolocationPolicyService::METADATA_REQUIREMENT_KEY => SignerGeolocationMode::OPTIONAL->value,
		]);

		$this->assertSame(
			SignerGeolocationMode::DISABLED,
			$this->getService()->getFrozenRequirement($signRequest),
		);
	}

	public function testPersistEffectiveRequirementStoresPerSignerRequirement(): void {
		$file = $this->createFileWithSnapshot(['mode' => 'optional']);
		$service = $this->getService();

		$requiredSigner = new SignRequest();
		$requiredSigner->setId(10);
		$optionalSigner = new SignRequest();
		$optionalSigner->setId(11);

		$this->signRequestMapper
			->method('getById')
			->willReturnCallback(static function (int $id): SignRequest {
				$signRequest = new SignRequest();
				$signRequest->setId($id);
				return $signRequest;
			});

		$this->signRequestMapper
			->expects($this->exactly(2))
			->method('update')
			->with($this->callback(static function (SignRequest $updated): bool {
				$metadata = $updated->getMetadata() ?? [];
				$requirement = $metadata[SignerGeolocationPolicyService::METADATA_REQUIREMENT_KEY] ?? null;

				return in_array($requirement, [
					SignerGeolocationMode::REQUIRED->value,
					SignerGeolocationMode::DISABLED->value,
				], true);
			}));

		$service->persistEffectiveRequirement($requiredSigner, $file, true);
		$service->persistEffectiveRequirement($optionalSigner, $file, false);
		$this->addToAssertionCount(1);
	}

	public function testPersistEffectiveRequirementPreservesExistingMetadata(): void {
		$file = $this->createFileWithSnapshot(['mode' => 'optional']);
		$signRequest = new SignRequest();
		$signRequest->setId(42);

		$storedSignRequest = new SignRequest();
		$storedSignRequest->setId(42);
		$storedSignRequest->setMetadata([
			'notify' => [
				[
					'method' => 'mail',
					'date' => 1_700_000_000,
				],
			],
		]);

		$this->signRequestMapper
			->expects($this->once())
			->method('getById')
			->with(42)
			->willReturn($storedSignRequest);

		$this->signRequestMapper
			->expects($this->once())
			->method('update')
			->with($this->callback(function (SignRequest $updated): bool {
				$metadata = $updated->getMetadata() ?? [];

				return isset($metadata['notify'])
					&& $metadata[SignerGeolocationPolicyService::METADATA_REQUIREMENT_KEY] === SignerGeolocationMode::DISABLED->value;
			}));

		$this->getService()->persistEffectiveRequirement($signRequest, $file, false);
	}

	/** @param array{mode: string} $policyValue */
	private function createFileWithSnapshot(array $policyValue): File {
		$file = new File();
		$file->setMetadata([
			'policy_snapshot' => [
				SignerGeolocationPolicy::KEY => [
					'effectiveValue' => $policyValue,
					'sourceScope' => 'system',
				],
			],
		]);

		return $file;
	}
}
