<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Validation;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\DocMdp\Validator as DocMdpValidator;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethod\RuntimeRequirementValidator;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SequentialSigningService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IUser;

class SignerValidator {
	public function __construct(
		private IL10N $l10n,
		private SignRequestMapper $signRequestMapper,
		private FileMapper $fileMapper,
		private IdDocsMapper $idDocsMapper,
		private IdentifyMethodService $identifyMethodService,
		private SequentialSigningService $sequentialSigningService,
		private DocMdpValidator $docMdpValidator,
		private RuntimeRequirementValidator $runtimeRequirementValidator,
	) {
	}

	public function validateIdentifySigners(array $data): void {
		if (empty($data['signers'])) {
			return;
		}
		if (!is_array($data['signers'])) {
			throw new LibresignException($this->l10n->t('No signers'));
		}
		$this->docMdpValidator->validateSignersCount($data);
		$this->validateDocMdpPdfRestrictions($data);
		foreach ($data['signers'] as $signer) {
			if (!is_array($signer) || empty($signer)) {
				throw new LibresignException($this->l10n->t('No signers'));
			}
			if (isset($signer['displayName']) && strlen($signer['displayName']) > 64) {
				throw new LibresignException('Display name must not be longer than 64 characters');
			}
			foreach ($this->normalizeSignerIdentifyMethods($signer) as $method) {
				$this->validateIdentifyMethodForRequest($method['name'], $method['value']);
			}
		}
	}

	/** @return list<array{name: string, value: string}> */
	public function normalizeSignerIdentifyMethods(array $signer): array {
		if (empty($signer['identifyMethods']) || !is_array($signer['identifyMethods'])) {
			throw new LibresignException('No identify methods for signer');
		}
		$normalizedMethods = [];
		foreach ($signer['identifyMethods'] as $data) {
			if (!is_array($data) || !array_key_exists('method', $data) || !array_key_exists('value', $data)) {
				throw new LibresignException('Invalid identify method structure');
			}
			$normalizedMethods[] = ['name' => $data['method'], 'value' => $data['value']];
		}
		return $normalizedMethods;
	}

	/**
	 * @param list<array<string, mixed>> $signers
	 * @return list<array<string, mixed>>
	 */
	public function normalizeRequestSigners(array $signers): array {
		$normalizedSigners = [];
		foreach ($signers as $signer) {
			if (!is_array($signer)) {
				throw new LibresignException($this->l10n->t('No signers'));
			}
			$normalizedMethods = array_map(
				fn (array $method): array => ['method' => $method['name'], 'value' => $method['value']],
				$this->normalizeSignerIdentifyMethods($signer),
			);
			$normalizedSigners[] = [...$signer, 'identifyMethods' => $normalizedMethods];
		}
		return $normalizedSigners;
	}

	public function validateSigner(string $uuid, ?IUser $user = null): void {
		$this->validateSignerUuidExists($uuid);
		$this->validateSignerStatus($uuid);
		$this->validateIdentifyMethod($uuid);
	}

	public function validateSignerUuid(string $uuid): void {
		$this->validateSignerUuidExists($uuid);
	}

	public function validateRenewSigner(string $uuid, ?IUser $user = null): void {
		$this->validateSignerUuidExists($uuid);
		$signRequest = $this->signRequestMapper->getByUuid($uuid);
		$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
		foreach ($identifyMethods as $methods) {
			foreach ($methods as $identifyMethod) {
				$identifyMethod->validateToRenew($user);
			}
		}
	}

	public function validateUuidFormat(string $uuid): void {
		if (!$uuid || !preg_match('/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i', $uuid)) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [['message' => $this->l10n->t('Invalid UUID')]],
			]), Http::STATUS_NOT_FOUND);
		}
	}

	public function validateCredentials(SignRequest $signRequest, string $identifyMethodName, string $identifyValue, string $token): void {
		if ($identifyMethodName === IdentifyMethodService::IDENTIFY_PASSWORD && $token === '') {
			throw new LibresignException($this->l10n->t('libresign', 'Invalid password'));
		}
		$this->validateIfIdentifyMethodExists($identifyMethodName);
		if ($signRequest->getSigned()) {
			throw new LibresignException($this->l10n->t('File already signed.'));
		}
		$identifyMethod = $this->resolveIdentifyMethod($signRequest, $identifyMethodName, $identifyValue);
		$identifyMethod->setCodeSentByUser($token);
		$identifyMethod->validateToSign();
		$this->runtimeRequirementValidator->validate($signRequest);
	}

	public function validateIfIdentifyMethodExists(string $identifyMethod): void {
		if (!$this->identifyMethodService->exists($identifyMethod)) {
			throw new LibresignException($this->l10n->t('Invalid identification method'));
		}
	}

	private function validateSignerStatus(string $uuid): void {
		$signRequest = $this->signRequestMapper->getByUuid($uuid);
		$status = $signRequest->getStatusEnum();
		$file = $this->fileMapper->getById($signRequest->getFileId());
		$this->sequentialSigningService->setFile($file);

		if ($status === SignRequestStatus::DRAFT) {
			try {
				if ($this->idDocsMapper->getByFileId($signRequest->getFileId())) {
					return;
				}
			} catch (\Throwable) {
			}
			$this->throwSignerActionError($this->l10n->t('You are not allowed to sign this document yet'));
		}
		if ($status === SignRequestStatus::SIGNED) {
			$this->throwSignerActionError($this->l10n->t('Document already signed'));
		}
		if (
			$this->sequentialSigningService->isOrderedNumericFlow()
			&& $this->sequentialSigningService->hasPendingLowerOrderSigners($signRequest->getFileId(), $signRequest->getSigningOrder())
		) {
			$this->throwSignerActionError($this->l10n->t('You are not allowed to sign this document yet'));
		}
	}

	private function validateIdentifyMethod(string $uuid): void {
		$signRequest = $this->signRequestMapper->getByUuid($uuid);
		foreach ($this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId()) as $methods) {
			foreach ($methods as $identifyMethod) {
				$identifyMethod->validateToIdentify();
			}
		}
	}

	private function validateSignerUuidExists(string $uuid): void {
		$this->validateUuidFormat($uuid);
		try {
			$signRequest = $this->signRequestMapper->getByUuid($uuid);
			$this->fileMapper->getById($signRequest->getFileId());
		} catch (DoesNotExistException) {
			$this->throwSignerActionError($this->l10n->t('Invalid UUID'));
		}
	}

	private function validateIdentifyMethodForRequest(string $name, string $identifyValue): void {
		$identifyMethod = $this->identifyMethodService->getInstanceOfIdentifyMethod($name, $identifyValue);
		$identifyMethod->validateToRequest();
		if (empty($identifyMethod->getSignatureMethods())) {
			throw new LibresignException('No signature methods for identify method ' . $name);
		}
	}

	private function validateDocMdpPdfRestrictions(array $data): void {
		if (empty($data['uuid']) || empty($data['signers'])) {
			return;
		}
		try {
			$this->docMdpValidator->validatePdfRestrictions($this->fileMapper->getByUuid($data['uuid']));
		} catch (DoesNotExistException) {
		}
	}

	private function resolveIdentifyMethod(SignRequest $signRequest, string $methodName, ?string $identifyValue): IIdentifyMethod {
		if (!$signRequest->getId()) {
			return $this->identifyMethodService->setCurrentIdentifyMethod()->getInstanceOfIdentifyMethod($methodName, $identifyValue);
		}
		$methodsList = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
		$identifyMethod = $this->searchMethodByNameAndValue($methodsList, $methodName, $identifyValue);
		if ($identifyMethod) {
			return $identifyMethod;
		}
		$signMethods = $this->identifyMethodService->getSignMethodsOfIdentifiedFactors($signRequest->getId());
		$identifyMethod = $this->searchMethodByNameAndValue($signMethods, $methodName, $identifyValue);
		if ($identifyMethod) {
			return $identifyMethod;
		}
		if (!empty($methodsList)) {
			return $this->getFirstAvailableMethod($methodsList);
		}
		if (!empty($signMethods)) {
			return $this->getFirstAvailableMethod($signMethods);
		}
		throw new LibresignException($this->l10n->t('Invalid identification method'));
	}

	private function searchMethodByNameAndValue(array $methods, string $methodName, ?string $identifyValue): ?IIdentifyMethod {
		if (!isset($methods[$methodName])) {
			return null;
		}
		if ($identifyValue) {
			foreach ($methods[$methodName] as $identifyMethod) {
				if (!$identifyMethod instanceof IIdentifyMethod) {
					$identifyMethod = $this->getIdentifyMethodByNameAndValue($methodName, $identifyValue);
				}
				if ($identifyMethod->getEntity()->getIdentifierValue() === $identifyValue) {
					return $identifyMethod;
				}
			}
			return null;
		}
		$identifyMethod = current($methods[$methodName]);
		if (!$identifyMethod instanceof IIdentifyMethod) {
			$identifyMethod = $this->getIdentifyMethodByNameAndValue($methodName, $identifyValue);
		}
		return $identifyMethod;
	}

	private function getIdentifyMethodByNameAndValue(string $identifyMethodName, ?string $identifyValue): IIdentifyMethod {
		return $this->identifyMethodService->setCurrentIdentifyMethod()->getInstanceOfIdentifyMethod($identifyMethodName, $identifyValue);
	}

	private function getFirstAvailableMethod(array $methods): IIdentifyMethod {
		foreach ($methods as $methodGroup) {
			if (!empty($methodGroup)) {
				return current($methodGroup);
			}
		}
		throw new LibresignException($this->l10n->t('Invalid identification method'));
	}

	private function throwSignerActionError(string $message): never {
		throw new LibresignException(json_encode([
			'action' => JSActions::ACTION_DO_NOTHING,
			'errors' => [['message' => $message]],
		]));
	}
}
