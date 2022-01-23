<?php

namespace OCA\Libresign\Tests\Api;

use ByJG\ApiTools\AbstractRequester;
use ByJG\ApiTools\ApiRequester;
use ByJG\ApiTools\Base\Schema;
use ByJG\ApiTools\Exception\DefinitionNotFoundException;
use ByJG\ApiTools\Exception\GenericSwaggerException;
use ByJG\ApiTools\Exception\HttpMethodNotFoundException;
use ByJG\ApiTools\Exception\InvalidDefinitionException;
use ByJG\ApiTools\Exception\NotMatchedException;
use ByJG\ApiTools\Exception\PathNotFoundException;
use ByJG\ApiTools\Exception\StatusCodeNotMatchedException;
use ByJG\ApiTools\OpenApi\OpenApiSchema;
use ByJG\Util\Psr7\MessageException;
use ByJG\Util\Psr7\Response;
use donatj\MockWebServer\MockWebServer;
use OCA\Libresign\Tests\Unit\TestCase;
use Symfony\Component\Yaml\Yaml;

class ApiTestCase extends TestCase {
	/**
	 * @var Schema
	 */
	protected $schema;

	/**
	 * @var AbstractRequester
	 */
	protected $requester = null;

	/**
	 * @var \OCA\Libresign\Tests\Api\ApiRequester
	 */
	public $request;

	/**
	 * @var MockWebServer
	 */
	protected static $server;

	public static function setUpBeforeClass(): void {
		self::$server = new MockWebServer();
		self::$server->start();
	}

	public function setUp(): void {
		parent::setUp();
		$data = Yaml::parse(file_get_contents('build/site/site/.vuepress/public/specs/api.yaml'));
		$data['servers'][] = ['url' => 'http://localhost/apps/libresign/api/0.1'];
		/** @var OpenApiSchema */
		$schema = \ByJG\ApiTools\Base\Schema::getInstance($data);
		$this->setSchema($schema);
		$systemConfig = \OC::$server->get(\OC\SystemConfig::class);
		$systemConfig->setValue('auth.bruteforce.protection.enabled', false);
		$this->request = new \OCA\Libresign\Tests\Api\ApiRequester();
	}

	/**
	 * configure the schema to use for requests
	 *
	 * When set, all requests without an own schema use this one instead.
	 *
	 * @param Schema|null $schema
	 */
	public function setSchema($schema) {
		$this->schema = $schema;
	}

	public function setRequester(AbstractRequester $requester) {
		$this->requester = $requester;
	}

	/**
	 * @return AbstractRequester
	 */
	protected function getRequester() {
		if (is_null($this->requester)) {
			$this->requester = new ApiRequester();
		}
		return $this->requester;
	}

	/**
	 * @param string $method The HTTP Method: GET, PUT, DELETE, POST, etc
	 * @param string $path The REST path call
	 * @param int $statusExpected
	 * @param array|null $query
	 * @param array|null $requestBody
	 * @param array $requestHeader
	 * @return mixed
	 * @throws DefinitionNotFoundException
	 * @throws GenericSwaggerException
	 * @throws HttpMethodNotFoundException
	 * @throws InvalidDefinitionException
	 * @throws NotMatchedException
	 * @throws PathNotFoundException
	 * @throws StatusCodeNotMatchedException
	 * @throws MessageException
	 * @deprecated Use assertRequest instead
	 */
	protected function makeRequest(
		$method,
		$path,
		$statusExpected = 200,
		$query = null,
		$requestBody = null,
		$requestHeader = []
	) {
		$this->checkSchema();
		$body = $this->requester
			->withSchema($this->schema)
			->withMethod($method)
			->withPath($path)
			->withQuery($query)
			->withRequestBody($requestBody)
			->withRequestHeader($requestHeader)
			->assertResponseCode($statusExpected)
			->send();

		// Note:
		// This code is only reached if the send is successful and
		// all matches are satisfied. Otherwise an error is throwed before
		// reach this
		$this->assertTrue(true);

		return $body;
	}

	/**
	 * @param AbstractRequester $request
	 * @return Response
	 * @throws DefinitionNotFoundException
	 * @throws GenericSwaggerException
	 * @throws HttpMethodNotFoundException
	 * @throws InvalidDefinitionException
	 * @throws NotMatchedException
	 * @throws PathNotFoundException
	 * @throws StatusCodeNotMatchedException
	 * @throws MessageException
	 */
	public function assertRequest(AbstractRequester $request = null) {
		if (!$request) {
			$request = $this->request;
		}
		// Add own schema if nothing is passed.
		if (!$request->hasSchema()) {
			$this->checkSchema();
			$request = $request->withSchema($this->schema);
		}

		// Request based on the Swagger Request definitios
		$body = $request->send();

		// Note:
		// This code is only reached if the send is successful and
		// all matches are satisfied. Otherwise an error is throwed before
		// reach this
		$this->assertTrue(true);

		return $body;
	}

	/**
	 * @throws GenericSwaggerException
	 */
	protected function checkSchema() {
		if (!$this->schema) {
			throw new GenericSwaggerException('You have to configure a schema for either the request or the testcase');
		}
	}
}
