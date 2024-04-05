<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 * @method int getSignRequestId()
 * @method void setSignRequestId(int $signRequestId)
 * @method string getType()
 * @method void setType(string $type)
 * @method array getMetadata()
 * @method void setMetadata(array $metadata)
 * @method int getPage()
 * @method void setPage(int $page)
 * @method int getUrx()
 * @method void setUrx(int $urx)
 * @method int getUry()
 * @method void setUry(int $ury)
 * @method int getLlx()
 * @method void setLlx(int $llx)
 * @method int getLly()
 * @method void setLly(int $lly)
 * @method \DateTime getCreatedAt()
 */
class FileElement extends Entity {
	/** @var integer */
	public $id;

	/** @var integer */
	protected $fileId;

	/** @var int */
	protected $signRequestId;

	/** @var string */
	protected $type;

	/** @var string */
	protected $metadata;

	/** @var integer */
	protected $page;

	/** @var integer */
	protected $urx;

	/** @var integer */
	protected $ury;

	/** @var integer */
	protected $llx;

	/** @var integer */
	protected $lly;

	/** @var \DateTime */
	protected $createdAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('fileId', 'integer');
		$this->addType('signRequestId', 'integer');
		$this->addType('type', 'string');
		$this->addType('metadata', Types::JSON);
		$this->addType('page', 'integer');
		$this->addType('urx', 'integer');
		$this->addType('ury', 'integer');
		$this->addType('llx', 'integer');
		$this->addType('lly', 'integer');
		$this->addType('createdAt', 'datetime');
	}

	/**
	 * @param \DateTime|string $createdAt
	 */
	public function setCreatedAt($createdAt): void {
		if (!$createdAt instanceof \DateTime) {
			$createdAt = new \DateTime($createdAt);
		}
		$this->createdAt = $createdAt;
		$this->markFieldUpdated('createdAt');
	}
}
