<?php

declare(strict_types=1);

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Represents a row in the gopaperless_entitlements table.
 *
 * Key Concept:
 * - Entitlement = what a user is allowed to do AFTER payment
 *
 * Example:
 * - Product: SIGN_DOCUMENT
 * - Entitlement: remaining_uses = 1
 *
 * IMPORTANT:
 * - Created ONLY after successful payment
 * - Checked BEFORE allowing an action (e.g. signing)
 *
 * Future-ready:
 * - remaining_uses → supports credits
 * - expires_at → supports subscriptions
 *
 * @method void setUserId(string $userId)
 * @method string getUserId()
 *
 * @method void setProductCode(string $code)
 * @method string getProductCode()
 *
 * @method void setRemainingUses(?int $uses)
 * @method ?int getRemainingUses()
 *
 * @method void setExpiresAt(?\DateTimeInterface | string $date)
 * @method ?\DateTimeInterface getExpiresAt()
 *
 * @method void setCreatedAt(\DateTimeInterface | string $date)
 * @method \DateTimeInterface getCreatedAt()
 */
class Entitlement extends Entity {

	/**
	 * CRITICAL:
	 * Initialize ALL typed properties to avoid hydration errors
	 */

	protected string $userId = '';
	protected string $productCode = '';

	/**
	 * NULL = unlimited (future use)
	 */
	protected ?int $remainingUses = null;

	/**
	 * NULL = no expiry
	 */
	protected ?\DateTimeInterface $expiresAt = null;

	protected \DateTimeInterface $createdAt;

	/**
	 * @throws \Exception
	 */
	public function __construct() {

		// Ensure createdAt is ALWAYS initialized
		$this->createdAt = new \DateTimeImmutable(
			'now', new \DateTimeZone('UTC')
		);

		/**
		 * Type mappings for Nextcloud hydration
		 */

		// integers
		$this->addType('remainingUses', 'integer');

		// datetime
		$this->addType('createdAt', 'datetime');
		$this->addType('expiresAt', 'datetime');

		// strings
		$this->addType('userId', 'string');
		$this->addType('productCode', 'string');
	}

	/**
	 * Validate entity before insert/update
	 */
	public function validate(): void {

		if ($this->userId === '') {
			throw new \InvalidArgumentException('userId is required');
		}

		if ($this->productCode === '') {
			throw new \InvalidArgumentException('productCode is required');
		}

		// remainingUses can be null (unlimited)
		if ($this->remainingUses !== null && $this->remainingUses < 0) {
			throw new \InvalidArgumentException('remainingUses cannot be negative');
		}
	}

	/**
	 * Check if entitlement is expired
	 */
	public function isExpired(): bool {
		return $this->expiresAt !== null && $this->expiresAt < new \DateTime();
	}

	/**
	 * Check if entitlement can be used
	 */
	public function canUse(): bool {

		// Expiry check
		if ($this->isExpired()) {
			return false;
		}

		// Unlimited usage
		if ($this->remainingUses === null) {
			return true;
		}

		return $this->remainingUses > 0;
	}

	/**
	 * Consume one usage
	 *
	 * NOTE:
	 * - Does NOT persist (service must handle update)
	 */
	public function consume(): void {

		if ($this->remainingUses === null) {
			// unlimited → nothing to decrement
			return;
		}

		if ($this->remainingUses <= 0) {
			throw new \RuntimeException('No remaining uses');
		}

		$this->setRemainingUses($this->remainingUses - 1);
	}

	public function getRemainingUses(): ?int {
		return $this->remainingUses;
	}
}
