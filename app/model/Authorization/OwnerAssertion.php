<?php

namespace Authorization;

use Nette\InvalidStateException;
use Nette\Security\Permission;
use Nette\Security\User;

/**
 * Due to author's laziness there's no class doc (or it's self explaining).
 * 
 * @author Michal Koutný <michal@fykos.cz>
 */
class OwnerAssertion {

    /**
     * @var User
     */
    private $user;

    public function __construct(User $user) {
        $this->user = $user;
    }

    /**
     * 
     * @param \Authorization\Permission $acl
     * @param string $role
     * @param string $resourceId
     * @param string $privilege
     * @return boolean
     * @throws InvalidStateException
     */
    public function isSubmitUploader(Permission $acl, $role, $resourceId, $privilege) {
        if (!$this->user->isLoggedIn()) {
            throw new InvalidStateException('Expecting logged user.');
        }

        $submit = $acl->getQueriedResource();

        return $submit->getContestant()->getPerson()->getLogin()->login_id === $this->user->getId();
    }

}
