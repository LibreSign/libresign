<?php

declare(strict_types=1);

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setProvider(string $provider)
 * @method string getProvider()
 *
 * @method void setCountry(string $country)
 * @method string getCountry()
 *
 * @method void setCountryCode(?string $countryCode)
 * @method ?string getCountryCode()
 *
 * @method void setPrefix(?string $prefix)
 * @method ?string getPrefix()
 *
 * @method void setCurrency(?string $currency)
 * @method ?string getCurrency()
 *
 * @method void setInstructions(?string $instructions)
 * @method ?string getInstructions()
 *
 * @method void setLogo(?string $logo)
 * @method ?string getLogo()
 *
 * @method void setRawPayload(?string $payload)
 * @method ?string getRawPayload()
 *
 * @method void setCreatedAt(string $createdAt)
 * @method string getCreatedAt()
 *
 * @method void setUpdatedAt(?string $updatedAt)
 * @method ?string getUpdatedAt()
 */
class DpoMobileOption extends Entity
{
	public function __construct()
	{
		$this->addType('id', 'integer');

		$this->addType('provider', 'string');
		$this->addType('country', 'string');
		$this->addType('countryCode', 'string');
		$this->addType('prefix', 'string');
		$this->addType('currency', 'string');
		$this->addType('instructions', 'string');
		$this->addType('logo', 'string');

		// store JSON as string (consistent with your Payment entity pattern)
		$this->addType('rawPayload', 'string');

		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
	}
}
