<?php

namespace OCA\Libresign\Tests\Unit\Controller;

use ByJG\ApiTools\OpenApi\OpenApiSchema;
use PHPUnit\Framework\TestCase;
use OCA\Libresign\Tests\Unit\ApiTestTrait;
use Symfony\Component\Yaml\Yaml;

final class SignatureControllerTest extends TestCase {
	use ApiTestTrait;
	public function setUp(): void
	{
		$data = Yaml::parse(file_get_contents('docs/.vuepress/public/specs/api.yaml'));
		$data['servers'][] = ['url' => 'http://localhost/apps/libresign/api/0.1'];
		/** @var OpenApiSchema */
		$schema = \ByJG\ApiTools\Base\Schema::getInstance($data);
		$this->setSchema($schema);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testHasRootCertReturnSuccess() {
		$request = new \OCA\Libresign\Tests\Unit\ApiRequester();
		$request
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('admin:admin')
			])
			->withPath('/signature/has-root-cert');

		$this->assertRequest($request);
	}
}
