<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignatureRejection\FilePolicy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\AbstractFilePolicyApplier;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyConfig;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValidator;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionPolicyService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IUser;

/**
 * Owns the lifecycle of the rejection rules of a document.
 *
 * The five rejection settings are resolved for whoever requests the signature,
 * with the choices sent along the request applied on top of what the layers
 * above allow. What comes out is frozen on the document, one snapshot entry per
 * setting, and is the effective configuration for the whole signing flow.
 *
 * While the request is still a draft the requester may change their choices,
 * and the draft is revalidated against the current administrative policy on
 * every update: a choice the administrator no longer allows is dropped in
 * favor of what is now inherited, so a workflow never starts under a rule that
 * has been revoked. Once the flow starts the stored configuration is frozen: it
 * can no longer change, but a client resending the values the request already
 * has stays an idempotent update rather than an error.
 */
class SignatureRejectionFilePolicyApplier extends AbstractFilePolicyApplier {
	private readonly ?SignatureRejectionPolicyService $storedValueReader;
	private readonly SignatureRejectionPolicyValidator $rejectionPolicyValidator;

	public function __construct(
		PolicyService $policyService,
		FileService $fileService,
		?IL10N $l10n = null,
		?FileMapper $fileMapper = null,
	) {
		parent::__construct($policyService, $fileService, $l10n);
		$this->storedValueReader = $fileMapper === null
			? null
			: new SignatureRejectionPolicyService($fileMapper);
		$this->rejectionPolicyValidator = new SignatureRejectionPolicyValidator($l10n);
	}

	#[\Override]
	public function apply(FileEntity $file, array $data): void {
		$user = ($data['userManager'] ?? null) instanceof IUser ? $data['userManager'] : null;
		$submittedValues = $this->readSubmittedValues($data);

		$this->storeConfiguration(
			$file,
			$data,
			fn (string $policyKey, array $overrides, ?array $activeContext): ResolvedPolicy
				=> $activeContext === null
					? $this->policyService->resolveForUser($policyKey, $user, $overrides)
					: $this->policyService->resolveForUser($policyKey, $user, $overrides, $activeContext),
			$submittedValues,
			$submittedValues,
		);
	}

	#[\Override]
	public function sync(FileEntity $file, array $data): void {
		$submittedValues = $this->readSubmittedValues($data);

		if ($this->hasSigningFlowStarted($file)) {
			$this->assertFrozenConfigurationIsKept($file, $submittedValues);

			// The configuration is frozen: an identical resend or a value-less
			// update changes nothing, and only a request that never recorded a
			// configuration still gets the disabled default written for it.
			if ($file->isEnvelope() || $this->readStoredValues($file) !== []) {
				return;
			}
		} elseif ($file->isEnvelope() && $submittedValues === []) {
			// An envelope is created before the file policy appliers run, so the
			// configuration the request was created with lives on the documents it
			// contains, and updating an envelope never re-synchronizes them.
			// Writing a freshly resolved configuration here would therefore shadow
			// the stored choice, so an envelope is only ever written when the
			// requester explicitly sends new values.
			return;
		}

		$metadataBeforeUpdate = $file->getMetadata() ?? [];
		$this->storeConfiguration(
			$file,
			$data,
			$this->resolverForUserId($file),
			// A draft keeps the choices the requester already made, but they are
			// resolved again: one the administrator no longer allows falls back to
			// what the document now inherits.
			$submittedValues + $this->readRequesterChoices($file),
			$submittedValues,
		);

		if (($file->getMetadata() ?? []) !== $metadataBeforeUpdate) {
			$this->fileService->update($file);
		}
	}

	#[\Override]
	public function supportsCoreFlowSync(): bool {
		return true;
	}

	/**
	 * Resolve the five settings with the given choices applied, refuse anything
	 * the layers above do not allow, and freeze the result on the document.
	 *
	 * @param callable(string, array<string, mixed>, ?array<string, mixed>): ResolvedPolicy $resolve
	 * @param array<string, mixed> $overrides Choices to resolve with
	 * @param array<string, mixed> $submittedValues Choices sent with this very request
	 */
	private function storeConfiguration(
		FileEntity $file,
		array $data,
		callable $resolve,
		array $overrides,
		array $submittedValues,
	): void {
		$activeContext = $this->extractActiveContext($data);

		/** @var array<string, ResolvedPolicy> $resolvedPolicies */
		$resolvedPolicies = [];
		$resolvedValues = [];
		foreach (SignatureRejectionPolicy::ALL_KEYS as $policyKey) {
			$resolvedPolicy = $resolve($policyKey, $overrides, $activeContext);
			$resolvedPolicies[$policyKey] = $resolvedPolicy;
			$resolvedValues[$policyKey] = $resolvedPolicy->getEffectiveValue();
		}

		$this->assertSubmittedValuesWereApplied($submittedValues, $resolvedPolicies);
		$configuration = $this->validateCombination($resolvedValues, array_keys($submittedValues));
		$effectiveValues = $configuration->toKeyedValues();

		foreach ($resolvedPolicies as $policyKey => $resolvedPolicy) {
			$this->storePolicySnapshot($file, $resolvedPolicy, $effectiveValues[$policyKey]);
		}
	}

	/**
	 * A setting the requester asked for is only accepted when it survives the
	 * resolution: anything the administrative layers keep for themselves comes
	 * back as the inherited value instead, and asking for it is an error rather
	 * than a silently different document.
	 *
	 * @param array<string, mixed> $submittedValues
	 * @param array<string, ResolvedPolicy> $resolvedPolicies
	 */
	private function assertSubmittedValuesWereApplied(array $submittedValues, array $resolvedPolicies): void {
		foreach ($submittedValues as $policyKey => $submittedValue) {
			$resolvedPolicy = $resolvedPolicies[$policyKey] ?? null;
			if (!$resolvedPolicy instanceof ResolvedPolicy) {
				continue;
			}

			$requested = SignatureRejectionPolicyConfig::normalizeKeyedValue($policyKey, $submittedValue);
			if ($requested === SignatureRejectionPolicyConfig::normalizeKeyedValue($policyKey, $resolvedPolicy->getEffectiveValue())) {
				continue;
			}

			if ($policyKey === SignatureRejectionPolicy::KEY_ENABLED && $requested === true) {
				throw new LibresignException(
					// TRANSLATORS Error shown when a signature request tries to offer signature rejection while a higher-level LibreSign policy keeps it disabled.
					$this->translate('Signature rejection is disabled by policy and cannot be enabled for this document.'),
					Http::STATUS_UNPROCESSABLE_ENTITY,
				);
			}

			throw new LibresignException(
				$this->translateWithParameters(
					// TRANSLATORS Error shown when a signature request asks for a rejection setting that a higher-level LibreSign policy does not allow. The first placeholder receives the setting name, the second the scope that blocked it.
					'The rejection setting %1$s cannot be used on this document: it is defined by %2$s.',
					[$policyKey, $resolvedPolicy->getBlockedBy() ?? $resolvedPolicy->getSourceScope()],
				),
				Http::STATUS_UNPROCESSABLE_ENTITY,
			);
		}
	}

	/**
	 * @param array<string, mixed> $resolvedValues
	 * @param list<string> $submittedKeys
	 */
	private function validateCombination(array $resolvedValues, array $submittedKeys): SignatureRejectionPolicyConfig {
		try {
			return $this->rejectionPolicyValidator->validateRequest($resolvedValues, $submittedKeys);
		} catch (\InvalidArgumentException $exception) {
			throw new LibresignException($exception->getMessage(), Http::STATUS_UNPROCESSABLE_ENTITY);
		}
	}

	/**
	 * Updating an existing request resolves from the stored owner, exactly like
	 * every other file policy applier.
	 *
	 * @return callable(string, array<string, mixed>, ?array<string, mixed>): ResolvedPolicy
	 */
	private function resolverForUserId(FileEntity $file): callable {
		return fn (string $policyKey, array $overrides, ?array $activeContext): ResolvedPolicy
			=> $activeContext === null
				? $this->policyService->resolveForUserId($policyKey, $file->getUserId(), $overrides)
				: $this->policyService->resolveForUserId($policyKey, $file->getUserId(), $overrides, $activeContext);
	}

	/**
	 * The rejection settings sent with this request, in the order the policy
	 * defines them.
	 *
	 * @return array<string, mixed>
	 */
	private function readSubmittedValues(array $data): array {
		if (!isset($data['policyOverrides']) || !is_array($data['policyOverrides'])) {
			return [];
		}

		$submittedValues = [];
		foreach (SignatureRejectionPolicy::ALL_KEYS as $policyKey) {
			if (array_key_exists($policyKey, $data['policyOverrides'])) {
				$submittedValues[$policyKey] = $data['policyOverrides'][$policyKey];
			}
		}

		return $submittedValues;
	}

	/**
	 * The settings the requester chose themselves, as opposed to the ones the
	 * document inherited.
	 *
	 * @return array<string, mixed>
	 */
	private function readRequesterChoices(FileEntity $file): array {
		$requesterChoices = [];
		foreach ($this->readSnapshotEntries($file) as $policyKey => $entry) {
			if (($entry['sourceScope'] ?? null) === 'request') {
				$requesterChoices[$policyKey] = $entry['effectiveValue'];
			}
		}

		return $requesterChoices;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function readStoredValues(FileEntity $file): array {
		$storedValues = [];
		foreach ($this->readSnapshotEntries($file) as $policyKey => $entry) {
			$storedValues[$policyKey] = $entry['effectiveValue'];
		}

		return $storedValues;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function readSnapshotEntries(FileEntity $file): array {
		$metadata = $file->getMetadata() ?? [];
		$policySnapshot = $metadata['policy_snapshot'] ?? null;
		if (!is_array($policySnapshot)) {
			return [];
		}

		$entries = [];
		foreach (SignatureRejectionPolicy::ALL_KEYS as $policyKey) {
			$entry = $policySnapshot[$policyKey] ?? null;
			if (is_array($entry) && array_key_exists('effectiveValue', $entry)) {
				$entries[$policyKey] = $entry;
			}
		}

		return $entries;
	}

	private function hasSigningFlowStarted(FileEntity $file): bool {
		return $file->getStatus() >= FileStatus::ABLE_TO_SIGN->value;
	}

	/**
	 * Once the flow starts the configuration is frozen: it cannot change, but a
	 * client resending the complete form state with the values the request
	 * already has stays an idempotent update. The comparison uses the effective
	 * stored configuration, which for an envelope lives on the documents it
	 * contains.
	 *
	 * @param array<string, mixed> $submittedValues
	 */
	private function assertFrozenConfigurationIsKept(FileEntity $file, array $submittedValues): void {
		if ($submittedValues === []) {
			return;
		}

		$frozenValues = $this->frozenConfiguration($file)->toKeyedValues();
		foreach ($submittedValues as $policyKey => $submittedValue) {
			if (SignatureRejectionPolicyConfig::normalizeKeyedValue($policyKey, $submittedValue) === $frozenValues[$policyKey]) {
				continue;
			}

			throw new LibresignException(
				// TRANSLATORS Error shown when someone tries to change how signers may reject a document after the signing flow already started.
				$this->translate('The signature rejection settings cannot be changed after the signing flow has started.'),
				Http::STATUS_UNPROCESSABLE_ENTITY,
			);
		}
	}

	/**
	 * The effective stored configuration of the request, read the same way the
	 * signing flow reads it, so an envelope answers with the configuration
	 * stored on the documents it contains.
	 */
	private function frozenConfiguration(FileEntity $file): SignatureRejectionPolicyConfig {
		if ($this->storedValueReader !== null) {
			return $this->storedValueReader->getConfig($file);
		}

		return SignatureRejectionPolicyConfig::fromKeyedValues($this->readStoredValues($file));
	}

	private function translate(string $message): string {
		return $this->l10n?->t($message) ?? $message;
	}

	/** @param list<mixed> $parameters */
	private function translateWithParameters(string $message, array $parameters): string {
		return $this->l10n?->t($message, $parameters) ?? vsprintf($message, $parameters);
	}
}
