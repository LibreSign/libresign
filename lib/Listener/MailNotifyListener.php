<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
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

namespace OCA\Libresign\Listener;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\MailService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<SendSignNotificationEvent> */
class MailNotifyListener implements IEventListener {
	public function __construct(
		protected IUserSession $userSession,
		protected IUserManager $userManager,
		protected IdentifyService $identifyService,
		protected MailService $mail,
		private SignRequestMapper $signRequestMapper,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		/** @var SendSignNotificationEvent $event */
		match (get_class($event)) {
			SendSignNotificationEvent::class => $this->sendMailNotification(
				$event->getSignRequest(),
				$event->getLibreSignFile(),
				$event->getIdentifyMethod(),
			),
		};
	}

	protected function sendMailNotification(
		SignRequest $signRequest,
		FileEntity $libreSignFile,
		IIdentifyMethod $identifyMethod,
	): void {
		try {
			if ($identifyMethod->getEntity()->isDeletedAccount()) {
				return;
			}
			$email = '';
			if ($identifyMethod->getName() === 'account') {
				$email = $this->userManager
					->get($identifyMethod->getEntity()->getIdentifierValue())
					->getEMailAddress();
			} elseif ($identifyMethod->getName() === 'email') {
				$email = $identifyMethod->getEntity()->getIdentifierValue();
			}
			if (empty($email)) {
				return;
			}
			$isFirstNotification = $this->signRequestMapper->incrementNotificationCounter($signRequest, 'mail');
			if ($isFirstNotification) {
				$this->mail->notifyUnsignedUser($signRequest, $email);
				return;
			}
			$this->mail->notifySignDataUpdated($signRequest, $email);
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}
	}
}
