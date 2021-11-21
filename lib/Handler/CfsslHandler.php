<?php

namespace OCA\Libresign\Handler;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use OCA\Libresign\Exception\LibresignException;

/**
 * Class FileMapper
 *
 * @package OCA\Libresign\Handler
 *
 * @method CfsslHandler setPassword(string $password)
 * @method string getPassword()
 * @method CfsslHandler setCommonName(string $commonName)
 * @method string getCommonName()
 * @method CfsslHandler setHosts(array $hosts)
 * @method array getHosts()
 * @method CfsslHandler setFriendlyName(string $friendlyName)
 * @method string getFriendlyName()
 * @method CfsslHandler setCountry(string $country)
 * @method string getCountry()
 * @method CfsslHandler setOrganization(string $organization)
 * @method string getOrganization()
 * @method CfsslHandler setOrganizationUnit(string $organizationUnit)
 * @method string getOrganizationUnit()
 * @method CfsslHandler setCfsslUri(string $cfsslUri)
 * @method string getCfsslUri()
 * @method CfsslHandler setClient(ClientInterface $client)
 * @method Client getClient()
 */
class CfsslHandler {
	private $commonName;
	private $hosts = [];
	private $friendlyName;
	private $country;
	private $organization;
	private $organizationUnit;
	private $cfsslUri;
	private $password;
	/** @var ClientInterface */
	private $client;
	public function __call($name, $arguments) {
		if (!preg_match('/^(?<type>get|set)(?<property>.+)/', $name, $matches)) {
			throw new \LogicException(sprintf('Cannot set non existing property %s->%s = %s.', \get_class($this), $name, var_export($arguments, true)));
		}
		$property = lcfirst($matches['property']);
		if (!property_exists($this, $property)) {
			throw new \LogicException(sprintf('Cannot set non existing property %s->%s = %s.', \get_class($this), $name, var_export($arguments, true)));
		}
		if ($matches['type'] === 'get') {
			return $this->$property;
		}
		$this->$property = $arguments[0] ?? null;
		return $this;
	}

	public function getClient(): ClientInterface {
		if (!$this->client) {
			$this->setClient(new Client(['base_uri' => $this->getCfsslUri()]));
		}
		return $this->client;
	}

	public function generateCertificate(): string {
		$certKeys = $this->newCert();
		$certContent = null;
		try {
			openssl_pkcs12_export(
				$certKeys['certificate'],
				$certContent,
				$certKeys['private_key'],
				$this->getPassword(),
				['friendly_name' => $this->getFriendlyName()],
			);
		} catch (\Throwable $th) {
			throw new LibresignException('Error while creating certificate file', 500);
		}

		return $certContent;
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 * @return array
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
					'names' => [
						[
							'C' => $this->getCountry(),
							'O' => $this->getOrganization(),
							'OU' => $this->getOrganizationUnit(),
							'CN' => $this->getCommonName(),
						],
					],
				],
			],
		];
		try {
			$response = $this->getClient()
				->request('post',
					'newcert',
					$json
				)
			;
		} catch (TransferException $th) {
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
	 * @param string $cfsslUri
	 * @return array
	 */
	public function health(string $cfsslUri): array {
		try {
			$response = $this->getClient()
				->request('get',
					'health',
					[
						'base_uri' => $cfsslUri
					]
				)
			;
		} catch (TransferException $th) {
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
}
