<?php
/*
 * Code written by Sam Huggill http://shuggill.wordpress.com/
 *
 */
require_once('phpquery.php');

class HighriseForms
{
	private $_accountname;
	private $_username;
	private $_password;
	private $_cookiefile;
	private $_errMsg;
	
	private $_ch;
	
	private $_urls = array(
					'login'=>'https://launchpad.37signals.com/authenticate',
					'base' =>'',
					'welcome' => 'welcome',
					'backup' => 'recording_exports'
					);
	
	public function setAccountName($n) { $this->_accountname = $n; }
	public function setUsername($n) { $this->_username = $n; }
	public function setPassword($n) { $this->_password = $n; }
	public function setCookieFile($n) { $this->_cookiefile = $n; }
	public function setUrl($n) { $this->_urls['base'] = $n; }
	
	public function getError() { return $this->_errMsg; }
	
	public function triggerBackup() {
		$success = true;
		
		// go to the welcome page first, to get the token.
		$resp = $this->_fetchUrl($this->_urls['base'].$this->_urls['welcome'], 'GET', array(), false, $this->_ch);
		
		phpQuery::newDocument($resp['data']);
		$authToken = pq('input[name=authenticity_token]')->val();
		
		$formFields = array(
			'authenticity_token' => $authToken
		);
		
		$resp = $this->_fetchUrl($this->_urls['base'].$this->_urls['backup'], 'POST', $formFields, false, $this->_ch);
		
		// has this worked?
		// TODO
		
		return $success;
	}
	
	public function login() {
		
		$loggedIn = true;
		$errMsg = '';
		
		$loginFields = array(
			'product'=>'highrise',
			'subdomain'=>$this->_accountname,
			'username'=>$this->_username,
			'password'=>$this->_password,
			'submit'=>'Sign in'
			);
		
		$resp = $this->_fetchUrl($this->_urls['login'], 'POST', $loginFields, false);
		
		// reuse this cURL handle later.
		$this->_ch = $resp['handle'];
		
		// did the login go OK?
		phpQuery::newDocument($resp['data']);
		if(pq('div.flash_error')->text() != '') {
			$loggedIn = false;
			$errMsg = pq('div.flash_error')->text();
		}
		
		$this->_errMsg = $errMsg;
		
		return $loggedIn;
	}
	
	private function _fetchUrl($url, $method='GET', $fields = array(), $close = true, $ch = null) {

		$fields_string='';
		if($method == 'POST') {
			foreach($fields as $key=>$value) { $fields_string .= urlencode($key).'='.urlencode($value).'&'; }
			rtrim($fields_string,'&');
		}

		if($ch == null)	$ch = curl_init();

		curl_setopt($ch,CURLOPT_URL,$url);
		if($method == 'POST') {
			curl_setopt($ch,CURLOPT_POST,count($fields));
			curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
		}

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		//curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->_cookiefile);

		$data = curl_exec($ch);

		if($close) curl_close($ch);

		return array('data'=>$data, 'handle'=>$ch);
	}
}

?>