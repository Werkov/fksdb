<?php

namespace OrgModule;

use AbstractModelSingle;
use FKSDB\Components\Forms\Factories\AddressFactory;
use FKSDB\Components\Forms\Factories\SchoolFactory;
use FKSDB\Components\Grids\SchoolsGrid;
use FormUtils;
use ModelException;
use Nette\Application\UI\Form;
use Nette\Diagnostics\Debugger;
use Nette\NotImplementedException;
use ServiceAddress;
use ServiceSchool;

/**
 * Due to author's laziness there's no class doc (or it's self explaining).
 * 
 * @author Michal Koutný <michal@fykos.cz>
 */
class SchoolPresenter extends EntityPresenter {

    const CONT_ADDRESS = 'address';
    const CONT_SCHOOL = 'school';

    protected $modelResourceId = 'school';

    /**
     * @var ServiceSchool
     */
    private $serviceSchool;

    /**
     * @var ServiceAddress
     */
    private $serviceAddress;

    /**
     * @var SchoolFactory
     */
    private $schoolFactory;

    /**
     * @var AddressFactory
     */
    private $addressFactory;

    public function injectServiceSchool(ServiceSchool $serviceSchool) {
        $this->serviceSchool = $serviceSchool;
    }

    public function injectServiceAddress(ServiceAddress $serviceAddress) {
        $this->serviceAddress = $serviceAddress;
    }

    public function injectSchoolFactory(SchoolFactory $schoolFactory) {
        $this->schoolFactory = $schoolFactory;
    }

    public function injectAddressFactory(AddressFactory $addressFactory) {
        $this->addressFactory = $addressFactory;
    }

    public function actionDelete($id) {
        // This should set active flag to false.
        throw new NotImplementedException();
    }

    protected function createComponentCreateComponent($name) {
        $form = $this->createForm();

        $form->addSubmit('send', 'Vložit');
        $form->onSuccess[] = array($this, 'handleCreateFormSuccess');

        return $form;
    }

    protected function createComponentEditComponent($name) {
        $form = $this->createForm();

        $form->addSubmit('send', 'Uložit');
        $form->onSuccess[] = array($this, 'handleEditFormSuccess');

        return $form;
    }

    protected function setDefaults(AbstractModelSingle $model, Form $form) {
        $defaults = array(
            self::CONT_SCHOOL => $model->toArray(),
            self::CONT_ADDRESS => $model->getAddress()->toArray(),
        );
        $form->setDefaults($defaults);
    }

    protected function createComponentGrid($name) {
        $grid = new SchoolsGrid();

        return $grid;
    }

    private function createForm() {
        $form = new Form();

        $schoolContainer = $this->schoolFactory->createSchool();
        $form->addComponent($schoolContainer, self::CONT_SCHOOL);

        $addressContainer = $this->addressFactory->createAddress();
        $form->addComponent($addressContainer, self::CONT_ADDRESS);
        
        return $form;
    }

    protected function createModel($id) {
        return $this->serviceSchool->findByPrimary($id);
    }

    /**
     * @internal
     * @param Form $form
     */
    public function handleCreateFormSuccess(Form $form) {
        $connection = $this->serviceSchool->getConnection();
        $values = $form->getValues();


        try {
            if (!$connection->beginTransaction()) {
                throw new ModelException();
            }

            /*
             * Address
             */
            $data = FormUtils::emptyStrToNull($values[self::CONT_ADDRESS]);
            $address = $this->serviceAddress->createNew($data);
            $this->serviceAddress->save($address);

            /*
             * School
             */
            $data = FormUtils::emptyStrToNull($values[self::CONT_SCHOOL]);
            $school = $this->serviceSchool->createNew($data);
            $school->address_id = $address->address_id;
            $this->serviceSchool->save($school);

            /*
             * Finalize
             */
            if (!$connection->commit()) {
                throw new ModelException();
            }

            $this->flashMessage('Škola založena');
            $this->redirect('list');
        } catch (ModelException $e) {
            $connection->rollBack();
            Debugger::log($e, Debugger::ERROR);
            $this->flashMessage('Chyba při zakládání školy.', 'error');
        }
    }

    /**
     * @internal
     * @param Form $form
     */
    public function handleEditFormSuccess(Form $form) {
        $connection = $this->serviceSchool->getConnection();
        $values = $form->getValues();
        $school = $this->getModel();
        $address = $school->getAddress();

        try {
            if (!$connection->beginTransaction()) {
                throw new ModelException();
            }

            /*
             * Address
             */
            $data = FormUtils::emptyStrToNull($values[self::CONT_ADDRESS]);
            $this->serviceAddress->updateModel($address, $data);
            $this->serviceAddress->save($address);

            /*
             * School
             */
            $data = FormUtils::emptyStrToNull($values[self::CONT_SCHOOL]);
            $this->serviceSchool->updateModel($school, $data);
            $this->serviceSchool->save($school);

            /*
             * Finalize
             */
            if (!$connection->commit()) {
                throw new ModelException();
            }

            $this->flashMessage('Škola upravena');
            $this->redirect('list');
        } catch (ModelException $e) {
            $connection->rollBack();
            Debugger::log($e, Debugger::ERROR);
            $this->flashMessage('Chyba při úpravě školy.', 'error');
        }
    }

}