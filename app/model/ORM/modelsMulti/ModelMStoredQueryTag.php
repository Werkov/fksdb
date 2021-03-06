<?php

/**
 *
 * @author Lukáš Timko <lukast@fykos.cz>
 */
class ModelMStoredQueryTag extends AbstractModelMulti {

    /**
     * @return ModelStoredQueryTagType
     */
    public function getStoredQueryTagType() {
        return $this->getMainModel();
    }

    /**
     * @return ModelStoredQueryTag
     */
    public function getStoredQueryTag() {
        return $this->getJoinedModel();
    }

}