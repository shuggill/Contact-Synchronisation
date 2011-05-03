<?php
/*
 * Code written by Sam Huggill http://shuggill.wordpress.com/
 *
 */
class HighriseAPI
{

  private $dbgEn = false;
  private $url = "";
  private $apiKey = "";
  
  public function setUrl($n) { $this->url = $n; }
  public function setApiKey($n) { $this->apiKey = $n; }
  public function setDebug($n) { $this->dbgEn = $n; }

  /**
   * get tags
   * @param string $subject case, companies, deal, person
   * @param int $subjectId  The ID associated with this subject
   * @return <type>
   */
  public function getTags($subject="", $subjectId=0) {
    switch(strtolower($subject)) {
      case 'company':
        return ( $this->_getData("companies/{$subjectId}/tags.xml") );
        break;
      default:
        return ( $this->_getData("tags.xml") );
        break;
    }
  }

  public function getPeople($tagId="") {
    if(!empty($tagId)) {
      return ( $this->_getData("people.xml?tag_id={$tagId}") );
    } else {
      return ( $this->_getData("people.xml") );
    }
  }

  public function getPerson($id) {
    return ( $this->_getData("people/{$id}.xml") );
  }

  public function getCompanies($tagId="") {
    if(!empty($tagId)) {
      return ( $this->_getData("companies.xml?tag_id={$tagId}") );
    } else {
      return ( $this->_getData("companies.xml") );
    }
  }

  public function getCompany($id, $search='') {
    
    if($search != '') {
      return ( $this->_getData("companies/search.xml?term={$search}"));
    } else {
      return ( $this->_getData("companies/{$id}.xml") );
    }
  }

  public function getCompanyPeople($id) {
    return ( $this->_getData("companies/{$id}/people.xml") );
  }

  private function _getData($uriExtension) {
  
    $xmlObj = '';
  
    try
    {
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
        
        $this->dbg($base_url, $response);

        $xmlObj = new SimpleXMLElement($response);
    }
    catch(Exception $e)
    {
        $this->dbg('highrise_getData:Exception', $e->getMessage());
    }

    return ($xmlObj);
  }
  
    private function dbg($msg, $response='') {
        if($this->dbgEn) {
          echo '<pre class="debugBox debugVar" style="padding: 5px; position: relative; text-align: left; font-size: 12px; color: #a00; background-color:#fee; border: 1px solid red; max-height: 300px; overflow: auto;">';
          echo '<b style="background-color: #fdd; padding: 2px; " ><em><u>'.htmlentities($msg).'</u></em></b> :'."\n\n";
          //if(is_object($response) || is_array($response)) echo " <span style=\"position: absolute; right:5px; \">(Click to expand)</span>\n";
          print_r($response);
          echo '</pre>';
        }
    }
}

?>