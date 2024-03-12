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

namespace OCA\Libresign\Activity\Provider;

use OCA\Libresign\AppInfo\Application;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\RichObjectStrings\Definitions;

class SignRequest implements IProvider {
	public function __construct(
		protected IFactory $languageFactory,
		protected IURLGenerator $url,
		protected Definitions $definitions,
		protected IManager $activityManager,
		protected IUserManager $userManager,
	) {
	}

	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		if ($event->getApp() !== Application::APP_ID) {
			throw new \InvalidArgumentException('Wrong app');
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

		if ($this->activityManager->getRequirePNG()) {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.png')));
		} else {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg')));
		}

		if (in_array($event->getSubject(), ['new_sign_request', 'update_sign_request'])) {
			$l = $this->languageFactory->get(Application::APP_ID, $language);
			$parameters = $event->getSubjectParameters();

			$subject = $this->getParsedSubject($l, $event->getSubject());
			$event->setParsedSubject(
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

		return $event;
	}

	private function getParsedSubject($l, $subject) {
		if ($subject === 'new_sign_request') {
			return $l->t('{from} requested your signature on {file}');
		} elseif ($subject === 'update_sign_request') {
			return $l->t('{from} made changes on {file}');
		}
	}
}
