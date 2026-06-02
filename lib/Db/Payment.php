<?php

declare(strict_types=1);

namespace OCA\Libresign\Db;

use DateTimeInterface;
use OCA\Libresign\Enum\PaymentProvider;
use OCA\Libresign\Enum\PaymentStatus;
use OCA\Libresign\Service\Payment\DTO\PaymentMetadataDTO;
use OCP\AppFramework\Db\Entity;

/**
 * Represents a row in the gopaperless_payments table.
 *
 * NOTE:
 * Nextcloud Entity uses reflection and WILL try to access ALL properties.
 * Therefore, ALL typed properties MUST be initialized (either here or via setters).
 *
 * @method void setPaymentAttemptId(string $id)
 * @method string getPaymentAttemptId()
 *
 * @method void setTransactionId(int $id)
 * @method int getTransactionId()
 *
 * @method void setTransactionReference(?string $reference)
 * @method ?string getTransactionReference()
 *
 * @method void setUserId(?string $userId)
 * @method ?string getUserId()
 *
 * @method void setProvider(string $provider)
 * @method string getProvider()
 *
 * @method void setProviderReference(?string $reference)
 * @method ?string getProviderReference()
 *
 * @method void setProviderMetadata(?string $metadata)
 * @method ?string getProviderMetadata()
 *
 * @method void setAmount(int $amount)
 * @method int getAmount()
 *
 * @method void setCurrency(string $currency)
 * @method string getCurrency()
 *
 * @method void setStatus(string $status)
 * @method string getStatus()
 *
 * @method void setCreatedAt(\DateTimeInterface | string $date)
 * @method \DateTimeInterface|string getCreatedAt()
 *
 * @method void setUpdatedAt(\DateTimeInterface | string $date)
 * @method \DateTimeInterface|string getUpdatedAt()
 *
 * @method void setExpiresAt(\DateTimeInterface | string $date)
 * @method \DateTimeInterface|string getExpiresAt()
 *
 * @method void setPaidAt(?\DateTimeInterface | string $date)
 * @method ?\DateTimeInterface|string getPaidAt()
 *
 * @method void setDisplayCurrency(?string $currency)
 * @method ?string getDisplayCurrency()
 *
 * @method void setDisplayAmount(?int $amount)
 * @method ?int getDisplayAmount()
 *
 * @method void setFxRate(?string $rate)
 * @method ?string getFxRate()
 *
 * @method void setFxRateSource(?string $source)
 * @method ?string getFxRateSource()
 *
 * @method void setFxRateLockedAt(?\DateTimeInterface|string $date)
 * @method ?\DateTimeInterface|string getFxRateLockedAt()
 *
 * @method void setPhoneE164Digits(?string $digits)
 * @method ?string getPhoneE164Digits()
 *
 * @method void setPhoneRegion(?string $region)
 * @method ?string getPhoneRegion()
 *
 * @method void setPhoneCountry(?string $country)
 * @method ?string getPhoneCountry()
 *
 * @method void setVerificationStatus(?string $status)
 * @method ?string getVerificationStatus()
 *
 * @method void setVerificationLastCheckedAt(?\DateTimeInterface|string $date)
 * @method ?\DateTimeInterface|string getVerificationLastCheckedAt()
 *
 * @method void setVerificationRetryCount(int $count)
 * @method int getVerificationRetryCount()
 *
 * @method void setNextVerificationAt(?\DateTimeInterface|string $date)
 * @method ?\DateTimeInterface|string getNextVerificationAt()
 *
 * @method void setVerificationLockedAt(?\DateTimeInterface|string $date)
 * @method ?\DateTimeInterface|string getVerificationLockedAt()
 *
 * @method void setLastErrorCode(?string $code)
 * @method ?string getLastErrorCode()
 *
 * @method void setLastErrorMessage(?string $message)
 * @method ?string getLastErrorMessage()
 *
 * @method void setLastErrorAt(?\DateTimeInterface|string $date)
 * @method ?\DateTimeInterface|string getLastErrorAt()
 *
 */
class Payment extends Entity
{

	/**
	 * 🚨 CRITICAL:
	 * All non-nullable typed properties MUST have default values
	 * to avoid "must not be accessed before initialisation" errors.
	 */

	protected string $paymentAttemptId = '';
	protected int $transactionId = 0;
	protected ?string $transactionReference = null;
	protected ?string $userId = null;

	protected string $provider = '';
	protected ?string $providerReference = null;
	protected ?string $providerMetadata = null;

	protected ?string $phoneE164Digits = null;
	protected ?string $phoneRegion = null;
	protected ?string $phoneCountry = null;

	protected ?string $verificationStatus = null;
	protected DateTimeInterface|string|null $verificationLastCheckedAt = null;
	protected int $verificationRetryCount = 0;
	protected DateTimeInterface|string|null $nextVerificationAt = null;
	protected DateTimeInterface|string|null $verificationLockedAt = null;


	protected ?string $lastErrorCode = null;
	protected ?string $lastErrorMessage = null;
	protected DateTimeInterface|string|null $lastErrorAt = null;

	protected int $amount = 0;
	protected string $currency = '';

	protected ?string $displayCurrency = null;
	protected ?int $displayAmount = null;

	// fxRate stored as string to preserve precision (DECIMAL(18,6)) and avoid PHP float rounding issues.
	// This value MUST be treated as a fixed-point decimal.
	protected ?string $fxRate = null;
	protected ?string $fxRateSource = null;
	protected DateTimeInterface|string|null $fxRateLockedAt = null;

	/**
	 * Default to pending to ensure safe initialization
	 */
	protected string $status = PaymentStatus::PENDING->value;

	protected DateTimeInterface|string $createdAt = '';
	protected DateTimeInterface|string|null $paidAt = null;
	protected DateTimeInterface|string|null $updatedAt = null;
	protected DateTimeInterface|string|null $expiresAt = null;

	/**
	 * @throws \Exception
	 */
	public function __construct()
	{

		$now = $this->now();
		/**
		 * Ensure createdAt is tracked by Entity
		 * so ORM persists it during insert.
		 * Ensure DateTime is ALWAYS initialized
		 */
		$this->setCreatedAt($now);

		$this->setUpdatedAt($now);
		/**
		 * Type mappings for Nextcloud hydration
		 */

		// integer fields
		$this->addType('transactionId', 'integer');
		$this->addType('amount', 'integer');
		$this->addType('displayAmount', 'integer');
		$this->addType('verificationRetryCount', 'integer');

		// datetime fields
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
		$this->addType('expiresAt', 'datetime');
		$this->addType('paidAt', 'datetime');
		$this->addType('fxRateLockedAt', 'datetime');
		$this->addType('lastErrorAt', 'datetime');
		$this->addType('verificationLastCheckedAt', 'datetime');
		$this->addType('nextVerificationAt', 'datetime');
		$this->addType('verificationLockedAt', 'datetime');

		// string fields
		$this->addType('paymentAttemptId', 'string');
		$this->addType('userId', 'string');
		$this->addType('provider', 'string');
		$this->addType('providerReference', 'string');
		$this->addType('providerMetadata', 'string');
		$this->addType('currency', 'string');
		$this->addType('status', 'string');
		$this->addType('displayCurrency', 'string');
		$this->addType('fxRate', 'string');
		$this->addType('fxRateSource', 'string');
		$this->addType('phoneE164Digits', 'string');
		$this->addType('phoneRegion', 'string');
		$this->addType('phoneCountry', 'string');
		$this->addType('verificationStatus', 'string');
		$this->addType('lastErrorCode', 'string');
		$this->addType('lastErrorMessage', 'string');
		$this->addType('transactionReference', 'string');
	}

	/**
	 * Defensive check to ensure entity is valid before insert
	 */
	public function validate(): void
	{
		if ($this->paymentAttemptId === '') {
			throw new \InvalidArgumentException('paymentAttemptId is required');
		}
		if ($this->currency === '') {
			throw new \InvalidArgumentException('currency is required');
		}
		if ($this->provider === '') {
			throw new \InvalidArgumentException('provider is required');
		}
		if ($this->transactionId === 0) {
			throw new \InvalidArgumentException('transactionId is required');
		}
		if ($this->amount <= 0) {
			throw new \InvalidArgumentException('amount is required');
		}
	}

	public function touch(): self
	{
		$this->setUpdatedAt($this->now());

		return $this;
	}

	public function markPaid(): self
	{
		$now = $this->now();

		$this->setPaymentStatus(PaymentStatus::PAID);
		$this->setPaidAt($now);

		$this->clearVerificationState();

		$this->touch();

		return $this;
	}

	public function markFailed(
		?string $errorCode = null,
		?string $errorMessage = null
	): self {

		$now = $this->now();

		$this->setPaymentStatus(PaymentStatus::FAILED);

		$this->setLastErrorCode($errorCode);
		$this->setLastErrorMessage($errorMessage);
		$this->setLastErrorAt($now);

		$this->clearVerificationState();

		$this->touch();

		return $this;
	}

	public function markExpired(): self
	{
		$this->setPaymentStatus(PaymentStatus::EXPIRED);

		$this->clearVerificationState();

		$this->touch();

		return $this;
	}

	public function getPaymentStatus(): PaymentStatus
	{
		return PaymentStatus::from($this->status);
	}

	public function setPaymentStatus(PaymentStatus $status): void
	{
		$this->setStatus($status->value);
	}

	public function getPaymentProvider(): PaymentProvider
	{
		return PaymentProvider::from($this->provider);
	}

	public function setPaymentProvider(PaymentProvider $provider): void
	{
		$this->setProvider($provider->value);
	}

	public function getProviderMetadataDecoded(): array
	{
		if ($this->providerMetadata === null || $this->providerMetadata === '') {
			return [];
		}

		$decoded = json_decode($this->providerMetadata, true);

		return is_array($decoded) ? $decoded : [];
	}

	public function mergeProviderMetadata(array $data): void
	{
		if (empty($data)) {
			return;
		}

		$current = $this->getProviderMetadataDecoded();

		$merged = array_merge($current, $data);

		$this->setProviderMetadataObject(
			PaymentMetadataDTO::fromArray($merged)
		);
	}

	public function getProviderMetadataObject(): PaymentMetadataDTO
	{
		return PaymentMetadataDTO::fromArray(
			$this->getProviderMetadataDecoded()
		);
	}

	public function setProviderMetadataObject(PaymentMetadataDTO $dto): void
	{
		$now = $this->now();

		$dto = $dto->with(updatedAt: $now);

		$this->setProviderMetadata(
			json_encode($dto->toArray(), JSON_THROW_ON_ERROR)
		);

		$this->setUpdatedAt($now);
	}

	/**
	 * Mark payment as actively being reconciled.
	 *
	 * Prevents concurrent background workers
	 * from verifying the same payment simultaneously.
	 */
	public function lockVerification(): self
	{
		$this->setVerificationLockedAt($this->now());
		$this->touch();

		return $this;
	}

	/**
	 * Release reconciliation lock.
	 *
	 * Allows future verification attempts
	 * if payment remains pending.
	 */
	public function unlockVerification(): self
	{
		$this->setVerificationLockedAt(null);
		$this->touch();

		return $this;
	}

	/**
	 * Stale verification locks are ignored
	 * to recover from crashed workers.
	 */
	private const VERIFICATION_LOCK_TIMEOUT_SECONDS = 600;

	/**
	 * Check whether reconciliation is currently in progress.
	 */
	public function isLocked(): bool
	{
		if ($this->verificationLockedAt === null) {
			return false;
		}

		$timeout = (
			new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
			->modify(
				'-' . self::VERIFICATION_LOCK_TIMEOUT_SECONDS . ' seconds'
			);

		$lockedAt = $this->asDateTime(
			$this->verificationLockedAt
		);

		if ($lockedAt === null) {
			return false;
		}

		return $lockedAt > $timeout;
	}

	/**
	 * Schedule next background verification attempt.
	 *
	 * Uses lightweight progressive backoff
	 * to avoid excessive provider polling.
	 */
	public function scheduleNextVerification(int $retryCount): self
	{
		$delay = match (true) {
			$retryCount === 0 => 10,
			$retryCount === 1 => 30,
			$retryCount === 2 => 60,
			$retryCount === 3 => 120,
			default => 300,
		};

		$nextVerification = (new \DateTimeImmutable(
			'now',
			new \DateTimeZone('UTC')
		))->modify("+{$delay} seconds");

		$this->setNextVerificationAt($nextVerification);
		$this->touch();

		return $this;
	}


	/**
	 * Determine whether reconciliation may continue.
	 *
	 * Prevents infinite polling for abandoned
	 * or permanently unresolved payments.
	 *
	 * TODO:
	 * - classify provider errors (transient vs final)
	 * - stop retries on non-recoverable failures
	 * - consider capped exponential backoff
	 */
	public function shouldRetry(): bool
	{
		if ($this->getPaymentStatus() === PaymentStatus::PAID) {
			return false;
		}

		if ($this->getPaymentStatus() === PaymentStatus::FAILED) {
			return false;
		}

		if ($this->getPaymentStatus() === PaymentStatus::EXPIRED) {
			return false;
		}

		if ($this->verificationRetryCount >= 6) {
			return false;
		}

		// Provider-specific terminal failure detection
		// $terminalErrorCodes = [
		// 	'INSUFFICIENT_FUNDS',
		// 	'CARD_DECLINED',
		// 	'INVALID_ACCOUNT',
		// 	'REJECTED_BY_USER',
		// ];

		// if (
		// 	$this->lastErrorCode !== null &&
		// 	in_array($this->lastErrorCode, $terminalErrorCodes, true)
		// ) {
		// 	return false;
		// }

		return true;
	}

	public function incrementVerificationRetryCount(): self
	{
		$this->setVerificationRetryCount(
			$this->verificationRetryCount + 1
		);

		$this->touch();

		return $this;
	}


	public function clearVerificationState(): self
	{
		// Release reconciliation lock
		$this->setVerificationLockedAt(null);

		// Stop reconciliation scheduling
		$this->setNextVerificationAt(null);

		$this->touch();

		return $this;
	}


	/**
	 * Determine whether payment is eligible
	 * for background verification.
	 */
	public function isReadyForVerification(): bool
	{
		if ($this->isLocked()) {
			return false;
		}

		if ($this->nextVerificationAt === null) {
			return true;
		}

		$nextVerificationAt = $this->asDateTime($this->nextVerificationAt);

		if ($nextVerificationAt === null) {
			return true;
		}

		$now = new \DateTimeImmutable(
			'now',
			new \DateTimeZone('UTC')
		);

		return $nextVerificationAt <= $now;
	}

	public function getUpdatedAtImmutable(): ?\DateTimeImmutable
	{
		return $this->asDateTime($this->updatedAt);
	}

	public function getExpiresAtImmutable(): ?\DateTimeImmutable
	{
		return $this->asDateTime($this->expiresAt);
	}

	// Date time helpers
	public function getCreatedAtImmutable(): ?\DateTimeImmutable
	{
		return $this->asDateTime($this->createdAt);
	}

	public function getPaidAtImmutable(): ?\DateTimeImmutable
	{
		return $this->asDateTime($this->paidAt);
	}

	public function getVerificationLockedAtImmutable(): ?\DateTimeImmutable
	{
		return $this->asDateTime($this->verificationLockedAt);
	}

	public function getNextVerificationAtImmutable(): ?\DateTimeImmutable
	{
		return $this->asDateTime($this->nextVerificationAt);
	}

	public function getVerificationLastCheckedAtImmutable(): ?\DateTimeImmutable
	{
		return $this->asDateTime($this->verificationLastCheckedAt);
	}

	public function getLastErrorAtImmutable(): ?\DateTimeImmutable
	{
		return $this->asDateTime($this->lastErrorAt);
	}

	public function getFxRateLockedAtImmutable(): ?\DateTimeImmutable
	{
		return $this->asDateTime($this->fxRateLockedAt);
	}

	private function asDateTime(
		\DateTimeInterface|string|null $value
	): ?\DateTimeImmutable {

		if ($value === null) {
			return null;
		}

		if ($value instanceof \DateTimeInterface) {
			return \DateTimeImmutable::createFromInterface($value);
		}

		try {
			return new \DateTimeImmutable($value);
		} catch (\Exception) {
			return null;
		}
	}

	private function now(): \DateTimeImmutable
	{
		return new \DateTimeImmutable(
			'now',
			new \DateTimeZone('UTC'),
		);
	}
}
