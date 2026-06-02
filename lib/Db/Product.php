<?php

declare(strict_types=1);

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Represents a row in the gopaperless_products table.
 *
 * Key Concepts:
 * - code = WHAT the user is paying for (e.g. SIGN_DOCUMENT)
 * - row  = pricing variant (can have multiple per code)
 *
 * IMPORTANT:
 * - Only ONE product per code should have isDefault = true
 * - Version is currently unused (reserved for future)
 *
 * @method void setCode(string $code)
 * @method string getCode()
 *
 * @method void setUses(int $uses)
 * @method int getUses()
 *
 * @method void setName(string $name)
 * @method string getName()
 *
 * @method void setAmount(int $amount)
 * @method int getAmount()
 *
 * @method void setCurrency(string $currency)
 * @method string getCurrency()
 *
 * @method void setVersion(int $version)
 * @method int getVersion()
 *
 * @method void setActive(bool $active)
 * @method bool getActive()
 *
 * @method void setIsDefault(bool $isDefault)
 * @method bool getIsDefault()
 *
 * @method void setCreatedAt(\DateTimeInterface | string $date)
 * @method \DateTimeInterface getCreatedAt()
 *
 * @method void setUpdatedAt(\DateTimeInterface | string $date)
 * @method \DateTimeInterface getUpdatedAt()
 */
class Product extends Entity {

	/**
	 * Same rule as Payment:
	 * All typed properties MUST be initialized
	 */

	protected string $code = '';
	protected string $name = '';

	protected int $amount = 0;
	protected int $uses = 0;
	protected string $currency = '';

	/**
	 * Reserved for future versioning (NOT used yet)
	 */
	protected int $version = 1;

	protected bool $active = true;
	protected bool $isDefault = false;

	protected \DateTimeInterface $createdAt;
	protected \DateTimeInterface $updatedAt;

	/**
	 * @throws \Exception
	 */
	public function __construct() {

		// Ensure timestamps are always initialized
		$this->createdAt = new \DateTimeImmutable(
			'now', new \DateTimeZone('UTC')
		);
		$this->updatedAt = new \DateTimeImmutable(
			'now', new \DateTimeZone('UTC')
		);

		/**
		 * Type mappings for hydration
		 */

		// integers
		$this->addType('amount', 'integer');
		$this->addType('uses', 'integer');
		$this->addType('version', 'integer');

		// booleans
		$this->addType('active', 'boolean');
		$this->addType('isDefault', 'boolean');

		// datetime
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');

		// strings
		$this->addType('code', 'string');
		$this->addType('name', 'string');
		$this->addType('currency', 'string');
	}

	/**
	 * Defensive validation before insert/update
	 */
	public function validate(): void {
		if ($this->code === '') {
			throw new \InvalidArgumentException('Product code is required');
		}
		if ($this->name === '') {
			throw new \InvalidArgumentException('Product name is required');
		}
		if ($this->amount <= 0) {
			throw new \InvalidArgumentException('Product amount must be greater than 0');
		}
		if ($this->uses <= 0) {
			throw new \InvalidArgumentException('Product uses must be greater than 0');
		}
		if ($this->currency === '') {
			throw new \InvalidArgumentException('Product currency is required');
		}
	}
}
