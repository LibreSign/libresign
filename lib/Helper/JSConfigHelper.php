<?php

namespace OCA\Libresign\Helper;

use OC\Files\Filesystem;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserManager;

class JSConfigHelper {
	/** @var ISession */
	private $session;
	/** @var IRequest */
	private $request;
	/** @var FileMapper */
	private $fileMapper;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var IL10N */
	private $l10n;
	/** @var IRootFolder */
	private $root;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IUserManager */
	protected $userManager;

	public function __construct(
		ISession $session,
		IRequest $request,
		FileMapper $fileMapper,
		FileUserMapper $fileUserMapper,
		IL10N $l10n,
		IRootFolder $root,
		IURLGenerator $urlGenerator,
		IUserManager $userManager
	) {
		$this->session = $session;
		$this->request = $request;
		$this->fileMapper = $fileMapper;
		$this->fileUserMapper = $fileUserMapper;
		$this->l10n = $l10n;
		$this->root = $root;
		$this->urlGenerator = $urlGenerator;
		$this->userManager = $userManager;
	}

	/**
	 * @param array $settings
	 */
	public function extendJsConfig(array $settings) {
		$appConfig = json_decode($settings['array']['oc_appconfig'], true);
		$appConfig['libresign'] = $this->getConfig('url');
		$settings['array']['oc_appconfig'] = json_encode($appConfig);
	}

	/**
	 * Undocumented function
	 *
	 * @param string $formatOfPdfOnSign (base64,url,file)
	 * @return array|string
	 */
	public function getConfig(string $formatOfPdfOnSign): array {
		$uuid = $this->request->getParam('uuid');
		$userId = $this->session->get('user_id');
		try {
			$fileUser = $this->fileUserMapper->getByUuid($uuid);
		} catch (\Throwable $th) {
			$return['action'] = JSActions::ACTION_DO_NOTHING;
			$return['errors'][] = $this->l10n->t('Invalid UUID');
			return $return;
		}
		$fileUserId = $fileUser->getUserId();
		if (!$fileUserId) {
			if ($userId) {
				$return['action'] = JSActions::ACTION_DO_NOTHING;
				$return['errors'][] = $this->l10n->t('This is not your file');
				return $return;
			}
			if ($this->userManager->userExists($fileUser->getEmail())) {
				$return['action'] = JSActions::ACTION_REDIRECT;
				$return['errors'][] = $this->l10n->t('User already exists. Please login.');
				$return['redirect'] = $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
					'redirect_url' => $this->urlGenerator->linkToRoute(
						'libresign.page.sign',
						['uuid' => $uuid]
					),
				]);
				return $return;
			}
			$return['action'] = JSActions::ACTION_CREATE_USER;
			return $return;
		}
		if ($fileUser->getSigned()) {
			$return['action'] = JSActions::ACTION_SHOW_ERROR;
			$return['errors'][] = $this->l10n->t('File already signed.');
			return $return;
		}
		if (!$userId) {
			$return['action'] = JSActions::ACTION_REDIRECT;

			$return['redirect'] = $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
				'redirect_url' => $this->urlGenerator->linkToRoute(
					'libresign.page.sign',
					['uuid' => $uuid]
				),
			]);
			$return['errors'][] = $this->l10n->t('You are not logged in. Please log in.');
			return $return;
		}
		if ($fileUserId !== $userId) {
			$return['action'] = JSActions::ACTION_DO_NOTHING;
			$return['errors'][] = $this->l10n->t('Invalid user');
			return $return;
		}
		$fileData = $this->fileMapper->getById($fileUser->getFileId());
		Filesystem::initMountPoints($fileData->getUserId());
		$fileToSign = $this->root->getById($fileData->getNodeId());
		if (count($fileToSign) < 1) {
			$return['action'] = JSActions::ACTION_DO_NOTHING;
			$return['errors'][] = $this->l10n->t('File not found');
			return $return;
		}
		/** @var File */
		$fileToSign = $fileToSign[0];
		$return['action'] = JSActions::ACTION_SIGN;
		$return['user']['name'] = $fileUser->getDisplayName();
		switch ($formatOfPdfOnSign) {
			case 'base64':
				$pdf = ['base64' => base64_encode($fileToSign->getContent())];
				break;
			case 'url':
				$pdf = ['url' => $this->urlGenerator->linkToRoute('libresign.page.getPdf', ['uuid' => $uuid])];
				break;
			case 'file':
				$pdf = ['file' => $fileToSign];
				break;
		}
		$return['sign'] = [
			'pdf' => $pdf,
			'filename' => $fileData->getName(),
			'description' => $fileUser->getDescription()
		];
		return $return;
	}
}
