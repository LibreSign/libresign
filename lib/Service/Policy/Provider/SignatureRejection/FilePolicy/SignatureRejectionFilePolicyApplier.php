<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignatureRejection\FilePolicy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Policy\AbstractFilePolicyApplier;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValue;
use OCP\AppFramework\Http;
use OCP\IUser;

/**
 * Owns the lifecycle of the rejection rules of a document.
 *
 * Signature rejection is opt-in per signature request. An enabled policy only
 * allows the requester to offer rejection on a document, it never turns it on by
 * itself, so a request that says nothing about rejection keeps it disabled.
 *
 * The choice is stored with the request and is the effective value for the whole
 * signing flow. While the request is still a draft the requester may change it,
 * but only by sending a new value: an unrelated update never re-evaluates it.
 * Once the flow starts the stored value is frozen, so neither a later policy
 * change nor a later request can alter the rules the signers were shown.
 */
class SignatureRejectionFilePolicyApplier extends AbstractFilePolicyApplier {

	#[\Override]
	public function apply(FileEntity $file, array $data): void {
		$user = ($data['userManager'] ?? null) instanceof IUser ? $data['userManager'] : null;
		$this->storeRequestedValue($file, $data, fn (array $requestOverrides, ?array $activeContext): ResolvedPolicy
			=> $activeContext === null
				? $this->policyService->resolveForUser(SignatureRejectionPolicy::KEY, $user, $requestOverrides)
				: $this->policyService->resolveForUser(SignatureRejectionPolicy::KEY, $user, $requestOverrides, $activeContext));
	}

	#[\Override]
	public function sync(FileEntity $file, array $data): void {
		$requestedChoice = $this->readRequestedChoice($data);

		if ($this->hasSigningFlowStarted($file)) {
			$this->assertValueIsNotChangedAfterTheFlowStarted($requestedChoice);
		}

		if ($file->isEnvelope()) {
			$this->syncEnvelope($file, $requestedChoice, $data);
			return;
		}

		// The update says nothing about rejection: keep what the request stores.
		if ($requestedChoice === null && $this->readStoredValue($file) !== null) {
			return;
		}

		$metadataBeforeUpdate = $file->getMetadata() ?? [];
		$this->storeRequestedValue($file, $data, $this->resolverForUserId($file));

		if (($file->getMetadata() ?? []) !== $metadataBeforeUpdate) {
			$this->fileService->update($file);
		}
	}

	#[\Override]
	public function supportsCoreFlowSync(): bool {
		return true;
	}

	/**
	 * An envelope is created before the file policy appliers run, so the value the
	 * request was created with lives on the documents it contains, and updating an
	 * envelope never re-synchronizes them. Writing a freshly resolved value on the
	 * envelope here would therefore shadow the stored choice, so an envelope is only
	 * ever written when the requester explicitly sends a new value.
	 */
	private function syncEnvelope(FileEntity $envelope, ?bool $requestedChoice, array $data): void {
		if ($requestedChoice === null) {
			return;
		}

		$metadataBeforeUpdate = $envelope->getMetadata() ?? [];
		$this->storeRequestedValue($envelope, $data, $this->resolverForUserId($envelope));

		if (($envelope->getMetadata() ?? []) !== $metadataBeforeUpdate) {
			$this->fileService->update($envelope);
		}
	}

	/**
	 * Resolve the administrative value, then store what the requester asked for on
	 * top of it. Anything other than an explicit opt-in stores rejection disabled.
	 *
	 * @param callable(array<string, mixed>, ?array<string, mixed>): ResolvedPolicy $resolve
	 */
	private function storeRequestedValue(FileEntity $file, array $data, callable $resolve): void {
		$activeContext = $this->extractActiveContext($data);
		$administrativePolicy = $resolve([], $activeContext);

		if ($this->readRequestedChoice($data) !== true) {
			$this->storePolicySnapshot($file, $administrativePolicy, SignatureRejectionPolicyValue::defaults());
			return;
		}

		$administrativeValue = SignatureRejectionPolicyValue::normalize($administrativePolicy->getEffectiveValue());
		$proposedValue = SignatureRejectionPolicyValue::withEnabled($administrativeValue, true);

		// Offering rejection on your own request is not an override of the
		// administrative value, it is the choice the policy exists to grant, so it
		// does not go through the request-override delegation gate. The only thing
		// that has to be refused is asking for more than the policy allows.
		if (!SignatureRejectionPolicyValue::isRequestOverrideAllowed($proposedValue, $administrativeValue)) {
			throw new LibresignException(
				// TRANSLATORS Error shown when a signature request tries to offer signature rejection while a higher-level LibreSign policy keeps it disabled.
				$this->translate('Signature rejection is disabled by policy and cannot be enabled for this document.'),
				Http::STATUS_UNPROCESSABLE_ENTITY,
			);
		}

		$this->storePolicySnapshot($file, $administrativePolicy, $proposedValue);
	}

	/**
	 * Updating an existing request resolves from the stored owner, exactly like
	 * every other file policy applier.
	 *
	 * @return callable(array<string, mixed>, ?array<string, mixed>): ResolvedPolicy
	 */
	private function resolverForUserId(FileEntity $file): callable {
		return fn (array $requestOverrides, ?array $activeContext): ResolvedPolicy
			=> $activeContext === null
				? $this->policyService->resolveForUserId(SignatureRejectionPolicy::KEY, $file->getUserId(), $requestOverrides)
				: $this->policyService->resolveForUserId(SignatureRejectionPolicy::KEY, $file->getUserId(), $requestOverrides, $activeContext);
	}

	private function readRequestedChoice(array $data): ?bool {
		if (!isset($data['policyOverrides']) || !is_array($data['policyOverrides'])) {
			return null;
		}

		if (!array_key_exists(SignatureRejectionPolicy::KEY, $data['policyOverrides'])) {
			return null;
		}

		return SignatureRejectionPolicyValue::readRequestedChoice(
			$data['policyOverrides'][SignatureRejectionPolicy::KEY],
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function readStoredValue(FileEntity $file): ?array {
		$metadata = $file->getMetadata() ?? [];
		$policySnapshot = $metadata['policy_snapshot'] ?? null;
		if (!is_array($policySnapshot)) {
			return null;
		}

		$entry = $policySnapshot[SignatureRejectionPolicy::KEY] ?? null;
		if (!is_array($entry) || !array_key_exists('effectiveValue', $entry)) {
			return null;
		}

		return SignatureRejectionPolicyValue::normalize($entry['effectiveValue']);
	}

	private function hasSigningFlowStarted(FileEntity $file): bool {
		return $file->getStatus() >= FileStatus::ABLE_TO_SIGN->value;
	}

	/**
	 * Once the flow starts the stored value is frozen, so the setting may no longer
	 * be sent at all. Refusing it outright, instead of only refusing a different
	 * value, keeps a single document and an envelope behaving the same way: the
	 * value of an envelope lives on the documents it contains and is not reachable
	 * from here, so a "same value" comparison is not possible for one of them.
	 */
	private function assertValueIsNotChangedAfterTheFlowStarted(?bool $requestedChoice): void {
		if ($requestedChoice === null) {
			return;
		}

		throw new LibresignException(
			// TRANSLATORS Error shown when someone tries to change whether signers may reject a document after the signing flow already started.
			$this->translate('The signature rejection setting cannot be changed after the signing flow has started.'),
			Http::STATUS_UNPROCESSABLE_ENTITY,
		);
	}

	private function translate(string $message): string {
		return $this->l10n?->t($message) ?? $message;
	}
}
