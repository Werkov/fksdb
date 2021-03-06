<?php

use Nette\Security\IResource;

/**
 *
 * @author Michal Koutný <xm.koutny@gmail.com>
 */
class ModelSchool extends AbstractModelSingle implements IResource {

    /**
     * @return ModelAddress
     */
    public function getAddress() {
        $data = $this->address;
        return ModelAddress::createFromTableRow($data);
    }

    public function getResourceId() {
        return 'school';
    }

}
