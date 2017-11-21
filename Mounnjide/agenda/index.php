<?php
require_once '../../../google-api-php-client-2.2.0/vendor/autoload.php';
session_start();
$client = new Google_Client();
$client->setAuthConfig('client_secret.json');
//$client->addScope(Google_Service_Drive::DRIVE_METADATA_READONLY);
$client->addScope(array("https://www.googleapis.com/auth/calendar"));
if(isset($_GET['out'])){
    unset($_SESSION['access_token']);
    $client->revokeToken();
}
//v�rifie que le token n'ets pas exp�r�
//if ($client->isAccessTokenExpired()) {
//    unset($_SESSION['access_token']);
//}
//pour supprimer les droits https://myaccount.google.com/permissions?pli=1
//print_r($_SESSION['access_token']);
//$_SESSION['access_token'] = array("access_token"=>"ya29.GlvYBAAizcoG4SH14m1nTmBnZXqgabVmkNJyd0d1wFBMfDOTDmJvHWaD86CRJjFXRSY0SEiTfZjpvpWGzFAAkTfuhCICZ_hznkuCkDtSI5OIlCAz2M4aPOwZp3jS","token_type"=>"Bearer", "expires_in"=>"3599", "created"=>1506954203);
if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
	$client->setAccessToken($_SESSION['access_token']);
	$cal_service = new Google_Service_Calendar($client);
	//
	try {
	    
	    switch ($_GET['q']) {
	        case 'all':
	            //Pour la liste compl�te des calendrier de la personne
	            $r = getAllCalendar($cal_service);
        	        break;	        
	        case 'info':
	            //Pour les infos d'un calendrier
	            $calendar = $cal_service->calendarList->get($_GET['id']);
	            $r = getCalendarInfo($calendar, $cal_service);
	            break;
	        case 'present':
	            //Pour ajouter un pr�sent
	            $r = insertPresent($cal_service, $_GET['id']);
	            break;
	        default:
	            $r = "rien";
	           break;
				
	    }
	    	echo json_encode($r);    
	} catch (Exception $e) {
	    echo 'ERREUR : ',  $e->getMessage(), "\n";
	}
    //
} else {
	$redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . '/THYP_17-18/Mounnjide/agenda/callback.php';
	//echo $redirect_uri ; //pour afficher
	header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
}
 function getAllCalendar($service)
{
    //Pour la liste compl�te des calendrier de la personne
    $calendarList = 	$service->calendarList->listCalendarList();    
    while(true) {
        foreach ($calendarList->getItems() as $calendarListEntry) {
            $calendars[] = getCalendarInfo($calendarListEntry, $service);
        }
        $pageToken = $calendarList->getNextPageToken();
        if ($pageToken) {
            $optParams = array('pageToken' => $pageToken);
            $calendarList = $service->calendarList->listCalendarList($optParams);
        } else {
            break;
        }
    }
    return $calendars;
    
}
    
function getCalendarInfo($cal, $service)
{
      if(isset($_GET['startdate']) && isset($_GET['enddate']) ){
      $optParams = array(
      "timeMin" => $_GET['startdate'],
      "timeMax" => $_GET['enddate']
      );
      
           $events = $service->events->listEvents($cal->getId(), $optParams);
     $r = array();
     foreach ($events->getItems() as $event) {
        
        $info = array();
        $info["recid"] = $event->getId();
        $info["title"] = $event->summary;
        $info["summary"]=$cal->getSummary();
        $info["id"]=$cal->getId();
        $info["access"]=$cal->getAccessRole();
        $info["description"]=$cal->getDescription();
        $info["location"]=$cal->getLocation();
        array_push($r , $info);
               
      }  
    return $r;
  }
  else{
        $optParams = array(
          "timeMin" => "2017/10/01",
          "timeMax" => "2017/11/21"
          );
          
          $r = array("summary"=>$cal->getSummary()
        ,"id"=>$cal->getId()
        ,"access"=>$cal->getAccessRole()
        ,"description"=>$cal->getDescription()
        ,"location"=>$cal->getLocation()
    );
        
    //r�cup�re les roles
    if($r["access"]!="writer" && $r["access"]!="reader"){
        $roles = getListeAcl($r["id"], $service);
        $r["roles"]=$roles;
    }
    
    return $r;
  }
    
    
}
function getListeAcl($idCal,$service)
{
    $acls =array();
    $acl = $service->acl->listAcl($idCal);
    foreach ($acl->getItems() as $rule) {
        $acls[]=getAclInfo($rule);
    }
    return $acls;
}
function getAclInfo($acl)
{
    $r = array("id"=>$acl->getId()
        ,"role"=>$acl->getRole()
    );
    return $r;
}
function insertPresent($service, $calendarId, $desc, $mails){
    //merci � https://developers.google.com/google-apps/calendar/v3/reference/events/insert
    $date = new DateTime();
    $dateDeb = $date->format('Y-m-d').'T'.$date->format('H:i:s');//'2017-10-17T14:30:00'
    $date->add(new DateInterval('PT60S'));
    $dateFin = $date->format('Y-m-d').'T'.$date->format('H:i:s');
    echo $dateDeb." - ".$dateFin;
    $attendees = array();
    foreach ($mails as $m) {
        $attendees[]=array('email'=>$m);
    }
    /*
     * array(
            array('email' => 'lpage@example.com'),
            array('email' => 'sbrin@example.com'),
        )
     */
    //pour la g�olocalisation merci � https://stackoverflow.com/questions/409999/getting-the-location-from-an-ip-address
    
    $event = new Google_Service_Calendar_Event(array(
        'summary' => 'Pr�sent',
        'location' => 'Paris 8',
        'description' => $desc,
        'start' => array(
            'dateTime' => $dateDeb,
            'timeZone' => 'Europe/Paris',
            
        ),
        'end' => array(
            'dateTime' => $dateFin,
            'timeZone' => 'Europe/Paris',
        ),
        'attendees' => $attendees,
    ));
    //print_r($event);
    $event = $service->events->insert($calendarId, $event);
    return array('message'=>'Event created', 'event'=>$event);
    
}
?>