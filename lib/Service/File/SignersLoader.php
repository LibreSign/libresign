<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use DateTime;
use DateTimeInterface;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\Accounts\IAccountManager;
use OCP\IUserManager;
use stdClass;

/**
 * Handles loading signer data for files
 */
class SignersLoader {
	private bool $signersLibreSignLoaded = false;

	public function __construct(
		private SignRequestMapper $signRequestMapper,
		private IdentifyMethodService $identifyMethodService,
		private IAccountManager $accountManager,
		private IUserManager $userManager,
	) {
	}

	public function loadLibreSignSigners(
		?File $file,
		stdClass $fileData,
		FileResponseOptions $options,
		array $certData = [],
	): void {
		if ($this->signersLibreSignLoaded || !$file) {
			return;
		}
		$signers = $this->signRequestMapper->getByFileId($file->getId());
		foreach ($signers as $signer) {
			$identifyMethods = $this->identifyMethodService
				->setIsRequest(false)
				->getIdentifyMethodsFromSignRequestId($signer->getId());
			if (!empty($fileData->signers)) {
				$found = array_filter($fileData->signers, function (stdClass $found) use ($identifyMethods) {
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
					// Find next available index
					$index = count($fileData->signers);
				}
			} else {
				$index = 0;
			}
			if (!isset($fileData->signers[$index])) {
				$fileData->signers[$index] = new stdClass();
			}
			$fileData->signers[$index]->signRequestId = $signer->getId();
			$fileData->signers[$index]->signed = $signer->getSigned();
			$fileData->signers[$index]->status = $signer->getStatus();
			$fileData->signers[$index]->statusText = $this->signRequestMapper->getTextOfSignerStatus($signer->getStatus());
			$fileData->signers[$index]->signingOrder = $signer->getSigningOrder();
			$fileData->signers[$index]->description = $signer->getDescription();
			$fileData->signers[$index]->displayName = $signer->getDisplayName();
			$fileData->signers[$index]->request_sign_date = $signer->getCreatedAt()->format(DateTimeInterface::ATOM);
			$fileData->signers[$index]->identifyMethods = [];
			$fileData->signers[$index]->visibleElements = [];
			foreach ($identifyMethods as $type => $methods) {
				foreach ($methods as $identifyMethod) {
					$entity = $identifyMethod->getEntity();

					$fileData->signers[$index]->identifyMethods[] = [
						'method' => $entity->getIdentifierKey(),
						'value' => $entity->getIdentifierValue(),
						'mandatory' => $entity->getMandatory(),
					];

					switch ($type) {
						case 'account':
							$fileData->signers[$index]->displayName = $entity->getIdentifierValue();
							$fileData->signers[$index]->uid = $entity->getIdentifierKey() . ':' . $entity->getIdentifierValue();
							if (!isset($fileData->signers[$index]->email)) {
								$user = $this->userManager->get($entity->getIdentifierValue());
								if (!$user) {
									break;
								}
								$account = $this->accountManager->getAccount($user);
								$fileData->signers[$index]->email = $account->getProperty('email');
							}
							break;
						case 'email':
							$fileData->signers[$index]->email = $entity->getIdentifierValue();
							$fileData->signers[$index]->uid = $entity->getIdentifierKey() . ':' . $entity->getIdentifierValue();
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
					$fileData->signers[$index]->displayName = $uid;
					$fileData->signers[$index]->uid = 'account:' . $uid;
				}
			}
			$fileData->signers[$index]->me = false;
			if ($options->getMe() || $options->getIdentifyMethodId()) {
				$currentUserData = new stdClass();
				$currentUserData->me = false;
				if ($options->getMe()?->getUID() === $file->getUserId()) {
					$currentUserData->me = true;
				}
				if (!$currentUserData->me) {
					foreach ($identifyMethods as $methods) {
						foreach ($methods as $identifyMethod) {
							$entity = $identifyMethod->getEntity();
							if (!$currentUserData->me) {
								if ($options->getIdentifyMethodId() === $entity->getId()
									|| $options->getMe()?->getUID() === $entity->getIdentifierValue()
									|| $options->getMe()?->getEMailAddress() === $entity->getIdentifierValue()
								) {
									$currentUserData->me = true;
								}
							}
						}
					}
				}
				$fileData->signers[$index]->me = $currentUserData->me;
			}

			if ($fileData->signers[$index]->me) {
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

	public function loadSignersFromCertData(stdClass $fileData, array $certData, FileResponseOptions $options): void {
		foreach ($certData as $index => $signer) {
			if (!isset($fileData->signers[$index])) {
				$fileData->signers[$index] = new stdClass();
			}
			$fileData->signers[$index]->status = 2;
			$fileData->signers[$index]->statusText = $this->signRequestMapper->getTextOfSignerStatus(2);

			if (isset($signer['timestamp'])) {
				$fileData->signers[$index]->timestamp = $signer['timestamp'];
				if (isset($signer['timestamp']['genTime']) && $signer['timestamp']['genTime'] instanceof DateTimeInterface) {
					$fileData->signers[$index]->timestamp['genTime'] = $signer['timestamp']['genTime']->format(DateTimeInterface::ATOM);
				}
			}
			if (isset($signer['signingTime']) && $signer['signingTime'] instanceof DateTimeInterface) {
				$fileData->signers[$index]->signingTime = $signer['signingTime'];
				$fileData->signers[$index]->signed = $signer['signingTime']->format(DateTimeInterface::ATOM);
			}
			if (isset($signer['docmdp'])) {
				$fileData->signers[$index]->docmdp = $signer['docmdp'];
			}
			if (isset($signer['modifications'])) {
				$fileData->signers[$index]->modifications = $signer['modifications'];
			}
			if (isset($signer['modification_validation'])) {
				$fileData->signers[$index]->modification_validation = $signer['modification_validation'];
			}
			if (isset($signer['chain'])) {
				$fileData->signers[$index]->chain = [];
				foreach ($signer['chain'] as $chainIndex => $chainItem) {
					$chainArr = $chainItem;
					if (isset($chainItem['validFrom_time_t']) && is_numeric($chainItem['validFrom_time_t'])) {
						$chainArr['valid_from'] = (new DateTime('@' . $chainItem['validFrom_time_t'], new \DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
					}
					if (isset($chainItem['validTo_time_t']) && is_numeric($chainItem['validTo_time_t'])) {
						$chainArr['valid_to'] = (new DateTime('@' . $chainItem['validTo_time_t'], new \DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
					}
					$chainArr['displayName'] = $chainArr['name'] ?? ($chainArr['subject']['CN'] ?? '');
					$fileData->signers[$index]->chain[$chainIndex] = $chainArr;
					if ($chainIndex === 0) {
						foreach ($chainArr as $key => $value) {
							if (!isset($fileData->signers[$index]->$key)) {
								$fileData->signers[$index]->$key = $value;
							}
						}
						$fileData->signers[$index]->uid = $this->identifyMethodService->resolveUid($chainArr, $options->getHost());
					}
				}
			}
			if (isset($signer['uid'])) {
				$fileData->signers[$index]->uid = $signer['uid'];
			}
			if (isset($signer['signDate'])) {
				$fileData->signers[$index]->signDate = $signer['signDate'];
			}
			if (isset($signer['type'])) {
				$fileData->signers[$index]->type = $signer['type'];
			}
		}
	}

	public function reset(): void {
		$this->signersLibreSignLoaded = false;
	}

}
