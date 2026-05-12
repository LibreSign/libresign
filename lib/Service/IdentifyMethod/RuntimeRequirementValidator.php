<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\IdentifyMethodRequirement;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicy;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicyValue;
use OCP\IL10N;

class RuntimeRequirementValidator {
	public function __construct(
		private IdentifyMethodService $identifyMethodService,
		private FileMapper $fileMapper,
		private IL10N $l10n,
	) {
	}

	public function validate(SignRequest $signRequest): void {
		$methodsByName = $this->getMethodsByName($signRequest);
		if (empty($methodsByName)) {
			return;
		}

		$summary = $this->summarizeVerificationState($methodsByName);
		$this->validateRequiredFactorsCompleted($summary);

		$minimumTotalVerifiedFactors = $this->resolveMinimumTotalVerifiedFactors(
			$signRequest,
			$summary['methodNames'],
			$summary['hasOptionalFactor'],
		);
		if ($minimumTotalVerifiedFactors === null) {
			return;
		}

		$this->validateMinimumFactorsCompleted($summary, $minimumTotalVerifiedFactors);
	}

	/**
	 * @return array<string, array<IIdentifyMethod>>
	 */
	private function getMethodsByName(SignRequest $signRequest): array {
		if (!$signRequest->getId()) {
			return [];
		}

		return $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
	}

	/**
	 * @param array<string, list<IIdentifyMethod>> $methodsByName
	 * @return array{
	 * 	requiredFactors: int,
	 * 	identifiedRequiredFactors: int,
	 * 	identifiedFactors: int,
	 * 	hasOptionalFactor: bool,
	 * 	methodNames: list<string>
	 * }
	 */
	private function summarizeVerificationState(array $methodsByName): array {

		$requiredFactors = 0;
		$identifiedRequiredFactors = 0;
		$identifiedFactors = 0;
		$hasOptionalFactor = false;
		$methodNames = [];

		foreach ($methodsByName as $methods) {
			foreach ($methods as $identifyMethod) {
				$entity = $identifyMethod->getEntity();
				$methodName = $entity->getIdentifierKey();
				$methodNames[$methodName] = true;

				$isRequired = $entity->getRequirement() === IdentifyMethodRequirement::REQUIRED->value;
				$isIdentified = $entity->getIdentifiedAtDate() !== null;

				if (!$isRequired) {
					$hasOptionalFactor = true;
				}

				if ($isRequired) {
					$requiredFactors++;
					if ($isIdentified) {
						$identifiedRequiredFactors++;
					}
				}

				if ($isIdentified) {
					$identifiedFactors++;
				}
			}
		}

		return [
			'requiredFactors' => $requiredFactors,
			'identifiedRequiredFactors' => $identifiedRequiredFactors,
			'identifiedFactors' => $identifiedFactors,
			'hasOptionalFactor' => $hasOptionalFactor,
			'methodNames' => array_keys($methodNames),
		];
	}

	/**
	 * Pure logic: validates that all required factors are identified.
	 * @param array{requiredFactors:int, identifiedRequiredFactors:int, identifiedFactors:int, hasOptionalFactor:bool, methodNames:list<string>} $summary
	 * @throws LibresignException
	 */
	public function validateRequiredFactorsCompleted(array $summary): void {
		if ($summary['identifiedRequiredFactors'] < $summary['requiredFactors']) {
			throw new LibresignException($this->l10n->t('You need to complete all required identification factors before signing.'));
		}
	}

	/**
	 * Pure logic: validates that total identified factors meet minimum requirement.
	 * @param array{requiredFactors:int, identifiedRequiredFactors:int, identifiedFactors:int, hasOptionalFactor:bool, methodNames:list<string>} $summary
	 * @param int $minimumTotalVerifiedFactors
	 * @throws LibresignException
	 */
	public function validateMinimumFactorsCompleted(array $summary, int $minimumTotalVerifiedFactors): void {
		$requiredVerifiedFactors = max($summary['requiredFactors'], $minimumTotalVerifiedFactors);
		if ($summary['identifiedFactors'] < $requiredVerifiedFactors) {
			throw new LibresignException(
				$this->l10n->t('You need to complete at least %s identification factors before signing.', [$requiredVerifiedFactors])
			);
		}
	}

	/**
	 * Pure logic: resolves maximum minimum requirement from settings array.
	 * @param list<mixed> $settings
	 * @param array<string, true> $methodSet
	 * @return int|null
	 */
	public function resolveMinimumFromSettingsList(array $settings, array $methodSet): ?int {
		return $this->resolveMinimumFromSettings($settings, $methodSet);
	}

	/**
	 * @param list<string> $methodNames
	 */
	private function resolveMinimumTotalVerifiedFactors(SignRequest $signRequest, array $methodNames, bool $hasOptionalFactor): ?int {
		// Runtime minimum enforcement is enabled only when optional factors exist.
		if (!$hasOptionalFactor) {
			return null;
		}

		$methodSet = array_fill_keys($methodNames, true);

		$minimumFromSnapshot = $this->resolveMinimumTotalVerifiedFactorsFromPolicySnapshot($signRequest, $methodSet);
		if ($minimumFromSnapshot !== null) {
			return $minimumFromSnapshot;
		}

		return $this->resolveMinimumFromSettings(
			$this->identifyMethodService->getIdentifyMethodsSettings(),
			$methodSet,
		);
	}

	/**
	 * @param list<mixed> $settings
	 * @param array<string, true> $methodSet
	 */
	private function resolveMinimumFromSettings(array $settings, array $methodSet): ?int {
		$minimum = null;
		foreach ($settings as $setting) {
			$candidate = $this->resolveMinimumCandidate($setting, $methodSet);
			if ($candidate === null) {
				continue;
			}

			$minimum = $minimum === null ? $candidate : max($minimum, $candidate);
		}

		return $minimum;
	}

	/**
	 * @param mixed $setting
	 * @param array<string, true> $methodSet
	 */
	private function resolveMinimumCandidate(mixed $setting, array $methodSet): ?int {
		if (!is_array($setting) || empty($setting['name']) || !isset($methodSet[$setting['name']])) {
			return null;
		}
		if (!array_key_exists('minimumTotalVerifiedFactors', $setting) || !is_numeric($setting['minimumTotalVerifiedFactors'])) {
			return null;
		}

		$candidate = (int)$setting['minimumTotalVerifiedFactors'];
		return $candidate < 1 ? null : $candidate;
	}

	/**
	 * @param array<string, true> $methodSet
	 */
	private function resolveMinimumTotalVerifiedFactorsFromPolicySnapshot(SignRequest $signRequest, array $methodSet): ?int {
		try {
			$file = $this->fileMapper->getById($signRequest->getFileId());
		} catch (\Throwable) {
			return null;
		}

		$metadata = $file->getMetadata() ?? [];
		if (!isset($metadata['policy_snapshot']) || !is_array($metadata['policy_snapshot'])) {
			return null;
		}

		$entry = $metadata['policy_snapshot'][IdentifyMethodsPolicy::KEY] ?? null;
		if (!is_array($entry) || !array_key_exists('effectiveValue', $entry)) {
			return null;
		}

		$normalized = IdentifyMethodsPolicyValue::normalize($entry['effectiveValue']);
		$factors = IdentifyMethodsPolicyValue::extractFactors($normalized);
		if (empty($factors)) {
			return null;
		}

		return $this->resolveMinimumFromSettings($factors, $methodSet);
	}
}
