<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\AccountService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class LibresignController extends Controller {
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var FileMapper */
	private $fileMapper;
	/** @var IL10N */
	private $l10n;
	/** @var AccountService */
	private $account;
	/** @var LoggerInterface */
	private $logger;
	/** @var IUserSession */
	private $userSession;
	/** @var IURLGenerator */
	private $urlGenerator;

	public function __construct(
		IRequest $request,
		FileUserMapper $fileUserMapper,
		FileMapper $fileMapper,
		IL10N $l10n,
		AccountService $account,
		LoggerInterface $logger,
		IURLGenerator $urlGenerator,
		IUserSession $userSession
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->fileUserMapper = $fileUserMapper;
		$this->fileMapper = $fileMapper;
		$this->l10n = $l10n;
		$this->account = $account;
		$this->logger = $logger;
		$this->urlGenerator = $urlGenerator;
		$this->userSession = $userSession;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function validateUuid($uuid) {
		return $this->validate('Uuid', $uuid);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function validateFileId($fileId) {
		return $this->validate('FileId', $fileId);
	}

	private function validate(string $type, $identifier) {
		$canSign = false;
		try {
			try {
				$file = call_user_func(
					[$this->fileMapper, 'getBy' . $type],
					$identifier
				);
			} catch (\Throwable $th) {
				throw new LibresignException('Invalid data to validate file', 404);
			}
			if (!$file) {
				throw new LibresignException('Invalid file identifier', 404);
			}

			$return['success'] = true;
			$return['name'] = $file->getName();
			$return['file'] = $this->urlGenerator->linkToRoute('libresign.page.getPdf', ['uuid' => $file->getUuid()]);
			$signatures = $this->fileUserMapper->getByFileId($file->id);
			foreach ($signatures as $signature) {
				$signatureToShow = [
					'signed' => $signature->getSigned(),
					'displayName' => $signature->getDisplayName(),
					'fullName' => $signature->getFullName(),
					'me' => false
				];
				if ($this->userSession->getUser()) {
					$uid = $this->userSession->getUser()->getUID();
					$signatureToShow['me'] = $uid === $signature->getUserId();
					if ($uid === $signature->getUserId() && !$signature->getSigned()) {
						$canSign = true;
					}
				}
				$return['signatures'][] = $signatureToShow;
			}
			$statusCode = Http::STATUS_OK;
		} catch (\Throwable $th) {
			$message = $this->l10n->t($th->getMessage());
			$this->logger->error($message);
			$return = [
				'success' => false,
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$message]
			];
			$statusCode = $th->getCode() ?? Http::STATUS_UNPROCESSABLE_ENTITY;
		}
		$return['settings'] = [
			'canRequestSign' => $this->account->canRequestSign($this->userSession->getUser()),
			'canSign' => $canSign
		];
		return new JSONResponse($return, $statusCode);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function list($page = null, $limit = null) {
		$return = $this->account->list($this->userSession->getUser(), $page, $limit);
		return new JSONResponse($return, Http::STATUS_OK);
	}
}
