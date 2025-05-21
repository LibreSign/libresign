<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;
use OC\Files\Filesystem;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use stdClass;

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
	private bool $signersLibreSignLoaded = false;
	private string $fileContent = '';
	private string $host = '';
	private ?File $file = null;
	private ?SignRequest $signRequest = null;
	private ?IUser $me = null;
	private ?int $identifyMethodId = null;
	private array $certData = [];
	private stdClass $fileData;
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
		private IRootFolder $root,
		protected LoggerInterface $logger,
		protected IL10N $l10n,
	) {
		$this->fileData = new stdClass();
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
		if ($show) {
			$this->fileData->settings = [
				'canSign' => false,
				'canRequestSign' => false,
				'signerFileUuid' => null,
				'phoneNumber' => '',
			];
		} else {
			unset($this->fileData->settings);
		}
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

	public function setHost(string $host): self {
		$this->host = $host;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setFile(File $file): self {
		$this->file = $file;
		$this->fileData->status = $this->file->getStatus();
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
		} catch (\Throwable) {
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
			unlink($file['tmp_name']);
			throw new InvalidArgumentException($this->l10n->t('Invalid file provided'));
		}
		if ($file['size'] > \OCP\Util::uploadLimit()) {
			unlink($file['tmp_name']);
			throw new InvalidArgumentException($this->l10n->t('File is too big'));
		}

		$this->fileContent = file_get_contents($file['tmp_name']);
		$mimeType = $this->mimeTypeDetector->detectString($this->fileContent);
		if ($mimeType !== 'application/pdf') {
			$this->fileContent = '';
			unlink($file['tmp_name']);
			throw new InvalidArgumentException($this->l10n->t('Invalid file provided'));
		}
		$this->fileData->size = $file['size'];

		$memoryFile = fopen($file['tmp_name'], 'rb');
		try {
			$this->certData = $this->pkcs12Handler->getCertificateChain($memoryFile);
			$this->fileData->status = File::STATUS_SIGNED;
			// Ignore when isnt a signed file
		} catch (LibresignException $e) {
			$this->fileData->status = File::STATUS_DRAFT;
		}
		fclose($memoryFile);
		unlink($file['tmp_name']);
		$this->fileData->hash = hash('sha256', $this->fileContent);
		try {
			$libresignFile = $this->fileMapper->getBySignedHash($this->fileData->hash);
			$this->setFile($libresignFile);
		} catch (DoesNotExistException) {
			$this->fileData->status = File::STATUS_NOT_LIBRESIGN_FILE;
		}
		$this->fileData->name = $file['name'];
		return $this;
	}

	private function getFile(): \OCP\Files\File {
		$nodeId = $this->file->getSignedNodeId();
		if (!$nodeId) {
			$nodeId = $this->file->getNodeId();
		}
		$fileToValidate = $this->root->getUserFolder($this->file->getUserId())->getById($nodeId);
		if (!count($fileToValidate)) {
			throw new LibresignException($this->l10n->t('File not found'), 404);
		}
		/** @var \OCP\Files\File */
		return current($fileToValidate);
	}

	private function getFileContent(): string {
		if ($this->fileContent) {
			return $this->fileContent;
		} elseif ($this->file) {
			try {
				return $this->fileContent = $this->getFile()->getContent();
			} catch (LibresignException $e) {
				throw new LibresignException($e->getMessage(), 404);
			} catch (\Throwable) {
				throw new LibresignException($this->l10n->t('Invalid data to validate file'), 404);
			}
		}
		return '';
	}

	private function loadFileMetadata(): void {
		if (!$content = $this->getFileContent()) {
			return;
		}
		$pdfParserService = $this->pdfParserService->setFile($content);
		if ($this->file) {
			$metadata = $this->file->getMetadata();
		}
		if (isset($metadata) && isset($metadata['p'])) {
			$dimensions = $metadata;
		} else {
			$dimensions = $pdfParserService->getPageDimensions();
		}
		$this->fileData->totalPages = $dimensions['p'];
		$this->fileData->size = strlen($content);
		$this->fileData->pdfVersion = $pdfParserService->getPdfVersion();
	}

	private function loadCertDataFromLibreSignFile(): void {
		if (!empty($this->certData) || !$this->validateFile || !$this->file || !$this->file->getSignedNodeId()) {
			return;
		}
		$file = $this->getFile();

		$resource = $file->fopen('rb');
		$this->certData = $this->pkcs12Handler->getCertificateChain($resource);
		fclose($resource);
	}

	private function loadLibreSignSigners(): void {
		if ($this->signersLibreSignLoaded || !$this->file) {
			return;
		}
		$signers = $this->signRequestMapper->getByFileId($this->file->getId());
		foreach ($signers as $signer) {
			$identifyMethods = $this->identifyMethodService
				->setIsRequest(false)
				->getIdentifyMethodsFromSignRequestId($signer->getId());
			if (!empty($this->fileData->signers)) {
				$found = array_filter($this->fileData->signers, function ($found) use ($identifyMethods) {
					if (!isset($found['uid'])) {
						return false;
					}
					[$key, $value] = explode(':', (string)$found['uid']);
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
					$totalSigners = count($signers);
					$totalCert = count($this->certData);
					// When only have a signature, consider that who signed is who need to sign
					if ($totalCert === 1 && $totalSigners === $totalCert) {
						$index = 0;
					} else {
						$index = count($this->fileData->signers);
					}
				}
			} else {
				$index = 0;
			}
			$this->fileData->signers[$index]['identifyMethods'] = $identifyMethods;
			$this->fileData->signers[$index]['displayName'] = $signer->getDisplayName();
			$this->fileData->signers[$index]['me'] = false;
			$this->fileData->signers[$index]['signRequestId'] = $signer->getId();
			$this->fileData->signers[$index]['description'] = $signer->getDescription();
			$this->fileData->signers[$index]['visibleElements'] = $this->getVisibleElements($signer->getId());
			$this->fileData->signers[$index]['request_sign_date'] = $signer->getCreatedAt()->format(DateTimeInterface::ATOM);
			if (empty($this->fileData->signers[$index]['signed'])) {
				if ($signer->getSigned()) {
					$this->fileData->signers[$index]['signed'] = $signer->getSigned()->format(DateTimeInterface::ATOM);
				} else {
					$this->fileData->signers[$index]['signed'] = null;
				}
			}
			$metadata = $signer->getMetadata();
			if (!empty($metadata['remote-address'])) {
				$this->fileData->signers[$index]['remote_address'] = $metadata['remote-address'];
			}
			if (!empty($metadata['user-agent'])) {
				$this->fileData->signers[$index]['user_agent'] = $metadata['user-agent'];
			}
			if (!empty($metadata['notify'])) {
				foreach ($metadata['notify'] as $notify) {
					$this->fileData->signers[$index]['notify'][] = [
						'method' => $notify['method'],
						'date' => (new \DateTime('@' . $notify['date']))->format(DateTimeInterface::ATOM),
					];
				}
			}
			if ($signer->getSigned() && empty($this->fileData->signers[$index]['signed'])) {
				if ($signer->getSigned()) {
					$this->fileData->signers[$index]['signed'] = $signer->getSigned()->format(DateTimeInterface::ATOM);
				} else {
					$this->fileData->signers[$index]['signed'] = null;
				}
			}
			// @todo refactor this code
			if ($this->me || $this->identifyMethodId) {
				$this->fileData->signers[$index]['sign_uuid'] = $signer->getUuid();
				// Identifi if I'm file owner
				if ($this->me?->getUID() === $this->file->getUserId()) {
					$email = array_reduce($identifyMethods[IdentifyMethodService::IDENTIFY_EMAIL] ?? [], function (?string $carry, IIdentifyMethod $identifyMethod): ?string {
						if ($identifyMethod->getEntity()->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL) {
							$carry = $identifyMethod->getEntity()->getIdentifierValue();
						}
						return $carry;
					}, '');
					$this->fileData->signers[$index]['email'] = $email;
					$user = $this->userManager->getByEmail($email);
					if ($user && count($user) === 1) {
						$this->fileData->signers[$index]['userId'] = $user[0]->getUID();
					}
				}
				// Identify if I'm signer
				foreach ($identifyMethods as $methods) {
					foreach ($methods as $identifyMethod) {
						$entity = $identifyMethod->getEntity();
						if ($this->identifyMethodId === $entity->getId()
							|| $this->me?->getUID() === $entity->getIdentifierValue()
							|| $this->me?->getEMailAddress() === $entity->getIdentifierValue()
						) {
							$this->fileData->signers[$index]['me'] = true;
							if (!$signer->getSigned()) {
								$this->fileData->settings['canSign'] = true;
								$this->fileData->settings['signerFileUuid'] = $signer->getUuid();
							}
						}
					}
				}
			}
			if ($this->fileData->signers[$index]['me']) {
				$this->fileData->url = $this->urlGenerator->linkToRoute('libresign.page.getPdfFile', ['uuid' => $this->fileData->signers[$index]['sign_uuid']]);
				$this->fileData->signers[$index]['signatureMethods'] = $this->identifyMethodService->getSignMethodsOfIdentifiedFactors($signer->getId());
			}
			$this->fileData->signers[$index]['identifyMethods'] = array_reduce($this->fileData->signers[$index]['identifyMethods'], function ($carry, $list) {
				foreach ($list as $identifyMethod) {
					$carry[] = [
						'method' => $identifyMethod->getEntity()->getIdentifierKey(),
						'value' => $identifyMethod->getEntity()->getIdentifierValue(),
						'mandatory' => $identifyMethod->getEntity()->getMandatory(),
					];
				}
				return $carry;
			}, []);
			ksort($this->fileData->signers[$index]);
		}
		$this->signersLibreSignLoaded = true;
	}

	private function loadSignersFromCertData(): void {
		$this->loadCertDataFromLibreSignFile();
		foreach ($this->certData as $index => $signer) {
			if (!empty($signer['chain'][0]['name'])) {
				$this->fileData->signers[$index]['subject'] = $signer['chain'][0]['name'];
			}
			if (!empty($signer['chain'][0]['validFrom_time_t'])) {
				$this->fileData->signers[$index]['valid_from'] = (new DateTime('@' . $signer['chain'][0]['validFrom_time_t']))->format(DateTimeInterface::ATOM);
			}
			if (!empty($signer['chain'][0]['validTo_time_t'])) {
				$this->fileData->signers[$index]['valid_to'] = (new DateTime('@' . $signer['chain'][0]['validTo_time_t']))->format(DateTimeInterface::ATOM);
			}
			if (!empty($signer['signingTime'])) {
				$this->fileData->signers[$index]['signed'] = $signer['signingTime']->format(DateTimeInterface::ATOM);
			}
			$this->fileData->signers[$index]['signature_validation'] = $signer['chain'][0]['signature_validation'];
			if (!empty($signer['chain'][0]['certificate_validation'])) {
				$this->fileData->signers[$index]['certificate_validation'] = $signer['chain'][0]['certificate_validation'];
			}
			if (!empty($signer['chain'][0]['signatureTypeSN'])) {
				$this->fileData->signers[$index]['hash_algorithm'] = $signer['chain'][0]['signatureTypeSN'];
			}
			if (!empty($signer['chain'][0]['subject']['UID'])) {
				$this->fileData->signers[$index]['uid'] = $signer['chain'][0]['subject']['UID'];
			} elseif (!empty($signer['chain'][0]['subject']['CN']) && preg_match('/^(?<key>.*):(?<value>.*), /', (string)$signer['chain'][0]['subject']['CN'], $matches)) {
				// Used by CFSSL
				$this->fileData->signers[$index]['uid'] = $matches['key'] . ':' . $matches['value'];
			} elseif (!empty($signer['chain'][0]['extensions']['subjectAltName'])) {
				// Used by old certs of LibreSign
				preg_match('/^(?<key>(email|account)):(?<value>.*)$/', (string)$signer['chain'][0]['extensions']['subjectAltName'], $matches);
				if ($matches) {
					if (str_ends_with($matches['value'], $this->host)) {
						$uid = str_replace('@' . $this->host, '', $matches['value']);
						$userFound = $this->userManager->get($uid);
						if ($userFound) {
							$this->fileData->signers[$index]['uid'] = 'account:' . $uid;
						} else {
							$userFound = $this->userManager->getByEmail($matches['value']);
							if ($userFound) {
								$userFound = current($userFound);
								$this->fileData->signers[$index]['uid'] = 'account:' . $userFound->getUID();
							} else {
								$this->fileData->signers[$index]['uid'] = 'email:' . $matches['value'];
							}
						}
					} else {
						$userFound = $this->userManager->getByEmail($matches['value']);
						if ($userFound) {
							$userFound = current($userFound);
							$this->fileData->signers[$index]['uid'] = 'account:' . $userFound->getUID();
						} else {
							$userFound = $this->userManager->get($matches['value']);
							if ($userFound) {
								$this->fileData->signers[$index]['uid'] = 'account:' . $userFound->getUID();
							} else {
								$this->fileData->signers[$index]['uid'] = $matches['key'] . ':' . $matches['value'];
							}
						}
					}
				}
			}
			if (!empty($signer['chain'][0]['subject']['CN'])) {
				$this->fileData->signers[$index]['displayName'] = $signer['chain'][0]['subject']['CN'];
			} elseif (!empty($this->fileData->signers[$index]['uid'])) {
				$this->fileData->signers[$index]['displayName'] = $this->fileData->signers[$index]['uid'];
			}
			for ($i = 1; $i < count($signer['chain']); $i++) {
				$this->fileData->signers[$index]['chain'][] = [
					'displayName' => $signer['chain'][$i]['name'],
				];
			}
		}
	}

	private function loadSigners(): void {
		if (!$this->showSigners) {
			return;
		}
		$this->loadSignersFromCertData();
		$this->loadLibreSignSigners();
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
		} catch (\Throwable) {
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

	private function loadSettings(): void {
		if (!$this->showSettings) {
			return;
		}
		if ($this->me) {
			$this->fileData->settings = array_merge($this->fileData->settings, $this->accountService->getSettings($this->me));
			$this->fileData->settings['phoneNumber'] = $this->getPhoneNumber();
			$status = $this->getIdentificationDocumentsStatus($this->me->getUID());
			if ($status === self::IDENTIFICATION_DOCUMENTS_NEED_SEND) {
				$this->fileData->settings['needIdentificationDocuments'] = true;
				$this->fileData->settings['identificationDocumentsWaitingApproval'] = false;
			} elseif ($status === self::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL) {
				$this->fileData->settings['needIdentificationDocuments'] = true;
				$this->fileData->settings['identificationDocumentsWaitingApproval'] = true;
			}
		}
	}

	public function getIdentificationDocumentsStatus(?string $userId): int {
		if (!$this->appConfig->getValueBool(Application::APP_ID, 'identification_documents', false)) {
			return self::IDENTIFICATION_DOCUMENTS_DISABLED;
		}

		if (!empty($userId)) {
			$files = $this->fileMapper->getFilesOfAccount($userId);
		}
		if (empty($files) || !count($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_SEND;
		}
		$deleted = array_filter($files, fn (File $file) => $file->getStatus() === File::STATUS_DELETED);
		if (count($deleted) === count($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_SEND;
		}

		$signed = array_filter($files, fn (File $file) => $file->getStatus() === File::STATUS_SIGNED);
		if (count($signed) !== count($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL;
		}

		return self::IDENTIFICATION_DOCUMENTS_APPROVED;
	}

	private function loadLibreSignData(): void {
		if (!$this->file) {
			return;
		}
		$this->fileData->uuid = $this->file->getUuid();
		$this->fileData->name = $this->file->getName();
		$this->fileData->status = $this->file->getStatus();
		$this->fileData->created_at = $this->file->getCreatedAt()->format(DateTimeInterface::ATOM);
		$this->fileData->statusText = $this->fileMapper->getTextOfStatus($this->file->getStatus());
		$this->fileData->nodeId = $this->file->getNodeId();

		$this->fileData->requested_by = [
			'userId' => $this->file->getUserId(),
			'displayName' => $this->userManager->get($this->file->getUserId())->getDisplayName(),
		];
		$this->fileData->file = $this->urlGenerator->linkToRoute('libresign.page.getPdf', ['uuid' => $this->file->getUuid()]);
		if ($this->showVisibleElements) {
			$signers = $this->signRequestMapper->getByMultipleFileId([$this->file->getId()]);
			$this->fileData->visibleElements = [];
			foreach ($this->signRequestMapper->getVisibleElementsFromSigners($signers) as $visibleElements) {
				$this->fileData->visibleElements = array_merge(
					$this->formatVisibleElementsToArray(
						$visibleElements,
						$this->file->getMetadata()
					),
					$this->fileData->visibleElements
				);
			}
		}
	}

	private function loadMessages(): void {
		if (!$this->showMessages) {
			return;
		}
		$messages = [];
		if ($this->fileData->settings['canSign']) {
			$messages[] = [
				'type' => 'info',
				'message' => $this->l10n->t('You need to sign this document')
			];
		}
		if ($this->fileData->settings['canRequestSign']) {
			$this->loadLibreSignSigners();
			if (empty($this->fileData->signers)) {
				$messages[] = [
					'type' => 'info',
					'message' => $this->l10n->t('You cannot request signature for this document, please contact your administrator')
				];
			}
		}
		if ($messages) {
			$this->fileData->messages = $messages;
		}
	}

	/**
	 * @return LibresignValidateFile
	 */
	public function toArray(): array {
		$this->loadLibreSignData();
		$this->loadFileMetadata();
		$this->loadSettings();
		$this->loadSigners();
		$this->loadMessages();
		$return = json_decode(json_encode($this->fileData), true);
		ksort($return);
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
		$page ??= 1;
		$length ??= (int)$this->appConfig->getValueInt(Application::APP_ID, 'length_of_page', 100);

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
							if (filter_var($identifyMethod->getIdentifierValue(), FILTER_VALIDATE_EMAIL)) {
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
						'request_sign_date' => $signer->getCreatedAt()->format(DateTimeInterface::ATOM),
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
							!empty($file['metadata'])?json_decode((string)$file['metadata'], true):[]
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
		} catch (NotFoundException) {
		}
	}
}
