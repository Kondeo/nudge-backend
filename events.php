<?php

 // Allow from any origin
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');    // cache for 1 day
    }

    // Access-Control headers are received during OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");         

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

        exit(0);
    }

   

include 'Slim/Slim.php';

$app = new Slim();

$app->get('/events', 'getEvents');
$app->get('/events/user/:id', 'getUserEvents');
$app->get('/events/:id', 'getEvent');
$app->post('/events', 'newEvent');
$app->put('/events/:id', 'updateEvent');
$app->delete('/events/:id', 'deleteEvent');

$app->run();

function getEvents() {
    $request = Slim::getInstance()->request();
    $requestjson = json_decode($request->getBody());

    //Get All Events
    $sql = "SELECT

        *

        FROM events

        WHERE public=1

        ORDER BY date LIMIT 200";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $response = $stmt->fetchObject();
        $db = null;
        echo json_encode($response);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
        exit;
    }
}

function getUserEvents($id) {
    $request = Slim::getInstance()->request();
    $requestjson = json_decode($request->getBody());

    //Check if username exists
    $sql = "SELECT

        *

        FROM events
        WHERE host_id=:host_id AND public=1
        ORDER BY date
        LIMIT 200";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("host_id", $id);
        $stmt->execute();
        $usercheck = $stmt->fetchObject();
        $db = null;
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
        exit;
    }
}

function getMyEvents() {
    $request = Slim::getInstance()->request();
    $requestjson = json_decode($request->getBody());

    $sql = "SELECT

        user_id

        FROM sessions WHERE token=:token LIMIT 1";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("token", $requestjson->session_token);
        $stmt->execute();
        $session = $stmt->fetchObject();
        $db = null;
        //echo json_encode($user);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    if(!isset($session->id)){
        echo '{"error":{"text":"Token is not valid","errorid":"12"}}';
        exit;
    }

    //Check if username exists
    $sql = "SELECT

        *

        FROM events
        WHERE host_id=:host_id
        ORDER BY date
        LIMIT 200";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("host_id", $session->user_id);
        $stmt->execute();
        $myevents = $stmt->fetchObject();
        echo json_encode($myevents);
        $db = null;
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
        exit;
    }
}

function getInvitedEvents() {
    $request = Slim::getInstance()->request();
    $requestjson = json_decode($request->getBody());

    $sql = "SELECT

        user_id

        FROM sessions WHERE token=:token LIMIT 1";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("token", $requestjson->session_token);
        $stmt->execute();
        $session = $stmt->fetchObject();
        $db = null;
        //echo json_encode($user);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    if(!isset($session->id)){
        echo '{"error":{"text":"Token is not valid","errorid":"12"}}';
        exit;
    }

    //Check if username exists
    $sql = "SELECT

        *

        FROM event_attendees
        WHERE attendee_id=:attendee_id";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("attendee_id", $session->user_id);
        $stmt->execute();
        $eventinvites = $stmt->fetchObject();
        $db = null;
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
        exit;
    }

    $increment = 0;
    foreach($eventinvites as $invite) {

        $sql = "SELECT

            *

            FROM events
            WHERE id=:id";

        try {
            $db = getConnection();
            $stmt = $db->prepare($sql);
            $stmt->bindParam("id", $invite->event_id);
            $stmt->execute();
            $eventinvites = $stmt->fetchObject();
            $db = null;
        } catch(PDOException $e) {
            echo '{"error":{"text":'. $e->getMessage() .'}}';
            exit;
        }

        $invitedevents[$increment] = $eventinvites;
        $increment = $increment + 1;
    }

    echo json_encode($invitedevents);
}

function getEvent($id) {
    $request = Slim::getInstance()->request();
    $requestjson = json_decode($request->getBody());

    $sql = "SELECT

        *

        FROM events WHERE id=:id LIMIT 1";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("id", $id);
        $stmt->execute();
        $response = $stmt->fetchObject();
        $db = null;
        echo json_encode($response);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}

function updateEvent($id) {
    $request = Slim::getInstance()->request();
    $body = $request->getBody();
    $requestjson = json_decode($body);

    $sql = "SELECT

        user_id

        FROM sessions WHERE key=:key LIMIT 1";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("key", $requestjson->session_token);
        $stmt->execute();
        $session = $stmt->fetchObject();
        $db = null;
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    if(!isset($session->id)){
        echo '{"error":{"text":"Token is not valid","errorid":"12"}}';
        exit;
    }

    $sql = "UPDATE events 
    SET 
    
    this=:this

    WHERE id=:id AND user_id=:user_id";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);

        $stmt->bindParam("id", $id);
        $stmt->bindParam("user_id", $session->user_id);
        
        $stmt->execute();
        $db = null;
        echo json_encode($requestjson);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}

function deleteEvent($id) {
    $request = Slim::getInstance()->request();
    $body = $request->getBody();
    $requestjson = json_decode($body);

    $sql = "SELECT

        user_id

        FROM sessions WHERE key=:key LIMIT 1";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("key", $requestjson->session_token);
        $stmt->execute();
        $session = $stmt->fetchObject();
        $db = null;
        echo json_encode($user);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    if(!isset($session->id)){
        echo '{"error":{"text":"Token is not valid","errorid":"12"}}';
        exit;
    }

    $sql = "DELETE FROM events WHERE id=:id AND host_id=:host_id LIMIT 1";
    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("id", $id);
        $stmt->bindParam("host_id", $session->user_id);
        $stmt->execute();
        $db = null;
        echo json_encode($user);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}

function findByParameter() {

    //INCOMPLETE FUNCTION

    $request = Slim::getInstance()->request();
    $requestparams = json_decode($request->getBody());
    //$requestparams->FirstName = $FirstName;
    //$requestparams->LastName = $LastName;
    //$requestparams->Address1 = $Address1;
    $FirstName = $requestparams->FirstName;
    $LastName = $requestparams->LastName;
    $Company = $requestparams->Company;
    $Address1 = $requestparams->Address1;
    $Email1 = $requestparams->Email1;
    $Phone1 = $requestparams->Phone1;
    $City = $requestparams->City;

    // Keep track of received parameters
    $paramsreceived = 0;

    // Check parameters for activity. If not active, assign wildcard for search.
    // Additionally, add to the received counter if active.
    if(isset($requestparams->FirstName)) {
        $FirstName = "%".$FirstName."%";
        $paramsreceived = $paramsreceived + 1;
    } else {
        $FirstName = "%";
    }
    if(isset($requestparams->LastName)) {
        $LastName = "%".$LastName."%";
        $paramsreceived = $paramsreceived + 1;
    } else {
        $LastName = "%";
    }
    if(isset($requestparams->Company)) {
        $Company = "%".$Company."%";
        $paramsreceived = $paramsreceived + 1;
    } else {
        $Company = "%";
    }
    if(isset($requestparams->Address1)) {
        $Address1 = "%".$Address1."%";
        $paramsreceived = $paramsreceived + 1;
    } else {
        $Address1 = "%";
    }
    if(isset($requestparams->Email1)) {
        $Email1 = "%".$Email1."%";
        $paramsreceived = $paramsreceived + 1;
    } else {
        $Email1 = "%";
    }
    if(isset($requestparams->Phone1)) {
        $Phone1 = "%".$Phone1."%";
        $paramsreceived = $paramsreceived + 1;
    } else {
        $Phone1 = "%";
    }
    if(isset($requestparams->City)) {
        $City = "%".$City."%";
        $paramsreceived = $paramsreceived + 1;
    } else {
        $City = "%";
    }

    // If no parameters are active, throw an error and exit.
    // If this were not here, the entire database would be returned when no parameters were entered.
    if($paramsreceived = 0){
        echo '{"error":{"text":'. $e->getMessage() .'}}';
        exit;
    }

    $sql = "SELECT * FROM contacts WHERE
        FirstName LIKE :firstname AND
        LastName LIKE :lastname AND
        Company LIKE :company AND
        Address1 LIKE :address1 AND
        Email LIKE :email1 AND
        HomePhone LIKE :phone1 AND
        City LIKE :city 
    ORDER BY LastName
    LIMIT 200";
    

    
    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        //$query = "%".$query."%";
        
        $stmt->bindParam("firstname", $FirstName);
        $stmt->bindParam("lastname", $LastName);
        $stmt->bindParam("company", $Company);
        $stmt->bindParam("address1", $Address1);
        $stmt->bindParam("email1", $Email1);
        $stmt->bindParam("phone1", $Phone1);
        $stmt->bindParam("city", $City);

        $stmt->execute();
        $contacts = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
        echo '{"contacts": ' . json_encode($contacts) . '}';
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}

function getConnection() {
    $dbhost="kondeo.com";
    $dbuser="nudgeit";
    $dbpass="nudgeit";
    $dbname="nudgeit";
    $dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);  
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $dbh;
}

?>
