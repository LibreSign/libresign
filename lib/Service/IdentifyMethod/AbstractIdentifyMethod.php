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

namespace OCA\Libresign\Service\IdentifyMethod;

use InvalidArgumentException;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\SessionService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\IHasher;
use Psr\Log\LoggerInterface;
use Wobeto\EmailBlur\Blur;

abstract class AbstractIdentifyMethod implements IIdentifyMethod {
	protected bool $canCreateAccount = true;
	protected IdentifyMethod $entity;
	protected string $name;
	public string $friendlyName;
	protected ?IUser $user = null;
	protected string $codeSentByUser = '';
	protected array $settings = [];
	protected bool $willNotify = true;
	public function __construct(
		private IAppConfig $appConfig,
		private IL10N $l10n,
		private IdentifyMethodMapper $identifyMethodMapper,
		private SignRequestMapper $signRequestMapper,
		private FileMapper $fileMapper,
		private IRootFolder $root,
		private IHasher $hasher,
		private IUserManager $userManager,
		private IURLGenerator $urlGenerator,
		private IUserMountCache $userMountCache,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
		private SessionService $sessionService,
	) {
		$className = (new \ReflectionClass($this))->getShortName();
		$this->name = lcfirst($className);
		$this->cleanEntity();
	}

	public function getName(): string {
		return $this->name;
	}

	public function isEnabledAsSignatueMethod(): bool {
		$settings = $this->getSettings();
		return $settings['enabled_as_signature_method'];
	}

	public function setCodeSentByUser(string $code): void {
		$this->codeSentByUser = $code;
	}

	public function setUser(?IUser $user): void {
		$this->user = $user;
	}

	public function cleanEntity(): void {
		$this->entity = new IdentifyMethod();
		$this->entity->setIdentifierKey($this->name);
	}

	public function setEntity(IdentifyMethod $entity): void {
		$this->entity = $entity;
	}

	public function getEntity(): IdentifyMethod {
		return $this->entity;
	}

	public function getSettings(): array {
		$this->getSettingsFromDatabase();
		return $this->settings;
	}

	public function notify(bool $isNew): void {
	}

	public function willNotifyUser(bool $willNotify): void {
		$this->willNotify = $willNotify;
	}

	public function validateToRequest(): void {
	}

	public function validateToCreateAccount(string $value): void {
	}

	public function validateToSign(): void {
	}

	protected function throwIfFileNotFound(): void {
		$signRequest = $this->signRequestMapper->getById($this->getEntity()->getSignRequestId());
		$fileEntity = $this->fileMapper->getById($signRequest->getFileId());

		$nodeId = $fileEntity->getNodeId();

		$mountsContainingFile = $this->userMountCache->getMountsForFileId($nodeId);
		foreach ($mountsContainingFile as $fileInfo) {
			$this->root->getByIdInPath($nodeId, $fileInfo->getMountPoint());
		}
		$fileToSign = $this->root->getById($nodeId);
		if (count($fileToSign) < 1) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->l10n->t('File not found')],
			]));
		}
	}

	protected function throwIfMaximumValidityExpired(): void {
		$maximumValidity = (int) $this->appConfig->getAppValue('maximum_validity', (string) SessionService::NO_MAXIMUM_VALIDITY);
		if ($maximumValidity <= 0) {
			return;
		}
		$signRequest = $this->signRequestMapper->getById($this->getEntity()->getSignRequestId());
		$now = $this->timeFactory->getTime();
		if ($signRequest->getCreatedAt() + $maximumValidity < $now) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->l10n->t('Link expired.')],
			]));
		}
	}

	protected function throwIfInvalidToken(): void {
		if (empty($this->codeSentByUser)) {
			return;
		}
		if (!$this->hasher->verify($this->codeSentByUser, $this->getEntity()->getCode())) {
			throw new LibresignException($this->l10n->t('Invalid code.'));
		}
	}

	protected function renewSession(): void {
		$this->sessionService->setIdentifyMethodId($this->getEntity()->getId());
		$renewalInterval = (int) $this->appConfig->getAppValue('renewal_interval', (string) SessionService::NO_RENEWAL_INTERVAL);
		if ($renewalInterval <= 0) {
			return;
		}
		$this->sessionService->resetDurationOfSignPage();
	}

	protected function updateIdentifiedAt(): void {
		if ($this->getEntity()->getCode() && !$this->getEntity()->getIdentifiedAtDate()) {
			return;
		}
		$this->getEntity()->setIdentifiedAtDate($this->timeFactory->getDateTime());
		$this->willNotify = false;
		$this->save();
	}

	protected function throwIfRenewalIntervalExpired(): void {
		$renewalInterval = (int) $this->appConfig->getAppValue('renewal_interval', (string) SessionService::NO_RENEWAL_INTERVAL);
		if ($renewalInterval <= 0) {
			return;
		}
		$signRequest = $this->signRequestMapper->getById($this->getEntity()->getSignRequestId());
		$startTime = $this->sessionService->getSignStartTime();
		$createdAt = $signRequest->getCreatedAt();
		$lastAttempt = $this->getEntity()->getLastAttemptDate()?->format('U');
		$lastActionDate = max(
			$startTime,
			$createdAt,
			$lastAttempt,
		);
		$now = $this->timeFactory->getTime();
		$this->logger->debug('AbstractIdentifyMethod::throwIfRenewalIntervalExpired Times', [
			'renewalInterval' => $renewalInterval,
			'startTime' => $startTime,
			'createdAt' => $createdAt,
			'lastAttempt' => $lastAttempt,
			'lastActionDate' => $lastActionDate,
			'now' => $now,
		]);
		if ($lastActionDate + $renewalInterval < $now) {
			$this->logger->debug('AbstractIdentifyMethod::throwIfRenewalIntervalExpired Exception');
			$blur = new Blur($this->getEntity()->getIdentifierValue());
			throw new LibresignException(json_encode([
				'action' => $this->getRenewAction(),
				// TRANSLATORS title that is displayed at screen to notify the signer that the link to sign the document expired
				'title' => $this->l10n->t('Link expired'),
				'body' => $this->l10n->t(<<<'BODY'
					The link to sign the document has expired.
					We will send a new link to the email %1$s.
					Click below to receive the new link and be able to sign the document.
					BODY,
					[$blur->make()]
				),
				'uuid' => $signRequest->getUuid(),
				// TRANSLATORS Button to renew the link to sign the document. Renew is the action to generate a new sign link when the link expired.
				'renewButton' => $this->l10n->t('Renew'),
			]));
		}
	}

	protected function throwIfNeedToCreateAccount() {
		if (!$this->canCreateAccount) {
			return;
		}
		if ($this->sessionService->getSignStartTime()) {
			return;
		}
		$email = $this->getEntity()->getIdentifierValue();
		throw new LibresignException(json_encode([
			'action' => JSActions::ACTION_CREATE_USER,
			'settings' => ['accountHash' => md5($email)],
			'message' => $this->l10n->t('You need to create an account to sign this file.'),
		]));
	}

	private function getRenewAction(): int {
		switch ($this->name) {
			case 'email':
				return JSActions::ACTION_RENEW_EMAIL;
		}
		throw new InvalidArgumentException('Invalid identify method name');
	}

	protected function throwIfAlreadySigned(): void {
		$signRequest = $this->signRequestMapper->getById($this->getEntity()->getSignRequestId());
		$fileEntity = $this->fileMapper->getById($signRequest->getFileId());
		if ($fileEntity->getStatus() === FileEntity::STATUS_SIGNED
			|| (!is_null($signRequest) && $signRequest->getSigned())
		) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_SHOW_ERROR,
				'errors' => [$this->l10n->t('File already signed.')],
			]));
		}
	}

	protected function getSettingsFromDatabase(array $default = [], array $immutable = []): array {
		if ($this->settings) {
			return $this->settings;
		}
		$default = array_merge(
			[
				'name' => $this->name,
				'friendly_name' => $this->friendlyName,
				'enabled' => true,
				'enabled_as_signature_method' => false,
				'mandatory' => true,
			],
			$default
		);
		$customConfig = $this->getSavedSettings();
		$customConfig = $this->removeKeysThatDontExists($customConfig, $default);
		$customConfig = $this->overrideImmutable($customConfig, $immutable);
		$customConfig = $this->getDefaultValues($customConfig, $default);
		$this->settings = $customConfig;
		return $this->settings;
	}

	private function overrideImmutable(array $customConfig, array $immutable) {
		return array_merge($customConfig, $immutable);
	}

	private function getSavedSettings(): array {
		return array_merge(
			$this->getSavedIdentifyMethodsSettings(),
			$this->getSavedSignatureMethodsSettings(),
		);
	}

	private function getSavedIdentifyMethodsSettings(): array {
		$config = $this->appConfig->getAppValue('identify_methods', '[]');
		$config = json_decode($config, true);
		if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
			return [];
		}
		$current = array_reduce($config, function ($carry, $config) {
			if ($config['name'] === $this->name) {
				return $config;
			}
			return $carry;
		}, []);
		return $current;
	}

	private function getSavedSignatureMethodsSettings(): array {
		$config = $this->appConfig->getAppValue('signature_methods', '[]');
		$config = json_decode($config, true);
		if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
			return [];
		}
		foreach ($config as $id => $method) {
			if ($id !== $this->name) {
				continue;
			}
			return [
				'enabled_as_signature_method' => array_key_exists('enabled', $method) && $method['enabled'],
			];
		}
		return [];
	}

	private function getDefaultValues(array $customConfig, array $default): array {
		foreach ($default as $key => $value) {
			if (!isset($customConfig[$key]) || gettype($value) !== gettype($customConfig[$key])) {
				$customConfig[$key] = $value;
			}
		}
		return $customConfig;
	}

	private function removeKeysThatDontExists(array $customConfig, array $default): array {
		$diff = array_diff_key($customConfig, $default);
		foreach (array_keys($diff) as $invalidKey) {
			unset($customConfig[$invalidKey]);
		}
		return $customConfig;
	}

	public function validateToRenew(?IUser $user = null): void {
		$this->throwIfMaximumValidityExpired();
		$this->throwIfAlreadySigned();
		$this->throwIfFileNotFound();
	}

	public function save(): void {
		$this->refreshIdFromDatabaseIfNecessary();
		if ($this->getEntity()->getId()) {
			$this->identifyMethodMapper->update($this->getEntity());
			$this->notify(false);
		} else {
			$this->identifyMethodMapper->insertOrUpdate($this->getEntity());
			$this->notify(true);
		}
	}

	public function delete(): void {
		if ($this->getEntity()->getId()) {
			$this->identifyMethodMapper->delete($this->getEntity());
		}
	}

	private function refreshIdFromDatabaseIfNecessary(): void {
		$entity = $this->getEntity();
		if ($entity->getId()) {
			return;
		}
		if (!$entity->getSignRequestId() || !$entity->getIdentifierKey()) {
			return;
		}

		$identifyMethods = $this->identifyMethodMapper->getIdentifyMethodsFromSignRequestId($entity->getSignRequestId());
		$exists = array_filter($identifyMethods, function (IdentifyMethod $current) use ($entity): bool {
			return $current->getIdentifierKey() === $entity->getIdentifierKey();
		});
		if (!$exists) {
			return;
		}
		$exists = current($exists);
		$entity->setId($exists->getId());
	}
}
