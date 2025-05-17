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
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Notification\IManager;

class NotifyService {
	public function __construct(
		private ValidateHelper $validateHelper,
		private IUserSession $userSession,
		private SignRequestMapper $signRequestMapper,
		private IdentifyMethodService $identifyMethodService,
		private ITimeFactory $timeFactory,
		private IManager $notificationManager,
	) {
	}

	public function signer(int $nodeId, int $signRequestId): void {
		$this->validateHelper->canRequestSign($this->userSession->getUser());
		$this->validateHelper->validateLibreSignNodeId($nodeId);
		$this->validateHelper->iRequestedSignThisFile($this->userSession->getUser(), $nodeId);
		$signRequest = $this->signRequestMapper->getByFileIdAndSignRequestId($nodeId, $signRequestId);
		$this->notify($signRequest);
	}

	public function signers(int $nodeId, array $signers): void {
		$this->validateHelper->canRequestSign($this->userSession->getUser());
		$this->validateHelper->validateLibreSignNodeId($nodeId);
		$this->validateHelper->iRequestedSignThisFile($this->userSession->getUser(), $nodeId);
		foreach ($signers as $signer) {
			$this->validateHelper->haveValidMail($signer);
			$this->validateHelper->signerWasAssociated($signer);
			$this->validateHelper->notSigned($signer);
		}
		// @todo refactor this code
		$signRequests = $this->signRequestMapper->getByNodeId($nodeId);
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
		foreach ($identifyMethods as $methodName => $instances) {
			$identifyMethod = array_reduce($instances, function (?IIdentifyMethod $carry, IIdentifyMethod $identifyMethod) use ($signers): ?IIdentifyMethod {
				foreach ($signers as $signer) {
					$key = key($signer);
					$value = current($signer);
					$entity = $identifyMethod->getEntity();
					if ($entity->getIdentifierKey() === $key
						&& $entity->getIdentifierValue() === $value
					) {
						return $identifyMethod;
					}
				}
				return $carry;
			});
			if ($identifyMethod instanceof IIdentifyMethod) {
				$identifyMethod->willNotifyUser(true);
				$identifyMethod->notify();
			} else {
				foreach ($instances as $instance) {
					$instance->willNotifyUser(true);
					$instance->notify();
				}
			}
		}
	}
}
