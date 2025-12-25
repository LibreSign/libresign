<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use DateTimeInterface;
use InvalidArgumentException;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Helper\FileUploadHelper;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\File\FileListService;
use OCA\Libresign\Service\File\FileResponseOptions;
use OCA\Libresign\Service\File\SignersLoader;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
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
 * @psalm-import-type LibresignEnvelopeChildFile from ResponseDefinitions
 * @psalm-import-type LibresignValidateFile from ResponseDefinitions
 * @psalm-import-type LibresignVisibleElement from ResponseDefinitions
 */
class FileService {
	use TFile;

	private bool $signersLibreSignLoaded = false;
	private string $fileContent = '';
	private ?File $file = null;
	private ?SignRequest $signRequest = null;
	private array $certData = [];
	private stdClass $fileData;
	private FileResponseOptions $options;
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
		private IdDocsMapper $idDocsMapper,
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
		DocMdpHandler $docMdpHandler,
		private IRootFolder $root,
		protected LoggerInterface $logger,
		protected IL10N $l10n,
		private EnvelopeService $envelopeService,
		private SignersLoader $signersLoader,
		private FileListService $fileListService,
		FileUploadHelper $uploadHelper,
	) {
		$this->fileData = new stdClass();
		$this->options = new FileResponseOptions();
		$this->docMdpHandler = $docMdpHandler;
		$this->uploadHelper = $uploadHelper;
	}

	/**
	 * @return static
	 */
	public function showSigners(bool $show = true): self {
		$this->options->showSigners($show);
		return $this;
	}

	/**
	 * @return static
	 */
	public function showSettings(bool $show = true): self {
		$this->options->showSettings($show);
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
		$this->options->showVisibleElements($show);
		return $this;
	}

	/**
	 * @return static
	 */
	public function showMessages(bool $show = true): self {
		$this->options->showMessages($show);
		return $this;
	}

	/**
	 * @return static
	 */
	public function setMe(?IUser $user): self {
		$this->options->setMe($user);
		return $this;
	}

	public function setSignerIdentified(bool $identified = true): self {
		$this->options->setSignerIdentified($identified);
		return $this;
	}

	public function setIdentifyMethodId(?int $id): self {
		$this->options->setIdentifyMethodId($id);
		return $this;
	}

	public function setHost(string $host): self {
		$this->options->setHost($host);
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
		$this->options->validateFile($validateFile);
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

	public function validateUploadedFile(array $file): void {
		$this->uploadHelper->validateUploadedFile($file);
	}

	public function setFileFromRequest(?array $file): self {
		if ($file === null) {
			throw new InvalidArgumentException($this->l10n->t('No file provided'));
		}
		$this->uploadHelper->validateUploadedFile($file);

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
		} catch (LibresignException) {
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
		$fileToValidate = $this->root->getUserFolder($this->file->getUserId())->getFirstNodeById($nodeId);
		if (!$fileToValidate instanceof \OCP\Files\File) {
			throw new LibresignException($this->l10n->t('File not found'), 404);
		}
		return $fileToValidate;
	}

	public function getStatus(): int {
		return $this->file->getStatus();
	}

	public function getSignedNodeId(): ?int {
		$status = $this->file->getStatus();

		if (!in_array($status, [File::STATUS_PARTIAL_SIGNED, File::STATUS_SIGNED])) {
			return null;
		}
		return $this->file->getSignedNodeId();
	}

	private function getFileContent(): string {
		if ($this->fileContent) {
			return $this->fileContent;
		} elseif ($this->file) {
			try {
				return $this->fileContent = $this->getFile()->getContent();
			} catch (LibresignException $e) {
				throw $e;
			} catch (\Throwable $e) {
				$this->logger->error('Failed to get file content: ' . $e->getMessage(), [
					'fileId' => $this->file->getId(),
					'exception' => $e,
				]);
				throw new LibresignException($this->l10n->t('Invalid data to validate file'), 404, $e);
			}
		}
		return '';
	}

	public function isLibresignFile(int $nodeId): bool {
		try {
			return $this->fileMapper->fileIdExists($nodeId);
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('Invalid data to validate file'), 404);
		}
	}

	private function loadFileMetadata(): void {
		if (
			($this->file instanceof File && $this->file->getNodeType() !== 'file')
			|| !$content = $this->getFileContent()
		) {
			return;
		}
		$pdfParserService = $this->pdfParserService->setFile($content);
		if ($this->file) {
			$metadata = $this->file->getMetadata();
			$this->fileData->metadata = $metadata;
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


	private function loadCertificateChain(\OCP\Files\File $fileNode, File $libreSignFile): array {
		if (!$this->options->isValidateFile() || !$libreSignFile->getSignedNodeId()) {
			return [];
		}

		try {
			$resource = $fileNode->fopen('rb');
			$sha256 = $this->getSha256FromResource($resource);
			rewind($resource);
			if ($sha256 === $libreSignFile->getSignedHash()) {
				$this->pkcs12Handler->setIsLibreSignFile();
			}
			$certData = $this->pkcs12Handler->getCertificateChain($resource);
			fclose($resource);
			return $certData;
		} catch (\Exception $e) {
			return [];
		}
	}

	private function getSha256FromResource($resource): string {
		$hashContext = hash_init('sha256');
		while (!feof($resource)) {
			$buffer = fread($resource, 8192); // 8192 bytes = 8 KB
			hash_update($hashContext, $buffer);
		}
		return hash_final($hashContext);
	}

	private function loadLibreSignSigners(): void {
		$this->signersLoader->loadLibreSignSigners($this->file, $this->fileData, $this->options, $this->certData);
		$this->signersLibreSignLoaded = $this->signersLoader->reset() || true;
	}

	private function loadSigners(): void {
		if (!$this->options->isShowSigners()) {
			return;
		}

		if (!$this->options->isValidateFile() || !$this->file instanceof File) {
			return;
		}

		if ($this->file->getSignedNodeId()) {
			$fileNode = $this->getFile();
			$certData = $this->loadCertificateChain($fileNode, $this->file);
			if ($certData) {
				$this->signersLoader->loadSignersFromCertData($this->fileData, $certData, $this->options->getHost());
			}
		}
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
		if (!$this->options->isShowVisibleElements()) {
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
		if (!$this->options->getMe()) {
			return '';
		}
		$userAccount = $this->accountManager->getAccount($this->options->getMe());
		return $userAccount->getProperty(IAccountManager::PROPERTY_PHONE)->getValue();
	}

	private function loadSettings(): void {
		if (!$this->options->isShowSettings()) {
			return;
		}
		if ($this->options->getMe()) {
			$this->fileData->settings = array_merge($this->fileData->settings, $this->accountService->getSettings($this->options->getMe()));
			$this->fileData->settings['phoneNumber'] = $this->getPhoneNumber();
		}
		if ($this->options->isSignerIdentified() || $this->options->getMe()) {
			$status = $this->getIdentificationDocumentsStatus();
			if ($status === self::IDENTIFICATION_DOCUMENTS_NEED_SEND) {
				$this->fileData->settings['needIdentificationDocuments'] = true;
				$this->fileData->settings['identificationDocumentsWaitingApproval'] = false;
			} elseif ($status === self::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL) {
				$this->fileData->settings['needIdentificationDocuments'] = true;
				$this->fileData->settings['identificationDocumentsWaitingApproval'] = true;
			}
		}
	}

	public function getIdentificationDocumentsStatus(string $userId = ''): int {
		if (!$this->appConfig->getValueBool(Application::APP_ID, 'identification_documents', false)) {
			return self::IDENTIFICATION_DOCUMENTS_DISABLED;
		}

		if (!$userId && $this->options->getMe() instanceof IUser) {
			$userId = $this->options->getMe()->getUID();
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
		$this->fileData->signatureFlow = $this->file->getSignatureFlow();
		$this->fileData->docmdpLevel = $this->file->getDocmdpLevel();
		$this->fileData->nodeType = $this->file->getNodeType();
		$this->file = $this->fileMapper->getById($this->file->getId());

		if ($this->fileData->nodeType !== 'envelope' && !$this->file->getParentFileId()) {
			$fileId = $this->file->getId();

			$childrenFiles = $this->fileMapper->getChildrenFiles($fileId);

			if (!empty($childrenFiles)) {
				$this->file->setNodeType('envelope');
				$this->fileMapper->update($this->file);

				$this->fileData->nodeType = 'envelope';
				$this->fileData->filesCount = count($childrenFiles);
				$this->fileData->files = [];
			}
		}

		if ($this->fileData->nodeType === 'envelope') {
			$metadata = $this->file->getMetadata();
			$this->fileData->filesCount = $metadata['filesCount'] ?? 0;
			$this->fileData->files = [];
			$this->loadEnvelopeFiles();
			if ($this->file->getStatus() === File::STATUS_SIGNED) {
				$latestSignedDate = $this->getLatestSignedDateFromEnvelope();
				if ($latestSignedDate) {
					$this->fileData->signedDate = $latestSignedDate->format(DateTimeInterface::ATOM);
				}
			}
		}

		$this->fileData->requested_by = [
			'userId' => $this->file->getUserId(),
			'displayName' => $this->userManager->get($this->file->getUserId())->getDisplayName(),
		];
		$this->fileData->file = $this->urlGenerator->linkToRoute('libresign.page.getPdf', ['uuid' => $this->file->getUuid()]);

		$this->loadEnvelopeData();

		if ($this->options->isShowVisibleElements()) {
			$signers = $this->signRequestMapper->getByMultipleFileId([$this->file->getId()]);
			$this->fileData->visibleElements = [];
			foreach ($this->signRequestMapper->getVisibleElementsFromSigners($signers) as $visibleElements) {
				if (empty($visibleElements)) {
					continue;
				}
				$file = array_filter($this->fileData->files, fn (stdClass $file) => $file->id === $visibleElements[0]['file_id']);
				if (empty($file)) {
					continue;
				}
				$file = current($file);
				$this->fileData->visibleElements = array_merge(
					$this->fileListService->formatVisibleElements($visibleElements),
					$this->fileData->visibleElements
				);
			}
		}
	}

	private function getLatestSignedDateFromEnvelope(): ?\DateTime {
		if (!$this->file || $this->file->getNodeType() !== 'envelope') {
			return null;
		}

		$childrenFiles = $this->fileMapper->getChildrenFiles($this->file->getId());
		$latestDate = null;

		foreach ($childrenFiles as $childFile) {
			$signRequests = $this->signRequestMapper->getByFileId($childFile->getId());
			foreach ($signRequests as $signRequest) {
				$signed = $signRequest->getSigned();
				if ($signed && (!$latestDate || $signed > $latestDate)) {
					$latestDate = $signed;
				}
			}
		}

		return $latestDate;
	}

	private function loadEnvelopeFiles(): void {
		if (!$this->file || $this->file->getNodeType() !== 'envelope') {
			return;
		}

		$childrenFiles = $this->fileMapper->getChildrenFiles($this->file->getId());
		foreach ($childrenFiles as $childFile) {
			$this->fileData->files[] = $this->buildEnvelopeChildData($childFile);
		}
	}

	private function buildEnvelopeChildData(File $childFile): stdClass {
		$fileData = new stdClass();
		$fileData->id = $childFile->getId();
		$fileData->uuid = $childFile->getUuid();
		$fileData->name = $childFile->getName();
		$fileData->status = $childFile->getStatus();
		$fileData->statusText = $this->fileMapper->getTextOfStatus($childFile->getStatus());
		$fileData->nodeId = $childFile->getNodeId();
		$fileData->metadata = $childFile->getMetadata();
		$fileData->signers = [];

		$signRequests = $this->signRequestMapper->getByFileId($childFile->getId());
		foreach ($signRequests as $signRequest) {
			$identifyMethods = $this->identifyMethodService
				->setIsRequest(false)
				->getIdentifyMethodsFromSignRequestId($signRequest->getId());

			$email = '';
			foreach ($identifyMethods[IdentifyMethodService::IDENTIFY_EMAIL] ?? [] as $identifyMethod) {
				$entity = $identifyMethod->getEntity();
				if ($entity->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL) {
					$email = $entity->getIdentifierValue();
					break;
				}
			}

			$signed = null;
			if ($signRequest->getSigned()) {
				$signed = $signRequest->getSigned()->format(DateTimeInterface::ATOM);
			}

			$displayName = $signRequest->getDisplayName();
			if ($displayName === '' && $email !== '') {
				$displayName = $email;
			}

			$signer = new stdClass();
			$signer->signRequestId = $signRequest->getId();
			$signer->displayName = $displayName;
			$signer->email = $email;
			$signer->signed = $signed;
			$signer->status = $signRequest->getStatus();
			$signer->statusText = $this->signRequestMapper->getTextOfSignerStatus($signRequest->getStatus());
			$fileData->signers[] = $signer;
		}

		if ($this->options->isValidateFile() && $childFile->getSignedNodeId()) {
			$fileNode = $this->root->getUserFolder($childFile->getUserId())->getFirstNodeById($childFile->getSignedNodeId());
			if ($fileNode instanceof \OCP\Files\File) {
				$certData = $this->loadCertificateChain($fileNode, $childFile);
				if (!empty($certData)) {
					$this->signersLoader->loadSignersFromCertData($fileData, $certData, $this->options->getHost());
				}
			}
		}

		return $fileData;
	}

	private function loadEnvelopeData(): void {
		if (!$this->file->hasParent()) {
			return;
		}

		$envelope = $this->envelopeService->getEnvelopeByFileId($this->file->getId());
		if (!$envelope) {
			return;
		}

		$envelopeMetadata = $envelope->getMetadata();
		$this->fileData->envelope = [
			'id' => $envelope->getId(),
			'uuid' => $envelope->getUuid(),
			'name' => $envelope->getName(),
			'status' => $envelope->getStatus(),
			'statusText' => $this->fileMapper->getTextOfStatus($envelope->getStatus()),
			'filesCount' => $envelopeMetadata['filesCount'] ?? 0,
			'files' => [],
		];
	}

	private function loadMessages(): void {
		if (!$this->options->isShowMessages()) {
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
	 * @psalm-return LibresignValidateFile
	 */
	public function toArray(): array {
		$this->loadLibreSignData();
		$this->loadFileMetadata();
		$this->loadSettings();
		$this->loadSigners();
		$this->loadMessages();
		$this->computeEnvelopeSignersProgress();

		$return = json_decode(json_encode($this->fileData), true);
		ksort($return);
		return $return;
	}

	private function computeEnvelopeSignersProgress(): void {
		if (!$this->file || $this->file->getParentFileId()) {
			return;
		}
		if (empty($this->fileData->signers)) {
			return;
		}

		$childrenFiles = $this->fileMapper->getChildrenFiles($this->file->getId());
		if (empty($childrenFiles)) {
			return;
		}

		$signerProgress = [];
		foreach ($childrenFiles as $childFile) {
			$signRequests = $this->signRequestMapper->getByFileId($childFile->getId());
			foreach ($signRequests as $signRequest) {
				$signRequestId = $signRequest->getId();

				$identifyMethods = $this->identifyMethodService
					->setIsRequest(false)
					->getIdentifyMethodsFromSignRequestId($signRequestId);

				$signerKey = $this->buildSignerKey($identifyMethods);

				if (!isset($signerProgress[$signerKey])) {
					$signerProgress[$signerKey] = [
						'total' => 0,
						'signed' => 0,
					];
				}

				$signerProgress[$signerKey]['total']++;
				if ($signRequest->getSigned()) {
					$signerProgress[$signerKey]['signed']++;
				}
			}
		}

		foreach ($this->fileData->signers as $index => $signer) {
			$signerKey = $this->buildSignerKeyFromEnvelopeSigner($signer);
			if (isset($signerProgress[$signerKey])) {
				$this->fileData->signers[$index]->totalDocuments = $signerProgress[$signerKey]['total'];
				$this->fileData->signers[$index]->documentsSignedCount = $signerProgress[$signerKey]['signed'];
			} else {
				$this->fileData->signers[$index]->totalDocuments = 0;
				$this->fileData->signers[$index]->documentsSignedCount = 0;
			}
		}
	}

	private function buildSignerKey(array $identifyMethods): string {
		$keys = [];
		foreach ($identifyMethods as $methods) {
			foreach ($methods as $identifyMethod) {
				$entity = $identifyMethod->getEntity();
				$keys[] = $entity->getIdentifierKey() . ':' . $entity->getIdentifierValue();
			}
		}
		sort($keys);
		return implode('|', $keys);
	}

	private function buildSignerKeyFromEnvelopeSigner(stdClass $signer): string {
		if (empty($signer->identifyMethods)) {
			return '';
		}
		$keys = [];
		foreach ($signer->identifyMethods as $method) {
			$keys[] = $method['method'] . ':' . $method['value'];
		}
		sort($keys);
		return implode('|', $keys);
	}

	public function setFileByPath(string $path): self {
		$node = $this->folderService->getFileByPath($path);
		$this->setFileByType('FileId', $node->getId());
		return $this;
	}

	public function getMyLibresignFile(int $nodeId): File {
		return $this->signRequestMapper->getMyLibresignFile(
			userId: $this->options->getMe()->getUID(),
			filter: [
				'email' => $this->options->getMe()->getEMailAddress(),
				'nodeId' => $nodeId,
			],
		);
	}

	public function delete(int $fileId): void {
		$file = $this->fileMapper->getByFileId($fileId);

		$this->decrementEnvelopeFilesCountIfNeeded($file);

		if ($file->getNodeType() === 'envelope') {
			$childrenFiles = $this->fileMapper->getChildrenFiles($file->getId());
			foreach ($childrenFiles as $childFile) {
				$this->delete($childFile->getNodeId());
			}
		}

		$this->fileElementService->deleteVisibleElements($file->getId());
		$list = $this->signRequestMapper->getByFileId($file->getId());
		foreach ($list as $signRequest) {
			$this->signRequestMapper->delete($signRequest);
		}
		$this->idDocsMapper->deleteByFileId($file->getId());
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

	/**
	 * Process uploaded files with automatic rollback on error
	 *
	 * @param array $filesArray Normalized array of uploaded files
	 * @param IUser $user User who is uploading
	 * @param array $settings Upload settings
	 * @return list<array{fileNode: Node, name: string}>
	 * @throws LibresignException
	 */
	public function processUploadedFilesWithRollback(array $filesArray, IUser $user, array $settings): array {
		$processedFiles = [];
		$createdNodes = [];
		$shouldRollback = true;

		try {
			foreach ($filesArray as $uploadedFile) {
				$fileName = pathinfo($uploadedFile['name'], PATHINFO_FILENAME);

				$node = $this->getNodeFromUploadedFile([
					'userManager' => $user,
					'name' => $fileName,
					'uploadedFile' => $uploadedFile,
					'settings' => $settings,
				]);

				$createdNodes[] = $node;

				$this->validateHelper->validateNewFile([
					'file' => ['fileId' => $node->getId()],
					'userManager' => $user,
				]);

				$processedFiles[] = [
					'fileNode' => $node,
					'name' => $fileName,
				];
			}

			$shouldRollback = false;
			return $processedFiles;
		} finally {
			if ($shouldRollback) {
				$this->rollbackCreatedNodes($createdNodes);
			}
		}
	}

	/**
	 * @param Node[] $nodes
	 */
	private function rollbackCreatedNodes(array $nodes): void {
		foreach ($nodes as $node) {
			try {
				$node->delete();
			} catch (\Exception $deleteError) {
				$this->logger->error('Failed to rollback uploaded file', [
					'nodeId' => $node->getId(),
					'error' => $deleteError->getMessage(),
				]);
			}
		}
	}

	public function updateEnvelopeFilesCount(File $envelope, int $delta = 0): void {
		$metadata = $envelope->getMetadata();
		$currentCount = $metadata['filesCount'] ?? 0;
		$metadata['filesCount'] = max(0, $currentCount + $delta);
		$envelope->setMetadata($metadata);
		$this->fileMapper->update($envelope);
	}

	private function decrementEnvelopeFilesCountIfNeeded(File $file): void {
		if ($file->getParentFileId() === null) {
			return;
		}

		$parentEnvelope = $this->fileMapper->getById($file->getParentFileId());
		if ($parentEnvelope->getNodeType() === 'envelope') {
			$this->updateEnvelopeFilesCount($parentEnvelope, -1);
		}
	}
}
