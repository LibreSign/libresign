<?php

namespace OCA\Libresign\Helper;

use OC\Files\Filesystem;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
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
		$uuid = $this->request->getParam('uuid');
		$userId = $this->session->get('user_id');
		try {
			$fileUser = $this->fileUserMapper->getByUuid($uuid);
		} catch (\Throwable $th) {
			$appConfig['libresign']['action'] = JSActions::ACTION_DO_NOTHING;
			$appConfig['libresign']['errors'][] = $this->l10n->t('Invalid uuid');
			$settings['array']['oc_appconfig'] = json_encode($appConfig);
			return;
		}
		$fileUserId = $fileUser->getUserId();
		if (!$fileUserId) {
			if ($userId) {
				$appConfig['libresign']['action'] = JSActions::ACTION_DO_NOTHING;
				$appConfig['libresign']['errors'][] = $this->l10n->t('This is not your file');
				$settings['array']['oc_appconfig'] = json_encode($appConfig);
				return;
			}
			if ($this->userManager->userExists($data['email'])) {
				$appConfig['libresign']['action'] = JSActions::ACTION_REDIRECT;
				$appConfig['libresign']['errors'][] = $this->l10n->t('User already exists. Please loggin.');
				$appConfig['libresign']['redirect'] = $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
					'redirect_url' => $this->urlGenerator->linkToRoute(
						'libresign.Page.sign',
						['uuid' => $uuid]
					),
				]);
				$settings['array']['oc_appconfig'] = json_encode($appConfig);
				return;
			}
			$appConfig['libresign']['action'] = JSActions::ACTION_CREATE_USER;
			$settings['array']['oc_appconfig'] = json_encode($appConfig);
			return;
		}
		if (!$userId) {
			$appConfig['libresign']['action'] = JSActions::ACTION_REDIRECT;

			$appConfig['libresign']['redirect'] = $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
				'redirect_url' => $this->urlGenerator->linkToRoute(
					'libresign.page.sign',
					['uuid' => $uuid]
				),
			]);
			$appConfig['libresign']['errors'][] = $this->l10n->t('You are not logged in. Please log in.');
			$settings['array']['oc_appconfig'] = json_encode($appConfig);
			return;
		}
		if ($fileUserId != $userId) {
			$appConfig['libresign']['action'] = JSActions::ACTION_DO_NOTHING;
			$appConfig['libresign']['errors'][] = $this->l10n->t('Invalid user');
			$settings['array']['oc_appconfig'] = json_encode($appConfig);
			return;
		}
		$fileData = $this->fileMapper->getById($fileUser->getFileId());
		Filesystem::initMountPoints($fileData->getUserId());
		$fileToSign = $this->root->getById($fileData->getNodeId());
		if (count($fileToSign) < 1) {
			$appConfig['libresign']['action'] = JSActions::ACTION_DO_NOTHING;
			$appConfig['libresign']['errors'][] = $this->l10n->t('File not found');
			$settings['array']['oc_appconfig'] = json_encode($appConfig);
			return;
		}
		$fileToSign = $fileToSign[0];
		$appConfig['libresign']['action'] = JSActions::ACTION_SIGN;
		$appConfig['libresign']['user']['name'] = $fileUser->getDisplayName();
		$appConfig['libresign']['sign'] = [
			'pdf' => [
				'base64' => base64_encode($fileToSign->getContent())
			],
			'filename' => $fileData->getName(),
			'description' => $fileData->getDescription()
		];
		$settings['array']['oc_appconfig'] = json_encode($appConfig);
	}
}
