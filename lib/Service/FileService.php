<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use DateTimeInterface;
use InvalidArgumentException;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Helper\FileUploadHelper;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\Envelope\EnvelopeService;
use OCA\Libresign\Service\File\CertificateChainService;
use OCA\Libresign\Service\File\EnvelopeAssembler;
use OCA\Libresign\Service\File\EnvelopeProgressService;
use OCA\Libresign\Service\File\FileContentProvider;
use OCA\Libresign\Service\File\FileResponseOptions;
use OCA\Libresign\Service\File\MessagesLoader;
use OCA\Libresign\Service\File\MetadataLoader;
use OCA\Libresign\Service\File\MimeService;
use OCA\Libresign\Service\File\Pdf\PdfValidator;
use OCA\Libresign\Service\File\SettingsLoader;
use OCA\Libresign\Service\File\SignersLoader;
use OCA\Libresign\Service\File\UploadProcessor;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * @psalm-import-type LibresignEnvelopeChildFile from ResponseDefinitions
 * @psalm-import-type LibresignValidateFile from ResponseDefinitions
 * @psalm-import-type LibresignVisibleElement from ResponseDefinitions
 */
class FileService {

	private string $fileContent = '';
	private ?File $file = null;
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
		private IdDocsMapper $idDocsMapper,
		private IdentifyMethodService $identifyMethodService,
		private IUserManager $userManager,
		private IURLGenerator $urlGenerator,
		protected IMimeTypeDetector $mimeTypeDetector,
		protected Pkcs12Handler $pkcs12Handler,
		protected DocMdpHandler $docMdpHandler,
		protected PdfValidator $pdfValidator,
		private IRootFolder $root,
		protected LoggerInterface $logger,
		protected IL10N $l10n,
		private EnvelopeService $envelopeService,
		private SignersLoader $signersLoader,
		protected FileUploadHelper $uploadHelper,
		private EnvelopeAssembler $envelopeAssembler,
		private EnvelopeProgressService $envelopeProgressService,
		private CertificateChainService $certificateChainService,
		private MimeService $mimeService,
		private FileContentProvider $contentProvider,
		private UploadProcessor $uploadProcessor,
		private MetadataLoader $metadataLoader,
		private SettingsLoader $settingsLoader,
		private MessagesLoader $messagesLoader,
		private FileStatusService $fileStatusService,
	) {
		$this->initializeFileData();
		$this->options = new FileResponseOptions();
	}

	private function initializeFileData(): void {
		$this->fileData = new stdClass();
		$this->fileData->id = 0;
		$this->fileData->uuid = '';
		$this->fileData->name = '';
		$this->fileData->status = FileStatus::DRAFT->value;
		$this->fileData->statusText = '';
		$this->fileData->nodeId = 0;
		$this->fileData->nodeType = 'file';
		$this->fileData->created_at = '';
		$this->fileData->signUuid = null;
		$this->fileData->metadata = [];
		$this->fileData->signatureFlow = SignatureFlow::PARALLEL->value;
		$this->fileData->signers = [];
		$this->fileData->signersCount = 0;
		$this->fileData->requested_by = [
			'userId' => '',
			'displayName' => '',
		];
		$this->fileData->filesCount = 0;
		$this->fileData->files = [];
	}

	public function update(File $file): File {
		return $this->fileStatusService->update($file);
	}

	public function getNodeFromData(array $data): Node {
		$data['userManager'] = $data['userManager'] ?? '';
		if (!$this->folderService->getUserId() && $data['userManager'] instanceof \OCP\IUser) {
			$this->folderService->setUserId($data['userManager']->getUID());
		}

		if (isset($data['uploadedFile'])) {
			return $this->uploadProcessor->getNodeFromUploadedFile($data);
		}

		if (isset($data['file']['fileNode']) && $data['file']['fileNode'] instanceof Node) {
			return $data['file']['fileNode'];
		}
		if (isset($data['file']['fileId'])) {
			return $this->folderService->getFileByNodeId($data['file']['fileId']);
		}
		if (isset($data['file']['path'])) {
			return $this->folderService->getFileByPath($data['file']['path']);
		}
		if (isset($data['file']['nodeId'])) {
			return $this->folderService->getFileByNodeId($data['file']['nodeId']);
		}

		$content = $this->getFileRaw($data);
		$extension = $this->getExtension($content);

		$fileName = $data['name'];
		$this->validateFileContent($content, $fileName, $extension);

		$folderToFile = $this->folderService->getFolderForFile($data, $data['userManager']);
		$filename = $this->resolveFileName($data, $extension);
		return $folderToFile->newFile($filename, $content);
	}

	public function validateFileContent(string $content, string $fileName, string $extension): void {
		if ($extension === 'pdf') {
			$this->pdfValidator->validate($content, $fileName);
		}
	}

	private function getExtension(string $content): string {
		return $this->mimeService->getExtension($content);
	}

	private function getFileRaw(array $data): string {
		return $this->contentProvider->getContentFromData($data);
	}

	private function resolveFileName(array $data, string $extension): string {
		$name = '';
		if (isset($data['name'])) {
			$name = trim((string)$data['name']);
		}

		if ($name === '') {
			$basename = '';
			if (!empty($data['file']['url'])) {
				$path = (string)parse_url((string)$data['file']['url'], PHP_URL_PATH);
				if ($path !== '') {
					$basename = basename($path);
				}
			}
			if ($basename !== '') {
				$filenameNoExt = pathinfo($basename, PATHINFO_FILENAME);
				$name = $filenameNoExt !== '' ? $filenameNoExt : $basename;
			} else {
				$name = 'document';
			}
		}

		$name = preg_replace('/\s+/', '_', $name);
		$name = $name !== '' ? $name : 'document';
		$extensionSuffix = '.' . $extension;
		if (str_ends_with(strtolower($name), strtolower($extensionSuffix))) {
			return $name;
		}
		return $name . $extensionSuffix;
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

	public function setSignRequest(?\OCA\Libresign\Db\SignRequest $signRequest): self {
		$this->options->setSignRequest($signRequest);
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
		$this->initializeFileData();
		$this->file = $file;
		$this->fileData->status = $this->file->getStatus();
		return $this;
	}

	public function showValidateFile(bool $validateFile = true): self {
		$this->options->validateFile($validateFile);
		return $this;
	}

	private function setFileOrFail(callable $resolver): self {
		try {
			$file = $resolver();
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('Invalid data to validate file'), 404);
		}

		if (!$file instanceof File) {
			throw new LibresignException($this->l10n->t('Invalid file identifier'), 404);
		}

		return $this->setFile($file);
	}

	public function setFileById(int $fileId): self {
		return $this->setFileOrFail(fn () => $this->fileMapper->getById($fileId));
	}

	public function setFileByUuid(string $uuid): self {
		return $this->setFileOrFail(fn () => $this->fileMapper->getByUuid($uuid));
	}

	public function setFileBySignerUuid(string $uuid): self {
		return $this->setFileOrFail(fn () => $this->fileMapper->getBySignerUuid($uuid));
	}

	public function setFileByNodeId(int $nodeId): self {
		return $this->setFileOrFail(fn () => $this->fileMapper->getByNodeId($nodeId));
	}

	public function validateUploadedFile(array $file): void {
		$this->uploadHelper->validateUploadedFile($file);
	}

	public function setFileFromRequest(?array $file): self {
		if ($file === null) {
			throw new InvalidArgumentException($this->l10n->t('No file provided'));
		}
		$this->initializeFileData();
		$this->uploadHelper->validateUploadedFile($file);

		$this->fileContent = file_get_contents($file['tmp_name']);
		$mimeType = $this->mimeService->getMimeType($this->fileContent);
		if ($mimeType !== 'application/pdf') {
			$this->fileContent = '';
			unlink($file['tmp_name']);
			throw new InvalidArgumentException($this->l10n->t('Invalid file provided'));
		}
		$this->fileData->size = $file['size'];

		$memoryFile = fopen($file['tmp_name'], 'rb');
		try {
			$this->certData = $this->pkcs12Handler->getCertificateChain($memoryFile);
			$this->fileData->status = FileStatus::SIGNED->value;
		} catch (LibresignException) {
			$this->fileData->status = FileStatus::DRAFT->value;
		}
		fclose($memoryFile);
		unlink($file['tmp_name']);
		$this->fileData->hash = hash('sha256', $this->fileContent);
		try {
			$libresignFile = $this->fileMapper->getBySignedHash($this->fileData->hash);
			$this->setFile($libresignFile);
		} catch (DoesNotExistException) {
			$this->fileData->status = FileStatus::NOT_LIBRESIGN_FILE->value;
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

	public function isLibresignFile(int $nodeId): bool {
		return $this->fileMapper->nodeIdExists($nodeId);
	}

	public function getSignedNodeId(): ?int {
		$status = $this->file->getStatus();

		if (!in_array($status, [FileStatus::PARTIAL_SIGNED->value, FileStatus::SIGNED->value])) {
			return null;
		}
		return $this->file->getSignedNodeId();
	}

	private function loadSigners(): void {
		if (!$this->options->isShowSigners()) {
			return;
		}

		if (!$this->file instanceof File) {
			return;
		}

		$this->signersLoader->loadLibreSignSigners($this->file, $this->fileData, $this->options, $this->certData);

		if ($this->file->getSignedNodeId()) {
			$fileNode = $this->getFile();
			$certData = $this->certificateChainService->getCertificateChain($fileNode, $this->file, $this->options);
			if ($certData) {
				$this->signersLoader->loadSignersFromCertData($this->fileData, $certData, $this->options->getHost());
			}
		}
		$this->loadSignRequestData();
	}

	private function loadSignRequestData(): void {
		if (empty($this->fileData->uuid)) {
			return;
		}

		$this->fileData->url = $this->urlGenerator->linkToRoute('libresign.page.getPdfFile', ['uuid' => $this->fileData->uuid]);

		if (!empty($this->fileData->signers) && is_array($this->fileData->signers)) {
			foreach ($this->fileData->signers as $signer) {
				if (!empty($signer->me) && isset($signer->sign_uuid)) {
					$this->fileData->signUuid = $signer->sign_uuid;
					break;
				}
			}
		}
	}

	private function loadFileMetadata(): void {
		$this->metadataLoader->loadMetadata($this->file, $this->fileData);
	}

	private function loadSettings(): void {
		$this->settingsLoader->loadSettings($this->fileData, $this->options);
	}

	private function loadLibreSignData(): void {
		if (!$this->file) {
			return;
		}
		$this->fileData->id = $this->file->getId();
		$this->fileData->uuid = $this->file->getUuid();
		$this->fileData->name = $this->file->getName();
		$this->fileData->status = $this->file->getStatus();
		$this->fileData->created_at = $this->file->getCreatedAt()->format(DateTimeInterface::ATOM);
		$this->fileData->statusText = $this->fileMapper->getTextOfStatus($this->file->getStatus());
		$this->fileData->nodeId = $this->file->getNodeId();
		$this->fileData->signatureFlow = $this->file->getSignatureFlow();
		$this->fileData->docmdpLevel = $this->file->getDocmdpLevel();
		$this->fileData->nodeType = $this->file->getNodeType();

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
			if ($this->file->getStatus() === FileStatus::SIGNED->value) {
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

		if (!isset($this->fileData->files) || !is_array($this->fileData->files)) {
			$this->fileData->files = [];
		}
	}

	private function loadVisibleElements(): void {
		if (!$this->options->isShowVisibleElements()) {
			return;
		}
		$signers = $this->signRequestMapper->getByMultipleFileId([$this->file->getId()]);
		$this->fileData->visibleElements = [];
		$fileMetadata = $this->file->getMetadata();
		foreach ($this->signRequestMapper->getVisibleElementsFromSigners($signers) as $visibleElements) {
			if (empty($visibleElements)) {
				continue;
			}
			$this->fileData->visibleElements = array_merge(
				$this->fileElementService->formatVisibleElements($visibleElements, $fileMetadata),
				$this->fileData->visibleElements
			);
		}
	}

	private function getLatestSignedDateFromEnvelope(): ?\DateTime {
		if (!$this->file || $this->file->getNodeType() !== 'envelope') {
			return null;
		}

		$childrenFiles = $this->fileMapper->getChildrenFiles($this->file->getId());
		$latestDate = null;

		$childFileIds = array_map(fn ($childFile) => $childFile->getId(), $childrenFiles);
		if (empty($childFileIds)) {
			return null;
		}

		$signRequests = $this->signRequestMapper->getByMultipleFileId($childFileIds);
		foreach ($signRequests as $signRequest) {
			$signed = $signRequest->getSigned();
			if ($signed && (!$latestDate || $signed > $latestDate)) {
				$latestDate = $signed;
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
		return $this->envelopeAssembler->buildEnvelopeChildData($childFile, $this->options);
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
		$this->messagesLoader->loadMessages($this->file, $this->fileData, $this->options, $this->certData);
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
		$this->loadVisibleElements();
		$this->loadMessages();
		$this->computeEnvelopeSignersProgress();

		$return = json_decode(json_encode($this->fileData), true);
		ksort($return);
		return $return;
	}

	private function computeEnvelopeSignersProgress(): void {
		if (!$this->file || $this->file->getParentFileId() || empty($this->fileData->signers)) {
			return;
		}

		$childrenFiles = $this->fileMapper->getChildrenFiles($this->file->getId());
		if (empty($childrenFiles)) {
			return;
		}

		$childFileIds = array_map(fn ($childFile) => $childFile->getId(), $childrenFiles);
		$allSignRequests = $this->signRequestMapper->getByMultipleFileId($childFileIds);

		$signRequestsByFileId = array_fill_keys($childFileIds, []);
		foreach ($allSignRequests as $signRequest) {
			$signRequestsByFileId[$signRequest->getFileId()][] = $signRequest;
		}

		$identifyMethodsBySignRequest = [];
		if (!empty($allSignRequests)) {
			$allSignRequestIds = array_map(fn ($sr) => $sr->getId(), $allSignRequests);
			$identifyMethodsBySignRequest = $this->identifyMethodService
				->setIsRequest(false)
				->getIdentifyMethodsFromSignRequestIds($allSignRequestIds);
		}

		$this->envelopeProgressService->computeProgress(
			$this->fileData,
			$this->file,
			$childrenFiles,
			$signRequestsByFileId,
			$identifyMethodsBySignRequest
		);
	}

	public function delete(int $fileId, bool $deleteFile = true): void {
		$file = $this->fileMapper->getById($fileId);

		$this->decrementEnvelopeFilesCountIfNeeded($file);

		if ($file->getNodeType() === 'envelope') {
			$childrenFiles = $this->fileMapper->getChildrenFiles($file->getId());
			foreach ($childrenFiles as $childFile) {
				$this->delete($childFile->getId(), $deleteFile);
			}
		}

		$this->fileElementService->deleteVisibleElements($file->getId());
		$list = $this->signRequestMapper->getByFileId($file->getId());
		foreach ($list as $signRequest) {
			$this->identifyMethodService->deleteBySignRequestId($signRequest->getId());
			$this->signRequestMapper->delete($signRequest);
		}
		$this->idDocsMapper->deleteByFileId($file->getId());
		$this->fileMapper->delete($file);
		if ($deleteFile) {
			if ($file->getSignedNodeId()) {
				try {
					$signedNextcloudFile = $this->folderService->getFileByNodeId($file->getSignedNodeId());
					$parentFolder = $signedNextcloudFile->getParent();
					$signedNextcloudFile->delete();
					$this->deleteEmptyFolder($parentFolder);
				} catch (NotFoundException) {
				}
			}
			try {
				$nextcloudFile = $this->folderService->getFileByNodeId($file->getNodeId());
				$parentFolder = $nextcloudFile->getParent();
				$nextcloudFile->delete();
				$this->deleteEmptyFolder($parentFolder);
			} catch (NotFoundException) {
			}
		}
	}

	private function deleteEmptyFolder(\OCP\Files\Folder $folder): void {
		try {
			$contents = $folder->getDirectoryListing();
			if (count($contents) === 0) {
				$folder->delete();
			}
		} catch (\Exception $e) {
			$this->logger->debug('Could not delete empty folder: ' . $e->getMessage(), [
				'exception' => $e,
			]);
		}
	}

	public function processUploadedFilesWithRollback(array $filesArray, IUser $user, array $settings): array {
		return $this->uploadProcessor->processUploadedFilesWithRollback($filesArray, $user, $settings);
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
