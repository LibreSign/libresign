<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Tests\Unit\TestCase;
use Psr\Http\Message\ResponseInterface;

class ApiTestCase extends TestCase {
	/**
	 * @var Schema
	 */
	protected $schema;

	protected ?AbstractRequester $requester = null;

	/**
	 * @var \OCA\Libresign\Tests\Api\ApiRequester
	 */
	public $request;

	public function setUp(): void {
		parent::setUp();
		$data = json_decode(file_get_contents('openapi-full.json'), true);
		$data['servers'][] = ['url' => '/ocs/v2.php/ocsapp/apps/libresign'];
		$data = $this->removeBasePath($data);
		/** @var OpenApiSchema */
		$schema = \ByJG\ApiTools\Base\Schema::getInstance($data);
		$this->setSchema($schema);

		// Optmize loading time
		$systemConfig = \OCP\Server::get(\OC\SystemConfig::class);
		$systemConfig->setValue('auth.bruteforce.protection.enabled', false);

		// Reset settings
		$this->getMockAppConfig()->setValueBool(Application::APP_ID, 'identification_documents', false);

		$this->request = new \OCA\Libresign\Tests\Api\ApiRequester();
	}

	private function removeBasePath(array $data): array {
		$cleaned = [];
		foreach ($data['paths'] as $key => $value) {
			$key = preg_replace('~^/ocs/v2.php/apps/libresign~', '', (string)$key);
			$cleaned[$key] = $value;
		}
		$data['paths'] = $cleaned;
		return $data;
	}

	/**
	 * configure the schema to use for requests
	 *
	 * When set, all requests without an own schema use this one instead.
	 */
	public function setSchema(?Schema $schema):void {
		$this->schema = $schema;
	}

	public function setRequester(AbstractRequester $requester):void {
		$this->requester = $requester;
	}

	/**
	 * @return AbstractRequester
	 */
	protected function getRequester():?AbstractRequester {
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
	public function assertRequest(?AbstractRequester $request = null):Response|ResponseInterface {
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
