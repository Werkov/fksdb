<?php

namespace FKSDB\Components\Forms\Factories;

use FKS\Components\Forms\Containers\ContainerWithOptions;
use FKS\Components\Forms\Containers\IReferencedSetter;
use FKS\Components\Forms\Containers\IWriteonly;
use FKS\Components\Forms\Containers\ReferencedContainer;
use FKS\Components\Forms\Controls\ReferencedId;
use FKSDB\Components\Forms\Controls\Autocomplete\PersonProvider;
use ModelPerson;
use ModelPostContact;
use Nette\DeprecatedException;
use Nette\Forms\Container;
use Nette\Forms\Controls\BaseControl;
use Nette\Forms\Controls\TextInput;
use Nette\Forms\Form;
use Nette\InvalidArgumentException;
use Nette\InvalidStateException;
use Nette\Object;
use ORM\IModel;
use Persons\IModifialibityResolver;
use Persons\IVisibilityResolver;
use Persons\ReferencedPersonHandler;
use Persons\ReferencedPersonHandlerFactory;
use ServicePerson;
use ServiceFlag;

/**
 * Due to author's laziness there's no class doc (or it's self explaining).
 * 
 * @author Michal Koutný <michal@fykos.cz>
 */
class ReferencedPersonFactory extends Object implements IReferencedSetter {

    const SEARCH_EMAIL = 'email';
    const SEARCH_ID = 'id';
    const SEARCH_NONE = 'none';
    const TARGET_FORM = 0x1;
    const TARGET_VALIDATION = 0x2;
    const EXTRAPOLATE = 0x4;
    const HAS_DELIVERY = 0x8;

    /**
     * @var ServicePerson
     */
    private $servicePerson;

    /**
     * @var PersonFactory
     */
    private $personFactory;

    /**
     * @var ReferencedPersonHandlerFactory
     */
    private $referencedPersonHandlerFactory;

    /**
     * @var PersonProvider
     */
    private $personProvider;
    
    /**
     * @var ServiceFlag
     */
    private $serviceFlag;

    function __construct(ServicePerson $servicePerson, PersonFactory $personFactory, ReferencedPersonHandlerFactory $referencedPersonHandlerFactory, PersonProvider $personProvider, ServiceFlag $serviceFlag) {
        $this->servicePerson = $servicePerson;
        $this->personFactory = $personFactory;
        $this->referencedPersonHandlerFactory = $referencedPersonHandlerFactory;
        $this->personProvider = $personProvider;
        $this->serviceFlag = $serviceFlag;
    }

    /**
     * 
     * @param type $fieldsDefinition
     * @param type $acYear
     * @param type $searchType
     * @param type $allowClear
     * @param IModifialibityResolver $modifiabilityResolver is person's filled field modifiable?
     * @param IVisibilityResolver $visibilityResolver is person's writeonly field visible? (i.e. not writeonly then)
     * @return array
     */
    public function createReferencedPerson($fieldsDefinition, $acYear, $searchType, $allowClear, IModifialibityResolver $modifiabilityResolver, IVisibilityResolver $visibilityResolver) {

        $handler = $this->referencedPersonHandlerFactory->create($acYear);

        $hiddenField = new ReferencedId($this->servicePerson, $handler, $this);

        $container = new ReferencedContainer($hiddenField);
        if ($searchType == self::SEARCH_NONE) {
            $container->setSearch();
        } else {
            $container->setSearch($this->createSearchControl($searchType), $this->createSearchCallback($searchType), $this->createTermToValuesCallback($searchType));
        }

        $container->setAllowClear($allowClear);
        $container->setOption('acYear', $acYear);
        $container->setOption('modifiabilityResolver', $modifiabilityResolver);
        $container->setOption('visibilityResolver', $visibilityResolver);

        foreach ($fieldsDefinition as $sub => $fields) {
            $subcontainer = new ContainerWithOptions();
            if ($sub == ReferencedPersonHandler::POST_CONTACT_DELIVERY) {
                $subcontainer->setOption('showGroup', true);
                $subcontainer->setOption('label', _('Doručovací adresa'));
            } else if ($sub == ReferencedPersonHandler::POST_CONTACT_PERMANENT) {
                $subcontainer->setOption('showGroup', true);
                $label = _('Trvalá adresa');
                if (isset($container[ReferencedPersonHandler::POST_CONTACT_DELIVERY])) {
                    $label .= ' ' . _('(je-li odlišná od doručovací)');
                }
                $subcontainer->setOption('label', $label);
            }

            foreach ($fields as $fieldName => $metadata) {
                if (is_scalar($metadata)) {
                    $metadata = array(
                        'required' => $metadata,
                    );
                }
                $control = $this->personFactory->createField($sub, $fieldName, $acYear, $hiddenField, $metadata);
                $fullFieldName = "$sub.$fieldName";
                if ($handler->isSecondaryKey($fullFieldName)) {
                    if ($fieldName != 'email') {
                        throw new InvalidStateException("Should define uniqueness validator for field $sub.$fieldName.");
                    }

                    $control->addCondition(function() use($hiddenField) { // we use this workaround not to call getValue inside validation out of transaction
                                        $personId = $hiddenField->getValue(false);
                                        return $personId && $personId != ReferencedId::VALUE_PROMISE;
                                    })
                            ->addRule(function(BaseControl $control) use($fullFieldName, $hiddenField, $handler) {
                                        $personId = $hiddenField->getValue(false);

                                        $foundPerson = $handler->findBySecondaryKey($fullFieldName, $control->getValue());
                                        if ($foundPerson && $foundPerson->getPrimary() != $personId) {
                                            $hiddenField->setValue($foundPerson, IReferencedSetter::MODE_FORCE);
                                            return false;
                                        }
                                        return true;
                                    }, _('S e-mailem %value byla nalezena (formálně) jiná (ale pravděpodobně duplicitní) osoba, a tak ve formuláři nahradila původní.'));
                }

                $subcontainer->addComponent($control, $fieldName);
            }
            $container->addComponent($subcontainer, $sub);
        }

        return array(
            $hiddenField,
            $container,
        );
    }

    public function setModel(ReferencedContainer $container, IModel $model = null, $mode = self::MODE_NORMAL) {
        $acYear = $container->getOption('acYear');
        $modifiable = $model ? $container->getOption('modifiabilityResolver')->isModifiable($model) : true;
        $resolution = $model ? $container->getOption('modifiabilityResolver')->getResolutionMode($model) : ReferencedPersonHandler::RESOLUTION_OVERWRITE;
        $visible = $model ? $container->getOption('visibilityResolver')->isVisible($model) : true;
        $submittedBySearch = $container->isSearchSubmitted();
        $force = ($mode == self::MODE_FORCE);
        if ($mode == self::MODE_ROLLBACK) {
            $model = null;
        }

        $container->getReferencedId()->getHandler()->setResolution($resolution);
        $container->getComponent(ReferencedContainer::CONTROL_COMPACT)->setValue($model ? $model->getFullname() : null);

        foreach ($container->getComponents() as $sub => $subcontainer) {
            if (!$subcontainer instanceof Container) {
                continue;
            }

            foreach ($subcontainer->getComponents() as $fieldName => $component) {
                if (isset($container[ReferencedPersonHandler::POST_CONTACT_DELIVERY])) {
                    $options = self::TARGET_FORM | self::HAS_DELIVERY;
                } else {
                    $options = self::TARGET_FORM;
                }
                $realValue = $this->getPersonValue($model, $sub, $fieldName, $acYear, $options); // not extrapolated
                $value = $this->getPersonValue($model, $sub, $fieldName, $acYear, $options | self::EXTRAPOLATE);

                $controlModifiable = ($realValue !== null) ? $modifiable : true;
                $controlVisible = $this->isWriteonly($component) ? $visible : true;

                if (!$controlVisible && !$controlModifiable) {
                    $container[$sub]->removeComponent($component);
                } else if (!$controlVisible && $controlModifiable) {
                    $this->setWriteonly($component, true);
                    $component->setDisabled(false);
                } else if ($controlVisible && !$controlModifiable) {
                    $component->setDisabled();
                } else if ($controlVisible && $controlModifiable) {
                    $this->setWriteonly($component, false);
                    $component->setDisabled(false);
                }
                if ($mode == self::MODE_ROLLBACK) {
                    $component->setDisabled(false);
                    $this->setWriteonly($component, false);
                } else {
                    if ($submittedBySearch || $force) {
                        $component->setValue($value);
                    } else {
                        $component->setDefaultValue($value);
                    }
                    if ($realValue && $resolution == ReferencedPersonHandler::RESOLUTION_EXCEPTION) {
                        $component->setDisabled(); // could not store different value anyway
                    }
                }
            }
        }
    }

    private function setWriteonly($component, $value) {
        if ($component instanceof IWriteonly) {
            $component->setWriteonly($value);
        } else if ($component instanceof Container) {
            foreach ($component->getComponents() as $subcomponent) {
                $this->setWriteonly($subcomponent, $value);
            }
        }
    }

    private function isWriteonly($component) {
        if ($component instanceof IWriteonly) {
            return true;
        } else if ($component instanceof Container) {
            foreach ($component->getComponents() as $subcomponent) {
                if ($this->isWriteonly($subcomponent)) {
                    return true;
                }
            }
        }
    }

    private function createSearchControl($searchType) {
        switch ($searchType) {
            case self::SEARCH_EMAIL:
                $control = new TextInput(_('E-mail'));
                $control->addCondition(Form::FILLED)
                        ->addRule(Form::EMAIL, _('Neplatný tvar e-mailu.'));
                $control->setOption('description', _('Nejprve zkuste najít osobu v naší databázi podle e-mailu.'));
                break;
            case self::SEARCH_ID:
                $control = $this->personFactory->createPersonSelect(true, _('Jméno'), $this->personProvider);
        }
        return $control;
    }

    private function createSearchCallback($searchType) {
        $service = $this->servicePerson;
        switch ($searchType) {
            case self::SEARCH_EMAIL:
                return function($term) use($service) {
                            return $service->findByEmail($term);
                        };

                break;
            case self::SEARCH_ID:
                return function($term) use($service) {
                            return $service->findByPrimary($term);
                        };
        }
    }

    private function createTermToValuesCallback($searchType) {
        switch ($searchType) {
            case self::SEARCH_EMAIL:
                return function($term) {
                            return array('person_info' => array('email' => $term));
                        };
                break;
            case self::SEARCH_ID:
                return function($term) {
                            return array();
                        };
        }
    }

    public final function isFilled(ModelPerson $person, $sub, $field, $acYear) {
        $value = $this->getPersonValue($person, $sub, $field, $acYear, self::TARGET_VALIDATION);
        return !($value === null || $value === '');
    }

    private function getPersonValue(ModelPerson $person = null, $sub, $field, $acYear, $options) {
        if (!$person) {
            return null;
        }
        switch ($sub) {
            case 'person':
                return $person[$field];
            case 'person_info':
                $result = ($info = $person->getInfo()) ? $info[$field] : null;
                if ($field == 'agreed') {
                    // See isFilled() semantics. We consider those who didn't agree as NOT filled.
                    $result = $result ? true : null;
                }
                return $result;
            case 'person_history':
                return ($history = $person->getHistory($acYear, (bool) ($options & self::EXTRAPOLATE))) ? $history[$field] : null;
            case 'post_contact_d':
                return $person->getDeliveryAddress();
                break;
            case 'post_contact_p':
                if (($options & self::TARGET_VALIDATION) || !($options & self::HAS_DELIVERY)) {
                    return $person->getPermanentAddress();
                }
                return $person->getPermanentAddress(true);
                break;
            case 'person_has_flag':
                return ($flag = $person->getMPersonHasFlag($field)) ? (bool) $flag['value'] : null;
            default:
                throw new InvalidArgumentException("Unknown person sub '$sub'.");
        }
    }

}

