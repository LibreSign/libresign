<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025-2026 LibreCode coop and contributors
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
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Notification\GatewayNotificationComposer;
use OCA\Libresign\Service\TwofactorGatewayService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<Event> */
class TwofactorGatewayListener implements IEventListener {
	public function __construct(
		protected IUserSession $userSession,
		protected IUserManager $userManager,
		protected IdentifyService $identifyService,
		private SignRequestMapper $signRequestMapper,
		private TwofactorGatewayService $twofactorGatewayService,
		private LoggerInterface $logger,
		private GatewayNotificationComposer $composer,
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
		$this->deliver($signRequest, $identifyMethod, $libreSignFile, false);
	}

	protected function sendSignedNotification(
		SignRequest $signRequest,
		IIdentifyMethod $identifyMethod,
		FileEntity $libreSignFile,
	): void {
		$this->deliver($signRequest, $identifyMethod, $libreSignFile, true);
	}

	private function deliver(
		SignRequest $request,
		IIdentifyMethod $identifyMethod,
		FileEntity $file,
		bool $signed,
	): void {
		try {
			$entity = $identifyMethod->getEntity();
			if ($entity->isDeletedAccount()
				|| !in_array($entity->getIdentifierKey(), IdentifyMethodService::IDENTIFY_TWOFACTOR_GATEWAY_METHODS, true)) {
				return;
			}
			if (!$this->twofactorGatewayService->isEnabled()) {
				$this->logger->info('Twofactor Gateway app is not enabled');
				return;
			}
			$identifier = $entity->getIdentifierValue();
			if (!is_string($identifier) || trim($identifier) === '') {
				return;
			}
			$method = $entity->getIdentifierKey();
			$gatewayName = IdentifyMethodService::resolveTwofactorGatewayName($method);
			$payload = $signed
				? $this->composer->composeSignedFile($request, $file, $gatewayName)
				: $this->composer->composeSignRequest(
					$request,
					$file,
					$this->signRequestMapper->incrementNotificationCounter($request, $method),
					$gatewayName,
				);
			$this->twofactorGatewayService->send($payload->gatewayName, $identifier, $payload->body, $payload->extra);
		} catch (\InvalidArgumentException) {
			// Identifier, document details and option values must never reach logs.
			$this->logger->warning('Could not prepare gateway notification.', ['reason' => 'invalid_notification']);
		} catch (Exception) {
			$this->logger->error('Could not send gateway notification.', ['reason' => 'delivery_failure']);
		}
	}
}
