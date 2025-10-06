<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod;

use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Service\SessionService;
use OCA\TwoFactorGateway\Provider\Gateway\Factory;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IRootFolder;
use OCP\IUserSession;
use OCP\Server;
use Psr\Log\LoggerInterface;

class TwofactorGateway extends AbstractIdentifyMethod {
	public function __construct(
		protected IdentifyService $identifyService,
		private IdentifyMethodMapper $identifyMethodMapper,
		private IRootFolder $root,
		private ITimeFactory $timeFactory,
		private SessionService $sessionService,
		private FileElementMapper $fileElementMapper,
		private IUserSession $userSession,
		private LoggerInterface $logger,
		private IAppManager $appManager,
	) {
		parent::__construct(
			$identifyService,
		);
	}

	#[\Override]
	public function validateToIdentify(): void {
		$this->throwIfMaximumValidityExpired();
		$this->throwIfRenewalIntervalExpired();
		$this->throwIfFileNotFound();
		$this->throwIfAlreadySigned();
		$this->renewSession();
		$this->updateIdentifiedAt();
	}

	#[\Override]
	public function validateToSign(): void {
		$this->throwIfInvalidToken();
		$this->throwIfMaximumValidityExpired();
		$this->throwIfRenewalIntervalExpired();
		$this->throwIfFileNotFound();
		$this->throwIfAlreadySigned();
		$this->renewSession();
		$this->updateIdentifiedAt();
	}

	public function isTwofactorGatewayEnabled(): bool {
		$isAppEnabled = $this->appManager->isEnabledForAnyone('twofactor_gateway');
		if (!$isAppEnabled) {
			$this->logger->info('Twofactor Gateway app is not enabled');
			return false;
		}
		/** @var Factory */
		$gatewayFactory = Server::get(Factory::class);

		$gatewayName = strtolower($this->getId());

		$gateway = $gatewayFactory->get($gatewayName);
		return $gateway->isComplete();
	}

	#[\Override]
	public function getSettings(): array {
		if (!empty($this->settings)) {
			return $this->settings;
		}
		$this->settings = parent::getSettingsFromDatabase(
			default: [
				'enabled' => false,
			],
			immutable: [
				'test_url' => $this->identifyService->getUrlGenerator()->linkToRoute('settings.PersonalSettings.index', ['section' => 'security']),
			]
		);
		return $this->settings;
	}
}
