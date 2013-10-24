<?php

namespace PublicModule;

use BasePresenter;
use FKSDB\Components\Forms\Factories\AddressFactory;
use FKSDB\Components\Forms\Factories\ContestantFactory;
use FKSDB\Components\Forms\Factories\LoginFactory;
use FKSDB\Components\Forms\Factories\PersonFactory;
use FKSDB\Components\Forms\Rules\UniqueEmail;
use FKSDB\Components\Forms\Rules\UniqueEmailFactory;
use FKSDB\Components\Forms\Rules\UniqueLoginFactory;
use FormUtils;
use IContestPresenter;
use ModelContest;
use ModelException;
use ModelPerson;
use ModelPostContact;
use Nette\Application\UI\Form;
use Nette\Database\Connection;
use Nette\DateTime;
use Nette\Diagnostics\Debugger;
use ServiceAddress;
use ServiceContestant;
use ServiceLogin;
use ServiceMPostContact;
use ServicePerson;
use ServicePersonInfo;
use ServicePostContact;

/**
 * INPUT:
 *   contest (nullable)
 *   logged user (nullable)
 *   condition: the logged user is not contestant of the contest
 * 
 * OUTPUT:
 *   registered contestant for the current year
 *      - if contest was provided in that contest
 *      - if user was provided for that user
 * 
 * OPERATION
 *   - show/process person/login info iff logged user is null
 *   - show contest selector iff contest is null
 *   - contestant for filling default values
 *     - user must be logged in
 *     - if exists use last contestant from the provided contest
 *     - otherwise use last contestant from any contest (Vyfuk <= FYKOS)
 * 
 * Just proof of concept.
 * 
 * @author Michal Koutný <michal@fykos.cz>
 */
class RegisterPresenter extends BasePresenter implements IContestPresenter {

    const CONT_PERSON = 'person';
    const CONT_PERSON_INFO = 'person_info';
    const CONT_LOGIN = 'login';
    const CONT_ADDRESS = 'address';
    const CONT_CONTESTANT = 'contestant';

    /**
     * @var int
     * @persistent
     */
    public $contestId;

    /** @var ServicePerson */
    private $servicePerson;

    /** @var ServicePersonInfo */
    private $servicePersonInfo;

    /** @var ServiceLogin */
    private $serviceLogin;

    /** @var ServiceContestant */
    private $serviceContestant;

    /** @var ServiceAddress */
    private $serviceAddress;

    /** @var ServicePostContact */
    private $servicePostContact;

    /** @var ServiceMPostContact */
    private $serviceMPostContact;

    /** @var LoginFactory */
    private $loginFactory;

    /** @var PersonFactory */
    private $personFactory;

    /** @var AddressFactory */
    private $addressFactory;

    /** @var ContestantFactory */
    private $contestantFactory;

    /** @var UniqueEmailFactory */
    private $uniqueEmailFactory;

    /** @var UniqueLoginFactory */
    private $uniqueLoginFactory;

    /** @var Connection */
    private $connection;

    public function injectLoginFactory(LoginFactory $loginFactory) {
        $this->loginFactory = $loginFactory;
    }

    public function injectPersonFactory(PersonFactory $personFactory) {
        $this->personFactory = $personFactory;
    }

    public function injectAddressFactory(AddressFactory $addressFactory) {
        $this->addressFactory = $addressFactory;
    }

    public function injectContestantFactory(ContestantFactory $contestantFactory) {
        $this->contestantFactory = $contestantFactory;
    }

    public function injectServicePerson(ServicePerson $servicePerson) {
        $this->servicePerson = $servicePerson;
    }

    public function injectServicePersonInfo(ServicePersonInfo $servicePersonInfo) {
        $this->servicePersonInfo = $servicePersonInfo;
    }

    public function injectServiceLogin(ServiceLogin $serviceLogin) {
        $this->serviceLogin = $serviceLogin;
    }

    public function injectServiceContestant(ServiceContestant $serviceContestant) {
        $this->serviceContestant = $serviceContestant;
    }

    public function injectServiceAddress(ServiceAddress $serviceAddress) {
        $this->serviceAddress = $serviceAddress;
    }

    public function injectServicePostContact(ServicePostContact $servicePostContact) {
        $this->servicePostContact = $servicePostContact;
    }

    public function injectServiceMPostContact(ServiceMPostContact $serviceMPostContact) {
        $this->serviceMPostContact = $serviceMPostContact;
    }

    public function injectUniqueEmailFactory(UniqueEmailFactory $uniqueEmailFactory) {
        $this->uniqueEmailFactory = $uniqueEmailFactory;
    }

    public function injectUniqueLoginFactory(UniqueLoginFactory $uniqueLoginFactory) {
        $this->uniqueLoginFactory = $uniqueLoginFactory;
    }

    public function injectConnection(Connection $connection) {
        $this->connection = $connection;
    }

    /** @var ModelContest|null */
    private $selectedContest;

    public function getSelectedContest() {
        if ($this->selectedContest === null) {
            $this->selectedContest = $this->serviceContest->findByPrimary($this->contestId);
        }
        return $this->selectedContest;
    }

    public function getSelectedYear() {
        return $this->yearCalculator->getCurrentYear($this->getSelectedContest());
    }

    public function actionDefault() {
        if ($this->user->isLoggedIn()) {
            /** @var ModelPerson $person */
            $person = $this->user->getIdentity()->getPerson();
            if (!$person) { // impersonal login
                $this->redirect(':Authentication:login'); // would dispatch properly
            } else {
                $this->redirect('contestant');
            }
        }
    }

    public function actionContestant() {
        if ($this->user->isLoggedIn()) {
            /** @var ModelPerson $person */
            $person = $this->user->getIdentity()->getPerson();
            if (!$person) { // impersonal login
                $this->redirect(':Authentication:login'); // would dispatch properly
            }
            $currentContestants = $person->getActiveContestants($this->yearCalculator);

            /**
             * If the user is contestant in select contest, redirect him to dashboard,
             * otherwise allow him to register.
             */
            $contest = $this->getSelectedContest();
            if ($contest && isset($currentContestants[$contest->contest_id])) {
                // existing contestant 
                $this->redirect(':Public:Dashboard:default');
            }
        } else {
            // not logged in
            $this->redirect('default');
        }
    }

    public function createComponentRegisterForm($name) {
        $form = new Form();

        $group = $form->addGroup('Přihlašování');
        $emailRule = $this->uniqueEmailFactory->create(UniqueEmail::CHECK_LOGIN);
        $loginRule = $this->uniqueLoginFactory->create();
        $login = $this->loginFactory->createLogin(LoginFactory::SHOW_PASSWORD | LoginFactory::REQUIRE_PASSWORD, $group, $emailRule, $loginRule);
        $form->addComponent($login, self::CONT_LOGIN);

        $group = $form->addGroup('Osoba');
        $person = $this->personFactory->createPerson(0, $group);
        $form->addComponent($person, self::CONT_PERSON);

        $address = $this->addressFactory->createAddress($group);
        $form->addComponent($address, self::CONT_ADDRESS);

        $personInfo = $this->personFactory->createPersonInfo(PersonFactory::SHOW_LIKE_SUPPLEMENT | PersonFactory::REQUIRE_AGREEMENT, $group);
        $form->addComponent($personInfo, self::CONT_PERSON_INFO);

        $group = $form->addGroup('Řešitel');
        $options = ContestantFactory::REQUIRE_SCHOOL | ContestantFactory::REQUIRE_STUDY_YEAR;
        if (!$this->getSelectedContest()) {
            $options |= ContestantFactory::SHOW_CONTEST;
        }
        $contestant = $this->contestantFactory->createContestant($options, $group);
        $form->addComponent($contestant, self::CONT_CONTESTANT);


        $form->setCurrentGroup();
        $form->addSubmit('register', 'Registrovat');
        $form->onSuccess[] = array($this, 'handleRegisterFormSuccess');


        return $form;
    }

    public function createComponentContestantForm($name) {
        $form = new Form();

        // person
        $person = $this->user->getIdentity()->getPerson();
        $group = $form->addGroup('Osoba');
        $personContainer = $this->personFactory->createPerson(PersonFactory::DISABLED, $group);
        $personContainer->setDefaults($person->toArray());
        $form->addComponent($personContainer, self::CONT_PERSON);

        // contestant
        if ($this->getSelectedContest()) {
            $contestant = $person->getLastContestant($this->getSelectedContest());
        } else {
            $contestant = array();
        }

        $group = $form->addGroup('Řešitel');
        $options = ContestantFactory::REQUIRE_SCHOOL | ContestantFactory::REQUIRE_STUDY_YEAR;
        if (!$this->getSelectedContest()) {
            $options |= ContestantFactory::SHOW_CONTEST;
        }
        $contestantContainer = $this->contestantFactory->createContestant($options, $group);
        if ($contestant) {
            $contestantContainer->setDefaults($contestant); //TODO auto-increase study_year + class
        }
        $form->addComponent($contestantContainer, self::CONT_CONTESTANT);

        // address
        $address = $person->getDeliveryAddress();
        $group = $form->addGroup('Adresa');
        $addressContainer = $this->addressFactory->createAddress($group);
        if ($address) {
            $addressContainer->setDefaults($address);
        }
        $form->addComponent($addressContainer, self::CONT_ADDRESS);

        // person info
        $personInfo = $person->getInfo();
        if (!$personInfo || (!$personInfo->agreed || !$personInfo->origin)) {
            $group = $form->addGroup('Informace');
            $personInfoContainer = $this->personFactory->createPersonInfo(PersonFactory::SHOW_LIKE_SUPPLEMENT | PersonFactory::REQUIRE_AGREEMENT, $group);
            if ($personInfo) {
                $personInfoContainer->setDefaults($personInfo);
            }
            $form->addComponent($personInfoContainer, self::CONT_PERSON_INFO);
        }


        $form->setCurrentGroup();
        $form->addSubmit('register', 'Registrovat');
        $form->onSuccess[] = array($this, 'handleContestantFormSuccess');

        $form->addProtection('Vypršela časová platnost formuláře. Odešlete jej prosím znovu.');

        return $form;
    }

    public function handleRegisterFormSuccess(Form $form) {
        $values = $form->getValues();

        try {
            if (!$this->connection->beginTransaction()) {
                throw new ModelException();
            }
            // store person
            $personData = $values[self::CONT_PERSON];
            $person = $this->servicePerson->createNew($personData);
            $person->inferGender();
            $this->servicePerson->save($person);

            // store login
            $loginData = $values[self::CONT_LOGIN];
            $loginData = FormUtils::emptyStrToNull($loginData);
            $login = $this->serviceLogin->createNew($loginData);
            $login->person_id = $person->person_id;

            $this->serviceLogin->save($login); // save to retrieve login_id for hash salting

            $login->setHash($loginData['password']);
            $login->active = 1; // created accounts are active
            $this->serviceLogin->save($login);

            // store address
            $addressData = $values[self::CONT_ADDRESS];
            $addressData = FormUtils::emptyStrToNull($addressData);
            $mPostContact = $this->serviceMPostContact->createNew($addressData);
            $mPostContact->getJoinedModel()->person_id = $person->person_id;

            $this->serviceMPostContact->save($mPostContact);

            // store contestant
            $contestantData = $values[self::CONT_CONTESTANT];
            $contestantData = FormUtils::emptyStrToNull($contestantData);
            $contestant = $this->serviceContestant->createNew($contestantData);

            $contestant->person_id = $person->person_id;
            if ($this->getSelectedContest()) {
                $contestant->year = $this->getSelectedYear();
                $contestant->contest_id = $this->getSelectedContest()->contest_id;
            } else {
                $contestant->year = $this->yearCalculator->getCurrentYear($this->serviceContest->findByPrimary($contestant->contest_id));
            }


            $this->serviceContestant->save($contestant);

            // store person info
            $personInfoData = $values[self::CONT_PERSON_INFO];
            $personInfoData = FormUtils::emptyStrToNull($personInfoData);
            $personInfoData['agreed'] = $personInfoData['agreed'] ? new DateTime() : null;
            $personInfo = $this->servicePersonInfo->createNew($personInfoData);

            $personInfo->person_id = $person->person_id;

            $this->servicePersonInfo->save($personInfo);


            if (!$this->connection->commit()) {
                throw new ModelException();
            }

            $this->getUser()->login($login);
            $this->flashMessage($person->gender == 'F' ? 'Řešitelka úspěšně zaregistrována.' : 'Řešitel úspěšně zaregistrován.');
            $this->redirect(':Public:Dashboard:default');
        } catch (ModelException $e) {
            $this->connection->rollBack();
            $this->getUser()->logout(true);
            Debugger::log($e, Debugger::ERROR);
            $this->flashMessage('Při registraci došlo k chybě.', 'error');
        }
    }

    public function handleContestantFormSuccess(Form $form) {
        $values = $form->getValues();

        try {
            if (!$this->connection->beginTransaction()) {
                throw new ModelException();
            }
            $person = $this->user->getIdentity()->getPerson();

            /*
             * Contestant
             */
            $contestantData = $values[self::CONT_CONTESTANT];
            $contestantData = FormUtils::emptyStrToNull($contestantData);
            $contestant = $this->serviceContestant->createNew($contestantData);

            $contestant->person_id = $person->person_id;
            if ($this->getSelectedContest()) {
                $contestant->year = $this->getSelectedYear();
                $contestant->contest_id = $this->getSelectedContest()->contest_id;
            } else {
                $contestant->year = $this->yearCalculator->getCurrentYear($this->serviceContest->findByPrimary($contestant->contest_id));
            }


            $this->serviceContestant->save($contestant);

            /*
             * Address
             * TODO allow multiple addresses, not hardcode type of the post contact
             */
            foreach ($person->getMPostContacts() as $mPostContact) {
                $this->serviceMPostContact->dispose($mPostContact);
            }

            $dataPostContact = $values[self::CONT_ADDRESS];
            $dataPostContact = FormUtils::emptyStrToNull((array) $dataPostContact);
            $mPostContact = $this->serviceMPostContact->createNew($dataPostContact);
            $mPostContact->getPostContact()->person_id = $person->person_id;
            $mPostContact->getPostContact()->type = ModelPostContact::TYPE_PERMANENT;

            $this->serviceMPostContact->save($mPostContact);

            /*
             * Person info
             */
            $dataInfo = $values[self::CONT_PERSON_INFO];
            $dataInfo = FormUtils::emptyStrToNull($dataInfo);
            $dataInfo['agreed'] = $dataInfo['agreed'] ? new DateTime() : null;
            $personInfo = $person->getInfo();
            if (!$personInfo) {
                $personInfo = $this->servicePersonInfo->createNew($dataInfo);
                $personInfo->person_id = $person->person_id;
            } else {
                $this->servicePersonInfo->updateModel($personInfo, $dataInfo); // here we update date of the confirmation
            }

            $this->servicePersonInfo->save($personInfo);


            if (!$this->connection->commit()) {
                throw new ModelException();
            }

            $this->flashMessage($person->gender == 'F' ? 'Řešitelka úspěšně zaregistrována.' : 'Řešitel úspěšně zaregistrován.');
            $this->redirect(':Public:Dashboard:default');
        } catch (ModelException $e) {
            $this->connection->rollBack();
            Debugger::log($e, Debugger::ERROR);
            $this->flashMessage('Při registraci došlo k chybě.', 'error');
        }
    }

}
