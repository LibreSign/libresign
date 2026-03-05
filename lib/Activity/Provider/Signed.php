<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
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

class Signed implements IProvider {
	public function __construct(
		protected IFactory $languageFactory,
		protected IURLGenerator $url,
		protected Definitions $definitions,
		protected IManager $activityManager,
		protected IUserManager $userManager,
	) {
	}

	#[\Override]
	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		if ($event->getApp() !== Application::APP_ID) {
			throw new UnknownActivityException('app');
		}

		if ($event->getSubject() !== 'file_signed') {
			throw new UnknownActivityException('subject');
		}

		$this->definitions->definitions['sign-request'] = [
			'author' => 'LibreSign',
			'since' => '30.0.0',
			'parameters' => [
				'id' => [
					'since' => '30.0.0',
					'required' => true,
					'description' => 'The id of SignRequest object',
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

		if ($this->activityManager->getRequirePNG()) {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.png')));
		} else {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg')));
		}

		$l = $this->languageFactory->get(Application::APP_ID, $language);
		$parameters = $event->getSubjectParameters();

		$subject = $l->t('{from} signed {file}');
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

		return $event;
	}
}
