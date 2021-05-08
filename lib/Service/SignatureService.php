<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CfsslHandler;
use OCA\Libresign\Storage\ClientStorage;
use OCP\IConfig;

class SignatureService {
	/** @var CfsslHandler */
	private $cfsslHandler;

	/** @var ClientStorage */
	private $clientStorage;

	/** @var IConfig */
	private $config;

	public function __construct(
		CfsslHandler $cfsslHandler,
		ClientStorage $clientStorage,
		IConfig $config
	) {
		$this->cfsslHandler = $cfsslHandler;
		$this->clientStorage = $clientStorage;
		$this->config = $config;
	}

	public function generate(string $password) {
		$content = $this->cfsslHandler
			->setCommonName($this->config->getAppValue(Application::APP_ID, 'commonName'))
			->sethosts([])
			->setCountry($this->config->getAppValue(Application::APP_ID, 'country'))
			->setOrganization($this->config->getAppValue(Application::APP_ID, 'organization'))
			->setOrganizationUnit($this->config->getAppValue(Application::APP_ID, 'organizationUnit'))
			->setCfsslUri($this->config->getAppValue(Application::APP_ID, 'cfsslUri'))
			->setPassword($password)
			->generateCertificate();

		$folder = $this->clientStorage->createFolder($certificatePath);
		$certificateFile = $this->clientStorage->saveFile($this->cfsslHandler->getCommonName() . '.pfx', $content, $folder);

		return $certificateFile->getInternalPath();
	}

	public function hasRootCert() {
		return [
			'hasRootCert' => !empty($this->config->getAppValue(Application::APP_ID, 'authkey')),
		];
	}
}
