<?php
/**
 * @author Piotr Mrowczynski <piotr@owncloud.com>
 *
 * @copyright Copyright (c) 2017, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Group;

use OC\Group\BackendGroup;
use OCP\AppFramework\Db\Mapper;
use OCP\AppFramework\Db\Entity;
use OCP\IConfig;
use OCP\IDBConnection;

class GroupMapper extends Mapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'backend_groups', BackendGroup::class);
	}

	/**
	 * @param BackendGroup $entity
	 * @return Entity the saved entity with the set id
	 */
	public function insert(Entity $entity) {
		// run the normal entity insert operation to get an id
		return parent::insert($entity);
	}

	/**
	 * @param string $uid
	 * @return BackendGroup
	 */
	public function getGroup($gid) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($gid)));

		return $this->findEntity($qb->getSQL(), $qb->getParameters());
	}

	/**
	 * @param string $fieldName
	 * @param string $pattern
	 * @param integer $limit
	 * @param integer $offset
	 * @return BackendGroup[]
	 */
	public function search($fieldName, $pattern, $limit, $offset) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->iLike($fieldName, $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($pattern) . '%')))
			->orderBy($fieldName);

		return $this->findEntities($qb->getSQL(), $qb->getParameters(), $limit, $offset);
	}

}