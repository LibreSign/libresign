<?php

namespace OCA\Libresign\Handler\CertificateEngine;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CfsslServerHandler;
use OCP\IConfig;

/**
 * Class CfsslHandler
 *
 * @package OCA\Libresign\Handler
 *
 * @method string getCfsslUri()
 * @method CfsslHandler setClient(Client $client)
 * @method string getConfigPath()
 * @method CfsslHandler setConfigPath()
 */
class CfsslHandler extends AbstractHandler {
	public const CFSSL_URI = 'http://127.0.0.1:8888/api/v1/cfssl/';

	/** @var Client */
	protected $client;
	protected $cfsslUri;

	public function __construct(
		private CfsslServerHandler $cfsslServerHandler,
		private IConfig $config,
	) {
	}

	public function getClient(): Client {
		if (!$this->client) {
			$this->setClient(new Client(['base_uri' => $this->getCfsslUri()]));
		}
		$this->wakeUp();
		return $this->client;
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 */
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
		} catch (RequestException | ConnectException $th) {
			if ($th->getHandlerContext() && $th->getHandlerContext()['error']) {
				throw new \Exception($th->getHandlerContext()['error'], 1);
			}
			throw new LibresignException($th->getMessage(), 500);
		}

		$responseDecoded = json_decode($response->getBody(), true);
		if (!isset($responseDecoded['success']) || !$responseDecoded['success']) {
			throw new LibresignException('Error while generating certificate keys!', 500);
		}

		return $responseDecoded['result'];
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 */
	public function health(?string $cfsslUri = self::CFSSL_URI): array {
		try {
			if (!$this->getCfsslUri()) {
				$this->setCfsslUri($cfsslUri);
			}
			$client = $this->getClient();
			if (!$this->portOpen($cfsslUri)) {
				throw new LibresignException('CFSSL server is down', 500);
			}
			$response = $client
				->request('get',
					'health',
					[
						'base_uri' => $cfsslUri
					]
				)
			;
		} catch (RequestException | ConnectException $th) {
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

		$responseDecoded = json_decode($response->getBody(), true);
		if (!isset($responseDecoded['success']) || !$responseDecoded['success']) {
			throw new LibresignException('Error while check cfssl API health!', 500);
		}

		return $responseDecoded['result'];
	}

	private function wakeUp(): void {
		$cfsslUri = $this->getCfsslUri() ?? self::CFSSL_URI;
		if ($this->portOpen($cfsslUri)) {
			return;
		}
		$binary = $this->getBinary();
		if (!$binary) {
			return;
		}
		if (!file_exists($binary)) {
			throw new LibresignException('Binary of CFSSL not found');
		}
		$configPath = $this->getConfigPath();
		if (!$configPath) {
			throw new LibresignException('CFSSL not configured.');
		}
		$cmd = 'nohup ' . $binary . ' serve -address=127.0.0.1 ' .
			'-ca-key ' . $configPath . 'ca-key.pem ' .
			'-ca ' . $configPath . 'ca.pem '.
			'-config ' . $configPath . 'config_server.json > /dev/null 2>&1 & echo $!';
		shell_exec($cmd);
		$loops = 0;
		while (!$this->portOpen($cfsslUri) && $loops <= 4) {
			sleep(1);
			$loops++;
		}
	}

	private function portOpen(string $uri): bool {
		$host = parse_url($uri, PHP_URL_HOST);
		$port = parse_url($uri, PHP_URL_PORT);
		try {
			$socket = fsockopen($host, $port, $errno, $errstr, 0.1);
		} catch (\Throwable $th) {
		}
		if (isset($socket) && is_resource($socket)) {
			fclose($socket);
			return true;
		}
		return false;
	}

	private function getBinary(): string {
		$binary = $this->config->getAppValue(Application::APP_ID, 'cfssl_bin');
		
		if ($binary && !file_exists($binary)) {
			$this->config->deleteAppValue(Application::APP_ID, 'cfssl_bin');
			$binary = '';
		}
	
		if (!$binary) {
			throw new LibresignException('Binary of CFSSL not found. Install binaries.');
		}

		if (PHP_OS_FAMILY === 'Windows') {
			$binary .= '.exe';
		}
	
		return $binary;
	}

	private function getCfsslUri(): string {
		$uri = $this->cfsslUri;
		if ($uri) {
			return $uri;
		}

		$uri = $this->config->getAppValue(Application::APP_ID, 'cfssl_uri');
		if ($uri) {
			return $uri;
		}

		// In case config is an empty string
		$this->config->deleteAppValue(Application::APP_ID, 'cfssl_uri');

		// Binary is necessary for local Cfssl server
		$this->getBinary();
		return self::CFSSL_URI;
	}

	public function setCfsslUri($uri): void {
		$this->config->setAppValue(Application::APP_ID, 'cfsslUri', $cfsslUri);
		$this->cfsslUri = $uri;
	}

	public function genkey(): void {
		$binary = $this->getBinary();
		if (!$binary) {
			return;
		}
		$configPath = $this->getConfigPath();
		$cmd = $binary . ' genkey ' .
			'-initca=true ' . $configPath . 'csr_server.json | ' .
			$binary . 'json -bare ' . $configPath . 'ca;';
		shell_exec($cmd);
	}

	public function generateRootCert(
		string $commonName,
		array $names = [],
		string $configPath = '',
	): string {
		$key = bin2hex(random_bytes(16));

		$this->setConfigPath($configPath);
		$this->cfsslServerHandler->createConfigServer(
			$commonName,
			$names,
			$key,
			$configPath
		);

		$this->genkey();

		for ($i = 1; $i <= 4; $i++) {
			if ($this->health($this->getCfsslUri())) {
				break;
			}
			sleep(2);
		}

		return $key;
	}
}
