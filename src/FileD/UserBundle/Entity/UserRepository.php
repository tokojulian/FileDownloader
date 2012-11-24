<?php

namespace FileD\UserBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * UserRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class UserRepository extends EntityRepository
{

	/**
	 * Find active users
	 * @return the array of entities
	 */
	public function findActiveUsers()
	{
		$result = $this->_em->createQuery('
		        SELECT
		            u
		        FROM
		            FileDUserBundle:User u
		        WHERE
		            u.enabled = 1
				AND
					u.locked = 0
				ORDER BY u.username
		    ')
				->getResult();
		return $result;
	}
}
