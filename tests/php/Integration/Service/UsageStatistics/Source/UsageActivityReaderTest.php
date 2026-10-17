<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\UsageStatistics\Source;

use OCA\Libresign\Db\Crl;
use OCA\Libresign\Db\CrlMapper;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\CertificateType;
use OCA\Libresign\Enum\CRLStatus;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\NodeType;
use OCA\Libresign\Enum\ParticipantRole;
use OCA\Libresign\Service\UsageStatistics\Model\ReportPeriod;
use OCA\Libresign\Service\UsageStatistics\Source\UsageActivityReader;
use OCP\IDBConnection;
use OCP\Server;

/**
 * @group DB
 */
final class UsageActivityReaderTest extends \OCA\Libresign\Tests\Integration\TestCase {
	private FileMapper $fileMapper;
	private SignRequestMapper $signRequestMapper;
	private CrlMapper $crlMapper;
	private UsageActivityReader $reader;
	private int $sequence = 0;

	public function setUp(): void {
		parent::setUp();
		$this->fileMapper = Server::get(FileMapper::class);
		$this->signRequestMapper = Server::get(SignRequestMapper::class);
		$this->crlMapper = Server::get(CrlMapper::class);
		$this->reader = Server::get(UsageActivityReader::class);
		$this->deleteCertificates();
	}

	public function tearDown(): void {
		$this->deleteCertificates();
		parent::tearDown();
	}

	public function testNothingRecordedYet(): void {
		$this->assertNull($this->reader->firstActivityAt());
		$this->assertNull($this->reader->firstCertificateRecordedAt());
		$this->assertSame(0, $this->reader->countStandaloneFilesCreated($this->august()));
	}

	public function testCountsEachEventOnceInsideTheStartInclusiveEndExclusivePeriod(): void {
		$this->seedActivity();

		$august = $this->august();
		$this->assertSame(1, $this->reader->countStandaloneFilesCreated($august), 'files');
		$this->assertSame(2, $this->reader->countEnvelopesCreated($august), 'envelopes');
		$this->assertSame(4, $this->reader->countSigningRequestsCreated($august), 'requests');
		$this->assertSame(3, $this->reader->countSigningRequestsCompleted($august), 'completed');
		$this->assertSame(0, $this->reader->countSigningRequestsRejected($august), 'rejected');
		$this->assertSame(1, $this->reader->countObserversAdded($august), 'observers');

		$september = $august->next();
		$this->assertSame(1, $this->reader->countStandaloneFilesCreated($september), 'files in September');
		$this->assertSame(1, $this->reader->countSigningRequestsRejected($september), 'rejected in September');
	}

	public function testAPeriodWithoutActivityCountsZero(): void {
		$this->seedActivity();

		$october = $this->august()->next()->next();
		$this->assertSame(0, $this->reader->countStandaloneFilesCreated($october));
		$this->assertSame(0, $this->reader->countEnvelopesCreated($october));
		$this->assertSame(0, $this->reader->countSigningRequestsCreated($october));
		$this->assertSame(0, $this->reader->countSigningRequestsCompleted($october));
		$this->assertSame(0, $this->reader->countObserversAdded($october));
		$this->assertSame(0, $this->reader->countSigningCertificatesIssued($october));
	}

	public function testTheFirstActivityIsTheOldestLibreSignFile(): void {
		$this->seedActivity();

		$this->assertSame('2026-07-31 23:59:59', $this->reader->firstActivityAt()?->format('Y-m-d H:i:s'));
	}

	public function testOnlySigningCertificatesAreCounted(): void {
		$this->insertCertificate(CertificateType::ROOT, '2026-08-01 08:00:00');
		$this->insertCertificate(CertificateType::LEAF, '2026-08-02 10:00:00');
		$this->insertCertificate(CertificateType::LEAF, '2026-08-20 10:00:00', '2026-08-25 10:00:00');
		$this->insertCertificate(CertificateType::LEAF, '2026-09-01 00:00:00');
		$this->insertCertificate(CertificateType::INTERMEDIATE, '2026-08-03 00:00:00', '2026-08-04 00:00:00');

		$this->assertSame(2, $this->reader->countSigningCertificatesIssued($this->august()));
		$this->assertSame(1, $this->reader->countSigningCertificatesRevoked($this->august()));
		$this->assertSame('2026-08-01 08:00:00', $this->reader->firstCertificateRecordedAt()?->format('Y-m-d H:i:s'));
	}

	/**
	 * August 2026: one standalone file, one envelope with two files, one
	 * envelope stored before node_type existed, one identification document.
	 */
	private function seedActivity(): void {
		$standalone = $this->insertFile('2026-08-01 00:00:00');
		$this->insertSignRequest($standalone, '2026-08-01 00:00:00', signed: '2026-08-31 23:59:59');
		$this->insertSignRequest($standalone, '2026-08-05 00:00:00', rejected: '2026-09-01 00:00:00');
		$this->insertSignRequest($standalone, '2026-08-05 00:00:00', role: ParticipantRole::OBSERVER);

		$this->insertFile('2026-09-01 00:00:00');
		$this->insertFile('2026-07-31 23:59:59');

		$envelope = $this->insertFile('2026-08-10 00:00:00', NodeType::ENVELOPE);
		foreach (['2026-08-10 00:00:01', '2026-08-10 00:00:02'] as $createdAt) {
			$child = $this->insertFile($createdAt, NodeType::FILE, $envelope);
			$this->insertSignRequest($child, $createdAt, signed: '2026-08-12 00:00:00');
			$this->insertSignRequest($child, $createdAt, signed: '2026-08-12 00:00:00');
		}
		$this->insertSignRequest($envelope, '2026-08-10 00:00:00', signed: '2026-08-12 00:00:00');
		$this->insertSignRequest($envelope, '2026-08-10 00:00:00', signed: '2026-08-12 00:00:00');

		$legacyEnvelope = $this->insertFile('2026-08-11 00:00:00');
		$this->insertFile('2026-08-11 00:00:01', NodeType::FILE, $legacyEnvelope);

		$identificationDocument = $this->insertFile('2026-08-15 00:00:00');
		$idDocSignRequest = $this->insertSignRequest($identificationDocument, '2026-08-15 00:00:00', signed: '2026-08-16 00:00:00');
		Server::get(IdDocsMapper::class)->save($identificationDocument->getId(), $idDocSignRequest->getId(), 'usage-user', 'IDENTIFICATION');
	}

	private function insertFile(string $createdAt, NodeType $nodeType = NodeType::FILE, ?File $parent = null): File {
		$file = new File();
		$file->setNodeId(900000 + ++$this->sequence);
		$file->setUserId('usage-user');
		$file->setUuid(sprintf('00000000-0000-4000-8000-%012d', $this->sequence));
		$file->setCreatedAt(new \DateTime($createdAt, new \DateTimeZone('UTC')));
		$file->setName('usage-' . $this->sequence . '.pdf');
		$file->setStatus(FileStatus::DRAFT->value);
		$file->setNodeTypeEnum($nodeType);
		$file->setParentFileId($parent?->getId());
		return $this->fileMapper->insert($file);
	}

	private function insertSignRequest(
		File $file,
		string $createdAt,
		?string $signed = null,
		?string $rejected = null,
		ParticipantRole $role = ParticipantRole::SIGNER,
	): SignRequest {
		$utc = new \DateTimeZone('UTC');
		$signRequest = new SignRequest();
		$signRequest->setFileId($file->getId());
		$signRequest->setDisplayName('Signer ' . ++$this->sequence);
		$signRequest->setUuid(sprintf('11111111-0000-4000-8000-%012d', $this->sequence));
		$signRequest->setCreatedAt(new \DateTime($createdAt, $utc));
		$signRequest->setParticipantRole($role->value);
		if ($signed !== null) {
			$signRequest->setSigned(new \DateTime($signed, $utc));
		}
		if ($rejected !== null) {
			$signRequest->setRejectedAt(new \DateTime($rejected, $utc));
		}
		return $this->signRequestMapper->insert($signRequest);
	}

	private function insertCertificate(CertificateType $type, string $issuedAt, ?string $revokedAt = null): void {
		$utc = new \DateTimeZone('UTC');
		$certificate = new Crl();
		$certificate->setSerialNumber((string)(700000 + ++$this->sequence));
		$certificate->setOwner('usage-user');
		$certificate->setStatus($revokedAt === null ? CRLStatus::ISSUED : CRLStatus::REVOKED);
		$certificate->setIssuedAt(new \DateTime($issuedAt, $utc));
		$certificate->setCertificateType($type->value);
		if ($revokedAt !== null) {
			$certificate->setRevokedAt(new \DateTime($revokedAt, $utc));
		}
		$this->crlMapper->insert($certificate);
	}

	private function august(): ReportPeriod {
		return ReportPeriod::monthContaining(new \DateTimeImmutable('2026-08-15T00:00:00Z'));
	}

	private function deleteCertificates(): void {
		Server::get(IDBConnection::class)->getQueryBuilder()->delete('libresign_crl')->executeStatement();
	}
}
