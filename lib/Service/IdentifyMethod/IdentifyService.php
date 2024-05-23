<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\SessionService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Security\IHasher;
use Psr\Log\LoggerInterface;

class IdentifyService {
	private array $savedSettings = [];
	public function __construct(
		private IdentifyMethodMapper $identifyMethodMapper,
		private SessionService $sessionService,
		private ITimeFactory $timeFactory,
		private IEventDispatcher $eventDispatcher,
		private IRootFolder $root,
		private IAppConfig $appConfig,
		private SignRequestMapper $signRequestMapper,
		private IUserMountCache $userMountCache,
		private IL10N $l10n,
		private FileMapper $fileMapper,
		private IHasher $hasher,
		private IUserManager $userManager,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
	) {
	}

	public function save(IdentifyMethod $identifyMethod): void {
		$this->refreshIdFromDatabaseIfNecessary($identifyMethod);
		if ($identifyMethod->getId()) {
			$this->identifyMethodMapper->update($identifyMethod);
			return;
		}
		$this->identifyMethodMapper->insertOrUpdate($identifyMethod);
		return;
	}

	public function delete(IdentifyMethod $identifyMethod): void {
		if ($identifyMethod->getId()) {
			$this->identifyMethodMapper->delete($identifyMethod);
		}
	}

	private function refreshIdFromDatabaseIfNecessary(IdentifyMethod $identifyMethod): void {
		if ($identifyMethod->getId()) {
			return;
		}
		if (!$identifyMethod->getSignRequestId() || !$identifyMethod->getIdentifierKey()) {
			return;
		}

		$identifyMethods = $this->identifyMethodMapper->getIdentifyMethodsFromSignRequestId($identifyMethod->getSignRequestId());
		$exists = array_filter($identifyMethods, function (IdentifyMethod $current) use ($identifyMethod): bool {
			return $current->getIdentifierKey() === $identifyMethod->getIdentifierKey();
		});
		if (!$exists) {
			return;
		}
		$exists = current($exists);
		$identifyMethod->setId($exists->getId());
	}

	public function getSavedSettings(): array {
		if (!empty($this->savedSettings)) {
			return $this->savedSettings;
		}
		$config = $this->getAppConfig()->getAppValue('identify_methods', '[]');
		$config = json_decode($config, true);
		if (is_array($config)) {
			$this->savedSettings = $config;
		}
		return $this->savedSettings;
	}

	public function getEventDispatcher(): IEventDispatcher {
		return $this->eventDispatcher;
	}

	public function getSessionService(): SessionService {
		return $this->sessionService;
	}

	public function getTimeFactory(): ITimeFactory {
		return $this->timeFactory;
	}

	public function getRootFolder(): IRootFolder {
		return $this->root;
	}

	public function getAppConfig(): IAppConfig {
		return $this->appConfig;
	}

	public function getSignRequestMapper(): SignRequestMapper {
		return $this->signRequestMapper;
	}

	public function getUserMountCache(): IUserMountCache {
		return $this->userMountCache;
	}

	public function getL10n(): IL10N {
		return $this->l10n;
	}

	public function getFileMapper(): FileMapper {
		return $this->fileMapper;
	}

	public function getHasher(): IHasher {
		return $this->hasher;
	}

	public function getUserManager(): IUserManager {
		return $this->userManager;
	}

	public function getUrlGenerator(): IURLGenerator {
		return $this->urlGenerator;
	}

	public function getLogger(): LoggerInterface {
		return $this->logger;
	}
}
