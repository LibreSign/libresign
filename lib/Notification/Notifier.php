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
use OCA\Libresign\Db\FileUserMapper;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\IAction;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {
	public function __construct(
		private IFactory $factory,
		private IURLGenerator $urlGenerator,
		private FileMapper $fileMapper,
		private FileUserMapper $fileUserMapper
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
		bool $update
	): INotification {
		$parameters = $notification->getSubjectParameters();
		$fileUser = $this->fileUserMapper->getById($parameters['fileUser']);
		$notification
			->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')))
			->setLink($this->urlGenerator->linkToRouteAbsolute('libresign.page.sign', ['uuid' => $fileUser->getUuid()]));
		$notification->setParsedSubject($l->t('There is a file for you to sign'));
		if ($update) {
			$notification->setParsedMessage($l->t('Changes have been made in a file that you have to sign.'));
		}

		$signAction = $notification->createAction()
			->setParsedLabel($l->t('View'))
			->setPrimary(true)
			->setLink(
				$this->urlGenerator->linkToRouteAbsolute('libresign.page.sign', ['uuid' => $fileUser->getUuid()]),
				IAction::TYPE_GET
			);
		$notification->addParsedAction($signAction);
		return $notification;
	}
}
