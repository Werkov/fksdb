<?php

namespace Pipeline;

use Nette\InvalidStateException;
use RuntimeException;

/**
 * Represents a simple pipeline where each stage has its input and output and they
 * comprise a linear chain.
 * 
 * @todo Implement generic ILogger.
 * 
 * @author Michal Koutný <michal@fykos.cz>
 */
class Pipeline {

    const LOG_ERROR = 3;
    const LOG_WARNING = 2;
    const LOG_INFO = 1;

    /**
     * @var array of IStage
     */
    private $stages = array();

    /**
     * @var mixed
     */
    private $input;

    /**
     * @var bool
     */
    private $fixedStages = false;

    /**
     * @var array of string
     */
    private $log = array();

    /**
     * Stages can be added only in the build phase (not after setting the data).
     * 
     * @param \Pipeline\Stage $stage
     * @throws InvalidStateException
     */
    public function addStage(Stage $stage) {
        if ($this->fixedStages) {
            throw new InvalidStateException('Cannot modify pipeline after loading data.');
        }
        $this->stages[] = $stage;
        $stage->setPipeline($this);
    }

    /**
     * Input to the pipeline.
     * 
     * @param mixed $input
     */
    public function setInput($input) {
        $this->fixedStages = true;
        $this->input = $input;
    }

    /**
     * Starts the pipeline.
     * 
     * @return mixed    output of the last stage
     */
    public function run() {
        $this->clearLog();
        $data = $this->input;
        foreach ($this->stages as $stage) {
            $stage->setInput($data);
            $stage->process();
            $data = $stage->getOutput();
        }

        return $data;
    }

    // TODO implement log level
    public function log($message, $level = self::LOG_INFO) {
        $this->log[] = $message;
    }

    private function clearLog() {
        $this->log = array();
    }

    public function getLog() {
        return $this->log;
    }

}

class PipelineException extends RuntimeException {
    
}
