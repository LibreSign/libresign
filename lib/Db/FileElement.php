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
 * @method ?array getMetadata()
 * @method void setMetadata(array $metadata)
 * @method int getPage()
 * @method void setPage(int $page)
 * @method int getUrx()
 * @method void setUrx(int $urx)
 * @method int getUry()
 * @method void setUry(int $ury)
 * @method ?int getLlx()
 * @method void setLlx(int $llx)
 * @method ?int getLly()
 * @method void setLly(int $lly)
 * @method ?\DateTime getCreatedAt()
 */
class FileElement extends Entity {
	protected int $fileId = 0;
	protected int $signRequestId = 0;
	protected string $type = '';
	protected ?array $metadata = null;
	protected int $page = 0;
	protected int $urx = 0;
	protected int $ury = 0;
	protected ?int $llx = null;
	protected ?int $lly = null;
	protected ?\DateTime $createdAt = null;
	public function __construct() {
		$this->addType('id', Types::INTEGER);
		$this->addType('fileId', Types::INTEGER);
		$this->addType('signRequestId', Types::INTEGER);
		$this->addType('type', Types::STRING);
		$this->addType('metadata', Types::JSON);
		$this->addType('page', Types::INTEGER);
		$this->addType('urx', Types::INTEGER);
		$this->addType('ury', Types::INTEGER);
		$this->addType('llx', Types::INTEGER);
		$this->addType('lly', Types::INTEGER);
		$this->addType('createdAt', Types::DATETIME);
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
