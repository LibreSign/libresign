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
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\AbstractSignatureMethod;
use OCA\Libresign\Service\SessionService;
use OCP\IUser;
use Wobeto\EmailBlur\Blur;

abstract class AbstractIdentifyMethod implements IIdentifyMethod {
	protected IdentifyMethod $entity;
	protected string $name;
	protected string $friendlyName;
	protected ?IUser $user = null;
	protected string $codeSentByUser = '';
	protected array $settings = [];
	protected bool $willNotify = true;
	/**
	 * @var string[]
	 */
	public array $availableSignatureMethods = [];
	/**
	 * @var AbstractSignatureMethod[]
	 */
	protected array $signatureMethods = [];
	public function __construct(
		protected IdentifyService $identifyService,
	) {
		$className = (new \ReflectionClass($this))->getShortName();
		$this->name = lcfirst($className);
		$this->cleanEntity();
	}

	public static function getId(): string {
		$id = lcfirst(substr(strrchr(get_called_class(), '\\'), 1));
		return $id;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getFriendlyName(): string {
		return $this->friendlyName;
	}

	public function setCodeSentByUser(string $code): void {
		$this->codeSentByUser = $code;
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

	public function signatureMethodsToArray(): array {
		return array_map(function (AbstractSignatureMethod $method) {
			return [
				'label' => $method->friendlyName,
				'name' => $method->getName(),
				'enabled' => $method->isEnabled(),
			];
		}, $this->signatureMethods);
	}

	public function getEmptyInstanceOfSignatureMethodByName(string $name): AbstractSignatureMethod {
		if (!in_array($name, $this->availableSignatureMethods)) {
			throw new InvalidArgumentException(sprintf('%s is not a valid signature method of identify method %s', $name, $this->getName()));
		}
		$className = 'OCA\Libresign\Service\IdentifyMethod\\SignatureMethod\\' . ucfirst($name);
		if (!class_exists($className)) {
			throw new InvalidArgumentException('Invalid signature method. Set at identify method the list  of available signature methdos with right values.');
		}
		$signatureMethod = clone \OC::$server->get($className);
		$signatureMethod->cleanEntity();
		return $signatureMethod;
	}

	/**
	 * @return AbstractSignatureMethod[]
	 */
	public function getSignatureMethods(): array {
		return $this->signatureMethods;
	}

	public function getSettings(): array {
		$this->getSettingsFromDatabase();
		return $this->settings;
	}

	public function notify(): bool {
		if (!$this->willNotify) {
			return false;
		}
		$signRequest = $this->identifyService->getSignRequestMapper()->getById($this->getEntity()->getSignRequestId());
		$libresignFile = $this->identifyService->getFileMapper()->getById($signRequest->getFileId());
		$this->identifyService->getEventDispatcher()->dispatchTyped(new SendSignNotificationEvent(
			$signRequest,
			$libresignFile,
			$this
		));
		return true;
	}

	public function willNotifyUser(bool $willNotify): void {
		$this->willNotify = $willNotify;
	}

	public function validateToRequest(): void {
	}

	public function validateToCreateAccount(string $value): void {
	}

	public function validateToIdentify(): void {
	}

	protected function throwIfFileNotFound(): void {
		$signRequest = $this->identifyService->getSignRequestMapper()->getById($this->getEntity()->getSignRequestId());
		$fileEntity = $this->identifyService->getFileMapper()->getById($signRequest->getFileId());

		$nodeId = $fileEntity->getNodeId();

		$mountsContainingFile = $this->identifyService->getUserMountCache()->getMountsForFileId($nodeId);
		foreach ($mountsContainingFile as $fileInfo) {
			$this->identifyService->getRootFolder()->getByIdInPath($nodeId, $fileInfo->getMountPoint());
		}
		$fileToSign = $this->identifyService->getRootFolder()->getById($nodeId);
		if (count($fileToSign) < 1) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->identifyService->getL10n()->t('File not found')],
			]));
		}
	}

	protected function throwIfMaximumValidityExpired(): void {
		$maximumValidity = (int) $this->identifyService->getAppConfig()->getAppValue('maximum_validity', (string) SessionService::NO_MAXIMUM_VALIDITY);
		if ($maximumValidity <= 0) {
			return;
		}
		$signRequest = $this->identifyService->getSignRequestMapper()->getById($this->getEntity()->getSignRequestId());
		$now = $this->identifyService->getTimeFactory()->getTime();
		if ($signRequest->getCreatedAt() + $maximumValidity < $now) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->identifyService->getL10n()->t('Link expired.')],
			]));
		}
	}

	protected function throwIfInvalidToken(): void {
		if (empty($this->codeSentByUser)) {
			return;
		}
		if (!$this->identifyService->getHasher()->verify($this->codeSentByUser, $this->getEntity()->getCode())) {
			throw new LibresignException($this->identifyService->getL10n()->t('Invalid code.'));
		}
	}

	protected function renewSession(): void {
		$this->identifyService->getSessionService()->setIdentifyMethodId($this->getEntity()->getId());
		$renewalInterval = (int) $this->identifyService->getAppConfig()->getAppValue('renewal_interval', (string) SessionService::NO_RENEWAL_INTERVAL);
		if ($renewalInterval <= 0) {
			return;
		}
		$this->identifyService->getSessionService()->resetDurationOfSignPage();
	}

	protected function updateIdentifiedAt(): void {
		if ($this->getEntity()->getCode() && !$this->getEntity()->getIdentifiedAtDate()) {
			return;
		}
		$this->getEntity()->setIdentifiedAtDate($this->identifyService->getTimeFactory()->getDateTime());
		$this->willNotify = false;
		$this->identifyService->save($this->getEntity());
		$this->notify();
	}

	protected function throwIfRenewalIntervalExpired(): void {
		$renewalInterval = (int) $this->identifyService->getAppConfig()->getAppValue('renewal_interval', (string) SessionService::NO_RENEWAL_INTERVAL);
		if ($renewalInterval <= 0) {
			return;
		}
		$signRequest = $this->identifyService->getSignRequestMapper()->getById($this->getEntity()->getSignRequestId());
		$startTime = $this->identifyService->getSessionService()->getSignStartTime();
		$createdAt = $signRequest->getCreatedAt();
		$lastAttempt = $this->getEntity()->getLastAttemptDate()?->format('U');
		$lastActionDate = max(
			$startTime,
			$createdAt,
			$lastAttempt,
		);
		$now = $this->identifyService->getTimeFactory()->getTime();
		$this->identifyService->getLogger()->debug('AbstractIdentifyMethod::throwIfRenewalIntervalExpired Times', [
			'renewalInterval' => $renewalInterval,
			'startTime' => $startTime,
			'createdAt' => $createdAt,
			'lastAttempt' => $lastAttempt,
			'lastActionDate' => $lastActionDate,
			'now' => $now,
		]);
		if ($lastActionDate + $renewalInterval < $now) {
			$this->identifyService->getLogger()->debug('AbstractIdentifyMethod::throwIfRenewalIntervalExpired Exception');
			$blur = new Blur($this->getEntity()->getIdentifierValue());
			throw new LibresignException(json_encode([
				'action' => $this->getRenewAction(),
				// TRANSLATORS title that is displayed at screen to notify the signer that the link to sign the document expired
				'title' => $this->identifyService->getL10n()->t('Link expired'),
				'body' => $this->identifyService->getL10n()->t(<<<'BODY'
					The link to sign the document has expired.
					We will send a new link to the email %1$s.
					Click below to receive the new link and be able to sign the document.
					BODY,
					[$blur->make()]
				),
				'uuid' => $signRequest->getUuid(),
				// TRANSLATORS Button to renew the link to sign the document. Renew is the action to generate a new sign link when the link expired.
				'renewButton' => $this->identifyService->getL10n()->t('Renew'),
			]));
		}
	}

	private function getRenewAction(): int {
		switch ($this->name) {
			case 'email':
				return JSActions::ACTION_RENEW_EMAIL;
		}
		throw new InvalidArgumentException('Invalid identify method name');
	}

	protected function throwIfAlreadySigned(): void {
		$signRequest = $this->identifyService->getSignRequestMapper()->getById($this->getEntity()->getSignRequestId());
		$fileEntity = $this->identifyService->getFileMapper()->getById($signRequest->getFileId());
		if ($fileEntity->getStatus() === FileEntity::STATUS_SIGNED
			|| (!is_null($signRequest) && $signRequest->getSigned())
		) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_REDIRECT,
				'errors' => [$this->identifyService->getL10n()->t('File already signed.')],
				'redirect' => $this->identifyService->getUrlGenerator()->linkToRoute(
					'libresign.page.validationFile',
					['uuid' => $signRequest->getUuid()]
				),
			]));
		}
	}

	protected function getSettingsFromDatabase(array $default = [], array $immutable = []): array {
		if ($this->settings) {
			return $this->settings;
		}
		$this->loadSavedSettings();
		$default = array_merge(
			[
				'name' => $this->name,
				'friendly_name' => $this->friendlyName,
				'enabled' => true,
				'mandatory' => true,
				'signatureMethods' => $this->signatureMethodsToArray(),
			],
			$default
		);
		$this->removeKeysThatDontExists($default);
		$this->overrideImmutable($immutable);
		$this->settings = $this->applyDefault($this->settings, $default);
		return $this->settings;
	}

	private function overrideImmutable(array $immutable): void {
		$this->settings = array_merge($this->settings, $immutable);
	}

	private function loadSavedSettings(): void {
		$config = $this->identifyService->getSavedSettings();
		$this->settings = array_reduce($config, function ($carry, $config) {
			if ($config['name'] === $this->name) {
				return $config;
			}
			return $carry;
		}, []);
		foreach ($this->availableSignatureMethods as $signatureMethodName) {
			$this->signatureMethods[$signatureMethodName] =
				$this->getEmptyInstanceOfSignatureMethodByName($signatureMethodName);
		}
		if (!isset($this->settings['signatureMethods']) || !is_array($this->settings['signatureMethods'])) {
			return;
		}
		foreach ($this->settings['signatureMethods'] as $signatureMethodName => $settings) {
			if (!in_array($signatureMethodName, $this->availableSignatureMethods)) {
				unset($this->settings['signatureMethods'][$signatureMethodName]);
				continue;
			}
			$signatureMethod = $this->getEmptyInstanceOfSignatureMethodByName($signatureMethodName);
			if (isset($settings['enabled']) && $settings['enabled']) {
				$signatureMethod->enable();
			}
			$this->signatureMethods[$signatureMethodName] = $signatureMethod;
		}
	}

	private function applyDefault(array $customConfig, array $default): array {
		foreach ($default as $key => $value) {
			if (!isset($customConfig[$key])) {
				$customConfig[$key] = $value;
			} elseif (gettype($value) !== gettype($customConfig[$key])) {
				$customConfig[$key] = $value;
			} elseif (gettype($value) === 'array') {
				$customConfig[$key] = $this->applyDefault($customConfig[$key], $value);
			}
		}
		return $customConfig;
	}

	public function save(): void {
		$this->identifyService->save($this->getEntity());
		$this->notify();
	}

	public function delete(): void {
		$this->identifyService->delete($this->getEntity());
	}

	private function removeKeysThatDontExists(array $default): void {
		$diff = array_diff_key($this->settings, $default);
		foreach (array_keys($diff) as $invalidKey) {
			unset($this->settings[$invalidKey]);
		}
	}

	public function validateToRenew(?IUser $user = null): void {
		$this->throwIfMaximumValidityExpired();
		$this->throwIfAlreadySigned();
		$this->throwIfFileNotFound();
	}
}
