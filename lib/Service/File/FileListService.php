<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use DateTimeInterface;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;

/**
 * Service for formatting file list responses with visible elements.
 */
class FileListService {
	public function __construct(
		private SignRequestMapper $signRequestMapper,
		private IdentifyMethodService $identifyMethodService,
		private FileMapper $fileMapper,
		private IURLGenerator $urlGenerator,
		private IAppConfig $appConfig,
		private IL10N $l10n,
	) {
	}

	/**
	 * @return array[]
	 *
	 * @psalm-return array{data: array, pagination: array}
	 */
	public function listAssociatedFilesOfSignFlow(
		IUser $user,
		$page = null,
		$length = null,
		array $filter = [],
		array $sort = [],
	): array {
		$page ??= 1;
		$length ??= (int)$this->appConfig->getValueInt(Application::APP_ID, 'length_of_page', 100);

		$return = $this->signRequestMapper->getFilesAssociatedFilesWithMeFormatted(
			$user,
			$filter,
			$page,
			$length,
			$sort,
		);

		$signers = $this->signRequestMapper->getByMultipleFileId(array_column($return['data'], 'fileId'));
		$identifyMethods = $this->signRequestMapper->getIdentifyMethodsFromSigners($signers);
		$visibleElements = $this->signRequestMapper->getVisibleElementsFromSigners($signers);
		$return['data'] = $this->associateAllAndFormat($user, $return['data'], $signers, $identifyMethods, $visibleElements);

		$return['pagination']->setRouteName('ocs.libresign.File.list');
		return [
			'data' => $return['data'],
			'pagination' => $return['pagination']->getPagination($page, $length, $filter)
		];
	}

	private function associateAllAndFormat(IUser $user, array $files, array $signers, array $identifyMethods, array $visibleElements): array {
		foreach ($files as $key => $file) {
			$totalSigned = 0;
			foreach ($signers as $signerKey => $signer) {
				if ($signer->getFileId() === $file['fileId']) {
					/** @var array<IdentifyMethod> */
					$identifyMethodsOfSigner = $identifyMethods[$signer->getId()] ?? [];
					$data = [
						'email' => array_reduce($identifyMethodsOfSigner, function (string $carry, IdentifyMethod $identifyMethod): string {
							if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL) {
								return $identifyMethod->getIdentifierValue();
							}
							if (filter_var($identifyMethod->getIdentifierValue(), FILTER_VALIDATE_EMAIL)) {
								return $identifyMethod->getIdentifierValue();
							}
							return $carry;
						}, ''),
						'description' => $signer->getDescription(),
						'displayName'
							=> array_reduce($identifyMethodsOfSigner, function (string $carry, IdentifyMethod $identifyMethod): string {
								if (!$carry && $identifyMethod->getMandatory()) {
									return $identifyMethod->getIdentifierValue();
								}
								return $carry;
							}, $signer->getDisplayName()),
						'request_sign_date' => $signer->getCreatedAt()->format(DateTimeInterface::ATOM),
						'signed' => null,
						'signRequestId' => $signer->getId(),
						'signingOrder' => $signer->getSigningOrder(),
						'status' => $signer->getStatus(),
						'statusText' => $this->signRequestMapper->getTextOfSignerStatus($signer->getStatus()),
						'me' => array_reduce($identifyMethodsOfSigner, function (bool $carry, IdentifyMethod $identifyMethod) use ($user): bool {
							if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_ACCOUNT) {
								if ($user->getUID() === $identifyMethod->getIdentifierValue()) {
									return true;
								}
							} elseif ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL) {
								if (!$user->getEMailAddress()) {
									return false;
								}
								if ($user->getEMailAddress() === $identifyMethod->getIdentifierValue()) {
									return true;
								}
							}
							return $carry;
						}, false),
						'visibleElements' => $this->formatVisibleElements(
							$visibleElements[$signer->getId()] ?? [],
							!empty($file['metadata'])?json_decode((string)$file['metadata'], true):[],
							$file['uuid'],
						),
						'identifyMethods' => array_map(fn (IdentifyMethod $identifyMethod): array => [
							'method' => $identifyMethod->getIdentifierKey(),
							'value' => $identifyMethod->getIdentifierValue(),
							'mandatory' => $identifyMethod->getMandatory(),
						], array_values($identifyMethodsOfSigner)),
					];

					if ($data['me']) {
						$temp = array_map(function (IdentifyMethod $identifyMethodEntity) use ($signer): array {
							$this->identifyMethodService->setCurrentIdentifyMethod($identifyMethodEntity);
							$identifyMethod = $this->identifyMethodService
								->setIsRequest(false)
								->getInstanceOfIdentifyMethod(
									$identifyMethodEntity->getIdentifierKey(),
									$identifyMethodEntity->getIdentifierValue(),
								);
							$signatureMethods = $identifyMethod->getSignatureMethods();
							$return = [];
							foreach ($signatureMethods as $signatureMethod) {
								if (!$signatureMethod->isEnabled()) {
									continue;
								}
								$signatureMethod->setEntity($identifyMethod->getEntity());
								$return[$signatureMethod->getName()] = $signatureMethod->toArray();
							}
							return $return;
						}, array_values($identifyMethodsOfSigner));
						$data['signatureMethods'] = [];
						foreach ($temp as $methods) {
							$data['signatureMethods'] = array_merge($data['signatureMethods'], $methods);
						}
						$data['sign_uuid'] = $signer->getUuid();
						$files[$key]['url'] = $this->urlGenerator->linkToRoute('libresign.page.getPdfFile', ['uuid' => $signer->getuuid()]);
					}

					if ($signer->getSigned()) {
						$data['signed'] = $signer->getSigned()->format(DateTimeInterface::ATOM);
						$totalSigned++;
					}
					ksort($data);
					$files[$key]['signers'][] = $data;
					unset($signers[$signerKey]);
				}
			}
			if (empty($files[$key]['signers'])) {
				$files[$key]['signers'] = [];
				$files[$key]['statusText'] = $this->l10n->t('no signers');
			} else {
				usort($files[$key]['signers'], function ($a, $b) {
					$orderA = $a['signingOrder'] ?? PHP_INT_MAX;
					$orderB = $b['signingOrder'] ?? PHP_INT_MAX;

					if ($orderA !== $orderB) {
						return $orderA <=> $orderB;
					}

					return ($a['signRequestId'] ?? 0) <=> ($b['signRequestId'] ?? 0);
				});

				$files[$key]['statusText'] = $this->fileMapper->getTextOfStatus((int)$files[$key]['status']);
			}
			unset($files[$key]['id'], $files[$key]['fileId']);
			ksort($files[$key]);
		}
		return $files;
	}

	/**
	 * Format visible elements for file list response.
	 *
	 * @param FileElement[] $visibleElements Array of FileElement objects
	 * @param array $metadata File metadata containing page dimensions
	 * @param string $uuid File UUID to include in response
	 * @return array Formatted visible elements
	 */
	public function formatVisibleElements(array $visibleElements, array $metadata, string $uuid): array {
		return array_map(function (FileElement $visibleElement) use ($metadata, $uuid) {
			$page = $visibleElement->getPage();
			$urx = (int)$visibleElement->getUrx();
			$ury = (int)$visibleElement->getUry();
			$llx = (int)$visibleElement->getLlx();
			$lly = (int)$visibleElement->getLly();

			$dimension = $metadata['d'][$page - 1];
			$height = abs($ury - $lly);
			$width = $urx - $llx;
			$top = (int)$dimension['h'] - $ury;
			$left = $llx;

			return [
				'elementId' => $visibleElement->getId(),
				'signRequestId' => $visibleElement->getSignRequestId(),
				'type' => $visibleElement->getType(),
				'uuid' => $uuid,
				'coordinates' => [
					'page' => $page,
					'urx' => $urx,
					'ury' => $ury,
					'llx' => $llx,
					'lly' => $lly,
					'left' => $left,
					'top' => $top,
					'width' => $width,
					'height' => $height,
				],
			];
		}, $visibleElements);
	}
}
