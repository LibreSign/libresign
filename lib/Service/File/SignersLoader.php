<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use DateTimeInterface;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SubjectAlternativeNameService;
use OCP\Accounts\IAccountManager;
use OCP\IUserManager;

/**
 * Handles loading signer data for files
 */
class SignersLoader {
	private bool $signersLibreSignLoaded = false;
	private CertificateSignersMergeService $certificateSignersMergeService;

	public function __construct(
		private SignRequestMapper $signRequestMapper,
		private IdentifyMethodService $identifyMethodService,
		private SubjectAlternativeNameService $subjectAlternativeNameService,
		private IAccountManager $accountManager,
		private IUserManager $userManager,
	) {
		$this->certificateSignersMergeService = new CertificateSignersMergeService();
	}

	public function loadLibreSignSigners(
		?File $file,
		\stdClass $fileData,
		FileResponseOptions $options,
		array $certData = [],
	): void {
		if ($this->signersLibreSignLoaded || !$file) {
			return;
		}
		$signers = $this->signRequestMapper->getByFileId($file->getId());
		if (empty($signers)) {
			return;
		}
		$signRequestIds = array_column(array_map(fn ($s) => ['id' => $s->getId()], $signers), 'id');
		$identifyMethodsBatch = $this->identifyMethodService
			->setIsRequest(false)
			->getIdentifyMethodsFromSignRequestIds($signRequestIds);

		foreach ($signers as $signer) {
			$identifyMethods = $identifyMethodsBatch[$signer->getId()] ?? [];
			if (!empty($fileData->signers)) {
				$found = array_filter($fileData->signers, function (\stdClass $found) use ($identifyMethods) {
					if (!isset($found->uid)) {
						return false;
					}
					[$key, $value] = explode(':', (string)$found->uid);
					foreach ($identifyMethods as $methods) {
						foreach ($methods as $identifyMethod) {
							$entity = $identifyMethod->getEntity();
							if ($key === $entity->getIdentifierKey() && $value === $entity->getIdentifierValue()) {
								return true;
							}
						}
					}
					return false;
				});
				if (!empty($found)) {
					$index = key($found);
				} else {
					$index = count($fileData->signers);
				}
			} else {
				$index = 0;
			}
			if (!isset($fileData->signers[$index])) {
				$fileData->signers[$index] = new \stdClass();
			}
			$fileData->signers[$index]->signRequestId = $signer->getId();
			$fileData->signers[$index]->signed = $signer->getSigned()?->format(DateTimeInterface::ATOM);
			$fileData->signers[$index]->status = $signer->getStatus();
			$fileData->signers[$index]->statusText = $this->signRequestMapper->getTextOfSignerStatus($signer->getStatus());
			$fileData->signers[$index]->signingOrder = $signer->getSigningOrder();
			$fileData->signers[$index]->description = $signer->getDescription();
			$fileData->signers[$index]->displayName = $signer->getDisplayName();
			$fileData->signers[$index]->request_sign_date = $signer->getCreatedAt()->format(DateTimeInterface::ATOM);
			$fileData->signers[$index]->metadata = $signer->getMetadata();
			$fileData->signers[$index]->identifyMethods = [];
			$fileData->signers[$index]->visibleElements = [];
			foreach ($identifyMethods as $type => $methods) {
				foreach ($methods as $identifyMethod) {
					$entity = $identifyMethod->getEntity();

					$fileData->signers[$index]->identifyMethods[] = [
						'method' => $entity->getIdentifierKey(),
						'value' => $entity->getIdentifierValue(),
						'requirement' => $entity->getRequirement(),
					];

					switch ($type) {
						case 'account':
							$fileData->signers[$index]->uid = $entity->getUniqueIdentifier();
							$currentDisplayName = $fileData->signers[$index]->displayName ?? '';
							if ($currentDisplayName === '' || $currentDisplayName === $entity->getIdentifierValue()) {
								$user = $this->userManager->get($entity->getIdentifierValue());
								if ($user) {
									$fileData->signers[$index]->displayName = $user->getDisplayName();
								} else {
									$fileData->signers[$index]->displayName = $entity->getIdentifierValue();
								}
							}
							if (!isset($fileData->signers[$index]->email)) {
								$user = $this->userManager->get($entity->getIdentifierValue());
								if (!$user) {
									break;
								}
								$account = $this->accountManager->getAccount($user);
								$fileData->signers[$index]->email = $account->getProperty('email')->getValue();
							}
							break;
						case 'email':
							$fileData->signers[$index]->email = $entity->getIdentifierValue();
							$fileData->signers[$index]->uid = $entity->getUniqueIdentifier();
							if (!isset($fileData->signers[$index]->displayName)) {
								$fileData->signers[$index]->displayName = $entity->getIdentifierValue();
							}
							break;
						case 'signatureinit':
							$fileData->signers[$index]->signatureMethod = 'password';
							if (!isset($fileData->signers[$index]->email)) {
								$fileData->signers[$index]->email = '';
							}
							break;
						case 'password':
							$fileData->signers[$index]->signatureMethod = 'password';
							if (!isset($fileData->signers[$index]->email)) {
								$fileData->signers[$index]->email = '';
							}
							break;
					}
				}
			}
			if (isset($fileData->signers[$index]->uid)) {
				$split = explode(':', $fileData->signers[$index]->uid);
				$matches = [
					'key' => $split[0],
					'value' => $split[1],
				];
				if (str_ends_with($matches['value'], $options->getHost())) {
					$uid = str_replace('@' . $options->getHost(), '', $matches['value']);
					if (!isset($fileData->signers[$index]->displayName) || $fileData->signers[$index]->displayName === '') {
						$fileData->signers[$index]->displayName = $uid;
					}
					$fileData->signers[$index]->uid = 'account:' . $uid;
				}
			}
			$fileData->signers[$index]->me = false;
			if ($options->getMe() || $options->getIdentifyMethodId()) {
				$currentUserData = new \stdClass();
				$currentUserData->me = false;
				foreach ($identifyMethods as $methods) {
					foreach ($methods as $identifyMethod) {
						$entity = $identifyMethod->getEntity();
						if ($options->getIdentifyMethodId() === $entity->getId()
							|| $options->getMe()?->getUID() === $entity->getIdentifierValue()
							|| $options->getMe()?->getEMailAddress() === $entity->getIdentifierValue()
						) {
							$currentUserData->me = true;
							break 2;
						}
					}
				}
				$fileData->signers[$index]->me = $currentUserData->me;
			}

			if ($fileData->signers[$index]->me) {
				$fileData->signers[$index]->sign_request_uuid = $signer->getUuid();
				if (!$signer->getSigned() && isset($fileData->settings)) {
					$fileData->settings['canSign'] = true;
				}
				$fileData->signers[$index]->signatureMethods = [];
				foreach ($identifyMethods as $methods) {
					foreach ($methods as $identifyMethod) {
						$entity = $identifyMethod->getEntity();
						$this->identifyMethodService->setCurrentIdentifyMethod($entity);
						$identifyMethodInstance = $this->identifyMethodService
							->setIsRequest(false)
							->getInstanceOfIdentifyMethod(
								$entity->getIdentifierKey(),
								$entity->getIdentifierValue(),
							);
						$signatureMethods = $identifyMethodInstance->getSignatureMethods();
						foreach ($signatureMethods as $signatureMethod) {
							if (!$signatureMethod->isEnabled()) {
								continue;
							}
							$signatureMethod->setEntity($identifyMethodInstance->getEntity());
							$fileData->signers[$index]->signatureMethods[$signatureMethod->getName()] = $signatureMethod->toArray();
						}
					}
				}
			}
		}
		ksort($fileData->signers);
		$this->signersLibreSignLoaded = true;
	}

	public function loadSignersFromCertData(\stdClass $fileData, array $certData, string $host): void {
		$this->certificateSignersMergeService->merge(
			$fileData,
			$certData,
			$host,
			$this->signRequestMapper->getTextOfSignerStatus(2),
			fn (array $certData, string $currentHost): ?string => $this->identifyMethodService->resolveUid($certData, $currentHost),
			fn (string $method, string $value): string => $this->subjectAlternativeNameService->build($method, $value),
			function (string $accountId): ?string {
				$user = $this->userManager->get($accountId);
				return $user ? $user->getDisplayName() : null;
			},
		);
	}

	public function reset(): void {
		$this->signersLibreSignLoaded = false;
	}

}
