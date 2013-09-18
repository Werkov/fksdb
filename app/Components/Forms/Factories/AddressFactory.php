<?php

namespace FKSDB\Components\Forms\Factories;

use FKSDB\Components\Forms\Containers\ModelContainer;
use Nette\Forms\ControlGroup;
use Nette\Forms\Form;
use ServiceCountry;

/**
 * Due to author's laziness there's no class doc (or it's self explaining).
 * 
 * @author Michal Koutný <michal@fykos.cz>
 */
class AddressFactory {

    /**
     * @var ServiceCountry
     */
    private $serviceCountry;

    public function __construct(ServiceCountry $serviceCountry) {
        $this->serviceCountry = $serviceCountry;
    }

    public function createAddress(ControlGroup $group = null) {
        $container = new ModelContainer();
        $container->setCurrentGroup($group);

        $container->addText('first_row', 'První řádek')
                ->setOption('description', 'První volitelný řádek adresy (např. bytem u)');

        $container->addText('second_row', 'Druhý řádek')
                ->setOption('description', 'Druhý volitelný řádek adresy (použití zřídka)');


        $container->addText('target', 'Místo')
                ->addRule(Form::FILLED, 'Adresa musí vyplněné místo.')
                ->setOption('description', 'Nejčastěji ulice a číslo, ale třeba i P. O. Box.');

        $container->addText('city', 'Město')
                ->addRule(Form::FILLED, 'Adresa musí mít vyplněné město.');


        $container->addText('postal_code', 'PSČ')
                ->addRule(Form::MAX_LENGTH, null, 5)
                ->setOption('description', 'Bez mezer');


        $countries = $container->addSelect('country_iso', 'Stát');

        $countries->setItems($this->serviceCountry->getTable()->order('name_cs')->fetchPairs('country_iso', 'name_cs')); //TODO i18n

        //$container->addHidden('address_id');
        
        return $container;
    }

}
