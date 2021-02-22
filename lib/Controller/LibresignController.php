<?php

namespace OCA\Libresign\Controller;

use OC\Files\Filesystem;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\JLibresignHandler;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\LibresignService;
use OCA\Libresign\Service\WebhookService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IRequest;
use setasign\Fpdi\Fpdi;

class LibresignController extends Controller {
	use HandleErrorsTrait;
	use HandleParamsTrait;

	/** @var LibresignService */
	private $service;

	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var FileMapper */
	private $fileMapper;
	/** @var IRootFolder */
	private $root;
	/** @var IL10N */
	private $l10n;
	/** @var AccountService */
	private $account;
	/** @var JLibresignHandler */
	private $libresignHandler;
	/** @var WebhookService */
	private $webhook;
	/** @var string */
	private $userId;

	public function __construct(
		IRequest $request,
		LibresignService $service,
		FileUserMapper $fileUserMapper,
		FileMapper $fileMapper,
		IRootFolder $root,
		IL10N $l10n,
		AccountService $account,
		JLibresignHandler $libresignHandler,
		WebhookService $webhook,
		$userId
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->service = $service;
		$this->fileUserMapper = $fileUserMapper;
		$this->fileMapper = $fileMapper;
		$this->root = $root;
		$this->l10n = $l10n;
		$this->account = $account;
		$this->libresignHandler = $libresignHandler;
		$this->webhook = $webhook;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @todo remove NoCSRFRequired
	 */
	public function sign(
		string $inputFilePath = null,
		string $outputFolderPath = null,
		string $certificatePath = null,
		string $password = null
	): JSONResponse {
		try {
			$this->checkParams([
				'inputFilePath' => $inputFilePath,
				'outputFolderPath' => $outputFolderPath,
				'certificatePath' => $certificatePath,
				'password' => $password,
			]);

			$fileSigned = $this->service->sign($inputFilePath, $outputFolderPath, $certificatePath, $password);

			return new JSONResponse(
				['fileSigned' => $fileSigned->getInternalPath()],
				HTTP::STATUS_OK
			);
		} catch (\Exception $exception) {
			return new JSONResponse(
				[
					'action' => JSActions::ACTION_DO_NOTHING,
					'errors' => [$this->l10n->t($exception->getMessage())]
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function signUsingUuid(string $uuid, string $password): JSONResponse {
		try {
			try {
				$fileUser = $this->fileUserMapper->getByUuidAndUserId($uuid, $this->userId);
			} catch (\Throwable $th) {
				throw new LibresignException($this->l10n->t('Invalid data to sign file'), 1);
			}
			if ($fileUser->getSigned()) {
				throw new LibresignException($this->l10n->t('File already signed by you'), 1);
			}
			$fileData = $this->fileMapper->getById($fileUser->getFileId());
			Filesystem::initMountPoints($fileData->getuserId());
			$originalFile = $this->root->getById($fileData->getNodeId());
			if (count($originalFile) < 1) {
				throw new LibresignException($this->l10n->t('File not found'));
			}
			$originalFile = $originalFile[0];
			$signedFilePath = preg_replace(
				'/' . $originalFile->getExtension() . '$/',
				$this->l10n->t('signed').'.'.$originalFile->getExtension(),
				$originalFile->getPath()
			);

			if ($this->root->nodeExists($signedFilePath)) {
				/** @var \OCP\Files\File */
				$fileToSign = $this->root->get($signedFilePath);
			} else {
				/** @var \OCP\Files\File */
				$fileToSign = $this->root->newFile($signedFilePath);
				$buffer = $this->writeFooter($originalFile);
				$fileToSign->putContent($buffer);
			}
			$certificatePath = $this->account->getPfx($fileUser->getUserId());
			list(, $signedContent) = $this->libresignHandler->signExistingFile($fileToSign, $certificatePath, $password);
			$fileToSign->putContent($signedContent);
			$fileUser->setSigned(time());
			$this->fileUserMapper->update($fileUser);

			$signers = $this->fileUserMapper->getByFileId($fileUser->getFileId());
			$total = array_reduce($signers, function ($carry, $signer) {
				$carry += $signer->getSigned() ? 1 : 0;
				return $carry;
			});
			if (count($signers) == $total) {
				$callbackUrl = $fileData->getCallback();
				if ($callbackUrl) {
					$this->webhook->notifyCallback(
						$callbackUrl,
						$fileData->getUuid(),
						$fileToSign
					);
				}
			}

			return new JSONResponse(
				[
					'action' => JSActions::ACTION_DO_NOTHING,
					'message' => $this->l10n->t('File signed')
				],
				Http::STATUS_OK
			);
		} catch (LibresignException $e) {
			return new JSONResponse(
				[
					'action' => JSActions::ACTION_DO_NOTHING,
					'errors' => [$e->getMessage()]
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		} catch (\Throwable $th) {
			$message = $th->getMessage();
			switch ($message) {
				case 'Certificate Password Invalid.':
					$message = $this->l10n->t($message);
					break;
				default:
					$message = $this->l10n->t('Internal error');
			}
			return new JSONResponse(
				[
					'action' => JSActions::ACTION_DO_NOTHING,
					'errors' => [$message]
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
	}

	private function writeFooter($file) {
		$pdf = new Fpdi();
		$pageCount = $pdf->setSourceFile($file->fopen('r'));

		for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
			$templateId = $pdf->importPage($pageNo);

			$pdf->AddPage();
			$pdf->useTemplate($templateId, ['adjustPageSize' => true]);

			$pdf->SetFont('Helvetica');
			$pdf->SetFontSize(8);
			$pdf->SetAutoPageBreak(false);
			$pdf->SetXY(5, -10);

			$pdf->Write(8, $this->l10n->t(
				'Digital signed by LibreSign. Validate in http://validador.lt.coop.br'
			));
		}

		return $pdf->Output('S');
	}
}
