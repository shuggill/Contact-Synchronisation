<?php
/*
 * Code written by Sam Huggill http://shuggill.wordpress.com/
 *
 */

class BasecampAPI
{
  private $dbgEn = false;
  private $url = "";
  private $apiKey = "";
  
  public function setUrl($n) { $this->url = $n; }
  public function setApiKey($n) { $this->apiKey = $n; }
  public function setDebug($n) { $this->dbgEn = $n; }
  
  public function getCompanies() {
    return ( $this->_getData("companies.xml") );
  }
  
  public function getProjects() {
    return ( $this->_getData("projects.xml") );
  }

  private function _getData($uriExtension) {

    $base_url = $this->url.$uriExtension;

    $session = curl_init();

    curl_setopt($session, CURLOPT_URL, $base_url);
    curl_setopt($session, CURLOPT_USERPWD, $this->apiKey.':X');
    curl_setopt($session, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($session, CURLOPT_HEADER, false);
    curl_setopt($session, CURLOPT_HTTPGET, 1);
    curl_setopt($session, CURLOPT_HTTPHEADER, array('Accept: application/xml', 'Content-Type: application/xml'));
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($session, CURLOPT_SSL_VERIFYPEER,false);
    curl_setopt($session, CURLOPT_FRESH_CONNECT, true);

    $response = curl_exec($session);
    curl_close($session);

    if($this->dbgEn) {
      echo '<pre class="debugBox debugVar" style="padding: 5px; position: relative; text-align: left; font-size: 12px; color: #a00; background-color:#fee; border: 1px solid red; max-height: 300px; overflow: auto;">';
      echo '<b style="background-color: #fdd; padding: 2px; " ><em><u>'.$base_url.'</u></em></b> :'."\n\n";
      if(is_object($response) || is_array($response)) echo " <span style=\"position: absolute; right:5px; \">(Click to expand)</span>\n";
      echo htmlentities($response);
      echo '</pre>';
    }

    $xmlObj = new SimpleXMLElement($response);

    return ($xmlObj);
  }
}

?>