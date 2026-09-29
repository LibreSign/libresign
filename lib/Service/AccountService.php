<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use InvalidArgumentException;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\File\AccountSettingsProvider;
use OCA\Libresign\Service\Policy\RequestSignAuthorizationService;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\Files\File;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use Sabre\DAV\UUIDUtil;

class AccountService {
	public function __construct(
		private IL10N $l10n,
		private AccountFileService $accountFileService,
		private AccountCertificateService $accountCertificateService,
		private AccountSettingsProvider $accountSettingsProvider,
		private IUserManager $userManager,
		private IAppConfig $appConfig,
		private NewUserMailHelper $newUserMail,
		private IdentifyMethodService $identifyMethodService,
		private IdentifyMethodMapper $identifyMethodMapper,
		private IdDocsService $idDocsService,
		private SignerElementsService $signerElementsService,
		private RequestSignAuthorizationService $requestSignAuthorizationService,
	) {
	}

	public function validateCreateToSign(array $data): void {
		if (!UUIDUtil::validateUUID($data['uuid'])) {
			// TRANSLATORS Account setup error when the signature-request UUID used to create a guest account is invalid.
			throw new LibresignException($this->l10n->t('Invalid UUID'), 1);
		}
		try {
			$signRequest = $this->getSignRequestByUuid($data['uuid']);
		} catch (\Throwable) {
			// TRANSLATORS Account setup error when the signature-request UUID used to create a guest account cannot be found.
			throw new LibresignException($this->l10n->t('UUID not found'), 1);
		}
		$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
		if (!array_key_exists('identify', $data['user'])) {
			// TRANSLATORS Account setup error when the identification method linked to the signature request is invalid for account creation.
			throw new LibresignException($this->l10n->t('Invalid identification method'), 1);
		}
		foreach ($data['user']['identify'] as $method => $value) {
			if (!array_key_exists($method, $identifyMethods)) {
				// TRANSLATORS Account setup error when the identification method linked to the signature request is invalid for account creation.
				throw new LibresignException($this->l10n->t('Invalid identification method'), 1);
			}
			foreach ($identifyMethods[$method] as $identifyMethod) {
				$identifyMethod->validateToCreateAccount($value);
			}
		}
		if (empty($data['password'])) {
			// TRANSLATORS Account setup error when creating a guest account to sign and the Nextcloud password is missing.
			throw new LibresignException($this->l10n->t('Password is mandatory'), 1);
		}
		$file = $this->getFileByUuid($data['uuid']);
		if (empty($file['fileToSign'])) {
			// TRANSLATORS Account setup error when the document linked to the signature-request UUID cannot be found.
			throw new LibresignException($this->l10n->t('File not found'));
		}
	}

	public function getFileByUuid(string $uuid): array {
		return $this->accountFileService->getFileByUuid($uuid);
	}

	public function validateCertificateData(array $data): void {
		$this->accountCertificateService->validateCertificateData($data);
	}

	public function getSignRequestByUuid(string $uuid): SignRequest {
		return $this->accountFileService->getSignRequestByUuid($uuid);
	}

	public function createToSign(string $uuid, string $email, string $password, ?string $signPassword): void {
		$signRequest = $this->getSignRequestByUuid($uuid);

		$newUser = $this->userManager->createUser($email, $password);
		$newUser->setDisplayName($signRequest->getDisplayName());
		$newUser->setSystemEMailAddress($email);

		$this->updateIdentifyMethodToAccount($signRequest->getId(), $email, $newUser->getUID());

		if ($this->appConfig->getValueString('core', 'newUser.sendEmail', 'yes') === 'yes') {
			try {
				$emailTemplate = $this->newUserMail->generateTemplate($newUser, false);
				$this->newUserMail->sendMail($newUser, $emailTemplate);
			} catch (\Exception) {
				throw new LibresignException('Unable to send the invitation', 1);
			}
		}

		if ($signPassword) {
			$this->accountCertificateService->createForUser($newUser, $signPassword);
		}
	}

	public function getCertificateEngineName(): string {
		return $this->accountCertificateService->getCertificateEngineName();
	}

	public function isSetupOk(): bool {
		return $this->accountCertificateService->isSetupOk();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getConfig(?IUser $user = null): array {
		return $this->accountSettingsProvider->getConfig($user);
	}

	public function getConfigFilters(?IUser $user = null): array {
		return $this->accountSettingsProvider->getConfigFilters($user);
	}

	public function getConfigSorting(?IUser $user = null): array {
		return $this->accountSettingsProvider->getConfigSorting($user);
	}

	private function updateIdentifyMethodToAccount(int $signRequestId, string $email, string $uid): void {
		$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequestId);
		foreach ($identifyMethods as $name => $methods) {
			if ($name === IdentifyMethodService::IDENTIFY_EMAIL) {
				foreach ($methods as $identifyMethod) {
					$entity = $identifyMethod->getEntity();
					if ($entity->getIdentifierValue() === $email) {
						$entity->setIdentifierKey(IdentifyMethodService::IDENTIFY_ACCOUNT);
						$entity->setIdentifierValue($uid);
						$this->identifyMethodMapper->update($entity);
					}
				}
			}
		}
	}

	private function getPhoneNumber(?IUser $user): string {
		return $this->accountSettingsProvider->getPhoneNumber($user);
	}

	public function hasSignatureFile(?IUser $user = null): bool {
		return $this->accountSettingsProvider->hasSignatureFile($user);
	}

	public function getPdfByUuid(string $uuid): File {
		return $this->accountFileService->getPdfByUuid($uuid);
	}

	public function getFileByNodeId(int $nodeId): File {
		return $this->accountFileService->getFileByNodeId($nodeId);
	}

	public function canRequestSign(?IUser $user = null): bool {
		return $this->requestSignAuthorizationService->canRequestSign($user);
	}

	public function getSettings(?IUser $user = null): array {
		$return['canRequestSign'] = $this->canRequestSign($user);
		$return['hasSignatureFile'] = $this->hasSignatureFile($user);
		$return['phoneNumber'] = $this->getPhoneNumber($user);
		return $return;
	}

	public function addFilesToAccount(array $files, IUser $user): void {
		$this->idDocsService->addIdDocs($files, $user);
	}

	public function deleteFileFromAccount(int $nodeId, IUser $user): void {
		$this->idDocsService->deleteIdDoc($nodeId, $user);
	}

	public function saveVisibleElements(array $elements, string $sessionId, ?IUser $user): void {
		$this->signerElementsService->saveVisibleElements($elements, $sessionId, $user);
	}

	public function saveVisibleElement(array $data, string $sessionId, ?IUser $user): void {
		$this->signerElementsService->saveVisibleElement($data, $sessionId, $user);
	}

	public function deleteSignatureElement(?IUser $user, string $sessionId, int $nodeId): void {
		$this->signerElementsService->deleteSignatureElement($user, $sessionId, $nodeId);
	}

	/**
	 * @throws LibresignException at savePfx
	 * @throws InvalidArgumentException
	 */
	public function uploadPfx(array $file, IUser $user): void {
		$this->accountCertificateService->uploadPfx($file, $user);
	}

	public function deletePfx(IUser $user): void {
		$this->accountCertificateService->deletePfx($user);
	}

	/**
	 * @throws LibresignException when have not a certificate file
	 */
	public function updatePfxPassword(IUser $user, string $current, string $new): void {
		$this->accountCertificateService->updatePfxPassword($user, $current, $new);
	}

	/**
	 * @throws LibresignException when have not a certificate file
	 */
	public function readPfxData(IUser $user, string $password): array {
		return $this->accountCertificateService->readPfxData($user, $password);
	}
}
