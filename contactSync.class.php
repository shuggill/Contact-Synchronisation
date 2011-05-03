<?php
/*
 * Code written by Sam Huggill http://shuggill.wordpress.com/
 *
 */
require_once 'Zend/Loader.php';
require_once 'basecampapi.class.php';
require_once 'highriseapi.class.php';
require_once 'basecampforms.class.php';
require_once 'highriseforms.class.php';

Zend_Loader::loadClass('Zend_Gdata');
Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
Zend_Loader::loadClass('Zend_Gdata_Docs');
Zend_Loader::loadClass('Zend_Uri_Http');
Zend_Loader::loadClass('Zend_Gdata_Query');
Zend_Loader::loadClass('Zend_Gdata_Feed');

define('CONTACT_SYNC_GOOGLE_FOLDER_LABEL', 'folder');
define('CONTACT_SYNC_GOOGLE_DOCS_FEED', 'http://docs.google.com/feeds/documents/private/full?showfolders=true');

class ContactSync
{  
    private $googleDomain='';
    private $googleUser='';
    private $googlePwd='';
    private $highriseTag='';
    private $highriseApiKey='';
    private $highriseUrl='';
    private $basecampApiKey='';
    private $basecampUrl='';
    private $dbgEn=false;

	private $basecampUsername;
	private $basecampPassword;
	private $basecampAccount;
	
	private $highriseUsername;
	private $highrisePassword;
	private $highriseAccount;
    
    /** @property BasecampAPI $basecampApi */
    private $basecampApi;
    /** @property HighriseAPI $highriseApi */
    private $highriseApi;
	/** @property Logger $logger */
	private $logger;
	/** @property BasecampForms $basecampForms */
	private $basecampForms;
	/** @property HighriseForms $highriseForms */
	private $highriseForms;
 
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
    
    public function setDebug($n) { $this->dbgEn=$n; }
    public function setGoogleDomain($n) { $this->googleDomain=$n; }
    public function setGoogleUser($n) { $this->googleUser=$n; }
    public function setGooglePwd($n) { $this->googlePwd=$n; }
    public function setHighriseTag($n) { $this->highriseTag=$n; }
    public function setHighriseApiKey($n) { $this->highriseApiKey=$n; }
    public function setHighriseUrl($n) { $this->highriseUrl=$n; }
    public function setBasecampApiKey($n) { $this->basecampApiKey=$n; }
    public function setBasecampUrl($n) { $this->basecampUrl=$n; }
	public function setLogger() { global $logger; $this->logger=&$logger; }
	
	public function setBasecampUsername($n) { $this->basecampUsername = $n; }
	public function setBasecampPassword($n) { $this->basecampPassword = $n; }
	public function setBasecampAccount($n)  { $this->basecampAccount = $n; }
	
	public function setHighriseUsername($n) { $this->highriseUsername = $n; }
	public function setHighrisePassword($n) { $this->highrisePassword = $n; }
	public function setHighriseAccount($n) { $this->highriseAccount = $n; }
	
	public function backupHighrise() {
		$this->highriseFormsInit();
		if($this->highriseForms->login()) {
			$this->logger->log('Triggering Highrise Backup');
			$this->highriseForms->triggerBackup();
		} else {
			$this->logger->log('Forms login to Highrise failed: '.$this->highriseForms->getError());
		}
	}
	
	public function backupBasecamp() {
		$this->basecampFormsInit();
		if($this->basecampForms->login()) {
			$this->logger->log('Triggering Basecamp Backup');
			$this->basecampForms->triggerBackup();			
		} else {
			$this->logger->log('Forms login to Basecamp failed: '.$this->basecampForms->getError());
		}
	}
	
	public function initMappings() {
		$hCompanies = $this->highriseGetCompanies($this->highriseTag);
        $bCompanies = $this->basecampGetCompanies();

		$this->logger->log('Initialising Mappings');

		$mappings=array();
        
        foreach($hCompanies as $hCompany) {
            $companyInBasecamp=false;

            foreach($bCompanies as $bCompany) {
                if($hCompany['name'] == $bCompany['name']) {
                    $companyInBasecamp=true;
                    break;
                }
            }
            
            if($companyInBasecamp) {
                $mappings[]=array(
                	'highriseId'=>$hCompany['id'],
					'basecampId'=>$bCompany['id'],
					'name'=>$hCompany['name']
                );
            }
        }

		$this->_writeMappings($mappings);
		
		echo "<pre>";
		print_r($mappings);
		echo "</pre>";
		
		echo "number of highrise companies: ".count($hCompanies);
	}
	
	public function updateBasecampCompanies() {
		
		$this->logger->log('Basecamp Company Update: Start');
		
        $hCompanies = $this->highriseGetCompanies($this->highriseTag);
		$mappings = $this->_getMappings();
		
		$this->basecampFormsInit();
		if($this->basecampForms->login()) {
			
			$companiesUpdated=0;
		
			// check for name changes.
			foreach($hCompanies as $hCompany) {
				foreach($mappings as $mapping) {
					if($hCompany['id'] == $mapping['highriseId']) {
						// update the company in basecamp.
						$this->basecampForms->updateCompany($hCompany['name'], $mapping['basecampId']);
						$companiesUpdated++;
					}
				}			
			}
		
			// check for new names.
	        $bCompanies = $this->basecampGetCompanies();
	        $companiesNotInBasecamp = array();
	        foreach($hCompanies as $hCompany) {
	            $companyInBasecamp=false;

	            foreach($bCompanies as $bCompany) {
	                if($hCompany['name'] == $bCompany['name']) {
	                    $companyInBasecamp=true;
	                    break;
	                }
	            }
            
	            if(!$companyInBasecamp) {
	                $companiesNotInBasecamp[] = $hCompany['name'];
	            }
	        }

			$companiesAdded=0;
					
			foreach($companiesNotInBasecamp as $company) {
				if($this->basecampForms->createCompany($company)) {
					// all OK.
					$this->logger->log('Created a Company in Basecamp', $company);
					$companiesAdded++;
				} else {
					$this->logger->log('Could not create company '.$company.' in Basecamp', $this->basecampForms->getError());
				}
			}
			
			// reinitialise the mappings database.
			if($companiesAdded > 0) {
				$this->initMappings();
			}
			
		} else {
			$this->logger->log('Basecamp Login Failed', $this->basecampForms->getError());
		}
		
		$this->logger->log('Basecamp Company Update: Complete', $companiesUpdated.' companies updated, '.$companiesAdded.' companies added');
	}
    
    public function basecampGenerateCompanyDiff() {
	
		$this->logger->log('Basecamp Diff: Start');
	
        $hCompanies = $this->highriseGetCompanies($this->highriseTag);
        $bCompanies = $this->basecampGetCompanies();
        
        $companiesNotInBasecamp = array();
        foreach($hCompanies as $hCompany) {
            $companyInBasecamp=false;
            foreach($bCompanies as $bCompany) {
                if($hCompany['name'] == $bCompany['name']) {
                    $companyInBasecamp=true;
                    break;
                }
            }
            
            if(!$companyInBasecamp) {
                $companiesNotInBasecamp[] = $hCompany['name'];
            }
        }
        
		$this->logger->log('Basecamp Diff: Complete',count($companiesNotInBasecamp)." companies not in Basecamp");
        $this->dbg('Companies not in basecamp', $companiesNotInBasecamp);
                
        return $companiesNotInBasecamp;        
    }
    
    public function updateGoogleFolders() {
	
		$this->logger->log('Google Folders Update: Start');
		
		$newGoogleFolders=0;
	
        // step 1 - get all tagged companies.
        $companies = $this->highriseGetCompanies($this->highriseTag);

        // step 2 - get all basecamp projects.
        $projects = $this->basecampGetProjects();
        
        // step 3 - get all Google Docs folders.
        $folders = $this->googleGetFolders();

        // step 4 - update Google Docs.
        for($i=0;$i<count($companies);$i++) {
        //foreach($companies as $company) {
        
            $company = $companies[$i];
        
            // step 4.1 check there's a folder for each company.
            $companyInGoogle=false;
            
            foreach($folders as $folder) {
                if($folder['title'] == $company['name']) {
                    // we have a match.
                    $companyInGoogle=true;
                }
                if($companyInGoogle) break;
            }
            
            if($companyInGoogle) {
                $folderId=$folder['id'];
            } else {
                // add a new folder for this company.
                $folderId = $this->googleCreateFolder($company['name']);
                $folderId = $this->googleParseFolderIdFromUri($folderId);
                $this->googleShareFolderWithDomain($folderId, 'reader'); 
				$newGoogleFolders++;               
            }
            
            // store this folder ID against the company so it can be used later.
            $companies[$i]['googleFolderId'] = $folderId;
            $company['googleFolderId'] = $folderId;
            
            // step 4.2 check there's a folder for each project in this company.
            foreach($projects as $project) {
                if($project['company'] == $company['name']) {
                    $projectInGoogle=false;
                    foreach($folders as $folder) {
                        // does the folder have a title=project and a label=company?
                        if($folder['title'] == $project['name']) {
                            foreach($folder['labels'] as $label) {
                                if($label == $company['name']) {
                                    $projectInGoogle=true;
                                }
                            }
                        }
                    }
                    
                    if($projectInGoogle) {
                        // happy days!
                    } else {
                        // add this project to Google.
                        $folderId = $this->googleCreateFolder($project['name'], $company['googleFolderId']);
                        $folderId = $this->googleParseFolderIdFromUri($folderId);
                        $this->googleShareFolderWithDomain($folderId, 'writer');
						$newGoogleFolders++;
                    }
                }
            }
        }

		$this->logger->log('Google Folders Update: Complete', $newGoogleFolders." folders added");
    }
    
    public function copyHighriseContactsToGoogle() {
        
		$this->logger->log('Highrise to Google: Start');

        // step 1 - get all companies.
        $companies = $this->highriseGetCompanies();
                
        // step 2 - get all people within the tagged companies.
        $people=array();
        foreach($companies as $company) {
            $cPeople = $this->highriseGetCompanyPeople((string)$company['id'], (string)$company['name']);
            if(count($cPeople) > 0) {
                $people = array_merge($people, $cPeople);
            }
        }
                
        // step 3 - add/update Google.
        
        // due to the lack of a query-by-email API call in Google Shared Contacts API, we have to 
        // get all shared contacts and then manually check :(
        $gPeople = $this->googleGetSharedContacts();
        
        $peopleAdded=0;

        foreach($people as $person) {
            // does the person exist in Google already?
            $inGoogle=false;
            $matched=array('name'=>false, 'email'=>false, 'highriseid'=>false, 'email-matches'=>'', 'id'=>'');
            foreach($gPeople as $gPerson) {
            
                // check $gPerson->notes vs. $person['id']
                if($gPerson->notes == $person['id']) {
                    $inGoogle=true;
                    $matched['highriseid']=true;
                }

                /*if($person['name'] == $gPerson->name) {
                    $inGoogle=true;
                    $matched['name']=true;
                }

                foreach($person['emails'] as $email) {
                    foreach($gPerson->emailAddress as $gEmail) {
                        if($email == $gEmail) {
                            $inGoogle=true;
                            $matched['email']=true;
                            $matched['email-matches'].=$email.',';
                        }
                    }
                }*/
                
                if($inGoogle) {
                    $matched['id']=(string)$gPerson->id;
                    break;
                }
            }
            
            $dbg="In Google? ".($inGoogle?'Yes':'No')."\n";
            $dbg.="Matched Id? ".($matched['highriseid']?'Yes':'No')."\n";
            $dbg.="Matched Name? ".($matched['name']?'Yes':'No')."\n";
            $dbg.="Matched Email? ".($matched['email']?'Yes':'No')."\n";
            $dbg.="Matched Emails: ".trim($matched['email-matches'],',');
            $this->dbg("Checking ".$person['name']." (".$matched['id'].")", $dbg);
            
            if($inGoogle) {
                // TODO: get the contact and update details?
                $this->dbg("Need to update ID", $matched['id']);
                $this->googleUpdateSharedContact((string)$gPerson->id, (string)$gPerson->selfUri, (string)$gPerson->editUri, $person['name'], $person['emails'], $person['phones'], $person['company'], $person['id']);
            } else {
                $this->googleSetSharedContact($person['name'], $person['emails'], $person['phones'], $person['company'], $person['id']);
                $peopleAdded++;
            }
        }
        
		$this->logger->log('Highrise to Google: Complete', $peopleAdded.' contacts added');
        $this->dbg("Number of Contacts Added to Google", $peopleAdded);
        
        return $peopleAdded;
    }
    
    public function highriseGetCompanyPeople($companyId, $companyName) {
        $this->highriseInit();
        $hPeople = $this->highriseApi->getCompanyPeople($companyId);
        $people = array();
        
        if($hPeople != '') {
            $people = array();
            foreach($hPeople as $hPerson) {
                $name=trim((string)$hPerson->{'first-name'}." ".(string)$hPerson->{'last-name'});
                $contactData=$hPerson->{'contact-data'};
                $hEmails=$contactData->{'email-addresses'};
                
                $emails=array();
                foreach($hEmails->{'email-address'} as $hEmail) {
                    $emails[]=(string)$hEmail->address;
                }
                
                $hPhones=$contactData->{'phone-numbers'};
                $phones=array();
                foreach($hPhones->{'phone-number'} as $hPhone) {
                    $phones[]=(string)$hPhone->number;
                }
                
                $people[]=array('name'=>$name, 'emails'=>$emails, 'phones'=>$phones, 'company'=>$companyName, 'id'=>(string)$hPerson->id);
            }
        }
        
        return $people;
    }
    
    public function highriseGetCompanies($tagId="") {

        $this->highriseInit();
        $hCompanies=$this->highriseApi->getCompanies($tagId);
        
        $companies=array();
        $meta='';
        $meta.='All Highrise Companies with tag='.$this->highriseTag."\n";
        foreach($hCompanies->company as $company) {
            $meta.="\t".$company->name."\n";
            $companies[]=array('name'=>(string)$company->name, 'id'=>(string)$company->id);
        }
        $this->dbg('Highrise Companies', $meta);
        
        return $companies;
    }
    
    public function basecampGetProjects() {
        $this->basecampInit();
        
        $bProjects=$this->basecampApi->getProjects();
        $projects=array();
        $meta='';
        foreach($bProjects->project as $project) {
            $meta.="\t".$project->name."\n";
            $projects[]=array('name'=>(string)$project->name, 'company'=>(string)$project->company->name);
        }
        $this->dbg('Basecamp Projects', $meta);
        
        return $projects;        
    }
    
    public function basecampGetCompanies() {
        $this->basecampInit();
        
        $bCompanies = $this->basecampApi->getCompanies();
        $companies = array();
        foreach($bCompanies->company as $company) {
            $companies[] = array('name'=>(string)$company->name, 'id'=>(string)$company->id);
        }
        
        return $companies;
    }
    
    public function googleCreateFolder($title, $parentFolderId='') {
        $id='';
        
        try
        {
            $client=$this->googleClientAuth('writely');
            $gdata=new Zend_Gdata($client);
            $gdata->setMajorProtocolVersion(3);
            
            $doc = new DOMDocument();
            $doc->formatOutput = true;
            $entry = $doc->createElement('entry');
            $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', 'http://www.w3.org/2005/Atom');
            $doc->appendChild($entry);
            
            // add category.
            $gCat = $doc->createElement('category');
            $gCat->setAttribute('scheme', 'http://schemas.google.com/g/2005#kind');
            $gCat->setAttribute('term', 'http://schemas.google.com/docs/2007#folder');
            $entry->appendChild($gCat);
            
            // add title.
            $gTitle = $doc->createElement('title', $title);
            $entry->appendChild($gTitle);
            
            // insert entry.
            $uri = 'http://docs.google.com/feeds/default/private/full'.($parentFolderId ? '/folder%3A'.$parentFolderId.'/contents' : '');
            $entryResult = $gdata->insertEntry($doc->saveXML(), $uri);
            $this->dbg('New Entry ID',(string)$entryResult->id);
            
            $id = (string)$entryResult->id;
            
        }
        catch(Exception $e)
        {
            $this->dbg('googleCreateFolder:Exception', $e->getMessage());
        }
        
        return $id;
        
    }
    
    public function googleShareFolderWithDomain($folderId, $role) {
        $aclId = '';
        try
        {
            $client=$this->googleClientAuth('writely');
            $gdata=new Zend_Gdata($client);
            $gdata->setMajorProtocolVersion(3);
            
            $doc = new DOMDocument();
            $doc->formatOutput = true;
            $entry = $doc->createElement('entry');
            $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', 'http://www.w3.org/2005/Atom');
            $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gAcl', 'http://schemas.google.com/acl/2007');
            $doc->appendChild($entry);
            
            // add category.
            $gCat = $doc->createElement('category');
            $gCat->setAttribute('scheme', 'http://schemas.google.com/g/2005#kind');
            $gCat->setAttribute('term', 'http://schemas.google.com/acl/2007#accessRule');
            $entry->appendChild($gCat);
            
            // add role.
            $gRole = $doc->createElement('gAcl:role');
            $gRole->setAttribute('value', $role);
            $entry->appendChild($gRole);
            
            // add scope.
            $gScope = $doc->createElement('gAcl:scope');
            $gScope->setAttribute('type', 'domain');
            $gScope->setAttribute('value', $this->googleDomain);
            $entry->appendChild($gScope);
            
            // insert entry.
            $entryResult = $gdata->insertEntry($doc->saveXML(), 'http://docs.google.com/feeds/default/private/full/folder%3A'.$folderId.'/acl');
            $this->dbg('New Entry ID',(string)$entryResult->id);
            
            $aclId = (string)$entryResult->id;
        }
        catch(Exception $e)
        {
            $this->dbg('googleShareFolderWithDomain:Exception', $e->getMessage());
        }
        
        return $aclId;
    }
    
    public function googleDeleteAllSharedContacts() {
	
		$this->logger->log('Google Contacts Delete All: Start');
	
        $peopleDeleted=0;
        try
        {
            // get all of the contacts.
            //$this->setDebug(false);
            $contacts=$this->googleGetSharedContacts();
            //$this->setDebug(true);
            
            $client=$this->googleClientAuth('cp');
            $client->setHeaders('If-Match: *');
            $gdata=new Zend_Gdata($client);
            $gdata->setMajorProtocolVersion(3);
            
            // delete them one-by-one.
            foreach($contacts as $contact) {
                $gdata->delete($contact->id);
                $peopleDeleted++;
            }
        }
        catch(Exception $e) {
            die("googleDeleteAllSharedContacts:Exception ({$peopleDeleted}): ".$e->getMessage());
        }
        
		$this->logger->log('Google Contacts Delete All: Complete', $peopleDeleted.' contacts deleted');
        $this->dbg("Number of Contacts Deleted from Google", $peopleDeleted);
        
        return $peopleDeleted;        
    }
    
    public function googleParseFolderIdFromUri($uri) {
        return end(explode(':',urldecode($uri)));
    }
    
    public function googleSetSharedContact($title, $email, $phone, $org, $highriseId) {
        try
        {
            $client=$this->googleClientAuth('cp');
            $gdata=new Zend_Gdata($client);
            $gdata->setMajorProtocolVersion(3);
            
            $doc = new DOMDocument();
            $doc->formatOutput = true;
            $entry = $doc->createElement('atom:entry');
            $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom');
            $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
            $doc->appendChild($entry);
            
            // add category.
            $gCat = $doc->createElement('atom:category');
            $gCat->setAttribute('scheme', 'http://schemas.google.com/g/2005#kind');
            $gCat->setAttribute('term', 'http://schemas.google.com/contact/2008#contact');
            $entry->appendChild($gCat);
            
            // add title.
            $gTitle = $doc->createElement('atom:title', $title);
            $gTitle->setAttribute('type','text');
            $entry->appendChild($gTitle);
            
            // add notes - this is where we store the Highrise Contact Id (used for UPDATES).
            $gNotes = $doc->createElement('atom:content', $highriseId);
            $gNotes->setAttribute('type', 'text');
            $entry->appendChild($gNotes);
            
            // add the fullname.
            $gName = $doc->createElement('gd:name');
            $entry->appendChild($gName);
            $gFullname = $doc->createElement('gd:fullName', $title);
            $gName->appendChild($gFullname);
            
            // add email.
            if(is_array($email)) {
                foreach($email as $email_addr) {
                    $gEmail = $doc->createElement('gd:email');
                    $gEmail->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
                    $gEmail->setAttribute('address', $email_addr);
                    $entry->appendChild($gEmail);                    
                }
            } else {
                $gEmail = $doc->createElement('gd:email');
                $gEmail->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
                $gEmail->setAttribute('address', $email);
                $entry->appendChild($gEmail);
            }
            
            // add phone.
            if(is_array($phone)) {
                foreach($phone as $phone_nmbr) {
                    $gPhone = $doc->createElement('gd:phoneNumber', $phone_nmbr);
                    $gPhone->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
                    //$gPhone->setAttribute('primary', 'true');
                    $entry->appendChild($gPhone);                
                }
            } else {
                $gPhone = $doc->createElement('gd:phoneNumber', $phone);
                $gPhone->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
                $gPhone->setAttribute('primary', 'true');
                $entry->appendChild($gPhone);
            }
            
            // add org.
            $gOrg = $doc->createElement('gd:organization');
            $gOrg->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
            $gOrgName = $doc->createElement('gd:orgName', $org);
            $gOrg->appendChild($gOrgName);
            $entry->appendChild($gOrg);
                        
            // insert entry.
            $entryResult = $gdata->insertEntry($doc->saveXML(), 'http://www.google.com/m8/feeds/contacts/'.$this->googleDomain.'/full');
            $this->dbg('New Entry ID',(string)$entryResult->id);
        }
        catch(Exception $e)
        {
            $this->dbg('googleSetSharedContact:Exception', $e->getMessage());
        }
     }
     
    public function googleUpdateSharedContact($id, $selfUri, $editUri, $title, $email, $phone, $org, $highriseId) {
        try
        {
            $client=$this->googleClientAuth('cp');
            $client->setHeaders('If-Match: *');
            $gdata=new Zend_Gdata($client);
            $gdata->setMajorProtocolVersion(3);
            
            // get the existing contact.
            $query = new Zend_Gdata_Query($id);
            $entry = $gdata->getEntry($query);
            $xml = simplexml_load_string($entry->getXML());
            
            // update title.
            $xnl->title = $title;            
            
            // update the fullname.
            $xml->name->fullName = $title;
            
            // change email.
            if(is_array($email)) {

            }
            
            // change phone.
            if(is_array($phone)) {

            }
            
            // change org.
            $xml->organization->orgName = $org;
                        
            // update entry.
            $entryResult = $gdata->updateEntry($xml->saveXML(), $entry->getEditLink()->href);
        }
        catch(Exception $e)
        {
            $this->dbg('googleUpdateSharedContact:Exception', $e->getMessage());
        }
    }
     
    public function googleGetSharedContacts() {
        
        $client=$this->googleClientAuth('cp');
        $gdata=new Zend_Gdata($client);
        $gdata->setMajorProtocolVersion(3);
        
        $query = new Zend_Gdata_Query('http://www.google.com/m8/feeds/contacts/'.$this->googleDomain.'/full');
        $feed = $gdata->getFeed($query);
                
        $this->dbg('Feed Title ', (string)$feed->title);
        $this->dbg('Contacts Found', (string)$feed->totalResults);  

        $x=0;
        
        $results=array();
        foreach($feed as $contact) {
        
            $this->dbg('Contact XML', $contact->getXML());
                
            $xml = simplexml_load_string($contact->getXML());
            $obj = new stdClass;
            $obj->name = (string)$contact->title;
            $obj->orgName = (string)$xml->organization->orgName;
            $obj->id = (string)$contact->id;
            $obj->notes = (string)$contact->content;
            
            // get the uris.
            foreach($contact->{'link'} as $link) {
                if((string)$link->rel == 'edit') {
                    $obj->editUri = (string)$link->href;
                }
                if((string)$link->rel == 'self') {
                    $obj->selfUri = (string)$link->href;
                }
            }
            
            $obj->emailAddress=array();
            foreach($xml->email as $e) {
                $obj->emailAddress[] = (string)$e['address'];
            }
            
            $obj->phoneNumber=array();
            foreach($xml->phoneNumber as $p) {
                $obj->phoneNumber[] = (string)$p;
            }
            
            $obj->website=array();
            foreach($xml->website as $w) {
                $obj->website[] = (string)$w['href'];
            }
            
            $results[] = $obj;
            $x++;
          }
          
          foreach($results as $r) {
            $meta='';
                        
            $meta.="Contact Title: ".$r->name."\n";
            $meta.="Email: ".@join(', ',$r->emailAddress)."\n";
            $meta.="Phone: ".@join(', ',$r->phoneNumber)."\n";
            $meta.="Website: ".@join(', ',$r->website)."\n";
            $meta.="Content: ".$r->notes."\n";
            $meta.="Edit URI: ".$r->editUri."\n";
            $meta.="Self URI: ".$r->selfUri."\n";
            
            $this->dbg('Contact',$meta);
        }
        
        return $results;
    }
    
    public function googleGetUserContacts() {
        
        $client=$this->googleClientAuth('cp');
        $gdata=new Zend_Gdata($client);
        $gdata->setMajorProtocolVersion(3);
        
        $query = new Zend_Gdata_Query('http://www.google.com/m8/feeds/contacts/default/full');
        $feed = $gdata->getFeed($query);
        
        $this->dbg('Feed Title ', (string)$feed->title);
        $this->dbg('Contacts Found', (string)$feed->totalResults);        
    }
    
    public function googleGetFolders() {
    
        $client=$this->googleClientAuth(Zend_Gdata_Docs::AUTH_SERVICE_NAME);
    
        $docs = new Zend_Gdata_Docs($client);
        $feed = $docs->getDocumentListFeed(CONTACT_SYNC_GOOGLE_DOCS_FEED);
        
        $this->dbg("Document Count",(string)$feed->totalResults);
        $this->dbg("Feed Title",(string)$feed->title);
        
        $folders=array();
        foreach($feed as $doc) {
            $meta="";
            
            $meta.="Title: ".$doc->title."\n";
            $meta.="Id: ".$doc->id."\n";
            
            foreach($doc->category as $cat) {
                $meta.="Cat Label: ".$cat->label."\n";
                if($cat->label == CONTACT_SYNC_GOOGLE_FOLDER_LABEL) {
                    // store all of the labels.
                    $labels=array();
                    foreach($doc->category as $label) {
                        $labels[]=(string)$label->label;
                    }
                    $folders[]=array('id'=>(string)$doc->id, 'title'=>(string)$doc->title, 'labels'=>$labels);
                }
            }
            
            $this->dbg("Doc Meta", $meta);
        }

        $this->dbg("Folders", $folders);
        
        return ( $folders );
    }
        
    private function highriseInit() {
        if(!$this->highriseApi) {
            $this->highriseApi = new HighriseAPI();
            $this->highriseApi->setUrl($this->highriseUrl);
            $this->highriseApi->setApiKey($this->highriseApiKey);
            $this->highriseApi->setDebug($this->dbgEn);
        }
    }
    
    private function basecampInit() {
        if(!$this->basecampApi) {
            $this->basecampApi = new BasecampAPI();
            $this->basecampApi->setUrl($this->basecampUrl);
            $this->basecampApi->setApiKey($this->basecampApiKey);
            $this->basecampApi->setDebug($this->dbgEn);
        }
    }

	private function basecampFormsInit() {
		if(!$this->basecampForms) {
			$cookieFile = tempnam("/tmp", "CURLCOOKIE");
			$this->basecampForms = new BasecampForms();
			$this->basecampForms->setAccountName($this->basecampAccount);
			$this->basecampForms->setUsername($this->basecampUsername);
			$this->basecampForms->setPassword($this->basecampPassword);
			$this->basecampForms->setCookieFile($cookieFile);
			$this->basecampForms->setUrl($this->basecampUrl);
		}
	}
	
	private function highriseFormsInit() {
		if(!$this->highriseForms) {
			$cookieFile = tempnam("/tmp", "HIGHRISECURLCOOKIE");
			$this->highriseForms = new HighriseForms();
			$this->highriseForms->setAccountName($this->highriseAccount);
			$this->highriseForms->setUsername($this->highriseUsername);
			$this->highriseForms->setPassword($this->highrisePassword);
			$this->highriseForms->setCookieFile($cookieFile);
			$this->basecampForms->setUrl($this->highriseUrl);
		}
	}

    private function googleClientAuth($service='') {
        try
        {
            $client = Zend_Gdata_ClientLogin::getHttpClient($this->googleUser, $this->googlePwd, $service);
        }
        catch (Zend_Gdata_App_CaptchaRequiredException $cre)
        {
            echo "URL of CAPTCHA image; ".$cre->getCaptchaUrl()."\n";
            echo "Token ID: ".$cre->getCaptchaToken()."\n";
        }
        catch (Zend_Gdata_App_AuthException $ae)
        {
            echo "Problem authenticating: ".$ae->exception()."\n";
        }
        
        return $client;
    }

	private function _writeMappings($mappingArray) {
		$baseInfo = pathinfo(__FILE__);
		$basePath = $baseInfo['dirname'];
		$file = $basePath.'/../mappings';
		
		$data = serialize($mappingArray);
		
		$fh = fopen($file, 'w');
		fwrite($fh, $data);
		fclose($fh);
	}
    
	private function _getMappings() {
		$baseInfo = pathinfo(__FILE__);
		$basePath = $baseInfo['dirname'];
		$file = $basePath.'/../mappings';
		
		if(filesize($file) > 0) {
			$fh = fopen($file, 'r');
			$data = fread($fh, filesize($file));
			fclose($fh);
			
			$data = unserialize($data);
		} else {
			$data = array();
		}
		
		return $data;
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