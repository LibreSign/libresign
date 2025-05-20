<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Activity\Provider;

use OCA\Libresign\AppInfo\Application;
use OCP\Activity\Exceptions\UnknownActivityException;
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
			throw new UnknownActivityException('Wrong app');
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

		if (in_array($event->getSubject(), ['new_sign_request', 'update_sign_request', 'new_file_signed'])) {
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

	private function getParsedSubject(\OCP\IL10N $l, string $subject) {
		if ($subject === 'new_sign_request') {
			return $l->t('{from} requested your signature on {file}');
		} elseif ($subject === 'update_sign_request') {
			return $l->t('{from} made changes on {file}');
		} elseif ($subject === 'new_file_signed') {
			return '{from} teste aqui foi {file}';
		}
	}
}
