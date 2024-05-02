<?php

declare(strict_types=1);

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
use OCA\Libresign\Tests\Unit\TestCase;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Yaml\Yaml;

class ApiTestCase extends TestCase {
	/**
	 * @var Schema
	 */
	protected $schema;

	protected AbstractRequester|null $requester = null;

	/**
	 * @var \OCA\Libresign\Tests\Api\ApiRequester
	 */
	public $request;

	public function setUp(): void {
		parent::setUp();
		$data = Yaml::parse(file_get_contents('build/site/site/.vuepress/public/specs/api.yaml'));
		$data['servers'][] = ['url' => 'http://localhost/ocs/v2.php/apps/libresign/api/v1'];
		/** @var OpenApiSchema */
		$schema = \ByJG\ApiTools\Base\Schema::getInstance($data);
		$this->setSchema($schema);

		// Optmize loading time
		$systemConfig = \OCP\Server::get(\OC\SystemConfig::class);
		$systemConfig->setValue('auth.bruteforce.protection.enabled', false);

		// Reset settings
		$this->mockAppConfig([
			'identification_documents' => '0',
		]);

		$this->request = new \OCA\Libresign\Tests\Api\ApiRequester();
	}

	/**
	 * configure the schema to use for requests
	 *
	 * When set, all requests without an own schema use this one instead.
	 */
	public function setSchema(Schema|null $schema):void {
		$this->schema = $schema;
	}

	public function setRequester(AbstractRequester $requester):void {
		$this->requester = $requester;
	}

	/**
	 * @return AbstractRequester
	 */
	protected function getRequester():AbstractRequester|null {
		if (is_null($this->requester)) {
			$this->requester = new ApiRequester();
		}
		return $this->requester;
	}

	/**
	 * @throws DefinitionNotFoundException
	 * @throws GenericSwaggerException
	 * @throws HttpMethodNotFoundException
	 * @throws InvalidDefinitionException
	 * @throws NotMatchedException
	 * @throws PathNotFoundException
	 * @throws StatusCodeNotMatchedException
	 * @throws MessageException
	 */
	public function assertRequest(AbstractRequester $request = null):Response|ResponseInterface {
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
	protected function checkSchema():void {
		if (!$this->schema) {
			throw new GenericSwaggerException('You have to configure a schema for either the request or the testcase');
		}
	}
}
