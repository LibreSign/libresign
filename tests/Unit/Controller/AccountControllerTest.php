<?php

namespace OCA\Libresign\Tests\Unit\Controller;

use donatj\MockWebServer\Response;
use OCA\Libresign\Tests\Unit\ApiTestCase;

/**
 * @group DB
 */
final class AccountControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testAccountCreateWithInvalidUuid() {
		$this->createUser('username', 'password');

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'email' => 'testuser01@test.coop',
				'password' => 'secret',
				'signPassword' => 'secretToSign'
			])
			->withPath('/account/create/1234564789')
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Invalid UUID', $body['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testAccountCreateWithSuccess() {
		$user = $this->createUser('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'email' => 'person@test.coop'
				]
			],
			'userManager' => $user
		]);

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'email' => 'person@test.coop',
				'password' => 'secret',
				'signPassword' => 'secretToSign'
			])
			->withPath('/account/create/' . $file['users'][0]->getUuid());

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testAccountSignatureEndpointWithSuccess() {
		$user = $this->createUser('username', 'password');
		$user->setEMailAddress('person@test.coop');

		self::$server->setResponseOfPath('/api/v1/cfssl/newcert', new Response(
			file_get_contents(__DIR__ . '/../../fixtures/cfssl/newcert-with-success.json')
		));

		$this->mockConfig([
			'libresign' => [
				'notifyUnsignedUser' => 0,
				'commonName' => 'CommonName',
				'country' => 'Brazil',
				'organization' => 'Organization',
				'organizationUnit' => 'organizationUnit',
				'cfsslUri' => self::$server->getServerRoot() . '/api/v1/cfssl/'
			]
		]);

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'signPassword' => 'password'
			])
			->withPath('/account/signature');

		$home = $user->getHome();
		$this->assertFileDoesNotExist($home . '/files/LibreSign/signature.pfx');
		$this->assertRequest();
		$this->assertFileExists($home . '/files/LibreSign/signature.pfx');
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testAccountSignatureEndpointWithFailure() {
		$this->createUser('username', 'password');

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'signPassword' => ''
			])
			->withPath('/account/signature')
			->assertResponseCode(401);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPostProfileDocumentsWithInvalidData() {
		$this->createUser('username', 'password');

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'files' => [
					[
						'type' => 'INVALID',
						'file' => [
							'base64' => 'invalid'
						]
					]
				]
			])
			->withPath('/account/profile/files')
			->assertResponseCode(401);

		$this->assertRequest();
	}
}
