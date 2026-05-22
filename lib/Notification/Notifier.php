<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Notification;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\IAction;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;
use OCP\RichObjectStrings\Definitions;

class Notifier implements INotifier {
	public function __construct(
		private IFactory $factory,
		private IURLGenerator $url,
		private Definitions $definitions,
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
	) {
	}

	#[\Override]
	public function getID(): string {
		return Application::APP_ID;
	}

	#[\Override]
	public function getName(): string {
		// TRANSLATORS Notification app category label shown in Nextcloud notification settings.
		return $this->factory->get(Application::APP_ID)->t('File sharing');
	}

	#[\Override]
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException();
		}

		$l = $this->factory->get(Application::APP_ID, $languageCode);

		return match ($notification->getSubject()) {
			'new_sign_request' => $this->parseSignRequest($notification, $l, false),
			'update_sign_request' => $this->parseSignRequest($notification, $l, true),
			'file_signed' => $this->parseSigned($notification, $l),
			'sign_request_canceled' => $this->parseCanceled($notification, $l),
			default => throw new UnknownNotificationException(),
		};
	}

	private function parseSignRequest(
		INotification $notification,
		IL10N $l,
		bool $update,
	): INotification {

		$this->definitions->definitions['sign-request'] = [
			'author' => 'LibreSign',
			'since' => '28.0.0',
			'parameters' => [
				'id' => [
					'since' => '28.0.0',
					'required' => true,
					'description' => 'The id of SignRequest object',
					'example' => '12345',
				],
				'name' => [
					'since' => '28.0.0',
					'required' => true,
					'description' => 'The display name of signer',
					'example' => 'John Doe',
				],
			],
		];

		$parameters = $notification->getSubjectParameters();
		$notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg')));
		if (isset($parameters['file'])) {
			$notification->setLink($parameters['file']['link']);
			$signAction = $notification->createAction()
				// TRANSLATORS Action button opening the related file from a LibreSign notification.
				->setParsedLabel($l->t('View'))
				->setPrimary(true)
				->setLink(
					$parameters['file']['link'],
					IAction::TYPE_WEB
				);
			$notification->addParsedAction($signAction);
			if (isset($parameters['from'])) {
				// TRANSLATORS Notification subject. {from} is the user who requested a signature, {file} is the document name.
				$subject = $l->t('{from} requested your signature on {file}');
				$notification->setParsedSubject(
					str_replace(
						['{from}', '{file}'],
						[
							$parameters['from']['name'],
							$parameters['file']['name'],
						],
						$subject
					))
					->setRichSubject($subject, $parameters);
			}
		}
		if ($update) {
			// TRANSLATORS Notification message informing the signer that a pending document changed and should be reviewed again.
			$notification->setParsedMessage($l->t('Changes have been made in a file that you have to sign.'));
		}

		if (isset($parameters['signRequest']) && isset($parameters['signRequest']['id'])) {
			$dismissAction = $notification->createAction()
				// TRANSLATORS Action button that dismisses this notification from the notification list.
				->setParsedLabel($l->t('Dismiss notification'))
				->setLink(
					$this->url->linkToOCSRouteAbsolute(
						'libresign.notify.notificationDismiss',
						[
							'apiVersion' => 'v1',
							'timestamp' => $notification->getDateTime()->getTimestamp(),
							'objectType' => 'signRequest',
							'objectId' => $parameters['signRequest']['id'],
							'subject' => 'new_sign_request',
						],
					),
					IAction::TYPE_DELETE
				);
			$notification->addParsedAction($dismissAction);
		}

		return $notification;
	}

	private function parseSigned(
		INotification $notification,
		IL10N $l,
	): INotification {

		$this->definitions->definitions['signer'] = [
			'author' => 'LibreSign',
			'since' => '30.0.0',
			'parameters' => [
				'id' => [
					'since' => '30.0.0',
					'required' => true,
					'description' => 'The identify method id',
					'example' => '12345',
				],
				'name' => [
					'since' => '30.0.0',
					'required' => true,
					'description' => 'The display name of signer',
					'example' => 'John Doe',
				],
			],
		];

		$parameters = $notification->getSubjectParameters();
		$notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg')));
		if (isset($parameters['file'])) {
			$notification->setLink($parameters['file']['link']);
			$signAction = $notification->createAction()
				// TRANSLATORS Action button opening the signed file from a notification.
				->setParsedLabel($l->t('View'))
				->setPrimary(true)
				->setLink(
					$parameters['file']['link'],
					IAction::TYPE_WEB
				);
			$notification->addParsedAction($signAction);
			if (isset($parameters['from'])) {
				// TRANSLATORS Notification subject. {from} is the signer name and {file} is the document name that was signed.
				$subject = $l->t('{from} signed {file}');
				$notification->setParsedSubject(
					str_replace(
						['{from}', '{file}'],
						[
							$parameters['from']['name'],
							$parameters['file']['name'],
						],
						$subject
					))
					->setRichSubject($subject, $parameters);
			}
		}

		if (isset($parameters['signedFile']) && isset($parameters['signedFile']['id'])) {
			$dismissAction = $notification->createAction()
				// TRANSLATORS Action button that dismisses this notification from the notification list.
				->setParsedLabel($l->t('Dismiss notification'))
				->setLink(
					$this->url->linkToOCSRouteAbsolute(
						'libresign.notify.notificationDismiss',
						[
							'apiVersion' => 'v1',
							'timestamp' => $notification->getDateTime()->getTimestamp(),
							'objectType' => 'signedFile',
							'objectId' => $parameters['signedFile']['id'],
							'subject' => 'file_signed',
						],
					),
					IAction::TYPE_DELETE
				);
			$notification->addParsedAction($dismissAction);
		}

		return $notification;
	}

	private function parseCanceled(
		INotification $notification,
		IL10N $l,
	): INotification {

		$this->definitions->definitions['sign-request'] = [
			'author' => 'LibreSign',
			'since' => '28.0.0',
			'parameters' => [
				'id' => [
					'since' => '28.0.0',
					'required' => true,
					'description' => 'The id of SignRequest object',
					'example' => '12345',
				],
				'name' => [
					'since' => '28.0.0',
					'required' => true,
					'description' => 'The display name of signer',
					'example' => 'John Doe',
				],
			],
		];

		$parameters = $notification->getSubjectParameters();
		$notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg')));

		if (isset($parameters['from']) && isset($parameters['file'])) {
			// TRANSLATORS Notification subject. {from} is the actor who canceled, {file} is the document name whose signature request was canceled.
			$subject = $l->t('{from} canceled the signature request for {file}');
			$notification->setParsedSubject(
				str_replace(
					['{from}', '{file}'],
					[
						$parameters['from']['name'],
						$parameters['file']['name'],
					],
					$subject
				))
				->setRichSubject($subject, $parameters);
		}

		if (isset($parameters['signRequest']) && isset($parameters['signRequest']['id'])) {
			$dismissAction = $notification->createAction()
				// TRANSLATORS Action button that dismisses this cancellation notification.
				->setParsedLabel($l->t('Dismiss notification'))
				->setLink(
					$this->url->linkToOCSRouteAbsolute(
						'libresign.notify.notificationDismiss',
						[
							'apiVersion' => 'v1',
							'timestamp' => $notification->getDateTime()->getTimestamp(),
							'objectType' => 'signRequest',
							'objectId' => $parameters['signRequest']['id'],
							'subject' => 'sign_request_canceled',
						],
					),
					IAction::TYPE_DELETE
				);
			$notification->addParsedAction($dismissAction);
		}

		return $notification;
	}
}
