<?php

use Nette\Security\IResource;

/**
 *
 * @author Michal Koutný <xm.koutny@gmail.com>
 */
class ModelEvent extends AbstractModelSingle implements IResource {

    private $eventType = false;
    private $contest = false;

    public function getEventType() {
        if ($this->eventType === false) {
            $this->eventType = ModelEventType::createFromTableRow($this->ref(DbNames::TAB_EVENT_TYPE, 'event_type_id'));
        }
        return $this->eventType;
    }

    /**
     * @return ModelContest
     */
    public function getContest() {
        if ($this->contest === false) {
            $this->contest = ModelContest::createFromTableRow($this->getEventType()->ref(DbNames::TAB_CONTEST, 'event_type_id'));
        }
        return $this->contest;
    }

    public function getResourceId() {
        return 'event';
    }

    public function __toString() {
        return $this->name;
    }

}

?>
