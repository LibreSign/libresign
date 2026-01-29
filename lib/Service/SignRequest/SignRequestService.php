<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignRequest;

use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use OCP\IUserManager;
use Sabre\DAV\UUIDUtil;

class SignRequestService {
	public function __construct(
		protected IL10N $l10n,
		protected SignRequestMapper $signRequestMapper,
		protected IUserManager $userManager,
		protected IdentifyMethodService $identifyMethodService,
		protected StatusService $signRequestStatusService,
	) {
	}

	/**
	 * Create or update a sign request for a signer
	 *
	 * @param array $identifyMethods Identify methods array
	 * @param string $displayName Signer display name
	 * @param string $description Signer description
	 * @param bool $notify Whether to notify the signer
	 * @param int $fileId File ID
	 * @param int $signingOrder Signing order
	 * @param int|null $fileStatus File status
	 * @param int|null $signerStatus Signer status
	 * @return SignRequestEntity
	 */
	public function createOrUpdateSignRequest(
		array $identifyMethods,
		string $displayName,
		string $description,
		bool $notify,
		int $fileId,
		int $signingOrder = 0,
		?int $fileStatus = null,
		?int $signerStatus = null,
	): SignRequestEntity {
		$identifyMethodsInstances = $this->identifyMethodService->getByUserData($identifyMethods);
		if (empty($identifyMethodsInstances)) {
			throw new \Exception($this->l10n->t('Invalid identification method'));
		}

		$signRequest = $this->getSignRequestByIdentifyMethod(
			current($identifyMethodsInstances),
			$fileId
		);

		$displayName = $this->getDisplayNameFromIdentifyMethodIfEmpty($identifyMethodsInstances, $displayName);
		$this->populateSignRequest($signRequest, $displayName, $signingOrder, $description, $fileId);

		$isNewSignRequest = !$signRequest->getId();
		$currentStatus = $signRequest->getStatusEnum();

		if ($isNewSignRequest || $currentStatus === \OCA\Libresign\Enum\SignRequestStatus::DRAFT || $currentStatus === \OCA\Libresign\Enum\SignRequestStatus::ABLE_TO_SIGN) {
			$desiredStatus = $this->signRequestStatusService->determineInitialStatus(
				$signingOrder,
				$fileId,
				$fileStatus,
				$signerStatus,
				$currentStatus
			);
			$this->signRequestStatusService->updateStatusIfAllowed($signRequest, $currentStatus, $desiredStatus, $isNewSignRequest);
		}

		$this->insertOrUpdateSignRequest($signRequest);

		$shouldNotify = $notify && $this->signRequestStatusService->shouldNotifySignRequest(
			$signRequest->getStatusEnum(),
			$fileStatus
		);

		foreach ($identifyMethodsInstances as $identifyMethod) {
			$identifyMethod->getEntity()->setSignRequestId($signRequest->getId());
			$identifyMethod->willNotifyUser($shouldNotify);
			$identifyMethod->save();
		}

		return $signRequest;
	}

	public function getSignRequestByIdentifyMethod(IIdentifyMethod $identifyMethod, int $fileId): SignRequestEntity {
		try {
			$signRequest = $this->signRequestMapper->getByIdentifyMethodAndFileId($identifyMethod, $fileId);
		} catch (DoesNotExistException) {
			$signRequest = new SignRequestEntity();
		}
		return $signRequest;
	}

	private function populateSignRequest(
		SignRequestEntity $signRequest,
		string $displayName,
		int $signingOrder,
		string $description,
		int $fileId,
	): void {
		$signRequest->setFileId($fileId);
		$signRequest->setSigningOrder($signingOrder);
		if (!$signRequest->getUuid()) {
			$signRequest->setUuid(UUIDUtil::getUUID());
		}
		if (!empty($displayName)) {
			$signRequest->setDisplayName($displayName);
		}
		if (!empty($description)) {
			$signRequest->setDescription($description);
		}
		if (!$signRequest->getId()) {
			$signRequest->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		}
	}

	/**
	 * @param IIdentifyMethod[] $identifyMethodsInstances
	 * @param string $displayName
	 * @return string
	 */
	private function getDisplayNameFromIdentifyMethodIfEmpty(array $identifyMethodsInstances, string $displayName): string {
		if (!empty($displayName)) {
			return $displayName;
		}
		foreach ($identifyMethodsInstances as $identifyMethod) {
			if ($identifyMethod->getName() === 'account') {
				return $this->userManager->get($identifyMethod->getEntity()->getIdentifierValue())->getDisplayName();
			}
		}
		foreach ($identifyMethodsInstances as $identifyMethod) {
			if ($identifyMethod->getName() !== 'account') {
				return $identifyMethod->getEntity()->getIdentifierValue();
			}
		}
		return '';
	}

	public function insertOrUpdateSignRequest(SignRequestEntity $signRequest): void {
		if ($signRequest->getId()) {
			$this->signRequestMapper->update($signRequest);
		} else {
			$this->signRequestMapper->insert($signRequest);
		}
	}
}
