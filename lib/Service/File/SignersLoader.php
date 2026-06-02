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
use OCA\Libresign\Service\SubjectAlternativeNameService;
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
		private SubjectAlternativeNameService $subjectAlternativeNameService,
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
					$index = count($fileData->signers);
				}
			} else {
				$index = 0;
			}
			if (!isset($fileData->signers[$index])) {
				$fileData->signers[$index] = new stdClass();
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
						'mandatory' => $entity->getMandatory(),
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
				$currentUserData = new stdClass();
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
				$fileData->signers[$index]->sign_uuid = $signer->getUuid();
				if (!$signer->getSigned() && isset($fileData->settings)) {
					$fileData->settings['canSign'] = true;
					$fileData->settings['signerFileUuid'] = $signer->getUuid();
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

	public function loadSignersFromCertData(stdClass $fileData, array $certData, string $host): void {
		$existingSigners = $fileData->signers ?? [];
		$indexMap = $this->buildSignerIndexMap($existingSigners);
		$usedIndexes = [];

		foreach ($certData as $index => $signer) {
			$targetIndex = $index;
			$isLibreSignMatch = false;

			$resolvedUid = $this->tryMatchWithExistingSigners($signer['chain'][0], $existingSigners, $host);
			if (!$resolvedUid) {
				$isLibreSignCert = isset($signer['chain'][0]['isLibreSignRootCA'])
					&& $signer['chain'][0]['isLibreSignRootCA'] === true;
				if ($isLibreSignCert) {
					$certUid = $signer['chain'][0]['subject']['UID'] ?? null;
					if ($certUid) {
						$resolvedUid = str_contains($certUid, ':') ? $certUid : 'account:' . $certUid;
					} else {
						$resolvedUid = null;
					}
				} else {
					$resolvedUid = $signer['uid'] ?? null;
					if (!$resolvedUid && isset($signer['chain'][0])) {
						$resolvedUid = $this->identifyMethodService->resolveUid($signer['chain'][0], $host);
					}
				}
			}

			$matchedIndex = $this->findMatchingSignerIndex($indexMap, $resolvedUid, $signer);
			if ($matchedIndex !== null) {
				$targetIndex = $matchedIndex;
				$isLibreSignMatch = isset($existingSigners[$matchedIndex]->signRequestId);
			} else {
				if (!empty($existingSigners)) {
					$targetIndex = $this->nextAvailableSignerIndex($existingSigners, $usedIndexes);
				}
			}
			$usedIndexes[$targetIndex] = true;

			if (!isset($fileData->signers[$targetIndex])) {
				$fileData->signers[$targetIndex] = new stdClass();
			}

			$preservedDisplayName = $isLibreSignMatch && isset($fileData->signers[$targetIndex]->displayName)
				? $fileData->signers[$targetIndex]->displayName
				: null;

			$fileData->signers[$targetIndex]->status = 2;
			$fileData->signers[$targetIndex]->statusText = $this->signRequestMapper->getTextOfSignerStatus(2);

			if (isset($signer['timestamp'])) {
				$fileData->signers[$targetIndex]->timestamp = $signer['timestamp'];
				if (isset($signer['timestamp']['genTime']) && $signer['timestamp']['genTime'] instanceof DateTimeInterface) {
					$fileData->signers[$targetIndex]->timestamp['genTime'] = $signer['timestamp']['genTime']->format(DateTimeInterface::ATOM);
				}
			}
			if (isset($signer['signingTime']) && $signer['signingTime'] instanceof DateTimeInterface) {
				$fileData->signers[$targetIndex]->signingTime = $signer['signingTime'];
				$fileData->signers[$targetIndex]->signed = $signer['signingTime']->format(DateTimeInterface::ATOM);
			}
			if (isset($signer['docmdp'])) {
				$fileData->signers[$targetIndex]->docmdp = $signer['docmdp'];
			}
			if (isset($signer['docmdp_validation'])) {
				$fileData->signers[$targetIndex]->docmdp_validation = $signer['docmdp_validation'];
			}
			if (isset($signer['modifications'])) {
				$fileData->signers[$targetIndex]->modifications = $signer['modifications'];
			}
			if (isset($signer['modification_validation'])) {
				$fileData->signers[$targetIndex]->modification_validation = $signer['modification_validation'];
			}

			if (isset($signer['chain'])) {
				$this->processChainData($fileData->signers[$targetIndex], $signer['chain']);
			}

			if (isset($signer['uid'])) {
				$fileData->signers[$targetIndex]->uid = $signer['uid'];
			} elseif ($resolvedUid) {
				$fileData->signers[$targetIndex]->uid = $resolvedUid;
			} elseif (isset($signer['chain'][0])) {
				$fileData->signers[$targetIndex]->uid = $this->identifyMethodService->resolveUid($signer['chain'][0], $host);
			}

			if (isset($signer['signDate'])) {
				$fileData->signers[$targetIndex]->signDate = $signer['signDate'];
			}
			if (isset($signer['type'])) {
				$fileData->signers[$targetIndex]->type = $signer['type'];
			}

			if ($preservedDisplayName) {
				$fileData->signers[$targetIndex]->displayName = $preservedDisplayName;
			} elseif (isset($fileData->signers[$targetIndex]->uid) && str_starts_with($fileData->signers[$targetIndex]->uid, 'account:')) {
				$accountId = substr($fileData->signers[$targetIndex]->uid, strlen('account:'));
				$user = $this->userManager->get($accountId);
				if ($user) {
					$fileData->signers[$targetIndex]->displayName = $user->getDisplayName();
				} else {
					$fileData->signers[$targetIndex]->displayName = $accountId;
				}
			} elseif (!isset($fileData->signers[$targetIndex]->displayName) && isset($signer['chain'][0])) {
				$fileData->signers[$targetIndex]->displayName = $signer['chain'][0]['name'] ?? ($signer['chain'][0]['subject']['CN'] ?? '');
			}

			if (isset($fileData->signers[$targetIndex]->uid)) {
				$indexMap[strtolower((string)$fileData->signers[$targetIndex]->uid)] = $targetIndex;
			}
		}
	}

	private function buildSignerIndexMap(array $signers): array {
		$map = [];
		foreach ($signers as $index => $signer) {
			if (isset($signer->uid)) {
				$map[strtolower((string)$signer->uid)] = $index;
			}
			if (!empty($signer->identifyMethods)) {
				foreach ($signer->identifyMethods as $identifyMethod) {
					if (isset($identifyMethod['method']) && isset($identifyMethod['value'])) {
						$identifier = $this->subjectAlternativeNameService->build($identifyMethod['method'], $identifyMethod['value']);
						$map[strtolower($identifier)] = $index;
					}
				}
			}
		}
		return $map;
	}

	private function findMatchingSignerIndex(array $indexMap, ?string $resolvedUid, array $certSigner): ?int {
		$identifiers = [];
		if ($resolvedUid) {
			$identifiers[] = strtolower($resolvedUid);
		}
		if (!empty($certSigner['uid'])) {
			$identifiers[] = strtolower((string)$certSigner['uid']);
		}
		foreach ($identifiers as $identifier) {
			if (isset($indexMap[$identifier])) {
				return $indexMap[$identifier];
			}
		}
		return null;
	}

	private function nextAvailableSignerIndex(array $existingSigners, array $usedIndexes): int {
		$index = count($existingSigners);
		while (isset($existingSigners[$index]) || isset($usedIndexes[$index])) {
			$index++;
		}
		return $index;
	}

	public function reset(): void {
		$this->signersLibreSignLoaded = false;
	}

	private function processChainData(stdClass $signer, array $chain): void {
		$signer->chain = [];

		foreach ($chain as $chainIndex => $chainItem) {
			$chainArr = $chainItem;

			if (isset($chainItem['validFrom_time_t']) && is_numeric($chainItem['validFrom_time_t'])) {
				$chainArr['valid_from'] = (new DateTime('@' . $chainItem['validFrom_time_t'], new \DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
			}
			if (isset($chainItem['validTo_time_t']) && is_numeric($chainItem['validTo_time_t'])) {
				$chainArr['valid_to'] = (new DateTime('@' . $chainItem['validTo_time_t'], new \DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
			}

			$chainArr['displayName'] = $chainArr['name'] ?? ($chainArr['subject']['CN'] ?? '');
			$signer->chain[$chainIndex] = $chainArr;
		}

		if (isset($chain[0])) {
			$this->enrichSignerWithCertificateValidation($signer, $chain[0]);
		}
	}

	private function enrichSignerWithCertificateValidation(stdClass $signer, array $endEntityCert): void {
		if (isset($endEntityCert['name']) && !isset($signer->name)) {
			$signer->name = $endEntityCert['name'];
		}
		if (isset($endEntityCert['hash']) && !isset($signer->hash)) {
			$signer->hash = $endEntityCert['hash'];
		}
		if (isset($endEntityCert['serialNumber']) && !isset($signer->serialNumber)) {
			$signer->serialNumber = $endEntityCert['serialNumber'];
		}
		if (isset($endEntityCert['serialNumberHex']) && !isset($signer->serialNumberHex)) {
			$signer->serialNumberHex = $endEntityCert['serialNumberHex'];
		}
		if (isset($endEntityCert['signatureTypeSN']) && !isset($signer->signatureTypeSN)) {
			$signer->signatureTypeSN = $endEntityCert['signatureTypeSN'];
		}

		if (isset($endEntityCert['subject']) && !isset($signer->subject)) {
			$signer->subject = $endEntityCert['subject'];
		}

		if (isset($endEntityCert['crl_urls']) && !isset($signer->crl_urls)) {
			$signer->crl_urls = $endEntityCert['crl_urls'];
		}
		if (isset($endEntityCert['crl_validation']) && !isset($signer->crl_validation)) {
			$signer->crl_validation = $endEntityCert['crl_validation'];
		}
		if (isset($endEntityCert['crl_revoked_at']) && !isset($signer->crl_revoked_at)) {
			$signer->crl_revoked_at = $endEntityCert['crl_revoked_at'];
		}

		if (isset($endEntityCert['signature_validation']) && !isset($signer->signature_validation)) {
			$signer->signature_validation = $endEntityCert['signature_validation'];
		}

		if (isset($endEntityCert['isLibreSignRootCA']) && !isset($signer->isLibreSignRootCA)) {
			$signer->isLibreSignRootCA = $endEntityCert['isLibreSignRootCA'];
		}
	}

	private function tryMatchWithExistingSigners(array $certData, array $existingSigners, string $host): ?string {
		if (empty($existingSigners)) {
			return null;
		}

		$certSerialNumber = $certData['serialNumber'] ?? null;
		$certSerialNumberHex = $certData['serialNumberHex'] ?? null;
		$certHash = $certData['hash'] ?? null;

		if (!$certSerialNumber && !$certSerialNumberHex && !$certHash) {
			return null;
		}

		foreach ($existingSigners as $signer) {
			if (!isset($signer->metadata) || !is_array($signer->metadata)) {
				continue;
			}

			$certInfo = $signer->metadata['certificate_info'] ?? null;
			if (!is_array($certInfo)) {
				continue;
			}

			if ($certSerialNumber && isset($certInfo['serialNumber'])) {
				if ($certSerialNumber === $certInfo['serialNumber']) {
					return $signer->uid ?? $this->identifyMethodService->resolveUid($certData, $host);
				}
			}

			if ($certSerialNumberHex && isset($certInfo['serialNumberHex'])) {
				if ($certSerialNumberHex === $certInfo['serialNumberHex']) {
					return $signer->uid ?? $this->identifyMethodService->resolveUid($certData, $host);
				}
			}

			if ($certHash && isset($certInfo['hash'])) {
				if ($certHash === $certInfo['hash']) {
					return $signer->uid ?? $this->identifyMethodService->resolveUid($certData, $host);
				}
			}
		}

		return null;
	}

}
