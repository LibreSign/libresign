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

namespace OCA\Libresign\Service;

use InvalidArgumentException;
use OC\Files\Filesystem;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClientService;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type LibresignValidateFile from ResponseDefinitions
 */
class FileService {
	use TFile;

	private bool $showSigners = false;
	private bool $showSettings = false;
	private bool $showVisibleElements = false;
	private bool $showMessages = false;
	private bool $validateFile = false;
	private string $fileContent = '';
	private ?File $file = null;
	private ?SignRequest $signRequest = null;
	private ?IUser $me = null;
	private ?int $identifyMethodId = null;
	private array $certData = [];
	private array $signers = [];
	private array $settings = [
		'canSign' => false,
		'canRequestSign' => false,
		'signerFileUuid' => null,
		'phoneNumber' => '',
	];
	public const IDENTIFICATION_DOCUMENTS_DISABLED = 0;
	public const IDENTIFICATION_DOCUMENTS_NEED_SEND = 1;
	public const IDENTIFICATION_DOCUMENTS_NEED_APPROVAL = 2;
	public const IDENTIFICATION_DOCUMENTS_APPROVED = 3;
	public function __construct(
		protected FileMapper $fileMapper,
		protected SignRequestMapper $signRequestMapper,
		protected FileElementMapper $fileElementMapper,
		protected FileElementService $fileElementService,
		protected FolderService $folderService,
		protected ValidateHelper $validateHelper,
		protected PdfParserService $pdfParserService,
		private AccountService $accountService,
		private IdentifyMethodService $identifyMethodService,
		private IUserSession $userSession,
		private IUserManager $userManager,
		private IAccountManager $accountManager,
		protected IClientService $client,
		private IDateTimeFormatter $dateTimeFormatter,
		private IAppConfig $appConfig,
		private IURLGenerator $urlGenerator,
		protected IMimeTypeDetector $mimeTypeDetector,
		protected Pkcs12Handler $pkcs12Handler,
		private IUserMountCache $userMountCache,
		private IRootFolder $root,
		protected LoggerInterface $logger,
		protected IL10N $l10n,
	) {
	}

	/**
	 * @return static
	 */
	public function showSigners(bool $show = true): self {
		$this->showSigners = $show;
		return $this;
	}

	/**
	 * @return static
	 */
	public function showSettings(bool $show = true): self {
		$this->showSettings = $show;
		return $this;
	}

	/**
	 * @return static
	 */
	public function showVisibleElements(bool $show = true): self {
		$this->showVisibleElements = $show;
		return $this;
	}

	/**
	 * @return static
	 */
	public function showMessages(bool $show = true): self {
		$this->showMessages = $show;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setMe(?IUser $user): self {
		$this->me = $user;
		return $this;
	}

	public function setIdentifyMethodId(?int $id): self {
		$this->identifyMethodId = $id;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setFile(File $file): self {
		$this->file = $file;
		return $this;
	}

	public function setSignRequest(SignRequest $signRequest): self {
		$this->signRequest = $signRequest;
		return $this;
	}

	public function showValidateFile(bool $validateFile = true): self {
		$this->validateFile = $validateFile;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setFileByType(string $type, $identifier): self {
		try {
			/** @var File */
			$file = call_user_func(
				[$this->fileMapper, 'getBy' . $type],
				$identifier
			);
		} catch (\Throwable $th) {
			throw new LibresignException($this->l10n->t('Invalid data to validate file'), 404);
		}
		if (!$file) {
			throw new LibresignException($this->l10n->t('Invalid file identifier'), 404);
		}
		$this->setFile($file);
		return $this;
	}

	public function setFileFromRequest(?array $file): self {
		if ($file === null) {
			throw new InvalidArgumentException($this->l10n->t('No file provided'));
		}
		if (
			$file['error'] !== 0 ||
			!is_uploaded_file($file['tmp_name']) ||
			Filesystem::isFileBlacklisted($file['tmp_name'])
		) {
			throw new InvalidArgumentException($this->l10n->t('Invalid file provided'));
		}
		if ($file['size'] > \OCP\Util::uploadLimit()) {
			throw new InvalidArgumentException($this->l10n->t('File is too big'));
		}

		$this->fileContent = file_get_contents($file['tmp_name']);
		$mimeType = $this->mimeTypeDetector->detectString($this->fileContent);
		if ($mimeType !== 'application/pdf') {
			$this->fileContent = '';
			unlink($file['tmp_name']);
			throw new InvalidArgumentException($this->l10n->t('Invalid file provided'));
		}

		$memoryFile = fopen($file['tmp_name'], 'rb');
		$this->certData = $this->pkcs12Handler->validatePdfContent($memoryFile);
		fclose($memoryFile);
		unlink($file['tmp_name']);
		$hash = hash('sha256', $this->fileContent);
		try {
			$libresignFile = $this->fileMapper->getBySignedHash($hash);
			$this->setFile($libresignFile);
		} catch (DoesNotExistException $e) {
		}
		return $this;
	}

	private function getFile(): \OCP\Files\File {
		$nodeId = $this->file->getSignedNodeId();
		if (!$nodeId) {
			$nodeId = $this->file->getNodeId();
		}
		$mountsContainingFile = $this->userMountCache->getMountsForFileId($nodeId);
		foreach ($mountsContainingFile as $fileInfo) {
			$this->root->getByIdInPath($nodeId, $fileInfo->getMountPoint());
		}
		$fileToValidate = $this->root->getById($nodeId);
		if (!count($fileToValidate)) {
			throw new LibresignException($this->l10n->t('Invalid data to validate file'), 404);
		}
		/** @var \OCP\Files\File */
		return current($fileToValidate);
	}

	private function getFileMetadata(): array {
		if ($this->fileContent) {
			$content = $this->fileContent;
		} elseif ($this->file) {
			$content = $this->getFile()->getContent();
		}
		if (empty($content)) {
			return [];
		}
		$metadata = $this->pdfParserService
			->setFile($content)
			->toArray();
		return $metadata;
	}

	private function getCertData(): array {
		if (!empty($this->certData) || !$this->validateFile || !$this->file->getSignedNodeId()) {
			return $this->certData;
		}
		$file = $this->getFile();

		$resource = $file->fopen('rb');
		$this->certData = $this->pkcs12Handler->validatePdfContent($resource);
		fclose($resource);
		return $this->certData;
	}

	private function getSigners(): array {
		if ($this->signers) {
			return $this->signers;
		}
		$signers = $this->signRequestMapper->getByFileId($this->file->getId());
		$certData = $this->getCertData();
		foreach ($signers as $signer) {
			$signatureToShow = [
				'signed' => $signer->getSigned() ?
					$this->dateTimeFormatter->formatDateTime($signer->getSigned())
					: null,
				'displayName' => $signer->getDisplayName(),
				'me' => false,
				'signRequestId' => $signer->getId(),
				'description' => $signer->getDescription(),
				'identifyMethods' => $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signer->getId()),
				'visibleElements' => $this->getVisibleElements($signer->getId()),
				'request_sign_date' => (new \DateTime())
					->setTimestamp($signer->getCreatedAt())
					->format('Y-m-d H:i:s'),
			];
			$metadata = $signer->getMetadata();
			if (!empty($metadata['remote-address'])) {
				$signatureToShow['remote_address'] = $metadata['remote-address'];
			}
			if (!empty($metadata['user-agent'])) {
				$signatureToShow['user_agent'] = $metadata['user-agent'];
			}
			if (!empty($metadata['notify'])) {
				$signatureToShow['notify'] = $metadata['notify'];
			}
			if ($signer->getSigned()) {
				$data['sign_date'] = (new \DateTime())
					->setTimestamp($signer->getSigned())
					->format('Y-m-d H:i:s');
				$mySignature = array_filter($certData, function ($data) use ($signatureToShow) {
					foreach ($signatureToShow['identifyMethods'] as $methods) {
						foreach ($methods as $identifyMethod) {
							$entity = $identifyMethod->getEntity();
							if (array_key_exists('UID', $data['subject'])) {
								if ($data['subject']['UID'] === $entity->getIdentifierKey() . ':' . $entity->getIdentifierValue()) {
									return true;
								}
							} else {
								preg_match('/(?<key>.*):(?<value>.*), /', $data['subject']['CN'], $matches);
								if ($matches) {
									if ($matches['key'] === $entity->getIdentifierKey() && $matches['value'] === $entity->getIdentifierValue()) {
										return true;
									}
								}
							}
						}
					}
				});
				if ($mySignature) {
					$mySignature = current($mySignature);
					$signatureToShow['subject'] = implode(
						', ',
						array_map(
							fn (string $key, string $value) => "$key: $value",
							array_keys($mySignature['subject']),
							$mySignature['subject']
						)
					);
					$signatureToShow['valid_from'] = $mySignature['validFrom_time_t'];
					$signatureToShow['valid_to'] = $mySignature['validTo_time_t'];
				}
			}
			// @todo refactor this code
			if ($this->me || $this->identifyMethodId) {
				$signatureToShow['sign_uuid'] = $signer->getUuid();
				$identifyMethodServices = $signatureToShow['identifyMethods'];
				// Identifi if I'm file owner
				if ($this->me?->getUID() === $this->file->getUserId()) {
					$email = array_reduce($identifyMethodServices[IdentifyMethodService::IDENTIFY_EMAIL] ?? [], function (?string $carry, IIdentifyMethod $identifyMethod): ?string {
						if ($identifyMethod->getEntity()->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL) {
							$carry = $identifyMethod->getEntity()->getIdentifierValue();
						}
						return $carry;
					}, '');
					$signatureToShow['email'] = $email;
					$user = $this->userManager->getByEmail($email);
					if ($user && count($user) === 1) {
						$signatureToShow['userId'] = $user[0]->getUID();
					}
				}
				// Identify if I'm signer
				foreach ($identifyMethodServices as $methods) {
					foreach ($methods as $identifyMethod) {
						$entity = $identifyMethod->getEntity();
						if ($this->identifyMethodId === $entity->getId()
							|| $this->me?->getUID() === $entity->getIdentifierValue()
							|| $this->me?->getEMailAddress() === $entity->getIdentifierValue()
						) {
							$signatureToShow['me'] = true;
							if (!$signer->getSigned()) {
								$this->settings['canSign'] = true;
								$this->settings['signerFileUuid'] = $signer->getUuid();
							}
						}
					}
				}
			}
			if ($signatureToShow['me']) {
				$signatureToShow['signatureMethods'] = $this->identifyMethodService->getSignMethodsOfIdentifiedFactors($signer->getId());
			}
			$signatureToShow['identifyMethods'] = array_reduce($signatureToShow['identifyMethods'], function ($carry, $list) {
				foreach ($list as $identifyMethod) {
					$carry[] = [
						'method' => $identifyMethod->getEntity()->getIdentifierKey(),
						'value' => $identifyMethod->getEntity()->getIdentifierValue(),
						'mandatory' => $identifyMethod->getEntity()->getMandatory(),
					];
				}
				return $carry;
			}, []);
			ksort($signatureToShow);
			$this->signers[] = $signatureToShow;
		}
		return $this->signers;
	}

	/**
	 * @return (mixed|string)[][]
	 *
	 * @psalm-return list<array{url: string, resolution: mixed}>
	 */
	private function getPages(): array {
		$return = [];

		$metadata = $this->file->getMetadata();
		for ($page = 1; $page <= $metadata['p']; $page++) {
			$return[] = [
				'url' => $this->urlGenerator->linkToRoute('ocs.libresign.File.getPage', [
					'apiVersion' => 'v1',
					'uuid' => $this->file->getUuid(),
					'page' => $page,
				]),
				'resolution' => $metadata['d'][$page - 1]
			];
		}
		return $return;
	}

	private function getVisibleElements(int $signRequestId): array {
		$return = [];
		if (!$this->showVisibleElements) {
			return $return;
		}
		try {
			$visibleElements = $this->fileElementMapper->getByFileIdAndSignRequestId($this->file->getId(), $signRequestId);
			foreach ($visibleElements as $visibleElement) {
				$element = [
					'elementId' => $visibleElement->getId(),
					'signRequestId' => $visibleElement->getSignRequestId(),
					'type' => $visibleElement->getType(),
					'coordinates' => [
						'page' => $visibleElement->getPage(),
						'urx' => $visibleElement->getUrx(),
						'ury' => $visibleElement->getUry(),
						'llx' => $visibleElement->getLlx(),
						'lly' => $visibleElement->getLly()
					]
				];
				$element['coordinates'] = array_merge(
					$element['coordinates'],
					$this->fileElementService->translateCoordinatesFromInternalNotation($element, $this->file)
				);
				$return[] = $element;
			}
		} catch (\Throwable $th) {
		}
		return $return;
	}

	private function getPhoneNumber(): string {
		if (!$this->me) {
			return '';
		}
		$userAccount = $this->accountManager->getAccount($this->me);
		return $userAccount->getProperty(IAccountManager::PROPERTY_PHONE)->getValue();
	}

	private function getSettings(): array {
		if ($this->me) {
			$this->settings = array_merge($this->settings, $this->accountService->getSettings($this->me));
			$this->settings['phoneNumber'] = $this->getPhoneNumber();
			$status = $this->getIdentificationDocumentsStatus($this->me->getUID());
			if ($status === self::IDENTIFICATION_DOCUMENTS_NEED_SEND) {
				$this->settings['needIdentificationDocuments'] = true;
				$this->settings['identificationDocumentsWaitingApproval'] = false;
			} elseif ($status === self::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL) {
				$this->settings['needIdentificationDocuments'] = true;
				$this->settings['identificationDocumentsWaitingApproval'] = true;
			}
		}
		return $this->settings;
	}

	public function getIdentificationDocumentsStatus(?string $userId): int {
		if (!$this->appConfig->getAppValue('identification_documents', '')) {
			return self::IDENTIFICATION_DOCUMENTS_DISABLED;
		}

		if (!empty($userId)) {
			$files = $this->fileMapper->getFilesOfAccount($userId);
		}
		if (empty($files) || !count($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_SEND;
		}
		$deleted = array_filter($files, function (File $file) {
			return $file->getStatus() === File::STATUS_DELETED;
		});
		if (count($deleted) === count($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_SEND;
		}

		$signed = array_filter($files, function (File $file) {
			return $file->getStatus() === File::STATUS_SIGNED;
		});
		if (count($signed) !== count($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL;
		}

		return self::IDENTIFICATION_DOCUMENTS_APPROVED;
	}

	private function getFileToArray(): array {
		$return = [];
		if ($this->fileContent) {
			return $this->getBinaryFileToArray();
		}
		if (!$metadata = $this->getFileMetadata()) {
			return $return;
		}
		$return['totalPages'] = $metadata['p'];
		if (!$this->file) {
			return array_merge(
				$return,
				$this->getBinaryFileToArray(),
			);
		}
		$return['uuid'] = $this->file->getUuid();
		$return['name'] = $this->file->getName();
		$return['status'] = $this->file->getStatus();
		$return['created_at'] = (new \DateTime())
			->setTimestamp($this->file->getCreatedAt())
			->format('Y-m-d H:i:s');
		$return['statusText'] = $this->fileMapper->getTextOfStatus($this->file->getStatus());
		$return['nodeId'] = $this->file->getNodeId();

		$return['requested_by'] = [
			'userId' => $this->file->getUserId(),
			'displayName' => $this->userManager->get($this->file->getUserId())->getDisplayName(),
		];
		$return['file'] = $this->urlGenerator->linkToRoute('libresign.page.getPdf', ['uuid' => $this->file->getUuid()]);
		if ($this->showVisibleElements) {
			$signers = $this->signRequestMapper->getByMultipleFileId([$this->file->getId()]);
			$return['visibleElements'] = [];
			foreach ($this->signRequestMapper->getVisibleElementsFromSigners($signers) as $visibleElements) {
				$return['visibleElements'] = array_merge(
					$this->formatVisibleElementsToArray(
						$visibleElements,
						$this->file->getMetadata()
					),
					$return['visibleElements']
				);
			}
		}
		foreach ($this->getSigners() as $signer) {
			if ($signer['me']) {
				$return['url'] = $this->urlGenerator->linkToRoute('libresign.page.getPdfFile', ['uuid' => $signer['sign_uuid']]);
				break;
			}
		}
		if ($this->showSigners) {
			$return['signers'] = $this->getSigners();
		}
		ksort($return);
		return $return;
	}

	private function getBinaryFileToArray(): array {
		if (!$this->fileContent) {
			return [];
		}
		$return = [];
		$return['status'] = $this->certData ? File::STATUS_SIGNED : File::STATUS_NOT_LIBRESIGN_FILE;
		return $return;
	}

	/**
	 * @return string[][]
	 *
	 * @psalm-return list<array{type: 'info', message: string}>
	 */
	private function getMessages(): array {
		$messages = [];
		if ($this->settings['canSign']) {
			$messages[] = [
				'type' => 'info',
				'message' => $this->l10n->t('You need to sign this document')
			];
		}
		if (!$this->settings['canRequestSign'] && empty($this->signers)) {
			$messages[] = [
				'type' => 'info',
				'message' => $this->l10n->t('You cannot request signature for this document, please contact your administrator')
			];
		}
		return $messages;
	}

	/**
	 * @return LibresignValidateFile
	 */
	public function toArray(): array {
		$return = $this->getFileToArray();
		if ($this->showSettings) {
			$return['settings'] = $this->getSettings();
		}
		if ($this->showMessages) {
			$messages = $this->getMessages();
			if ($messages) {
				$return['messages'] = $messages;
			}
		}
		return $return;
	}

	public function setFileByPath(string $path): self {
		$node = $this->folderService->getFileByPath($path);
		$this->setFileByType('FileId', $node->getId());
		return $this;
	}

	/**
	 * @return array[]
	 *
	 * @psalm-return array{data: array, pagination: array}
	 */
	public function listAssociatedFilesOfSignFlow(
		$page = null,
		$length = null,
		array $filter = [],
		array $sort = [],
	): array {
		$page = $page ?? 1;
		$length = $length ?? (int)$this->appConfig->getAppValue('length_of_page', '100');

		$return = $this->signRequestMapper->getFilesAssociatedFilesWithMeFormatted(
			$this->me,
			$filter,
			$page,
			$length,
			$sort,
		);

		$signers = $this->signRequestMapper->getByMultipleFileId(array_column($return['data'], 'id'));
		$identifyMethods = $this->signRequestMapper->getIdentifyMethodsFromSigners($signers);
		$visibleElements = $this->signRequestMapper->getVisibleElementsFromSigners($signers);
		$return['data'] = $this->associateAllAndFormat($this->me, $return['data'], $signers, $identifyMethods, $visibleElements);

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
				if ($signer->getFileId() === $file['id']) {
					/** @var array<IdentifyMethod> */
					$identifyMethodsOfSigner = $identifyMethods[$signer->getId()] ?? [];
					$data = [
						'email' => array_reduce($identifyMethodsOfSigner, function (string $carry, IdentifyMethod $identifyMethod): string {
							if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL) {
								return $identifyMethod->getIdentifierValue();
							}
							return $carry;
						}, ''),
						'description' => $signer->getDescription(),
						'displayName' =>
							array_reduce($identifyMethodsOfSigner, function (string $carry, IdentifyMethod $identifyMethod): string {
								if (!$carry && $identifyMethod->getMandatory()) {
									return $identifyMethod->getIdentifierValue();
								}
								return $carry;
							}, $signer->getDisplayName()),
						'request_sign_date' => (new \DateTime())
							->setTimestamp($signer->getCreatedAt())
							->format('Y-m-d H:i:s'),
						'signed' => null,
						'signRequestId' => $signer->getId(),
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
						'visibleElements' => $this->formatVisibleElementsToArray(
							$visibleElements[$signer->getId()] ?? [],
							!empty($file['metadata'])?json_decode($file['metadata'], true):[]
						),
						'identifyMethods' => array_map(function (IdentifyMethod $identifyMethod) use ($signer): array {
							return [
								'method' => $identifyMethod->getIdentifierKey(),
								'value' => $identifyMethod->getIdentifierValue(),
								'mandatory' => $identifyMethod->getMandatory(),
							];
						}, array_values($identifyMethodsOfSigner)),
					];

					if ($data['me']) {
						$temp = array_map(function (IdentifyMethod $identifyMethodEntity) use ($signer): array {
							$this->identifyMethodService->setCurrentIdentifyMethod($identifyMethodEntity);
							$identifyMethod = $this->identifyMethodService->getInstanceOfIdentifyMethod(
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
						$data['signed'] = $this->dateTimeFormatter->formatDateTime($signer->getSigned());
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
				$files[$key]['statusText'] = $this->fileMapper->getTextOfStatus((int)$files[$key]['status']);
			}
			unset($files[$key]['id']);
			ksort($files[$key]);
		}
		return $files;
	}

	/**
	 * @param FileElement[] $visibleElements
	 * @param array
	 * @return array
	 */
	private function formatVisibleElementsToArray(array $visibleElements, array $metadata): array {
		return array_map(function (FileElement $visibleElement) use ($metadata) {
			$element = [
				'elementId' => $visibleElement->getId(),
				'signRequestId' => $visibleElement->getSignRequestId(),
				'type' => $visibleElement->getType(),
				'coordinates' => [
					'page' => $visibleElement->getPage(),
					'urx' => $visibleElement->getUrx(),
					'ury' => $visibleElement->getUry(),
					'llx' => $visibleElement->getLlx(),
					'lly' => $visibleElement->getLly()
				]
			];
			$dimension = $metadata['d'][$element['coordinates']['page'] - 1];

			$element['coordinates']['left'] = $element['coordinates']['llx'];
			$element['coordinates']['height'] = abs($element['coordinates']['ury'] - $element['coordinates']['lly']);
			$element['coordinates']['top'] = $dimension['h'] - $element['coordinates']['ury'];
			$element['coordinates']['width'] = $element['coordinates']['urx'] - $element['coordinates']['llx'];

			return $element;
		}, $visibleElements);
	}

	public function getMyLibresignFile(int $nodeId): File {
		return $this->signRequestMapper->getMyLibresignFile(
			userId: $this->me->getUID(),
			filter: [
				'email' => $this->me->getEMailAddress(),
				'nodeId' => $nodeId,
			],
		);
	}

	public function delete(int $fileId): void {
		$file = $this->fileMapper->getByFileId($fileId);
		$this->fileElementService->deleteVisibleElements($file->getId());
		$list = $this->signRequestMapper->getByFileId($file->getId());
		foreach ($list as $signRequest) {
			$this->signRequestMapper->delete($signRequest);
		}
		$this->fileMapper->delete($file);
		if ($file->getSignedNodeId()) {
			$signedNextcloudFile = $this->folderService->getFileById($file->getSignedNodeId());
			$signedNextcloudFile->delete();
		}
		try {
			$nextcloudFile = $this->folderService->getFileById($fileId);
			$nextcloudFile->delete();
		} catch (NotFoundException $e) {
		}
	}
}
