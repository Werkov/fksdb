<?php

namespace OrgModule;

use FKSDB\Components\Forms\Factories\AddressFactory;
use FKSDB\Components\Forms\Factories\SchoolFactory;
use FKSDB\Components\Grids\SchoolsGrid;
use FormUtils;
use Kdyby\BootstrapFormRenderer\BootstrapRenderer;
use ModelException;
use Nette\Application\UI\Form;
use Nette\Diagnostics\Debugger;
use Nette\NotImplementedException;
use ORM\IModel;
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

    public function titleList() {
        $this->setTitle(_('Školy'));
    }

    public function titleCreate() {
        $this->setTitle(_('Založit školu'));
    }

    public function titleEdit($id) {
        $school = $this->getModel();
        $this->setTitle(sprintf(_('Úprava školy %s'), $school->name_abbrev));
    }

    public function actionDelete($id) {
        // This should set active flag to false.
        throw new NotImplementedException();
    }

    protected function createComponentCreateComponent($name) {
        $form = $this->createForm();

        $form->addSubmit('send', _('Vložit'));
        $form->onSuccess[] = array($this, 'handleCreateFormSuccess');

        return $form;
    }

    protected function createComponentEditComponent($name) {
        $form = $this->createForm();

        $form->addSubmit('send', _('Uložit'));
        $form->onSuccess[] = array($this, 'handleEditFormSuccess');

        return $form;
    }

    protected function setDefaults(IModel $model = null, Form $form) {
        if (!$model) {
            return;
        }
        $defaults = array(
            self::CONT_SCHOOL => $model->toArray(),
            self::CONT_ADDRESS => $model->getAddress()->toArray(),
        );
        $form->setDefaults($defaults);
    }

    protected function createComponentGrid($name) {
        $grid = new SchoolsGrid($this->serviceSchool);

        return $grid;
    }

    private function createForm() {
        $form = new Form();
        $form->setRenderer(new BootstrapRenderer());

        $schoolContainer = $this->schoolFactory->createSchool();
        $form->addComponent($schoolContainer, self::CONT_SCHOOL);

        $addressContainer = $this->addressFactory->createAddress(AddressFactory::REQUIRED | AddressFactory::NOT_WRITEONLY);
        $form->addComponent($addressContainer, self::CONT_ADDRESS);

        return $form;
    }

    protected function loadModel($id) {
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

            $this->flashMessage(_('Škola založena'), self::FLASH_SUCCESS);
            $this->backlinkRedirect();
            $this->redirect('list'); // if there's no backlink
        } catch (ModelException $e) {
            $connection->rollBack();
            Debugger::log($e, Debugger::ERROR);
            $this->flashMessage(_('Chyba při zakládání školy.'), self::FLASH_ERROR);
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

            $this->flashMessage(_('Škola upravena'), self::FLASH_SUCCESS);
            $this->backlinkRedirect();
            $this->redirect('list'); // if there's no backlink
        } catch (ModelException $e) {
            $connection->rollBack();
            Debugger::log($e, Debugger::ERROR);
            $this->flashMessage(_('Chyba při úpravě školy.'), self::FLASH_ERROR);
        }
    }

}
