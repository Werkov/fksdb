<?php

use Nette\Mail\IMailer;
use Nette\Mail\Message;
use Nette\Object;

/**
 * Due to author's laziness there's no class doc (or it's self explaining).
 * 
 * @author Michal Koutný <michal@fykos.cz>
 */
class LoggingMailer extends Object implements IMailer {

    /**
     * @var IMailer
     */
    private $mailer;
    private $logPath;
    private $logging = true;

    function __construct(IMailer $mailer) {
        $this->mailer = $mailer;
    }

    public function getLogPath() {
        return $this->logPath;
    }

    public function setLogPath($logPath) {
        $this->logPath = $logPath;
        @mkdir($this->logPath, 0770, true);
    }

    public function getLogging() {
        return $this->logging;
    }

    public function setLogging($logging) {
        $this->logging = $logging;
    }

    public function send(Message $mail) {
        try {
            $this->mailer->send($mail);
            $this->logMessage($mail);
        } catch (Exception $e) {
            $this->logMessage($mail, $e);
            throw $e;
        }
    }

    private function logMessage(Message $mail, Exception $e = null) {
        if (!$this->logging) {
            return;
        }
        $fingerprint = Utils::getFingerprint($mail->getHeaders());
        $filename = 'mail-' . @date('Y-m-d-H-i-s') . '-' . $fingerprint . '.txt';
        $f = fopen($this->logPath . DIRECTORY_SEPARATOR . $filename, 'w');

        if ($e) {
            fprintf($f, "FAILED %s\n", $e->getMessage());
        }
        fwrite($f, $mail->generateMessage());

        fclose($f);
    }

}
