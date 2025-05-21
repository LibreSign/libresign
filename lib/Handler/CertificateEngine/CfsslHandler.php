<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\CertificateEngine;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use OC\SystemConfig;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CfsslServerHandler;
use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCA\Libresign\Service\CertificatePolicyService;
use OCA\Libresign\Service\Install\InstallService;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\ITempManager;

/**
 * Class CfsslHandler
 *
 * @package OCA\Libresign\Handler
 *
 * @method CfsslHandler setClient(Client $client)
 */
class CfsslHandler extends AEngineHandler implements IEngineHandler {
	public const CFSSL_URI = 'http://127.0.0.1:8888/api/v1/cfssl/';

	/** @var Client */
	protected $client;
	protected $cfsslUri;
	private string $binary = '';

	public function __construct(
		protected IConfig $config,
		protected IAppConfig $appConfig,
		private SystemConfig $systemConfig,
		protected IAppDataFactory $appDataFactory,
		protected IDateTimeFormatter $dateTimeFormatter,
		protected ITempManager $tempManager,
		protected CfsslServerHandler $cfsslServerHandler,
		protected CertificatePolicyService $certificatePolicyService,
	) {
		parent::__construct($config, $appConfig, $appDataFactory, $dateTimeFormatter, $tempManager, $certificatePolicyService);

		$this->cfsslServerHandler->configCallback(fn () => $this->getConfigPath());
	}

	public function generateRootCert(
		string $commonName,
		array $names = [],
	): string {
		$key = bin2hex(random_bytes(16));

		$this->cfsslServerHandler->createConfigServer(
			$commonName,
			$names,
			$key,
			$this->expirity()
		);

		$this->genkey();

		$this->stopIfRunning();

		for ($i = 1; $i <= 4; $i++) {
			if ($this->isUp()) {
				break;
			}
			sleep(2);
		}

		return $key;
	}

	public function generateCertificate(): string {
		$certKeys = $this->newCert();
		return parent::exportToPkcs12(
			$certKeys['certificate'],
			$certKeys['private_key'],
			[
				'friendly_name' => $this->getFriendlyName(),
				'extracerts' => [
					$certKeys['certificate'],
					$certKeys['certificate_request'],
				],
			],
		);
	}

	public function isSetupOk(): bool {
		if (!parent::isSetupOk()) {
			return false;
		};
		$configPath = $this->getConfigPath();
		$certificate = file_exists($configPath . DIRECTORY_SEPARATOR . 'ca.pem');
		$privateKey = file_exists($configPath . DIRECTORY_SEPARATOR . 'ca-key.pem');
		if (!$certificate || !$privateKey) {
			return false;
		}
		try {
			$this->getClient();
			return true;
		} catch (\Throwable) {
		}
		return false;
	}

	public function configureCheck(): array {
		$return = $this->checkBinaries();
		$configPath = $this->getConfigPath();
		if (is_dir($configPath)) {
			return array_merge(
				$return,
				[(new ConfigureCheckHelper())
					->setSuccessMessage('Root certificate config files found.')
					->setResource('cfssl-configure')]
			);
		}
		return array_merge(
			$return,
			[(new ConfigureCheckHelper())
				->setErrorMessage('CFSSL (root certificate) not configured.')
				->setResource('cfssl-configure')
				->setTip('Run occ libresign:configure:cfssl --help')]
		);
	}

	public function toArray(): array {
		$return = parent::toArray();
		if (!empty($return['configPath'])) {
			$return['cfsslUri'] = $this->appConfig->getValueString(Application::APP_ID, 'cfssl_uri');
		}
		return $return;
	}

	public function getCommonName(): string {
		$uid = $this->getUID();
		if (!$uid) {
			return $this->commonName;
		}
		return $uid . ', ' . $this->commonName;
	}

	private function newCert(): array {
		$json = [
			'json' => [
				'profile' => 'CA',
				'request' => [
					'hosts' => $this->getHosts(),
					'CN' => $this->getCommonName(),
					'key' => [
						'algo' => 'rsa',
						'size' => 2048,
					],
					'names' => [],
				],
			],
		];

		$names = $this->getNames();
		if (!empty($names)) {
			$json['json']['request']['names'][] = $names;
		}

		try {
			$response = $this->getClient()
				->request('post',
					'newcert',
					$json
				)
			;
		} catch (RequestException|ConnectException $th) {
			if ($th->getHandlerContext() && $th->getHandlerContext()['error']) {
				throw new \Exception($th->getHandlerContext()['error'], 1);
			}
			throw new LibresignException($th->getMessage(), 500);
		}

		$responseDecoded = json_decode((string)$response->getBody(), true);
		if (!isset($responseDecoded['success']) || !$responseDecoded['success']) {
			throw new LibresignException('Error while generating certificate keys!', 500);
		}

		return $responseDecoded['result'];
	}

	private function genkey(): void {
		$binary = $this->getBinary();
		$configPath = $this->getConfigPath();
		$cmd = $binary . ' genkey ' .
			'-initca=true ' . $configPath . DIRECTORY_SEPARATOR . 'csr_server.json | ' .
			$binary . 'json -bare ' . $configPath . DIRECTORY_SEPARATOR . 'ca;';
		shell_exec($cmd);
	}

	private function getClient(): Client {
		if (!$this->client) {
			$this->setClient(new Client(['base_uri' => $this->getCfsslUri()]));
		}
		$this->wakeUp();
		return $this->client;
	}

	private function isUp(): bool {
		try {
			$client = $this->getClient();
			if (!$this->portOpen()) {
				throw new LibresignException('CFSSL server is down', 500);
			}
			$response = $client
				->request('get',
					'health',
					[
						'base_uri' => $this->getCfsslUri()
					]
				)
			;
		} catch (RequestException|ConnectException $th) {
			switch ($th->getCode()) {
				case 404:
					throw new \Exception('Endpoint /health of CFSSL server not found. Maybe you are using incompatible version of CFSSL server. Use latests version.', 1);
				default:
					if ($th->getHandlerContext() && $th->getHandlerContext()['error']) {
						throw new \Exception($th->getHandlerContext()['error'], 1);
					}
					throw new LibresignException($th->getMessage(), 500);
			}
		}

		$responseDecoded = json_decode((string)$response->getBody(), true);
		if (!isset($responseDecoded['success']) || !$responseDecoded['success']) {
			throw new LibresignException('Error while check cfssl API health!', 500);
		}

		if (empty($responseDecoded['result']) || empty($responseDecoded['result']['healthy'])) {
			return false;
		}

		return (bool)$responseDecoded['result']['healthy'];
	}

	private function wakeUp(): void {
		if ($this->portOpen()) {
			return;
		}
		$binary = $this->getBinary();
		$configPath = $this->getConfigPath();
		if (!$configPath) {
			throw new LibresignException('CFSSL not configured.');
		}
		$this->cfsslServerHandler->updateExpirity($this->expirity());
		$cmd = 'nohup ' . $binary . ' serve -address=127.0.0.1 ' .
			'-ca-key ' . $configPath . DIRECTORY_SEPARATOR . 'ca-key.pem ' .
			'-ca ' . $configPath . DIRECTORY_SEPARATOR . 'ca.pem ' .
			'-config ' . $configPath . DIRECTORY_SEPARATOR . 'config_server.json > /dev/null 2>&1 & echo $!';
		shell_exec($cmd);
		$loops = 0;
		while (!$this->portOpen() && $loops <= 4) {
			sleep(1);
			$loops++;
		}
	}

	private function portOpen(): bool {
		$host = parse_url($this->getCfsslUri(), PHP_URL_HOST);
		$port = parse_url($this->getCfsslUri(), PHP_URL_PORT);

		set_error_handler(function () { });
		$socket = fsockopen($host, $port, $errno, $errstr, 0.1);
		restore_error_handler();
		if (!$socket || $errno || $errstr) {
			return false;
		}
		fclose($socket);
		return true;
	}

	private function getServerPid(): int {
		$cmd = 'ps -eo pid,command|';
		$cmd .= 'grep "cfssl.*serve.*-address"|' .
			'grep -v grep|' .
			'grep -v defunct|' .
			'sed -e "s/^[[:space:]]*//"|cut -d" " -f1';
		$output = shell_exec($cmd);
		if (!is_string($output)) {
			return 0;
		}
		$pid = trim($output);
		return (int)$pid;
	}

	/**
	 * Parse command
	 *
	 * Have commands that need to be executed as sudo otherwise don't will work,
	 * by example the command runuser or kill. To prevent error when run in a
	 * GitHub Actions, these commands are executed prefixed by sudo when exists
	 * an environment called GITHUB_ACTIONS.
	 */
	private function parseCommand(string $command): string {
		if (getenv('GITHUB_ACTIONS') !== false) {
			$command = 'sudo ' . $command;
		}
		return $command;
	}

	private function stopIfRunning(): void {
		$pid = $this->getServerPid();
		if ($pid > 0) {
			exec($this->parseCommand('kill -9 ' . $pid));
		}
	}

	private function getBinary(): string {
		if ($this->binary) {
			return $this->binary;
		}

		if (PHP_OS_FAMILY === 'Windows') {
			throw new LibresignException('Incompatible with Windows');
		}

		if ($this->appConfig->hasKey(Application::APP_ID, 'cfssl_bin')) {
			$binary = $this->appConfig->getValueString(Application::APP_ID, 'cfssl_bin');
			if (!file_exists($binary)) {
				$this->appConfig->deleteKey(Application::APP_ID, 'cfssl_bin');
			}
			return $binary;
		}
		throw new LibresignException('Binary of CFSSL not found. Install binaries.');
	}

	private function getCfsslUri(): string {
		if ($this->cfsslUri) {
			return $this->cfsslUri;
		}

		if ($uri = $this->appConfig->getValueString(Application::APP_ID, 'cfssl_uri')) {
			return $uri;
		}
		// In case config is an empty string
		$this->appConfig->deleteKey(Application::APP_ID, 'cfssl_uri');

		$this->cfsslUri = self::CFSSL_URI;
		return $this->cfsslUri;
	}

	public function setCfsslUri($uri): void {
		if ($uri) {
			$this->appConfig->setValueString(Application::APP_ID, 'cfssl_uri', $uri);
		} else {
			$this->appConfig->deleteKey(Application::APP_ID, 'cfssl_uri');
		}
		$this->cfsslUri = $uri;
	}

	private function checkBinaries(): array {
		if (PHP_OS_FAMILY === 'Windows') {
			return [
				(new ConfigureCheckHelper())
					->setErrorMessage('CFSSL is incompatible with Windows')
					->setResource('cfssl'),
			];
		}
		$binary = $this->appConfig->getValueString(Application::APP_ID, 'cfssl_bin');
		if (!$binary) {
			return [
				(new ConfigureCheckHelper())
					->setErrorMessage('CFSSL not installed.')
					->setResource('cfssl')
					->setTip('Run occ libresign:install --cfssl'),
			];
		}

		if (!file_exists($binary)) {
			return [
				(new ConfigureCheckHelper())
					->setErrorMessage('CFSSL not found.')
					->setResource('cfssl')
					->setTip('Run occ libresign:install --cfssl'),
			];
		}
		$version = shell_exec("$binary version");
		if (!is_string($version) || empty($version)) {
			return [
				(new ConfigureCheckHelper())
					->setErrorMessage(sprintf(
						'Failed to run the command "%s" with user %s',
						"$binary version",
						get_current_user()
					))
					->setResource('cfssl')
					->setTip('Run occ libresign:install --cfssl')
			];
		}
		preg_match_all('/: (?<version>.*)/', $version, $matches);
		if (!$matches || !isset($matches['version']) || count($matches['version']) !== 2) {
			return [
				(new ConfigureCheckHelper())
					->setErrorMessage(sprintf(
						'Failed to identify cfssl version with command %s',
						"$binary version"
					))
					->setResource('cfssl')
					->setTip('Run occ libresign:install --cfssl')
			];
		}
		if (!str_contains($matches['version'][0], InstallService::CFSSL_VERSION)) {
			return [
				(new ConfigureCheckHelper())
					->setErrorMessage(sprintf(
						'Invalid version. Expected: %s, actual: %s',
						InstallService::CFSSL_VERSION,
						$matches['version'][0]
					))
					->setResource('cfssl')
					->setTip('Run occ libresign:install --cfssl')
			];
		}
		$return = [];
		$return[] = (new ConfigureCheckHelper())
			->setSuccessMessage('CFSSL binary path: ' . $binary)
			->setResource('cfssl');
		$return[] = (new ConfigureCheckHelper())
			->setSuccessMessage('CFSSL version: ' . $matches['version'][0])
			->setResource('cfssl');
		$return[] = (new ConfigureCheckHelper())
			->setSuccessMessage('Runtime: ' . $matches['version'][1])
			->setResource('cfssl');
		return $return;
	}
}
