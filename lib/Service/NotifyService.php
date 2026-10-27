<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCA\Libresign\Service\Validation\SigningRequestValidator;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Notification\IManager;

class NotifyService {
	public function __construct(
		private SigningRequestValidator $signingRequestValidator,
		private FileInputValidator $fileInputValidator,
		private IdentityDocumentValidator $identityDocumentValidator,
		private IUserSession $userSession,
		private SignRequestMapper $signRequestMapper,
		private IdentifyMethodService $identifyMethodService,
		private SignerIndexProvider $signerIndexProvider,
		private SignerNotificationPolicy $signerNotificationPolicy,
		private ITimeFactory $timeFactory,
		private IManager $notificationManager,
		private IL10N $l10n,
	) {
	}

	public function signer(int $fileId, int $signRequestId): void {
		$this->signingRequestValidator->canRequestSign($this->userSession->getUser());
		$this->fileInputValidator->validateLibreSignFileId($fileId);
		$this->signingRequestValidator->validateWorkflowIsNotClosedByFileId($fileId);
		// Authorize access to the document before exposing signer information.
		$this->signingRequestValidator->iRequestedSignThisFile($this->userSession->getUser(), $fileId);
		$signRequest = $this->signRequestMapper->getByFileIdAndSignRequestId($fileId, $signRequestId);
		$this->validatePendingNotification($signRequest);
		$this->notify($signRequest);
	}

	public function signers(int $fileId, array $signers): void {
		if ($signers === []) {
			throw new LibresignException($this->l10n->t('At least one signer is required'));
		}
		$this->signingRequestValidator->canRequestSign($this->userSession->getUser());
		$this->fileInputValidator->validateLibreSignFileId($fileId);
		$this->signingRequestValidator->validateWorkflowIsNotClosedByFileId($fileId);
		$this->signingRequestValidator->iRequestedSignThisFile($this->userSession->getUser(), $fileId);
		$signRequests = $this->signRequestMapper->getByFileId($fileId);
		$signRequestIndex = $this->signerIndexProvider->build($signRequests);
		foreach ($signers as $signer) {
			$this->identityDocumentValidator->haveValidMail($signer);
			$this->validateSignerForNotification($signer, $signRequestIndex);
		}
		foreach ($signRequests as $signRequest) {
			$this->notify($signRequest, $signers);
		}
	}

	public function notificationDismiss(
		string $objectType,
		int $objectId,
		string $subject,
		IUser $user,
		int $timestamp,
	): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setObject($objectType, (string)$objectId)
			->setDateTime($this->timeFactory->getDateTime('@' . $timestamp))
			->setUser($user->getUID())
			->setSubject($subject);
		$this->notificationManager->markProcessed($notification);
	}

	private function notify(SignRequest $signRequest, array $signers = []): void {
		$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
		$eventId = 'manual:' . bin2hex(random_bytes(16));
		foreach ($identifyMethods as $instances) {
			foreach ($instances as $instance) {
				$entity = $instance->getEntity();
				if ($signers !== [] && !$this->isSelectedRecipient($signers, $entity->getIdentifierKey(), $entity->getIdentifierValue())) {
					continue;
				}
				$instance->willNotifyUser(true);
				$instance->notify($eventId);
			}
		}
	}

	/** @param list<array<string, string>> $signers */
	private function isSelectedRecipient(array $signers, string $key, string $value): bool {
		foreach ($signers as $signer) {
			if (($signer[$key] ?? null) === $value) {
				return true;
			}
		}
		return false;
	}

	private function validatePendingNotification(SignRequest $request): void {
		if ($request->getSigned() !== null || !in_array($request->getStatusEnum(), [
			SignRequestStatus::ABLE_TO_SIGN,
			SignRequestStatus::OBSERVING,
		], true)) {
			throw new LibresignException($this->l10n->t('This participant is not waiting for a notification'));
		}
	}

	/**
	 * @param array<string, SignRequest[]> $signRequestIndex Indexed by identifierKey:identifierValue
	 * @throws \OCA\Libresign\Exception\LibresignException
	 */
	private function validateSignerForNotification(array $signer, array $signRequestIndex): void {
		$error = $this->signerNotificationPolicy->getValidationError($signer, $signRequestIndex);
		if ($error !== null) {
			$message = match ($error['code']) {
				// TRANSLATORS Error shown when notifying a person who was never asked to sign. %s is the signer identifier.
				'not_requested' => $this->l10n->t('No signature was requested to %s', $error['params']),
				// TRANSLATORS Error shown when notifying a signer who already completed their signature. %s is the signer name.
				'already_signed' => $this->l10n->t('%s already signed this file', $error['params']),
				// TRANSLATORS Error shown when the signer data used for a notification is invalid.
				default => $this->l10n->t('Invalid signer data'),
			};
			throw new \OCA\Libresign\Exception\LibresignException($message);
		}
	}
}
