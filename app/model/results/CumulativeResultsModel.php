<?php

/**
 * Cumulative results (sums and precentage) for chosen series.
 *
 * @author Michal Koutný <michal@fykos.cz>
 */
class CumulativeResultsModel extends AbstractResultsModel {

    /**
     * @var array of int
     */
    protected $series;

    /**
     * Cache
     * @var array
     */
    private $dataColumns = array();

    /**
     * Definition of header.
     * 
     * @return array
     */
    public function getDataColumns($category) {
        if ($this->series === null) {
            throw new \Nette\InvalidStateException('Series not specified.');
        }

        if (!isset($this->dataColumns[$category->id])) {
            $dataColumns = array();
            $sum = 0;
            foreach ($this->getSeries() as $series) {
                // sum points as sum of tasks
                $points = null;
                foreach ($this->getTasks($series) as $task) {
                    $points += $this->evaluationStrategy->getTaskPoints($task, $category);
                }
            
                $dataColumns[] = array(
                    self::COL_DEF_LABEL => $series,
                    self::COL_DEF_LIMIT => $points,
                    self::COL_ALIAS => self::DATA_PREFIX . count($dataColumns),
                );
                $sum += $points;
            }
            $dataColumns[] = array(
                self::COL_DEF_LABEL => self::LABEL_PERCETAGE,
                self::COL_DEF_LIMIT => 100,
                self::COL_ALIAS => self::ALIAS_PERCENTAGE,
            );
            $dataColumns[] = array(
                self::COL_DEF_LABEL => self::LABEL_SUM,
                self::COL_DEF_LIMIT => $sum,
                self::COL_ALIAS => self::ALIAS_SUM,
            );
            $this->dataColumns[$category->id] = $dataColumns;
        }
        return $this->dataColumns[$category->id];
    }

    public function getSeries() {
        return $this->series;
    }

    public function setSeries($series) {
        $this->dataColumns = null;
        $this->series = $series;
        // invalidate cache of columns
        $this->dataColumns = array();
    }

    public function getCategories() {
        return $this->evaluationStrategy->getCategories();
    }

    protected function composeQuery($category) {
        if (!$this->series) {
            throw new \Nette\InvalidStateException('Series not set.');
        }

        $select = array();
        $select[] = "IF(p.display_name IS NULL, CONCAT(p.other_name, ' ', p.family_name), p.display_name) AS `" . self::DATA_NAME . "`";
        $select[] = "sch.name_abbrev AS `" . self::DATA_SCHOOL . "`";

        $sum = $this->evaluationStrategy->getSumColumn();
        $i = 0;
        foreach ($this->getSeries() as $series) {
            $select[] = "round(SUM(IF(t.series = " . $series . ", " . $sum . ", null))) AS '" . self::DATA_PREFIX . $i . "'";
            $i += 1;
        }

        $select[] = "round(100 * SUM($sum) / SUM(".$this->evaluationStrategy->getTaskPointsColumn($category).")) AS '" . self::ALIAS_PERCENTAGE . "'";
        $select[] = "round(SUM($sum)) AS '" . self::ALIAS_SUM . "'";
        $select[] = "ct.ct_id";

        $study_years = $this->evaluationStrategy->categoryToStudyYears($category);

        $from = " from v_contestant ct
left join person p using(person_id)
left join school sch using(school_id)
left join task t ON t.year = ct.year AND t.contest_id = ct.contest_id
left join submit s ON s.task_id = t.task_id AND s.ct_id = ct.ct_id";

        $conditions = array(
            'ct.year' => $this->year,
            'ct.contest_id' => $this->contest->contest_id,
            't.series' => $this->getSeries(),
            'ct.study_year' => $study_years,
        );

        $query = "select " . implode(', ', $select);
        $query .= $from;

        $where = $this->conditionsToWhere($conditions);
        $query .= " where $where";

        $query .= " group by p.person_id"; //abuse MySQL misimplementation of GROUP BY
        $query .= " order by `" . self::ALIAS_SUM . "` DESC, p.family_name ASC, p.other_name ASC";

        $dataAlias = 'data';
        $wrappedQuery = "select $dataAlias.*, @rownum := @rownum + 1, @rank := IF($dataAlias." . self::ALIAS_SUM . " = @prevSum or ($dataAlias." . self::ALIAS_SUM . " is null and @prevSum is null), @rank, @rownum) AS `" . self::DATA_RANK_FROM . "`, @prevSum := $dataAlias." . self::ALIAS_SUM . "
        from ($query) data, (select @rownum := 0, @rank := 0, @prevSum := -1) init";
        return $wrappedQuery;
    }

}

?>
