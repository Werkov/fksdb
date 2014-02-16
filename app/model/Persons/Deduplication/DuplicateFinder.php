<?php

namespace Persons\Deduplication;

use Nette\Database\Table\ActiveRow;
use Nette\InvalidArgumentException;
use Nette\Utils\Strings;
use ServicePerson;

/**
 * Due to author's laziness there's no class doc (or it's self explaining).
 * 
 * @author Michal Koutný <michal@fykos.cz>
 */
class DuplicateFinder {

    const IDX_PERSON = 'person';
    const IDX_SCORE = 'score';

    /**
     * @var ServicePerson
     */
    private $servicePerson;

    /**
     * @var double [0,1) threshold for similarity score to consider two recrods equal persons
     */
    private $threshold = 0.84;

    function __construct(ServicePerson $servicePerson) {
        $this->servicePerson = $servicePerson;
    }

    public function getPairs() {
        $buckets = array();
        /* Create buckets for quadratic search. */
        foreach ($this->servicePerson->getTable() as $person) {
            $bucketKey = $this->getBucketKey($person);
            if (!isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = array();
            }
            $buckets[$bucketKey][] = $person;
        }

        /* Run quadratic comparison in each bucket */
        $pairs = array();
        foreach ($buckets as $bucket) {
            foreach ($bucket as $personA) {
                foreach ($bucket as $personB) {
                    if ($personA->person_id >= $personB->person_id) {
                        continue;
                    }
                    $score = $this->getSimilarityScore($personA, $personB);
                    if ($score > $this->threshold) {
                        $pairs[$personA->person_id] = array(
                            self::IDX_PERSON => $personB,
                            self::IDX_SCORE => $score,
                        );
                        continue; // we search only pairs, so each equivalence class is decomposed into pairs
                    }
                }
            }
        }
        return $pairs;
    }

    private function getBucketKey(ActiveRow $row) {
        $fam = Strings::webalize($row->family_name);
        return substr($fam, 0, 3) . substr($fam, -1);
        //return $row->gender . mb_substr($row->family_name, 0, 2);
    }

    /**
     * @todo Implement more than binary score.
     * 
     * @param ActiveRow $a
     * @param ActiveRow $b
     * @return float
     */
    private function getSimilarityScore(ActiveRow $a, ActiveRow $b) {
        /*
         * Email check
         */
        $piA = $a->getInfo();
        $piB = $b->getInfo();
        if (!$piA || !$piB) {
            $emailScore = 0.5; // cannot say anything
        } else if (!$piA->email || !$piB->email) {
            $emailScore = 0.8; // a little bit more
        } else {
            $emailScore = 1 - $this->relativeDistance($piA->email, $piB->email);
        }

        $familyScore = $this->stringScore($a->family_name, $b->family_name);
        $otherScore = $this->stringScore($a->other_name, $b->other_name);


        return 0.45 * $familyScore + 0.2 * $otherScore + 0.35 * $emailScore;
    }

    private function stringScore($a, $b) {
        return 1 - $this->relativeDistance(Strings::webalize($a), Strings::webalize($b));
    }

    private function relativeDistance($a, $b) {
        $maxLen = max(strlen($a), strlen($b));
        if ($maxLen == 0) {
            throw new InvalidArgumentException('Distance not defined.');
        }
        return levenshtein($a, $b) / $maxLen;
    }

}
