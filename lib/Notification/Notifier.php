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

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->factory->get(Application::APP_ID)->t('File sharing');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new \InvalidArgumentException();
		}

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

		$l = $this->factory->get(Application::APP_ID, $languageCode);

		switch ($notification->getSubject()) {
			case 'new_sign_request':
				return $this->parseSignRequest($notification, $l, false);
			case 'update_sign_request':
				return $this->parseSignRequest($notification, $l, true);
			default:
				throw new \InvalidArgumentException();
		}
	}

	private function parseSignRequest(
		INotification $notification,
		IL10N $l,
		bool $update,
	): INotification {
		$parameters = $notification->getSubjectParameters();
		$notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg')));
		if (isset($parameters['file'])) {
			$notification->setLink($parameters['file']['link']);
			$signAction = $notification->createAction()
				->setParsedLabel($l->t('View'))
				->setPrimary(true)
				->setLink(
					$parameters['file']['link'],
					IAction::TYPE_WEB
				);
			$notification->addParsedAction($signAction);
			if (isset($parameters['from'])) {
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
			$notification->setParsedMessage($l->t('Changes have been made in a file that you have to sign.'));
		}

		if (isset($parameters['signRequest']) && isset($parameters['signRequest']['id'])) {
			$dismissAction = $notification->createAction()
				->setParsedLabel($l->t('Dismiss notification'))
				->setLink(
					$this->url->linkToOCSRouteAbsolute(
						'libresign.notify.notificationDismiss',
						[
							'apiVersion' => 'v1',
							'timestamp' => $notification->getDateTime()->getTimestamp(),
							'signRequestId' => $parameters['signRequest']['id'],
						],
					),
					IAction::TYPE_DELETE
				);
			$notification->addParsedAction($dismissAction);
		}
		return $notification;
	}
}
