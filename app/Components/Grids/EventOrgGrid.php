<?php

namespace FKSDB\Components\Grids;

use Nette\Database\Table\Selection;
use ServiceEventOrg;
use SQL\SearchableDataSource;


class EventOrgsGrid extends BaseGrid {

    /**
     * @var ServiceEventOrg
     */
    private $serviceEventOrg;

    private $event_id;

    function __construct($event_id, ServiceEventOrg $serviceEventOrg) {
        parent::__construct();
        $this->event_id = $event_id;
        $this->serviceEventOrg = $serviceEventOrg;
    }

    protected function configure($presenter) {
        parent::configure($presenter);


        $orgs = $this->serviceEventOrg->findByEventID($this->event_id);

        $dataSource = new SearchableDataSource($orgs);
        /*   $dataSource->setFilterCallback(function(Selection $table, $value) {
               $tokens = preg_split('/\s+/', $value);
               foreach ($tokens as $token) {
                   $table->where('CONCAT(person.family_name, person.other_name, IFNULL(org.role,\'\'), IFNULL(org.contribution,\'\'))
                               LIKE CONCAT(\'%\', ? , \'%\')', $token);
               }
           });*/
        $this->setDataSource($dataSource);
        $this->addColumn('display_name', _('Jméno'))->setRenderer(function ($row) {
            $person = $row->getPerson();
            return $person->getFullname();
        });
        $this->addColumn('note', _('Poznámka'));
        $that = $this;
        $this->addButton("delete", _("Zmazať"))->setClass('btn btn-xs btn-danger')->setText('Zmazat')//todo i18n
        ->setLink(function ($row) use ($that) {
            return $that->link("delete!",$row->e_org_id);

        })->setConfirmationDialog(function () {
            return _("Opravdu znatat organizátora?"); //todo i18n
        });

    }

    public function handleDelete($id){
        $this->serviceEventOrg->getTable()->where('e_org_id', $id)->delete();
    }

}
