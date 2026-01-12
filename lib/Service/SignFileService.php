<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use DateTime;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use OC\AppFramework\Http as AppFrameworkHttp;
use OC\User\NoUserException;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCA\Libresign\DataObjects\VisibleElementAssoc;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocs;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Events\SignedEventFactory;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Handler\PdfTk\Pdf;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Handler\SignEngine\SignEngineFactory;
use OCA\Libresign\Handler\SignEngine\SignEngineHandler;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\Envelope\EnvelopeStatusDeterminer;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\IToken;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\Events\GenerateSecurePasswordEvent;
use OCP\Security\ICredentialsManager;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sabre\DAV\UUIDUtil;

class SignFileService {
	private ?SignRequestEntity $signRequest = null;
	private string $password = '';
	private ?FileEntity $libreSignFile = null;
	/** @var VisibleElementAssoc[] */
	private $elements = [];
	private bool $signWithoutPassword = false;
	private ?File $fileToSign = null;
	private ?File $createdSignedFile = null;
	private string $userUniqueIdentifier = '';
	private string $friendlyName = '';
	private ?IUser $user = null;
	private ?SignEngineHandler $engine = null;
	private ICache $cache;

	public function __construct(
		protected IL10N $l10n,
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private IdDocsMapper $idDocsMapper,
		private FooterHandler $footerHandler,
		protected FolderService $folderService,
		private IClientService $client,
		private IUserManager $userManager,
		protected LoggerInterface $logger,
		private IAppConfig $appConfig,
		protected ValidateHelper $validateHelper,
		private SignerElementsService $signerElementsService,
		private IRootFolder $root,
		private IUserSession $userSession,
		private IDateTimeZone $dateTimeZone,
		private FileElementMapper $fileElementMapper,
		private UserElementMapper $userElementMapper,
		private IEventDispatcher $eventDispatcher,
		protected ISecureRandom $secureRandom,
		private IURLGenerator $urlGenerator,
		private IdentifyMethodMapper $identifyMethodMapper,
		private ITempManager $tempManager,
		private SigningCoordinatorService $signingCoordinatorService,
		private IdentifyMethodService $identifyMethodService,
		private ITimeFactory $timeFactory,
		protected SignEngineFactory $signEngineFactory,
		private SignedEventFactory $signedEventFactory,
		private Pdf $pdf,
		private DocMdpHandler $docMdpHandler,
		private PdfSignatureDetectionService $pdfSignatureDetectionService,
		private SequentialSigningService $sequentialSigningService,
		private FileStatusService $fileStatusService,
		private IJobList $jobList,
		private ICredentialsManager $credentialsManager,
		private EnvelopeStatusDeterminer $envelopeStatusDeterminer,
		private TsaValidationService $tsaValidationService,
		ICacheFactory $cacheFactory,
	) {
		$this->cache = $cacheFactory->createDistributed('libresign_progress');
	}

	/**
	 * Can delete sing request
	 */
	public function canDeleteRequestSignature(array $data): void {
		if (!empty($data['uuid'])) {
			$signatures = $this->signRequestMapper->getByFileUuid($data['uuid']);
		} elseif (!empty($data['file']['fileId'])) {
			$signatures = $this->signRequestMapper->getByNodeId($data['file']['fileId']);
		} else {
			throw new \Exception($this->l10n->t('Please provide either UUID or File object'));
		}
		$signed = array_filter($signatures, fn ($s) => $s->getSigned());
		if ($signed) {
			throw new \Exception($this->l10n->t('Document already signed'));
		}
		array_walk($data['users'], function ($user) use ($signatures): void {
			$exists = array_filter($signatures, function (SignRequestEntity $signRequest) use ($user) {
				$identifyMethod = $this->identifyMethodService->getIdentifiedMethod($signRequest->getId());
				if ($identifyMethod->getName() === 'email') {
					return $identifyMethod->getEntity()->getIdentifierValue() === $user['email'];
				}
				return false;
			});
			if (!$exists) {
				throw new \Exception($this->l10n->t('No signature was requested to %s', $user['email']));
			}
		});
	}

	public function notifyCallback(File $file): void {
		$uri = $this->libreSignFile->getCallback();
		if (!$uri) {
			$uri = $this->appConfig->getValueString(Application::APP_ID, 'webhook_sign_url');
			if (!$uri) {
				return;
			}
		}
		$options = [
			'multipart' => [
				[
					'name' => 'uuid',
					'contents' => $this->libreSignFile->getUuid(),
				],
				[
					'name' => 'status',
					'contents' => $this->libreSignFile->getStatus(),
				],
				[
					'name' => 'file',
					'contents' => $file->fopen('r'),
					'filename' => $file->getName()
				]
			]
		];
		$this->client->newClient()->post($uri, $options);
	}

	/**
	 * @return static
	 */
	public function setLibreSignFile(FileEntity $libreSignFile): self {
		$this->libreSignFile = $libreSignFile;
		return $this;
	}

	public function setUserUniqueIdentifier(string $identifier): self {
		$this->userUniqueIdentifier = $identifier;
		return $this;
	}

	public function setFriendlyName(string $friendlyName): self {
		$this->friendlyName = $friendlyName;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setSignRequest(SignRequestEntity $signRequest): self {
		$this->signRequest = $signRequest;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setSignWithoutPassword(bool $signWithoutPassword = true): self {
		$this->signWithoutPassword = $signWithoutPassword;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setPassword(?string $password = null): self {
		$this->password = $password;
		return $this;
	}

	public function setCurrentUser(?IUser $user): self {
		$this->user = $user;
		return $this;
	}

	public function setVisibleElements(array $list): self {
		if (!$this->signRequest instanceof SignRequestEntity) {
			return $this;
		}
		$fileElements = $this->fileElementMapper->getByFileIdAndSignRequestId($this->signRequest->getFileId(), $this->signRequest->getId());
		$canCreateSignature = $this->signerElementsService->canCreateSignature();

		foreach ($fileElements as $fileElement) {
			$this->elements[] = $this->buildVisibleElementAssoc($fileElement, $list, $canCreateSignature);
		}

		return $this;
	}

	private function buildVisibleElementAssoc(FileElement $fileElement, array $list, bool $canCreateSignature): VisibleElementAssoc {
		if (!$canCreateSignature) {
			return new VisibleElementAssoc($fileElement);
		}

		$element = $this->array_find($list, fn (array $element): bool => ($element['documentElementId'] ?? '') === $fileElement->getId());
		$nodeId = $this->getNodeId($element, $fileElement);

		return $this->bindFileElementWithTempFile($fileElement, $nodeId);
	}

	private function getNodeId(?array $element, FileElement $fileElement): int {
		if ($this->isValidElement($element)) {
			return (int)$element['profileNodeId'];
		}

		return $this->retrieveUserElement($fileElement);
	}

	private function isValidElement(?array $element): bool {
		if (is_array($element) && !empty($element['profileNodeId']) && is_int($element['profileNodeId'])) {
			return true;
		}
		$this->logger->error('Invalid data provided for signing file.', ['element' => $element]);
		throw new LibresignException($this->l10n->t('Invalid data to sign file'), 1);
	}

	private function retrieveUserElement(FileElement $fileElement): int {
		try {
			if (!$this->user instanceof IUser) {
				throw new Exception('User not set');
			}
			$userElement = $this->userElementMapper->findOne([
				'user_id' => $this->user->getUID(),
				'type' => $fileElement->getType(),
			]);
		} catch (MultipleObjectsReturnedException|DoesNotExistException|Exception) {
			throw new LibresignException($this->l10n->t('You need to define a visible signature or initials to sign this document.'));
		}
		return $userElement->getNodeId();
	}

	private function bindFileElementWithTempFile(FileElement $fileElement, int $nodeId): VisibleElementAssoc {
		try {
			$node = $this->getNode($nodeId);
			if (!$node) {
				throw new \Exception('Node content is empty or unavailable.');
			}
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('You need to define a visible signature or initials to sign this document.'));
		}

		$tempFile = $this->tempManager->getTemporaryFile('_' . $nodeId . '.png');
		$content = $node->getContent();
		if (empty($content)) {
			$this->logger->error('Failed to retrieve content for node.', ['nodeId' => $nodeId, 'fileElement' => $fileElement]);
			throw new LibresignException($this->l10n->t('You need to define a visible signature or initials to sign this document.'));
		}
		file_put_contents($tempFile, $content);
		return new VisibleElementAssoc($fileElement, $tempFile);
	}

	private function getNode(int $nodeId): ?File {
		if ($this->user instanceof IUser) {
			return $this->folderService->getFileByNodeId($nodeId);
		}

		$filesOfElementes = $this->signerElementsService->getElementsFromSession();
		return $this->array_find($filesOfElementes, fn ($file) => $file->getId() === $nodeId);
	}

	/**
	 * Fallback to PHP < 8.4
	 *
	 * Reference: https://www.php.net/manual/en/function.array-find.php#130257
	 *
	 * @todo remove this after minor PHP version is >= 8.4
	 * @deprecated This method will be removed once the minimum PHP version is >= 8.4. Use native array_find instead.
	 */
	private function array_find(array $array, callable $callback): mixed {
		foreach ($array as $key => $value) {
			if ($callback($value, $key)) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * @return VisibleElementAssoc[]
	 */
	public function getVisibleElements(): array {
		return $this->elements;
	}

	/**
	 * Collect job arguments from the current service state WITHOUT credentials.
	 *
	 * Credentials are NOT included here. Instead, they are created per-file inside
	 * enqueueParallelSigningJobs() using ICredentialsManager to ensure proper lifecycle
	 * and secure storage. This prevents credentials from being stored in job queue records.
	 *
	 * @return array Arguments for background job queue (user, metadata, elements, etc.)
	 */
	public function getJobArgumentsWithoutCredentials(): array {
		$args = [];

		if (!empty($this->userUniqueIdentifier)) {
			$args['userUniqueIdentifier'] = $this->userUniqueIdentifier;
		}

		if (!empty($this->friendlyName)) {
			$args['friendlyName'] = $this->friendlyName;
		}

		if (!empty($this->elements)) {
			$args['visibleElements'] = $this->elements;
		}

		if ($this->signRequest instanceof SignRequestEntity && $this->signRequest->getMetadata()) {
			$args['metadata'] = $this->signRequest->getMetadata();
		}

		if ($this->user instanceof IUser) {
			$args['userId'] = $this->user->getUID();
		}

		return $args;
	}

	/**
	 * Validate all requirements before signing (e.g., TSA configuration)
	 * Throws exception if signing cannot proceed
	 */
	public function validateSigningRequirements(): void {
		$this->tsaValidationService->validateConfiguration();
	}

	public function sign(): void {
		$signRequests = $this->getSignRequestsToSign();

		if (empty($signRequests)) {
			throw new LibresignException('No sign requests found to process');
		}

		$envelopeLastSignedDate = $this->executeSigningStrategy($signRequests);

		$envelopeContext = $this->getEnvelopeContext();
		if ($envelopeContext['envelope'] instanceof FileEntity) {
			$this->updateEnvelopeStatus(
				$envelopeContext['envelope'],
				$envelopeContext['envelopeSignRequest'] ?? null,
				$envelopeLastSignedDate
			);
		}
	}

	private function executeSigningStrategy(array $signRequests): ?DateTimeInterface {
		if ($this->signingCoordinatorService->shouldUseParallelProcessing(count($signRequests))) {
			return $this->processParallelSigning($signRequests);
		}
		return $this->signSequentially($signRequests);
	}

	private function processParallelSigning(array $signRequests): ?DateTimeInterface {
		$this->enqueueParallelSigningJobs($signRequests, $this->getJobArgumentsWithoutCredentials());
		return $this->getLatestSignedDate($signRequests);
	}

	private function getLatestSignedDate(array $signRequests): ?DateTimeInterface {
		$latestSignedDate = null;

		foreach ($signRequests as $signRequestData) {
			try {
				$this->signRequestMapper->flushCache($signRequestData['signRequest']->getId());
				$signRequest = $this->signRequestMapper->getById($signRequestData['signRequest']->getId());
				if ($signRequest->getSigned()) {
					$latestSignedDate = $signRequest->getSigned();
				}
			} catch (DoesNotExistException) {
			}
		}

		return $latestSignedDate;
	}

	/**
	 * Sign a single file without processing envelope children.
	 * Used by SignSingleFileJob for parallel processing.
	 */
	public function signSingleFile(FileEntity $libreSignFile, SignRequestEntity $signRequest): void {
		$previousState = $this->saveCachedState();
		$this->resetCachedState();

		if ($libreSignFile->getSignedHash()) {
			$this->restoreCachedState($previousState);
			return;
		}

		$previousLibreSignFile = $this->libreSignFile;
		$previousSignRequest = $this->signRequest;
		$this->libreSignFile = $libreSignFile;
		$this->signRequest = $signRequest;

		try {
			$this->validateDocMdpAllowsSignatures();

			try {
				$signedFile = $this->getEngine()->sign();
			} catch (LibresignException|Exception $e) {
				$this->cleanupUnsignedSignedFile();
				$this->recordSignatureAttempt($e);
				throw $e;
			}

			$hash = $this->computeHash($signedFile);
			$this->updateSignRequest($hash);
			$this->updateLibreSignFile($libreSignFile, $signedFile->getId(), $hash);

			$this->dispatchSignedEvent();

			// Update envelope status after signing each child file (for parallel processing)
			$envelopeContext = $this->getEnvelopeContext();
			if ($envelopeContext['envelope'] instanceof FileEntity) {
				$this->updateEnvelopeStatus(
					$envelopeContext['envelope'],
					$envelopeContext['envelopeSignRequest'] ?? null,
					$signRequest->getSigned()
				);
			}
		} finally {
			$this->libreSignFile = $previousLibreSignFile;
			$this->signRequest = $previousSignRequest;
			$this->restoreCachedState($previousState);
		}
	}

	private function saveCachedState(): array {
		return [
			'fileToSign' => $this->fileToSign,
			'createdSignedFile' => $this->createdSignedFile,
			'engine' => $this->engine,
		];
	}

	private function resetCachedState(): void {
		$this->fileToSign = null;
		$this->createdSignedFile = null;
		$this->engine = null;
	}

	private function restoreCachedState(array $state): void {
		$this->fileToSign = $state['fileToSign'];
		$this->createdSignedFile = $state['createdSignedFile'];
		$this->engine = $state['engine'];
	}

	/**
	 * Enqueue individual signing jobs for parallel processing.
	 * Each file in an envelope gets its own job that runs in parallel via background workers.
	 *
	 * @param array $signRequests Pre-calculated sign requests to avoid re-querying
	 * @param array $jobArguments Arguments to pass to each job (userId, password, etc.)
	 * @return int Number of jobs enqueued
	 */
	public function enqueueParallelSigningJobs(array $signRequests, array $jobArguments = []): int {

		if (empty($signRequests)) {
			throw new LibresignException('No sign requests found to process');
		}

		$enqueued = 0;
		foreach ($signRequests as $signRequestData) {
			$file = $signRequestData['file'];
			$signRequest = $signRequestData['signRequest'];

			if ($file->getSignedHash()) {
				continue;
			}

			// Verify file exists before enqueuing job to prevent NotFoundException
			$nodeId = $file->getNodeId();
			$userId = $file->getUserId() ?? $signRequest->getUserId();

			if ($nodeId === null || !$this->verifyFileExists($userId, $nodeId)) {
				continue;
			}

			$this->enqueueSigningJobForFile($signRequest, $file, $jobArguments);
			$enqueued++;
		}

		return $enqueued;
	}

	private function enqueueSigningJobForFile(SignRequestEntity $signRequest, FileEntity $file, array $jobArguments): void {
		$args = $jobArguments;
		$args = $this->addCredentialsToJobArgs($args, $signRequest, $file);
		$args = array_merge($args, [
			'fileId' => $file->getId(),
			'signRequestId' => $signRequest->getId(),
		]);

		$this->jobList->add(SignSingleFileJob::class, $args);
	}

	private function addCredentialsToJobArgs(array $args, SignRequestEntity $signRequest, FileEntity $file): array {
		if (!($this->signWithoutPassword || !empty($this->password))) {
			return $args;
		}

		$credentialsId = 'libresign_sign_' . $signRequest->getId() . '_' . $file->getId() . '_' . $this->secureRandom->generate(8, ISecureRandom::CHAR_ALPHANUMERIC);
		$this->credentialsManager->store(
			$this->user?->getUID() ?? '',
			$credentialsId,
			[
				'signWithoutPassword' => $this->signWithoutPassword,
				'password' => $this->password,
				'timestamp' => time(),
				'expires' => time() + 3600,
			]
		);
		$args['credentialsId'] = $credentialsId;

		return $args;
	}

	/**
	 * Sign multiple files sequentially
	 *
	 * @return DateTimeInterface|null Last signed date
	 */
	private function signSequentially(array $signRequests): ?DateTimeInterface {
		$envelopeLastSignedDate = null;

		foreach ($signRequests as $index => $signRequestData) {
			$this->libreSignFile = $signRequestData['file'];
			if ($this->libreSignFile->getSignedHash()) {
				continue;
			}
			$this->signRequest = $signRequestData['signRequest'];
			$this->engine = null;
			$this->elements = [];
			$this->fileToSign = null;

			$this->validateDocMdpAllowsSignatures();

			try {
				$signedFile = $this->getEngine()->sign();
			} catch (LibresignException|Exception $e) {
				$this->cleanupUnsignedSignedFile();
				$this->recordSignatureAttempt($e);

				$isEnvelope = $this->libreSignFile->isEnvelope() || $this->libreSignFile->hasParent();
				if (!$isEnvelope) {
					throw $e;
				}
				continue;
			}

			$hash = $this->computeHash($signedFile);
			$envelopeLastSignedDate = $this->getEngine()->getLastSignedDate();

			$this->updateSignRequest($hash);
			$this->updateLibreSignFile($this->libreSignFile, $signedFile->getId(), $hash);

			$this->dispatchSignedEvent();
		}

		return $envelopeLastSignedDate;
	}



	/**
	 * Get sign requests to process.
	 *
	 * @return array Array of sign request data with 'file' => FileEntity, 'signRequest' => SignRequestEntity
	 */
	private function getSignRequestsToSign(): array {
		if (!$this->libreSignFile->isEnvelope()
			&& !$this->libreSignFile->hasParent()
		) {
			return [[
				'file' => $this->libreSignFile,
				'signRequest' => $this->signRequest,
			]];
		}

		return $this->buildEnvelopeSignRequests();
	}

	/**
	 * @return array Array of sign request data with 'file' => FileEntity, 'signRequest' => SignRequestEntity
	 */
	private function buildEnvelopeSignRequests(): array {
		$envelopeId = $this->libreSignFile->isEnvelope()
			? $this->libreSignFile->getId()
			: $this->libreSignFile->getParentFileId();

		$childFiles = $this->fileMapper->getChildrenFiles($envelopeId);
		if (empty($childFiles)) {
			throw new LibresignException('No files found in envelope');
		}

		$childSignRequests = $this->signRequestMapper->getByEnvelopeChildrenAndIdentifyMethod(
			$envelopeId,
			$this->signRequest->getId()
		);

		if (empty($childSignRequests)) {
			throw new LibresignException('No sign requests found for envelope files');
		}

		$signRequestsData = [];
		foreach ($childSignRequests as $childSignRequest) {
			$childFile = $this->array_find(
				$childFiles,
				fn (FileEntity $file) => $file->getId() === $childSignRequest->getFileId()
			);

			if ($childFile) {
				$signRequestsData[] = [
					'file' => $childFile,
					'signRequest' => $childSignRequest,
				];
			}
		}

		return $signRequestsData;
	}

	/**
	 * Get envelope context if the current file is or belongs to an envelope.
	 *
	 * @return array Array with 'envelope' => FileEntity or null, 'envelopeSignRequest' => SignRequestEntity or null
	 */
	private function getEnvelopeContext(): array {
		$result = [
			'envelope' => null,
			'envelopeSignRequest' => null,
		];

		if (!$this->libreSignFile->isEnvelope() && !$this->libreSignFile->hasParent()) {
			return $result;
		}

		if ($this->libreSignFile->isEnvelope()) {
			$result['envelope'] = $this->libreSignFile;
			$result['envelopeSignRequest'] = $this->signRequest;
			return $result;
		}

		try {
			$envelopeId = $this->libreSignFile->isEnvelope()
				? $this->libreSignFile->getId()
				: $this->libreSignFile->getParentFileId();
			$result['envelope'] = $this->fileMapper->getById($envelopeId);
			$identifyMethod = $this->identifyMethodService->getIdentifiedMethod($this->signRequest->getId());
			$result['envelopeSignRequest'] = $this->signRequestMapper->getByIdentifyMethodAndFileId(
				$identifyMethod,
				$result['envelope']->getId()
			);
		} catch (DoesNotExistException $e) {
			// Envelope not found or sign request not found, leave as null
		}

		return $result;
	}

	private function updateEnvelopeStatus(FileEntity $envelope, ?SignRequestEntity $envelopeSignRequest = null, ?DateTimeInterface $signedDate = null): void {
		$childFiles = $this->fileMapper->getChildrenFiles($envelope->getId());
		$signRequestsMap = $this->buildSignRequestsMap($childFiles);

		$status = $this->envelopeStatusDeterminer->determineStatus($childFiles, $signRequestsMap);
		$envelope->setStatus($status);

		$this->handleSignedEnvelopeSignRequest($envelope, $envelopeSignRequest, $signedDate, $status);

		$this->updateEnvelopeMetadata($envelope);
		$this->fileMapper->update($envelope);
		$this->updateEntityCacheAfterDbSave($envelope);
	}

	private function buildSignRequestsMap(array $childFiles): array {
		$signRequestsMap = [];
		foreach ($childFiles as $childFile) {
			$signRequestsMap[$childFile->getId()] = $this->signRequestMapper->getByFileId($childFile->getId());
		}
		return $signRequestsMap;
	}

	private function handleSignedEnvelopeSignRequest(FileEntity $envelope, ?SignRequestEntity $envelopeSignRequest, ?DateTimeInterface $signedDate, int $status): void {
		if ($status !== FileStatus::SIGNED->value || !($envelopeSignRequest instanceof SignRequestEntity)) {
			return;
		}

		$envelopeSignRequest->setSigned($signedDate ?: new DateTime());
		$envelopeSignRequest->setStatusEnum(\OCA\Libresign\Enum\SignRequestStatus::SIGNED);
		$this->signRequestMapper->update($envelopeSignRequest);
		$this->sequentialSigningService
			->setFile($envelope)
			->releaseNextOrder(
				$envelopeSignRequest->getFileId(),
				$envelopeSignRequest->getSigningOrder()
			);
	}

	private function updateEnvelopeMetadata(FileEntity $envelope): void {
		$meta = $envelope->getMetadata() ?? [];
		$meta['status_changed_at'] = (new DateTime())->format(DateTimeInterface::ATOM);
		$envelope->setMetadata($meta);
	}

	/**
	 * @throws LibresignException If the document has DocMDP level 1 (no changes allowed)
	 */
	protected function validateDocMdpAllowsSignatures(): void {
		$docmdpLevel = $this->libreSignFile->getDocmdpLevelEnum();

		if ($docmdpLevel === \OCA\Libresign\Enum\DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED) {
			throw new LibresignException(
				$this->l10n->t('This document has been certified with no changes allowed. You cannot add more signers to this document.'),
				AppFrameworkHttp::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		if ($docmdpLevel === \OCA\Libresign\Enum\DocMdpLevel::NOT_CERTIFIED) {
			$resource = $this->getLibreSignFileAsResource();

			try {
				if (!$this->docMdpHandler->allowsAdditionalSignatures($resource)) {
					throw new LibresignException(
						$this->l10n->t('This document has been certified with no changes allowed. You cannot add more signers to this document.'),
						AppFrameworkHttp::STATUS_UNPROCESSABLE_ENTITY
					);
				}
			} finally {
				fclose($resource);
			}
		}
	}

	/**
	 * @return resource
	 * @throws LibresignException
	 */
	protected function getLibreSignFileAsResource() {
		$files = $this->getNextcloudFiles($this->libreSignFile);
		if (empty($files)) {
			throw new LibresignException('File not found');
		}
		$fileToSign = current($files);
		$content = $fileToSign->getContent();
		$resource = fopen('php://memory', 'r+');
		if ($resource === false) {
			throw new LibresignException('Failed to create temporary resource for PDF validation');
		}
		fwrite($resource, $content);
		rewind($resource);
		return $resource;
	}

	protected function computeHash(File $file): string {
		return hash('sha256', $file->getContent());
	}

	protected function updateSignRequest(string $hash): void {
		$lastSignedDate = $this->getEngine()->getLastSignedDate();
		$this->signRequest->setSigned($lastSignedDate);
		$this->signRequest->setSignedHash($hash);
		$this->signRequest->setStatusEnum(\OCA\Libresign\Enum\SignRequestStatus::SIGNED);

		$this->signRequestMapper->update($this->signRequest);

		$this->sequentialSigningService
			->setFile($this->libreSignFile)
			->releaseNextOrder(
				$this->signRequest->getFileId(),
				$this->signRequest->getSigningOrder()
			);
	}

	protected function updateLibreSignFile(FileEntity $libreSignFile, int $nodeId, string $hash): void {
		$libreSignFile->setSignedNodeId($nodeId);
		$libreSignFile->setSignedHash($hash);
		$this->setNewStatusIfNecessary($libreSignFile);
		$meta = $libreSignFile->getMetadata() ?? [];
		$meta['status_changed_at'] = (new \DateTime())->format(\DateTimeInterface::ATOM);
		$libreSignFile->setMetadata($meta);
		$this->fileMapper->update($libreSignFile);
		$this->updateCacheAfterDbSave($libreSignFile); // Update cache AFTER DB save

		if ($libreSignFile->hasParent()) {
			$this->fileStatusService->propagateStatusToParent($libreSignFile->getParentFileId());
		}
	}

	protected function dispatchSignedEvent(): void {
		$event = $this->signedEventFactory->make(
			$this->signRequest,
			$this->libreSignFile,
			$this->getEngine()->getInputFile(),
		);
		$this->eventDispatcher->dispatchTyped($event);
	}

	protected function identifyEngine(File $file): SignEngineHandler {
		return $this->signEngineFactory->resolve($file->getExtension());
	}

	protected function getSignatureParams(): array {
		$certificateData = $this->readCertificate();
		$signatureParams = $this->buildBaseSignatureParams($certificateData);
		$signatureParams = $this->addEmailToSignatureParams($signatureParams, $certificateData);
		$signatureParams = $this->addMetadataToSignatureParams($signatureParams);
		return $signatureParams;
	}

	private function buildBaseSignatureParams(array $certificateData): array {
		return [
			'DocumentUUID' => $this->libreSignFile?->getUuid(),
			'IssuerCommonName' => $certificateData['issuer']['CN'] ?? '',
			'SignerCommonName' => $certificateData['subject']['CN'] ?? '',
			'LocalSignerTimezone' => $this->dateTimeZone->getTimeZone()->getName(),
			'LocalSignerSignatureDateTime' => (new DateTime('now', new \DateTimeZone('UTC')))
				->format(DateTimeInterface::ATOM)
		];
	}

	private function addEmailToSignatureParams(array $signatureParams, array $certificateData): array {
		if (isset($certificateData['extensions']['subjectAltName'])) {
			preg_match('/(?:email:)+(?<email>[^\s,]+)/', $certificateData['extensions']['subjectAltName'], $matches);
			if ($matches && filter_var($matches['email'], FILTER_VALIDATE_EMAIL)) {
				$signatureParams['SignerEmail'] = $matches['email'];
			} elseif (filter_var($certificateData['extensions']['subjectAltName'], FILTER_VALIDATE_EMAIL)) {
				$signatureParams['SignerEmail'] = $certificateData['extensions']['subjectAltName'];
			}
		}
		if (empty($signatureParams['SignerEmail']) && $this->user instanceof IUser) {
			$signatureParams['SignerEmail'] = $this->user->getEMailAddress();
		}
		if (empty($signatureParams['SignerEmail']) && $this->signRequest instanceof SignRequestEntity) {
			$identifyMethod = $this->identifyMethodService->getIdentifiedMethod($this->signRequest->getId());
			if ($identifyMethod->getName() === IdentifyMethodService::IDENTIFY_EMAIL) {
				$signatureParams['SignerEmail'] = $identifyMethod->getEntity()->getIdentifierValue();
			}
		}
		return $signatureParams;
	}

	private function addMetadataToSignatureParams(array $signatureParams): array {
		$signRequestMetadata = $this->signRequest->getMetadata();
		if (isset($signRequestMetadata['remote-address'])) {
			$signatureParams['SignerIP'] = $signRequestMetadata['remote-address'];
		}
		if (isset($signRequestMetadata['user-agent'])) {
			$signatureParams['SignerUserAgent'] = $signRequestMetadata['user-agent'];
		}
		return $signatureParams;
	}

	public function storeUserMetadata(array $metadata = []): self {
		$collectMetadata = $this->appConfig->getValueBool(Application::APP_ID, 'collect_metadata', false);
		if (!$collectMetadata || !$metadata) {
			return $this;
		}
		$this->signRequest->setMetadata(array_merge(
			$this->signRequest->getMetadata() ?? [],
			$metadata,
		));
		$this->signRequestMapper->update($this->signRequest);
		return $this;
	}

	/**
	 * @return SignRequestEntity[]
	 */
	protected function getSigners(): array {
		return $this->signRequestMapper->getByFileId($this->signRequest->getFileId());
	}

	protected function setNewStatusIfNecessary(FileEntity $libreSignFile): bool {
		$newStatus = $this->evaluateStatusFromSigners();

		if ($newStatus === null || $newStatus === $libreSignFile->getStatus()) {
			return false;
		}

		$libreSignFile->setStatus($newStatus);

		return true;
	}

	private function updateCacheAfterDbSave(FileEntity $libreSignFile): void {
		// Update cache AFTER database save to avoid race condition
		// where polling reads stale data from DB while cache has new value
		$cacheKey = 'status_' . $libreSignFile->getUuid();
		$status = $libreSignFile->getStatus();
		$this->cache->set($cacheKey, $status, 60); // Cache for 60 seconds
	}

	private function updateEntityCacheAfterDbSave(FileEntity $file): void {
		$cacheKey = 'status_' . $file->getUuid();
		$status = $file->getStatus();
		$this->cache->set($cacheKey, $status, 60);
	}

	private function evaluateStatusFromSigners(): ?int {
		$signers = $this->getSigners();

		$total = count($signers);

		if ($total === 0) {
			return null;
		}

		$totalSigned = count(array_filter($signers, fn ($s) => $s->getSigned() !== null));

		if ($totalSigned === $total) {
			return FileStatus::SIGNED->value;
		}

		if ($totalSigned > 0) {
			return FileStatus::PARTIAL_SIGNED->value;
		}

		return null;
	}

	private function getOrGeneratePfxContent(SignEngineHandler $engine): string {
		if ($certificate = $engine->getCertificate()) {
			return $certificate;
		}
		if ($this->signWithoutPassword) {
			$tempPassword = $this->generateTemporaryPassword();
			$this->setPassword($tempPassword);
			$engine->generateCertificate(
				[
					'host' => $this->userUniqueIdentifier,
					'uid' => $this->userUniqueIdentifier,
					'name' => $this->friendlyName,
				],
				$tempPassword,
				$this->friendlyName,
			);
		}
		return $engine->getPfxOfCurrentSigner();
	}

	private function generateTemporaryPassword(): string {
		$passwordEvent = new GenerateSecurePasswordEvent();
		$this->eventDispatcher->dispatchTyped($passwordEvent);
		return $passwordEvent->getPassword() ?? $this->secureRandom->generate(20);
	}

	protected function readCertificate(): array {
		return $this->getEngine()
			->readCertificate();
	}

	/**
	 * Get file to sign
	 *
	 * @throws LibresignException
	 */
	protected function getFileToSign(): File {
		if ($this->fileToSign instanceof File) {
			return $this->fileToSign;
		}

		$userId = $this->libreSignFile->getUserId()
			?? $this->user?->getUID()
			?? ($this->signRequest?->getUserId() ?? null);
		$nodeId = $this->libreSignFile->getNodeId();

		if ($userId === null) {
			throw new LibresignException($this->l10n->t('Invalid data to sign file'), 1);
		}

		try {
			$originalFile = $this->getNodeByIdUsingUid($userId, $nodeId);
		} catch (\Throwable $e) {
			$this->logger->error('[file-access] FAILED to find file - userId={userId} nodeId={nodeId} error={error}', [
				'userId' => $userId,
				'nodeId' => $nodeId,
				'error' => $e->getMessage(),
			]);
			throw $e;
		}

		// If the owner differs from the userId (shared scenario), retry using the owner's UID
		if ($originalFile->getOwner()->getUID() !== $userId) {
			$originalFile = $this->getNodeByIdUsingUid($originalFile->getOwner()->getUID(), $nodeId);
		}
		if ($this->isPdf($originalFile)) {
			$this->fileToSign = $this->getPdfToSign($originalFile);
		} else {
			$this->fileToSign = $originalFile;
		}
		return $this->fileToSign;
	}

	private function isPdf(File $file): bool {
		return strcasecmp($file->getExtension(), 'pdf') === 0;
	}

	protected function getEngine(): SignEngineHandler {
		if (!$this->engine) {
			$originalFile = $this->getFileToSign();
			$this->engine = $this->identifyEngine($originalFile);

			$this->configureEngine();
		}
		return $this->engine;
	}

	private function configureEngine(): void {
		$this->engine
			->setInputFile($this->getFileToSign())
			->setCertificate($this->getOrGeneratePfxContent($this->engine))
			->setPassword($this->password);

		if ($this->engine::class === Pkcs12Handler::class) {
			$this->engine
				->setVisibleElements($this->getVisibleElements())
				->setSignatureParams($this->getSignatureParams());
		}
	}

	public function getLibresignFile(?int $fileId, ?string $signRequestUuid = null): FileEntity {
		try {
			if ($fileId) {
				return $this->fileMapper->getById($fileId);
			}

			if ($signRequestUuid) {
				$signRequest = $this->signRequestMapper->getByUuid($signRequestUuid);
				return $this->fileMapper->getById($signRequest->getFileId());
			}

			throw new \Exception('Invalid arguments');

		} catch (DoesNotExistException) {
			throw new LibresignException($this->l10n->t('File not found'), 1);
		}
	}

	public function renew(SignRequestEntity $signRequest, string $method): void {
		$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
		if (empty($identifyMethods[$method])) {
			throw new LibresignException($this->l10n->t('Invalid identification method'));
		}

		$signRequest->setUuid(UUIDUtil::getUUID());
		$this->signRequestMapper->update($signRequest);

		array_map(function (IIdentifyMethod $identifyMethod): void {
			$entity = $identifyMethod->getEntity();
			$entity->setAttempts($entity->getAttempts() + 1);
			$entity->setLastAttemptDate($this->timeFactory->getDateTime());
			$identifyMethod->save();
		}, $identifyMethods[$method]);
	}

	public function requestCode(
		SignRequestEntity $signRequest,
		string $identifyMethodName,
		string $signMethodName,
		string $identify = '',
	): void {
		$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
		if (empty($identifyMethods[$identifyMethodName])) {
			throw new LibresignException($this->l10n->t('Invalid identification method'));
		}
		foreach ($identifyMethods[$identifyMethodName] as $identifyMethod) {
			try {
				$signatureMethod = $identifyMethod->getEmptyInstanceOfSignatureMethodByName($signMethodName);
				$signatureMethod->setEntity($identifyMethod->getEntity());
			} catch (InvalidArgumentException) {
				continue;
			}
			/** @var IToken $signatureMethod */
			$identifier = $identify ?: $identifyMethod->getEntity()->getIdentifierValue();
			$signatureMethod->requestCode($identifier, $identifyMethod->getEntity()->getIdentifierKey());
			return;
		}
		throw new LibresignException($this->l10n->t('Sending authorization code not enabled.'));
	}

	public function getSignRequestToSign(FileEntity $libresignFile, ?string $signRequestUuid, ?IUser $user): SignRequestEntity {
		$this->validateHelper->fileCanBeSigned($libresignFile);
		try {
			if ($libresignFile->isEnvelope()) {
				$childFiles = $this->fileMapper->getChildrenFiles($libresignFile->getId());
				$allSignRequests = [];
				foreach ($childFiles as $childFile) {
					$childSignRequests = $this->signRequestMapper->getByFileId($childFile->getId());
					$allSignRequests = array_merge($allSignRequests, $childSignRequests);
				}
				$signRequests = $allSignRequests;
			} else {
				$signRequests = $this->signRequestMapper->getByFileId($libresignFile->getId());
			}

			if (!empty($signRequestUuid)) {
				$signRequest = $this->getSignRequestByUuid($signRequestUuid);
			} else {
				$signRequest = array_reduce($signRequests, function (?SignRequestEntity $carry, SignRequestEntity $signRequest) use ($user): ?SignRequestEntity {
					$identifyMethods = $this->identifyMethodMapper->getIdentifyMethodsFromSignRequestId($signRequest->getId());
					$found = array_filter($identifyMethods, function (IdentifyMethod $identifyMethod) use ($user) {
						if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL
							&& $user
							&& (
								$identifyMethod->getIdentifierValue() === $user->getUID()
								|| $identifyMethod->getIdentifierValue() === $user->getEMailAddress()
							)
						) {
							return true;
						}
						if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_ACCOUNT
							&& $user
							&& $identifyMethod->getIdentifierValue() === $user->getUID()
						) {
							return true;
						}
						return false;
					});
					if (count($found) > 0) {
						return $signRequest;
					}
					return $carry;
				});
			}

			if (!$signRequest) {
				throw new DoesNotExistException('Sign request not found');
			}
			if ($signRequest->getSigned()) {
				throw new LibresignException($this->l10n->t('File already signed by you'), 1);
			}
			return $signRequest;
		} catch (DoesNotExistException) {
			throw new LibresignException($this->l10n->t('Invalid data to sign file'), 1);
		}
	}

	protected function getPdfToSign(File $originalFile): File {
		$this->logger->info('[PDF_TO_SIGN_START] Starting getPdfToSign for file', [
			'fileId' => $this->libreSignFile->getId(),
			'signedNodeId' => $this->libreSignFile->getSignedNodeId(),
			'originalFileName' => $originalFile->getName(),
		]);

		$file = $this->getSignedFile();
		if ($file instanceof File) {
			$this->logger->info('[PDF_REUSING_SIGNED] Reusing previously signed file', [
				'fileId' => $this->libreSignFile->getId(),
				'signedNodeId' => $this->libreSignFile->getSignedNodeId(),
				'signedFileName' => $file->getName(),
			]);
			return $file;
		}

		$originalContent = $originalFile->getContent();

		if ($this->pdfSignatureDetectionService->hasSignatures($originalContent)) {
			return $this->createSignedFile($originalFile, $originalContent);
		}
		$metadata = $this->footerHandler->getMetadata($originalFile, $this->libreSignFile);
		$footer = $this->footerHandler
			->setTemplateVar('uuid', $this->libreSignFile->getUuid())
			->setTemplateVar('signers', array_map(fn (SignRequestEntity $signer) => [
				'displayName' => $signer->getDisplayName(),
				'signed' => $signer->getSigned()
					? $signer->getSigned()->format(DateTimeInterface::ATOM)
					: null,
			], $this->getSigners()))
			->getFooter($metadata['d']);
		if ($footer) {
			$stamp = $this->tempManager->getTemporaryFile('stamp.pdf');
			file_put_contents($stamp, $footer);

			$input = $this->tempManager->getTemporaryFile('input.pdf');
			file_put_contents($input, $originalContent);

			try {
				$pdfContent = $this->pdf->applyStamp($input, $stamp);
			} catch (RuntimeException $e) {
				throw new LibresignException($e->getMessage());
			}
		} else {
			$pdfContent = $originalContent;
		}
		return $this->createSignedFile($originalFile, $pdfContent);
	}

	protected function getSignedFile(): ?File {
		$nodeId = $this->libreSignFile->getSignedNodeId();
		if (!$nodeId) {
			$this->logger->info('[SIGNED_FILE_NOT_SET] signedNodeId not set, will create new signed file', [
				'fileId' => $this->libreSignFile->getId(),
			]);
			return null;
		}

		$this->logger->info('[SIGNED_FILE_LOOKUP] Looking up previously signed file', [
			'fileId' => $this->libreSignFile->getId(),
			'signedNodeId' => $nodeId,
			'userId' => $this->libreSignFile->getUserId(),
		]);

		$fileToSign = $this->getNodeByIdUsingUid($this->libreSignFile->getUserId(), $nodeId);

		if ($fileToSign->getOwner()->getUID() !== $this->libreSignFile->getUserId()) {
			$fileToSign = $this->getNodeByIdUsingUid($fileToSign->getOwner()->getUID(), $nodeId);
		}
		return $fileToSign;
	}

	protected function getNodeByIdUsingUid(string $uid, int $nodeId): File {
		try {
			$userFolder = $this->root->getUserFolder($uid);
		} catch (NoUserException $e) {
			$this->logger->error('[file-access] NoUserException for uid={uid}', ['uid' => $uid]);
			throw new LibresignException($this->l10n->t('User not found.'));
		} catch (NotPermittedException $e) {
			$this->logger->error('[file-access] NotPermittedException for uid={uid}', ['uid' => $uid]);
			throw new LibresignException($this->l10n->t('You do not have permission for this action.'));
		}

		try {
			$fileToSign = $userFolder->getFirstNodeById($nodeId);
		} catch (\Throwable $e) {
			$this->logger->error('[file-access] Failed getFirstNodeById - nodeId={nodeId} error={error}', [
				'nodeId' => $nodeId,
				'error' => $e->getMessage(),
			]);
			throw $e;
		}

		if (!$fileToSign instanceof File) {
			$this->logger->error('[file-access] Node is not a File - nodeId={nodeId} type={type}', [
				'nodeId' => $nodeId,
				'type' => $fileToSign ? get_class($fileToSign) : 'NULL',
			]);
			throw new LibresignException($this->l10n->t('File not found'));
		}
		return $fileToSign;
	}

	/**
	 * Verify if file exists in filesystem before enqueuing background job
	 *
	 * @param string|null $uid User ID
	 * @param int $nodeId File node ID
	 * @return bool True if file exists and is accessible
	 */
	private function verifyFileExists(?string $uid, int $nodeId): bool {
		if ($uid === null || $nodeId === 0) {
			return false;
		}

		try {
			$userFolder = $this->root->getUserFolder($uid);
			$node = $userFolder->getFirstNodeById($nodeId);
			return $node instanceof File;
		} catch (\Throwable $e) {
			$this->logger->warning('[verify-file] File not accessible - nodeId={nodeId} uid={uid} error={error}', [
				'nodeId' => $nodeId,
				'uid' => $uid,
				'error' => $e->getMessage(),
			]);
			return false;
		}
	}

	private function cleanupUnsignedSignedFile(): void {
		if (!$this->createdSignedFile instanceof File) {
			return;
		}

		try {
			$this->createdSignedFile->delete();
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to delete temporary signed file: ' . $e->getMessage());
		} finally {
			$this->createdSignedFile = null;
		}
	}

	private function createSignedFile(File $originalFile, string $content): File {
		$filename = preg_replace(
			'/' . $originalFile->getExtension() . '$/',
			$this->l10n->t('signed') . '.' . $originalFile->getExtension(),
			basename($originalFile->getPath())
		);
		$owner = $originalFile->getOwner()->getUID();

		// Ensure unique filename by appending fileId to avoid collisions when multiple
		// files with the same original name are signed in envelope context
		$fileId = $this->libreSignFile->getId();
		$extension = $originalFile->getExtension();
		$nameWithoutExt = substr($filename, 0, -strlen($extension) - 1);
		$uniqueFilename = $nameWithoutExt . '_' . $fileId . '.' . $extension;

		$this->logger->info('[CREATE_SIGNED_FILE] Creating signed file', [
			'originalFileName' => $originalFile->getName(),
			'signedFileName' => $uniqueFilename,
			'owner' => $owner,
			'fileId' => $fileId,
			'originalProposedName' => $filename,
		]);

		try {
			/** @var \OCP\Files\Folder */
			$parentFolder = $this->root->getUserFolder($owner)->getFirstNodeById($originalFile->getParentId());

			// Use unique filename to avoid collision with other signed files
			$this->logger->info('[NEWFILE_ATTEMPT] About to call newFile() with unique filename', [
				'filename' => $uniqueFilename,
				'fileId' => $fileId,
				'owner' => $owner,
				'parentFolderId' => $originalFile->getParentId(),
			]);

			$this->createdSignedFile = $parentFolder->newFile($uniqueFilename, $content);

			$this->logger->info('[SIGNED_FILE_CREATED] Successfully created signed file', [
				'signedFileId' => $this->createdSignedFile->getId(),
				'signedFileName' => $this->createdSignedFile->getName(),
				'fileId' => $fileId,
				'contentSize' => strlen($content),
			]);

			return $this->createdSignedFile;
		} catch (NotPermittedException) {
			throw new LibresignException($this->l10n->t('You do not have permission for this action.'));
		} catch (\Exception $e) {
			$this->logger->error('[NEWFILE_ERROR] Error creating signed file', [
				'filename' => $uniqueFilename,
				'fileId' => $fileId,
				'error' => $e->getMessage(),
				'class' => get_class($e),
			]);
			throw $e;
		}
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getSignRequestByUuid(string $uuid): SignRequestEntity {
		$this->validateHelper->validateUuidFormat($uuid);
		return $this->signRequestMapper->getByUuid($uuid);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getFile(int $signRequestId): FileEntity {
		return $this->fileMapper->getById($signRequestId);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getFileByUuid(string $uuid): FileEntity {
		return $this->fileMapper->getByUuid($uuid);
	}

	public function getIdDocById(int $fileId): IdDocs {
		return $this->idDocsMapper->getByFileId($fileId);
	}

	/**
	 * @return File[] Array of files
	 */
	public function getNextcloudFiles(FileEntity $fileData): array {
		if ($fileData->getNodeType() === 'envelope') {
			$children = $this->fileMapper->getChildrenFiles($fileData->getId());
			$files = [];
			foreach ($children as $child) {
				$nodeId = $child->getNodeId();
				if ($nodeId === null) {
					throw new LibresignException(json_encode([
						'action' => JSActions::ACTION_DO_NOTHING,
						'errors' => [['message' => $this->l10n->t('File not found')]],
					]), AppFrameworkHttp::STATUS_NOT_FOUND);
				}
				$file = $this->root->getUserFolder($child->getUserId())->getFirstNodeById($nodeId);
				if ($file instanceof File) {
					$files[] = $file;
				}
			}
			return $files;
		}

		$nodeId = $fileData->getNodeId();
		if ($nodeId === null) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [['message' => $this->l10n->t('File not found')]],
			]), AppFrameworkHttp::STATUS_NOT_FOUND);
		}
		$fileToSign = $this->root->getUserFolder($fileData->getUserId())->getFirstNodeById($nodeId);
		if (!$fileToSign instanceof File) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [['message' => $this->l10n->t('File not found')]],
			]), AppFrameworkHttp::STATUS_NOT_FOUND);
		}
		return [$fileToSign];
	}

	/**
	 * @return array<FileEntity>
	 */
	public function getNextcloudFilesWithEntities(FileEntity $fileData): array {
		if ($fileData->getNodeType() === 'envelope') {
			$children = $this->fileMapper->getChildrenFiles($fileData->getId());
			$result = [];
			foreach ($children as $child) {
				$nodeId = $child->getNodeId();
				if ($nodeId === null) {
					throw new LibresignException(json_encode([
						'action' => JSActions::ACTION_DO_NOTHING,
						'errors' => [['message' => $this->l10n->t('File not found')]],
					]), AppFrameworkHttp::STATUS_NOT_FOUND);
				}
				$file = $this->root->getUserFolder($child->getUserId())->getFirstNodeById($nodeId);
				if ($file instanceof File) {
					$result[] = $child;
				}
			}
			return $result;
		}

		$nodeId = $fileData->getNodeId();
		if ($nodeId === null) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [['message' => $this->l10n->t('File not found')]],
			]), AppFrameworkHttp::STATUS_NOT_FOUND);
		}
		$fileToSign = $this->root->getUserFolder($fileData->getUserId())->getFirstNodeById($nodeId);
		if (!$fileToSign instanceof File) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [['message' => $this->l10n->t('File not found')]],
			]), AppFrameworkHttp::STATUS_NOT_FOUND);
		}
		return [$fileData];
	}

	public function validateSigner(string $uuid, ?IUser $user = null): void {
		$this->validateHelper->validateSigner($uuid, $user);
	}

	public function validateRenewSigner(string $uuid, ?IUser $user = null): void {
		$this->validateHelper->validateRenewSigner($uuid, $user);
	}

	public function getSignerData(?IUser $user, ?SignRequestEntity $signRequest = null): array {
		$return = ['user' => ['name' => null]];
		if ($signRequest) {
			$return['user']['name'] = $signRequest->getDisplayName();
		} elseif ($user) {
			$return['user']['name'] = $user->getDisplayName();
		}
		return $return;
	}

	public function getAvailableIdentifyMethodsFromSettings(): array {
		$identifyMethods = $this->identifyMethodService->getIdentifyMethodsSettings();
		$return = array_map(fn (array $identifyMethod): array => [
			'mandatory' => $identifyMethod['mandatory'],
			'identifiedAtDate' => null,
			'validateCode' => false,
			'method' => $identifyMethod['name'],
		], $identifyMethods);
		return $return;
	}

	public function getFileUrl(int $fileId, string $uuid): string {
		try {
			$this->idDocsMapper->getByFileId($fileId);
			return $this->urlGenerator->linkToRoute('libresign.page.getPdf', ['uuid' => $uuid]);
		} catch (DoesNotExistException) {
			return $this->urlGenerator->linkToRoute('libresign.page.getPdfFile', ['uuid' => $uuid]);
		}
	}

	/**
	 * Get PDF URLs for signing
	 * For envelopes: returns URLs for all child files
	 * For regular files: returns URL for the file itself
	 *
	 * @return string[]
	 */
	public function getPdfUrlsForSigning(FileEntity $fileEntity, SignRequestEntity $signRequestEntity): array {
		if (!$fileEntity->isEnvelope()) {
			return [
				$this->getFileUrl($fileEntity->getId(), $signRequestEntity->getUuid())
			];
		}

		$childSignRequests = $this->signRequestMapper->getByEnvelopeChildrenAndIdentifyMethod(
			$fileEntity->getId(),
			$signRequestEntity->getId()
		);

		$pdfUrls = [];
		foreach ($childSignRequests as $childSignRequest) {
			$pdfUrls[] = $this->getFileUrl(
				$childSignRequest->getFileId(),
				$childSignRequest->getUuid()
			);
		}

		return $pdfUrls;
	}

	private function recordSignatureAttempt(Exception $exception): void {
		if (!$this->libreSignFile) {
			return;
		}

		$metadata = $this->libreSignFile->getMetadata() ?? [];

		if (!isset($metadata['signature_attempts'])) {
			$metadata['signature_attempts'] = [];
		}

		$attempt = [
			'timestamp' => (new DateTime())->format(\DateTime::ATOM),
			'engine' => $this->engine ? get_class($this->engine) : 'unknown',
			'error_message' => $exception->getMessage(),
			'error_code' => $exception->getCode(),
		];

		$metadata['signature_attempts'][] = $attempt;
		$this->libreSignFile->setMetadata($metadata);
		$this->fileMapper->update($this->libreSignFile);
	}
}
