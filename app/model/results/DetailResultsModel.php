<?php

/**
 * Detailed results of a single series. Number of tasks is dynamic.
 *
 * @author Michal Koutný <michal@fykos.cz>
 */
class DetailResultsModel extends AbstractResultsModel {

    /**
     * @var int
     */
    protected $year;

    /**
     * @var ModelContest
     */
    protected $contest;

    /**
     * @var ServiceTask
     */
    protected $serviceTask;

    /**
     * @var \Nette\Database\Connection
     */
    protected $connection;

    /**
     * @var int
     */
    protected $series;

    /**
     *
     * @var IEvaluationStrategy
     */
    protected $evaluationStrategy;

    function __construct(ModelContest $contest, ServiceTask $serviceTask, \Nette\Database\Connection $connection, $year, IEvaluationStrategy $evaluationStrategy) {
        $this->contest = $contest;
        $this->serviceTask = $serviceTask;
        $this->connection = $connection;
        $this->year = $year;
        $this->evaluationStrategy = $evaluationStrategy;
    }

    /**
     * Cache
     * @var array
     */
    private $dataColumns = null;

    /**
     * Definition of header.
     * 
     * @return array
     */
    public function getDataColumns() {
        if ($this->dataColumns === null) {
            $this->dataColumns = array();
            $sum = 0;
            foreach ($this->getTasks() as $task) {
                $this->dataColumns[] = array(
                    self::COL_DEF_LABEL => $task->label,
                    self::COL_DEF_LIMIT => $task->points,
                    self::COL_ALIAS => self::DATA_PREFIX . count($this->dataColumns),
                );
                $sum += $task->points;
            }
            $this->dataColumns[] = array(
                self::COL_DEF_LABEL => self::LABEL_SUM,
                self::COL_DEF_LIMIT => $sum,
                self::COL_ALIAS => self::ALIAS_SUM,
            );
        }
        return $this->dataColumns;
    }

    public function getSeries() {
        return $this->series;
    }

    public function setSeries($series) {
        $this->series = $series;
    }

    public function getCategories() {
        return $this->evaluationStrategy->getCategories();
    }

    /**
     * @return \Nette\Database\Table\Selection
     * @throws \Nette\InvalidStateException
     */
    private function getTasks() {
        if (!$this->series) {
            throw new \Nette\InvalidStateException('Series not set.');
        }
        return $this->serviceTask->getTable()
                        ->select('task_id, label, points')
                        ->where(array(
                            'contest_id' => $this->contest->contest_id,
                            'year' => $this->year,
                            'series' => $this->series,
                        ))
                        ->order('tasknr');
    }

    protected function composeQuery($category) {
        if (!$this->series) {
            throw new \Nette\InvalidStateException('Series not set.');
        }

        $select = array();
        $select[] = "IF(p.display_name IS NULL, CONCAT(p.other_name, ' ', p.family_name), p.display_name) AS `" . self::DATA_NAME . "`";
        $select[] = "sch.name_abbrev AS `" . self::DATA_SCHOOL . "`";

        $tasks = $this->getTasks();
        $i = 0;
        foreach ($tasks as $task) {
            $points = $this->evaluationStrategy->getPointsColumn($task);
            $select[] = "round(MAX(IF(t.task_id = " . $task->task_id . ", " . $points . ", null))) AS '" . self::DATA_PREFIX . $i . "'";
            $i += 1;
        }
        $sum = $this->evaluationStrategy->getSumColumn();
        $select[] = "round(SUM($sum)) AS '" . self::ALIAS_SUM . "'";

        $study_years = $this->evaluationStrategy->categoryToStudyYears($category);

        $from = " from contestant ct
left join person p using(person_id)
left join school sch using(school_id)
left join task t ON t.year = ct.year AND t.contest_id = ct.contest_id
left join submit s ON s.task_id = t.task_id AND s.ct_id = ct.ct_id";

        $conditions = array(
            'ct.year' => $this->year,
            'ct.contest_id' => $this->contest->contest_id,
            't.series' => $this->series,
            'ct.study_year' => $study_years,
        );

        $query = "select " . implode(', ', $select);
        $query .= $from;

        $where = $this->conditionsToWhere($conditions);        
        $query .= " where $where";

        $query .= " group by p.person_id"; //abuse MySQL misimplementation of GROUP BY
        $query .= " order by `" . self::ALIAS_SUM . "` DESC, p.family_name ASC, p.other_name ASC";

        $dataAlias = 'data';
        $wrappedQuery = "select $dataAlias.*, @rownum := @rownum + 1, @rank := IF($dataAlias." . self::ALIAS_SUM . " = @prevSum, @rank, @rownum) AS `" . self::DATA_RANK_FROM . "`, @prevSum := $dataAlias." . self::ALIAS_SUM . "
        from ($query) data, (select @rownum := 0, @rank := 0, @prevSum := null) init";
        return $wrappedQuery;
    }

}

?>