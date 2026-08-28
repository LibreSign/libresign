<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Api\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicy;
use OCA\Libresign\Service\Policy\Provider\SignerGeolocation\SignerGeolocationPolicy;
use OCA\Libresign\Service\SignerGeolocation\SignerGeolocationPolicyService;
use OCA\Libresign\Tests\Api\ApiTestCase;

/**
 * @group DB
 */
final class SignerGeolocationSignFileControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testSignRejectsMissingRequiredGeolocation(): void {
		$user = $this->createAccount('geosigner', 'password');
		$this->configurePoliciesForGeolocationSigning();

		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))],
			'name' => 'geolocation-required',
			'signers' => [
				[
					'identifyMethods' => [[
						'method' => 'account',
						'value' => 'geosigner',
					]],
				],
			],
			'userManager' => $user,
		]);

		$signers = $this->getSignersFromFileId($file->getId());
		$this->assertSame(
			'required',
			($signers[0]->getMetadata() ?? [])[SignerGeolocationPolicyService::METADATA_REQUIREMENT_KEY] ?? null,
		);

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('geosigner:password'),
				'Content-Type' => 'application/json',
			])
			->withPath('/api/v1/sign/uuid/' . $signers[0]->getUuid())
			->withRequestBody([
				'method' => 'clickToSign',
			])
			->expectStatus(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertSame(
			'Geolocation is required to sign this document.',
			$body['ocs']['data']['errors'][0]['message'] ?? null,
		);
	}

	private function configurePoliciesForGeolocationSigning(): void {
		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueString(
			Application::APP_ID,
			'groups_request_sign',
			'{"allowGroups":["admin","testGroup"],"denyGroups":[]}',
		);

		$policyService = \OCP\Server::get(PolicyService::class);
		$policyService->saveSystem(
			SignerGeolocationPolicy::KEY,
			[
				'mode' => 'required',
				'allowRequesterOverride' => false,
			],
			false,
		);
		$policyService->saveSystem(
			IdentifyMethodsPolicy::KEY,
			[
				'factors' => [
					[
						'name' => 'account',
						'enabled' => true,
						'requirement' => 'required',
						'signatureMethods' => [
							'clickToSign' => ['enabled' => true],
						],
					],
				],
			],
			false,
		);
	}
}
