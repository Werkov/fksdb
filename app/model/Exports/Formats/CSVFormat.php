<?php

use Exports\IExportFormat;
use Exports\StoredQuery;
use Nette\Object;
use PePa\CSVResponse;

/**
 * Due to author's laziness there's no class doc (or it's self explaining).
 * 
 * @author Michal Koutný <michal@fykos.cz>
 */
class CSVFormat extends Object implements IExportFormat {
    
    const DEFAULT_DELIMITER = ';';
    const DEFAULT_QUOTE = false;

    /**
     * @var StoredQuery
     */
    private $storedQuery;
    private $delimiter;
    private $quote;
    private $header;

    function __construct(StoredQuery $storedQuery, $header, $delimiter = self::DEFAULT_DELIMITER, $quote = self::DEFAULT_QUOTE) {
        $this->storedQuery = $storedQuery;
        $this->delimiter = $delimiter;
        $this->quote = $quote;
        $this->header = $header;
    }

    public function getResponse() {
        $data = $this->storedQuery->getData();
        $name = isset($this->storedQuery->getQueryPattern()->name) ? $this->storedQuery->getQueryPattern()->name : 'adhoc';
        $name .= '.csv';

        $response = new CSVResponse($data, $name);
        $response->setAddHeading($this->header);
        $response->setQuotes($this->quote);
        $response->setGlue($this->delimiter);

        return $response;
    }

}
