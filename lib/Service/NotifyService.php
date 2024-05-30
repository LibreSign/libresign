<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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

	public function notificationDismiss(int $signRequestId, IUser $user, int $timestamp): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setObject('signRequest', (string) $signRequestId)
			->setDateTime($this->timeFactory->getDateTime('@' . $timestamp))
			->setUser($user->getUID())
			->setSubject('new_sign_request');
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
				$identifyMethod->notify();
			} else {
				foreach ($instances as $instance) {
					$instance->notify();
				}
			}
		}
	}
}
