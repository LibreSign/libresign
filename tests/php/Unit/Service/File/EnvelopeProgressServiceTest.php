<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\Db\File as DbFile;
use OCA\Libresign\Db\SignRequest as DbSignRequest;
use OCA\Libresign\Service\File\EnvelopeProgressService;
use OCA\Libresign\Tests\Unit\TestCase;

final class EnvelopeProgressServiceTest extends TestCase {
	private function makeSigner(string $method, string $value): \stdClass {
		$s = new \stdClass();
		$s->identifyMethods = [['method' => $method, 'value' => $value]];
		return $s;
	}

	private function identifyMethodWrapper(string $method, string $value): array {
		// return as array of arrays to match real identify-method service shape
		return [[new class($method, $value) {
			private string $method;
			private string $value;

			public function __construct(string $method, string $value) {
				$this->method = $method;
				$this->value = $value;
			}

			public function getEntity(): object {
				return new class($this->method, $this->value) {
					private string $method;
					private string $value;

					public function __construct(string $method, string $value) {
						$this->method = $method;
						$this->value = $value;
					}

					public function getIdentifierKey(): string {
						return $this->method;
					}
					public function getIdentifierValue(): string {
						return $this->value;
					}
				};
			}
		}]];
	}

	private function makeSignRequest(int $id, ?\DateTime $signed, ?string $method = null, ?string $value = null): DbSignRequest {
		$sr = new DbSignRequest();
		$sr->setId($id);
		$sr->setSigned($signed);
		return $sr;
	}

	public function testNoChildrenResultsZero(): void {
		$service = new EnvelopeProgressService();

		$fileData = new \stdClass();
		$fileData->signers = [ $this->makeSigner('email', 'b@example.com') ];

		$childrenFiles = [];
		$signRequestsByFileId = [];
		$identifyMethodsBySignRequest = [];

		$envelope = new DbFile();
		$envelope->setId(200);

		$service->computeProgress($fileData, $envelope, $childrenFiles, $signRequestsByFileId, $identifyMethodsBySignRequest);

		$this->assertSame(0, $fileData->signers[0]->totalDocuments);
		$this->assertSame(0, $fileData->signers[0]->documentsSignedCount);
	}

	public function testSingleChildAllUnsigned(): void {
		$service = new EnvelopeProgressService();

		$fileData = new \stdClass();
		$fileData->signers = [ $this->makeSigner('email', 'a@example.com') ];

		$child = new DbFile();
		$child->setId(21);
		$childrenFiles = [$child];

		$sr1 = $this->makeSignRequest(4, null);
		$sr2 = $this->makeSignRequest(5, null);

		$signRequestsByFileId = [21 => [$sr1, $sr2]];
		$identifyMethodsBySignRequest = [4 => $this->identifyMethodWrapper('email', 'a@example.com'), 5 => $this->identifyMethodWrapper('email', 'a@example.com')];

		$envelope = new DbFile();
		$envelope->setId(101);

		$service->computeProgress($fileData, $envelope, $childrenFiles, $signRequestsByFileId, $identifyMethodsBySignRequest);

		$this->assertSame(2, $fileData->signers[0]->totalDocuments);
		$this->assertSame(0, $fileData->signers[0]->documentsSignedCount);
	}

	public function testMixedSignedMultipleChildren(): void {
		$service = new EnvelopeProgressService();

		$fileData = new \stdClass();
		$fileData->signers = [ $this->makeSigner('email', 'a@example.com') ];

		$child1 = new DbFile();
		$child1->setId(11);
		$child2 = new DbFile();
		$child2->setId(12);
		$childrenFiles = [$child1, $child2];

		$sr1 = $this->makeSignRequest(1, null, 'email', 'a@example.com');
		$sr2 = $this->makeSignRequest(2, new \DateTime(), 'email', 'a@example.com');
		$sr3 = $this->makeSignRequest(3, new \DateTime(), 'email', 'a@example.com');

		$signRequestsByFileId = [11 => [$sr1, $sr2], 12 => [$sr3]];
		$identifyMethodsBySignRequest = [1 => $this->identifyMethodWrapper('email', 'a@example.com'), 2 => $this->identifyMethodWrapper('email', 'a@example.com'), 3 => $this->identifyMethodWrapper('email', 'a@example.com')];

		$envelope = new DbFile();
		$envelope->setId(100);

		$service->computeProgress($fileData, $envelope, $childrenFiles, $signRequestsByFileId, $identifyMethodsBySignRequest);

		$this->assertSame(3, $fileData->signers[0]->totalDocuments);
		$this->assertSame(2, $fileData->signers[0]->documentsSignedCount);
	}
}
