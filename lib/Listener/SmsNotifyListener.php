<?php

namespace OCA\Libresign\Listener;

use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Service\SMSService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class SmsNotifyListener implements IEventListener {
	public function __construct(
		private readonly SMSService $smsService,
		private readonly IdentifyService $identifyService,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof SendSignNotificationEvent) {
			return;
		}

		$identifyMethod = $event->getIdentifyMethod();

		if ($identifyMethod->getName() !== 'sms') {
			return;
		}

		$phone = $identifyMethod->getEntity()->getIdentifierValue();
		$signRequest = $event->getSignRequest();

		$link = $this->identifyService
			->getUrlGenerator()
			->linkToRouteAbsolute('libresign.page.sign', [
				'uuid' => $signRequest->getUuid()
			]);

		$this->smsService->sendSMS(
			$phone,
			"GoPaperless:\nYou have a document to sign.\n\n$link"
		);
	}
}
