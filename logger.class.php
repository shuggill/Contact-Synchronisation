<?php
/*
 * Code written by Sam Huggill http://shuggill.wordpress.com/
 *
 */
class Logger
{
	private $_logFile = '';
	
    /** singelton implementation start */
    private static $instance;
 
    private function __construct() {
        // don't allow direct creation of this object.
    }
    
    public function __clone() {
        // prevent object cloning.
        trigger_error("Clone is not allowed.", E_USER_ERROR);
    }
    
    public static function getInstance() {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c;
        }
        
        return self::$instance;
    }
    /** singelton implementation end */

	public function setLogFile($f) { $this->_logFile = $f; }

	public function init() {
		// if the log file doesn't exist create it.
		if(!file_exists($this->_logFile)) {
			$fh = fopen($this->_logFile, 'w+');
			fclose($fh);
			
			$this->_writeLog('Log File Created', $this->_logFile);
		}
	}

	public function log($msg, $data = '') {
		$this->_writeLog($msg, $data);
	}
	
	private function _writeLog($msg, $data = '') {
		if($msg != '') {
			$entry = date('Y-m-d H:i:s')."\t".$msg.($data == '' ? '' : "\t".$data)."\n";	
		} else {
			$entry = "\n";
		}
		
		$fh = fopen($this->_logFile, 'a');
		fwrite($fh, $entry);
		fclose($fh);
	}
}

?>