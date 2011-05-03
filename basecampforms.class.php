<?php
/*
 * Code written by Sam Huggill http://shuggill.wordpress.com/
 *
 */
require_once('phpquery.php');

class BasecampForms
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
					'companies' => 'companies',
					'backup' => 'global/export',
					'settingsdata' => 'global/data'
					);
	
	public function setAccountName($n) { $this->_accountname = $n; }
	public function setUsername($n) { $this->_username = $n; }
	public function setPassword($n) { $this->_password = $n; }
	public function setCookieFile($n) { $this->_cookiefile = $n; }
	public function setUrl($n) { $this->_urls['base'] = $n; }
	
	public function getError() { return $this->_errMsg; }
	
	public function triggerBackup() {
		$success = true;
		
		// go to the company page first, to get the token.
		$resp = $this->_fetchUrl($this->_urls['base'].$this->_urls['companies'], 'GET', array(), false, $this->_ch);
		
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
	
	public function updateCompany($newCompanyName, $id) {
	
		$success = true;
		
		$resp = $this->_fetchUrl($this->_urls['base'].$this->_urls['companies'].'/'.$id.'/edit', 'GET', array(), false, $this->_ch);
		
		phpQuery::newDocument($resp['data']);
		
		$formFields = array(
			'_method' => 'put',
			'authenticity_token' => pq('input[name=authenticity_token]')->val(),
			'company[name]' => $newCompanyName,
			'company[address_one]' => pq('input#company_address_one')->val(),
			'company[address_two]' => pq('input#company_address_two')->val(),
			'company[city]' => pq('input#company_city')->val(),
			'company[state]' => pq('input#company_state')->val(),
			'company[zip]' => pq('input#company_zip')->val(),
			'company[country]' => pq('select#company_country option:selected')->val(),
			'company[locale]' => pq('select#company_locale option:selected')->val(),
			'company[time_zone_id]' => pq('select#company_time_zone_id option:selected')->val(),
			'company[web_address]' => pq('input#company_web_address')->val(),
			'company[phone_number_office]' => pq('input#company_phone_number_office')->val(),
			'company[phone_number_fax]' => pq('input#company_phone_number_fax')->val(),
			'company[can_see_private]' => pq('input[name=company[can_see_private]]')->val(),
			'commit' => 'Save Changes'
		);
		
		$resp = $this->_fetchUrl($this->_urls['base'].$this->_urls['companies'].'/'.$id, 'POST', $formFields, false, $this->_ch);
		
		return $success;
	}
	
	public function createCompany($companyName) {
		
		$success = true;
		
		// go to the company page first, to get the token.
		$resp = $this->_fetchUrl($this->_urls['base'].$this->_urls['companies'], 'GET', array(), false, $this->_ch);
		
		phpQuery::newDocument($resp['data']);
		$authToken = pq('input[name=authenticity_token]')->val();
		
		$formFields = array(
			'authenticity_token' => $authToken,
			'company[name]' => $companyName,
			'commit' => 'Create Company'
		);
		
		$resp = $this->_fetchUrl($this->_urls['base'].$this->_urls['companies'], 'POST', $formFields, false, $this->_ch);
		
		// check - has this company already been created?
		phpQuery::newDocument($resp['data']);
		if(pq('div.flash_alert')->text() != '') {
			$success = false;
			$this->_errMsg = pq('div.flash_alert')->text();
		}
		
		return $success;
	}
	
	public function login() {
		
		$loggedIn = true;
		$errMsg = '';
		
		$loginFields = array(
			'product'=>'basecamp',
			'subdomain'=>$this->_accountname,
			'username'=>$this->_username,
			'password'=>$this->_password,
			'commit'=>'Sign in'
			);
		
		$resp = $this->_fetchUrl($this->_urls['login'], 'POST', $loginFields, false);
		
		// reuse this cURL handle later.
		$this->_ch = $resp['handle'];
		
		// did the login go OK?
		phpQuery::newDocument($resp['data']);
		if(pq('div.login_dialog')->attr('id') == 'login_dialog') {
			$loggedIn = false;
			$errMsg = pq('div.flash_alert')->text();
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