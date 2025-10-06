<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Listener;

use Exception;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\TwoFactorGateway\Provider\Gateway\Factory;
use OCP\App\IAppManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Server;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<Event> */
class TwofactorGatewayListener implements IEventListener {
	public function __construct(
		protected IUserSession $userSession,
		protected IUserManager $userManager,
		protected IdentifyService $identifyService,
		private SignRequestMapper $signRequestMapper,
		private LoggerInterface $logger,
		protected IAppManager $appManager,
		protected IL10N $l10n,
		protected IURLGenerator $urlGenerator,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		/** @var SendSignNotificationEvent|SignedEvent $event */
		match ($event::class) {
			SendSignNotificationEvent::class => $this->sendSignNotification(
				$event->getSignRequest(),
				$event->getIdentifyMethod(),
				$event->getLibreSignFile(),
			),
			SignedEvent::class => $this->sendSignedNotification(
				$event->getSignRequest(),
				$event->getIdentifyMethod(),
				$event->getLibreSignFile(),
			),
		};
	}

	protected function sendSignNotification(
		SignRequest $signRequest,
		IIdentifyMethod $identifyMethod,
		FileEntity $libreSignFile,
	): void {
		try {
			$entity = $identifyMethod->getEntity();
			if ($entity->isDeletedAccount()) {
				return;
			}
			if (!in_array($entity->getIdentifierKey(), ['sms', 'signal', 'telegram', 'whatsapp', 'xmpp'], true)) {
				return;
			}
			if (!$this->appManager->isEnabledForAnyone('twofactor_gateway')) {
				$this->logger->info('Twofactor Gateway app is not enabled');
				return;
			}
			$identifier = $entity->getIdentifierValue();
			if (empty($identifier)) {
				return;
			}

			$isFirstNotification = $this->signRequestMapper->incrementNotificationCounter($signRequest, $entity->getIdentifierKey());
			if ($isFirstNotification) {
				$message = $this->l10n->t('There is a document for you to sign. Access the link below:');
			} else {
				$message = $this->l10n->t('Changes have been made in a file that you have to sign. Access the link below:');
			}
			$message .= "\n";
			$link = $this->urlGenerator->linkToRouteAbsolute('libresign.page.sign', ['uuid' => $signRequest->getUuid()]);
			$message .= $libreSignFile->getName() . ': ' . $link;

			/** @var Factory */
			$gatewayFactory = Server::get(Factory::class);
			$gateway = $gatewayFactory->get(strtolower($entity->getIdentifierKey()));
			try {
				$gateway->send($identifier, $message);
			} catch (Exception $e) {
				$this->logger->error('Could not send 2FA message', [
					'identifier' => $identifier,
					'exception' => $e,
				]);
				return;
			}
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}
	}

	protected function sendSignedNotification(
		SignRequest $signRequest,
		IIdentifyMethod $identifyMethod,
		FileEntity $libreSignFile,
	): void {
		try {
			$entity = $identifyMethod->getEntity();
			if ($entity->isDeletedAccount()) {
				return;
			}
			if (!in_array($entity->getIdentifierKey(), ['sms', 'signal', 'telegram', 'whatsapp', 'xmpp'], true)) {
				return;
			}
			if (!$this->appManager->isEnabledForAnyone('twofactor_gateway')) {
				$this->logger->info('Twofactor Gateway app is not enabled');
				return;
			}
			$identifier = $entity->getIdentifierValue();
			if (empty($identifier)) {
				return;
			}

			$message = $this->l10n->t('LibreSign: A file has been signed');
			$message .= "\n";
			// TRANSLATORS The text in the message that is sent after a document has been signed by a user. %s will be replaced with the name of the user who signed the document.
			$message .= $this->l10n->t('%s signed the document. You can access it using the link below:', [$signRequest->getDisplayName()]);
			$link = $this->urlGenerator->linkToRouteAbsolute('libresign.page.indexFPath', [
				'path' => 'validation/' . $libreSignFile->getUuid(),
			]);
			$message .= "\n";
			$message .= $libreSignFile->getName() . ': ' . $link;

			/** @var Factory */
			$gatewayFactory = Server::get(Factory::class);
			$gateway = $gatewayFactory->get(strtolower($entity->getIdentifierKey()));
			try {
				$gateway->send($identifier, $message);
			} catch (Exception $e) {
				$this->logger->error('Could not send 2FA message', [
					'identifier' => $identifier,
					'exception' => $e,
				]);
				return;
			}
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}
	}
}
