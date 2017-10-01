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

namespace OC;

use OC\Group\BackendGroup;
use OC\User\Account;
use OCP\AppFramework\Db\Mapper;
use OCP\AppFramework\Db\Entity;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\AppFramework\Db\DoesNotExistException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OCP\DB\QueryBuilder\IQueryBuilder;

class MembershipManager {

	/**
	 * types of memberships in the group
	 */
	const MEMBERSHIP_TYPE_GROUP_USER = 0;
	const MEMBERSHIP_TYPE_GROUP_ADMIN = 1;

	protected $db;

	/** @var \OC\Group\GroupMapper */
	private $groupMapper;

	/** @var \OC\Group\GroupMapper */
	private $accountMapper;

	public function __construct(IDBConnection $db, \OC\User\AccountMapper $accountMapper, \OC\Group\GroupMapper $groupMapper) {
		$this->db = $db;
		$this->groupMapper = $groupMapper;
		$this->accountMapper = $accountMapper;
	}

	/**
	 * @param BackendGroup $entity
	 * @return Entity the saved entity with the set id
	 */
	public function deleteGroupMembers(BackendGroup $backendGroup) {
		// Delete first all group members
		$backendGroupId = $backendGroup->getId();
		$qb = $this->deleteGroupMemberAccountsSqlQuery(
			$backendGroupId,
			[self::MEMBERSHIP_TYPE_GROUP_USER, self::MEMBERSHIP_TYPE_GROUP_ADMIN]
		);
		$qb->execute();
	}

	/**
	 * @param BackendGroup $backendGroup
	 * @param Account $account
	 *
	 * @return boolean
	 */
	public function deleteGroupMember($backendGroup, $account) {
		$backendGroupId = $backendGroup->getId();
		$accountId = $account->getId();
		$qb = $this->deleteGroupMemberAccountsSqlQuery(
			$backendGroupId,
			[self::MEMBERSHIP_TYPE_GROUP_USER]
		);
		$qb->where($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId)));
		$qb->execute();

		return true;
	}

	/**
	 * Get all accounts of backend group which have group user type
	 * by group backend
	 *
	 * @param BackendGroup $backendGroup
	 *
	 * @return Account[]
	 */
	public function getGroupMembers($backendGroup) {
		$backendGroupId = $backendGroup->getId();

		$qb = $this->getAccountsByBGIdSqlQuery(
			$backendGroupId,
			[self::MEMBERSHIP_TYPE_GROUP_USER]
		);
		return $this->accountMapper->findEntities($qb->getSQL(), $qb->getParameters());
	}


	/**
	 * Get all accounts of backend group which have group user type
	 * and backend group is identified by group_id $gid,
	 *
	 * @param BackendGroup $backendGroup
	 *
	 * @return Account[]
	 */
	public function getGroupMembersByGid($gid) {
		$qb = $this->getAccountsByGidSqlQuery(
			$gid,
			[self::MEMBERSHIP_TYPE_GROUP_USER]
		);
		return $this->accountMapper->findEntities($qb->getSQL(), $qb->getParameters());
	}

	/**
	 * TODO: add descriptions
	 *
	 * @param BackendGroup $backendGroup
	 * @param Account $account
	 *
	 * @return boolean
	 */
	public function isGroupUserMember($backendGroup, $account) {
		return $this->isGroupMember($backendGroup->getId(), $account->getId(), self::MEMBERSHIP_TYPE_GROUP_USER);
	}

	/**
	 * TODO: add descriptions
	 *
	 * @param BackendGroup $backendGroup
	 * @param Account $account
	 *
	 * @return boolean
	 */
	public function isGroupAdminMember($backendGroup, $account) {
		return $this->isGroupMember($backendGroup->getId(), $account->getId(), self::MEMBERSHIP_TYPE_GROUP_ADMIN);
	}

	/**
	 * TODO: add descriptions
	 *
	 * @param BackendGroup $backendGroup
	 * @param Account $account
	 *
	 * @return boolean
	 */
	public function addGroupAdminMember($backendGroup, $account) {
		return $this->addGroupMember($backendGroup->getId(), $account->getId(), self::MEMBERSHIP_TYPE_GROUP_ADMIN);
	}

	/**
	 * TODO: add descriptions
	 *
	 * @param BackendGroup $backendGroup
	 * @param Account $account
	 *
	 * @return boolean
	 */
	public function addGroupUserMember($backendGroup, $account) {
		return $this->addGroupMember($backendGroup->getId(), $account->getId(), self::MEMBERSHIP_TYPE_GROUP_USER);
	}

	/**
	 * @return string the table name
	 * @since 10.0.4
	 */
	public function getTableName(){
		return 'memberships';
	}

	/**
	 * TODO: add descriptions
	 *
	 * @param int $backendGroupId
	 * @param int $accountId
	 *
	 * @return boolean
	 */
	private function isGroupMember($backendGroupId, $accountId, $membershipType) {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->expr()->literal('1'))
			->from($this->getTableName())
			->where($qb->expr()->eq('backend_group_id', $qb->createNamedParameter($backendGroupId)))
			->andWhere($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId)))
			->andWhere($qb->expr()->eq('membership_type', $qb->createNamedParameter($membershipType)));
		$resultArray = $qb->execute()->fetchAll();

		return empty($resultArray) ? false : true;
	}


	/**
	 * TODO: add descriptions
	 *
	 * @param int $backendGroupId
	 * @param int $accountId
	 * @param int $membershipType
	 *
	 * @return boolean
	 */
	private function addGroupMember($backendGroupId, $accountId, $membershipType) {
		$qb = $this->db->getQueryBuilder();

		$qb->insert($this->getTableName())
			->values([
				'backend_group_id' => $qb->createNamedParameter($backendGroupId),
				'account_id' => $qb->createNamedParameter($accountId),
				'membership_type' => $qb->createNamedParameter($membershipType),
			]);


		try {
			$qb->execute();
			return true;
		} catch (UniqueConstraintViolationException $e) {
			// TODO: hmmm raise some warning?
			return false;
		}
	}


	/*
	 * TODO: add descriptions
	 *
	 * @param int $backendGroupId
	 * @param int[] $membershipTypeArray
	 *
	 * @return IQueryBuilder
	 */
	private function deleteGroupMemberAccountsSqlQuery($backendGroupId, $membershipTypeArray) {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('backend_group_id', $qb->createNamedParameter($backendGroupId)))
			->andWhere($qb->expr()->in('membership_type',
				$qb->createNamedParameter($membershipTypeArray, IQueryBuilder::PARAM_INT_ARRAY)));
		return $qb;
	}

	/*
	 * Get SQL fetching all accounts of backend group identified by group_id $gid,
	 *
	 * @param int $backendGroupId
	 * @param int[] $membershipTypeArray
	 *
	 * @return IQueryBuilder
	 */
	private function getGroupMembersAccountsSqlQuery() {
		$qb = $this->db->getQueryBuilder();
		$qb->select(['a.id','a.user_id', 'a.lower_user_id', 'a.display_name', 'a.email', 'a.last_login', 'a.backend', 'a.state', 'a.quota', 'a.home'])
			->from($this->getTableName(), 'm')
			->innerJoin('m', $this->accountMapper->getTableName(), 'a', $qb->expr()->eq('a.id', 'm.account_id'));
		return $qb;
	}

	/*
	 * Get SQL fetching all accounts of backend group identified by group_id $gid,
	 *
	 * @param int $backendGroupId
	 * @param int[] $membershipTypeArray
	 *
	 * @return IQueryBuilder
	 */
	private function getAccountsByBGIdSqlQuery($backendGroupId, $membershipTypeArray) {
		$qb = $this->getGroupMembersAccountsSqlQuery();
		$qb->where($qb->expr()->eq('backend_group_id', $qb->createNamedParameter($backendGroupId)))
			->andWhere(
				$qb->expr()->in('membership_type', $qb->createNamedParameter(
					$membershipTypeArray,
					IQueryBuilder::PARAM_INT_ARRAY)
				));
		return $qb;
	}

	/*
	 * Get SQL fetching all accounts of backend group identified by group_id $gid,
	 *
	 * @param int $backendGroupId
	 * @param int[] $membershipTypeArray
	 *
	 * @return IQueryBuilder
	 */
	private function getAccountsByGidSqlQuery($gid, $membershipTypeArray) {
		$qb = $this->getGroupMembersAccountsSqlQuery();
		$qb->innerJoin('m', $this->groupMapper->getTableName(), 'g', $qb->expr()->eq('g.id', 'm.backend_group_id'))
			->where($qb->expr()->eq('g.group_id', $qb->createNamedParameter($gid)))
			->andWhere(
			$qb->expr()->in('membership_type', $qb->createNamedParameter(
				$membershipTypeArray,
				IQueryBuilder::PARAM_INT_ARRAY)
			));
		return $qb;
	}
}
