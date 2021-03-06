<?php

/**
 *
 * @author Lukáš Timko <lukast@fykos.cz>
 */
class ModelMPersonHasFlag extends AbstractModelMulti {

    /**
     * @return ModelFlag
     */
    public function getFlag() {
        return $this->getMainModel();
    }

    /**
     * @return ModelPersonHasFlag
     */
    public function getPersonHasFlag() {
        return $this->getJoinedModel();
    }

}