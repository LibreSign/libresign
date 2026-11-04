<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Api\Controller;

use OCA\Libresign\Service\FileElementService;
use OCA\Libresign\Tests\Api\ApiTestCase;
use OCP\IDBConnection;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @group DB
 * @internal
 */
final class VisibleElementAuthorizationApiTest extends ApiTestCase {
	public static function unauthorizedRequests(): array {
		return [
			'different owner, PATCH' => ['other-owner-patch'],
			'same owner, different document, PATCH' => ['other-document-patch'],
			'signer from different document, POST' => ['other-document-signer'],
			'different owner, DELETE' => ['other-owner-delete'],
			'same owner, different document, DELETE' => ['other-document-delete'],
		];
	}

	/**
	 * @runInSeparateProcess
	 */
	#[DataProvider('unauthorizedRequests')]
	public function testUnauthorizedRequestCannotChangePersistedElements(string $scenario): void {
		$owner = $this->createAccount('element_owner_a', 'password');
		$owner->setEMailAddress('element-a@test.coop');
		$otherOwner = $this->createAccount('element_owner_b', 'password');
		$otherOwner->setEMailAddress('element-b@test.coop');

		$createFile = function ($user, string $email, string $name) {
			return $this->requestSignFile([
				'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))],
				'name' => $name,
				'signers' => [[
					'identifyMethods' => [[
						'method' => 'email',
						'mandatory' => 0,
						'value' => $email,
					]],
				]],
				'userManager' => $user,
			]);
		};

		$firstFile = $createFile($owner, 'element-a@test.coop', 'document A');
		$secondFile = $createFile($otherOwner, 'element-b@test.coop', 'document B');
		$thirdFile = $createFile($owner, 'element-a@test.coop', 'document C');
		$firstSignerId = $this->getSignersFromFileId($firstFile->getId())[0]->getId();
		$secondSignerId = $this->getSignersFromFileId($secondFile->getId())[0]->getId();
		$thirdSignerId = $this->getSignersFromFileId($thirdFile->getId())[0]->getId();
		$coordinates = ['page' => 1, 'llx' => 10, 'lly' => 10, 'urx' => 110, 'ury' => 60];

		$element = \OCP\Server::get(FileElementService::class)->saveVisibleElement([
			'uuid' => $firstFile->getUuid(),
			'fileId' => $firstFile->getId(),
			'signRequestId' => $firstSignerId,
			'type' => 'signature',
			'coordinates' => $coordinates,
			'metadata' => [],
		]);
		$elementId = $element->getId();
		$this->assertGreaterThan(0, $elementId);

		$readRows = static function (): array {
			$db = \OCP\Server::get(IDBConnection::class);
			$qb = $db->getQueryBuilder();
			$qb->select('id', 'file_id', 'sign_request_id', 'type', 'page', 'llx', 'lly', 'urx', 'ury')
				->from('libresign_file_element')
				->orderBy('id', 'ASC');
			$result = $qb->executeQuery();
			try {
				return $result->fetchAllAssociative();
			} finally {
				$result->closeCursor();
			}
		};
		$before = $readRows();
		$this->assertCount(1, $before);
		$this->assertSame($firstFile->getId(), (int)$before[0]['file_id']);

		$targetFile = $secondFile;
		$targetSignerId = $secondSignerId;
		$user = 'element_owner_b';
		$method = 'PATCH';
		$body = [
			'fileId' => $targetFile->getId(),
			'signRequestId' => $targetSignerId,
			'type' => 'signature',
			'coordinates' => ['page' => 1, 'llx' => 20, 'lly' => 20, 'urx' => 120, 'ury' => 70],
		];

		switch ($scenario) {
			case 'other-document-patch':
				$user = 'element_owner_a';
				$targetFile = $thirdFile;
				$targetSignerId = $thirdSignerId;
				break;
			case 'other-document-signer':
				$user = 'element_owner_a';
				$targetFile = $firstFile;
				$targetSignerId = $secondSignerId;
				$method = 'POST';
				break;
			case 'other-owner-delete':
				$method = 'DELETE';
				break;
			case 'other-document-delete':
				$user = 'element_owner_a';
				$targetFile = $thirdFile;
				$targetSignerId = $thirdSignerId;
				$method = 'DELETE';
				break;
		}

		$path = '/api/v1/file-element/' . $targetFile->getUuid();
		if ($method !== 'POST') {
			$path .= '/' . $elementId;
		}
		$this->request
			->withPath($path)
			->withMethod($method)
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode($user . ':password'),
				'Content-Type' => 'application/json',
			])
			->expectStatus(404);
		if ($method !== 'DELETE') {
			$body['fileId'] = $targetFile->getId();
			$body['signRequestId'] = $targetSignerId;
			$this->request->withRequestBody($body);
		}
		$this->assertRequest();
		$this->assertSame($before, $readRows(), 'Denied operations must not modify persisted records');
	}
}
