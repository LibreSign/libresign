<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCP\IConfig;

class AdminSignatureService {
	/** @var IConfig */
	private $config;

	public function __construct(
		IConfig $config
	) {
		$this->config = $config;
	}

	/**
	 * @return string[]
	 *
	 * @psalm-return array{commonName: string, country: string, organization: string, organizationUnit: string, cfsslUri: string, configPath: string}
	 */
	public function loadKeys(): array {
		return [
			'commonName' => $this->config->getAppValue(Application::APP_ID, 'commonName'),
			'country' => $this->config->getAppValue(Application::APP_ID, 'country'),
			'organization' => $this->config->getAppValue(Application::APP_ID, 'organization'),
			'organizationUnit' => $this->config->getAppValue(Application::APP_ID, 'organizationUnit'),
			'cfsslUri' => $this->config->getAppValue(Application::APP_ID, 'cfsslUri'),
			'configPath' => $this->config->getAppValue(Application::APP_ID, 'configPath'),
		];
	}
}
