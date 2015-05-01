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

require 'ProtectedDocs/connection.php';

$app = new Slim();

$app->get('/events', 'getEvents');
$app->get('/events/user/:id', 'getUserEvents');
$app->post('/events/me', 'getMyEvents');
$app->get('/events/:id', 'getEvent');
$app->post('/events', 'newEvent');
$app->put('/events/:id', 'updateEvent');
$app->delete('/events/:id', 'deleteEvent');

$app->post('/events/rsvp/request', 'requestRSVP');
$app->post('/events/rsvp/invite', 'inviteRSVP');
$app->post('/events/rsvp/accept', 'acceptRSVP');
$app->post('/events/rsvp/attend', 'acceptRSVPInvite');
$app->post('/events/rsvp/cancel', 'cancelRSVP');
$app->post('/events/rsvp', 'getRSVPs');

$app->run();

//Get all events
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

//Get events hosted by a specific user id: $id
function getUserEvents($id) {
    $request = Slim::getInstance()->request();
    $requestjson = json_decode($request->getBody());

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

//Get all events that are hosted by the current user's id
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
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    if(!isset($session->user_id)){
        echo '{"error":{"text":"Token is not valid","errorid":"12"}}';
        exit;
    }

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
        $myevents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($myevents);
        $db = null;
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
        exit;
    }
}

//Get all events the user was invited to
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
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    if(!isset($session->user_id)){
        echo '{"error":{"text":"Token is not valid","errorid":"12"}}';
        exit;
    }

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

//Get a particular events details based on an event id: $id
function getEvent($id) {
    $request = Slim::getInstance()->request();
    $session_token = $request->params('session_token');

    $sql = "SELECT

        user_id

        FROM sessions WHERE token=:token LIMIT 1";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("token", $session_token);
        $stmt->execute();
        $session = $stmt->fetchObject();
        $db = null;
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    if(!isset($session->user_id)){
        echo '{"error":{"text":"Token is not valid","errorid":"12"}}';
        exit;
    }

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
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    if($id == $response->host_id){
        $rsvp_status = 6;
    } else {

        $sql = "SELECT status FROM event_attendees

        WHERE event_id=:event_id AND attendee_id=:myuserid

        ";

        try {
            $db = getConnection();
            $stmt = $db->prepare($sql);

            $stmt->bindParam("myuserid", $session->user_id);
            $stmt->bindParam("event_id", $id);

            $stmt->execute();
            $db = null;
            $rsvp_status = $stmt->fetchObject();
        } catch(PDOException $e) {
            echo '{"error":{"text":'. $e->getMessage() .'}}';
        }

    }

    //Friend status is for what info to get
    //Friend status return is what relationship
    //The user actually has. 0 = none 1 = requested 2 = requestme 5 = friends

    if($rsvp_status == false){
        $rsvp_status = "0";
    }

    if(is_object($rsvp_status)){
        $rsvp_status = $rsvp_status->status;
        //$friend_status_return = $friend_status;
    }

    if($rsvp_status == 0 || $rsvp_status == 1){

        $sql = "SELECT

        *

        FROM events WHERE id=:event_id";

    } else if($rsvp_status == 2 || $rsvp_status == 5 || $rsvp_status == 6){

        $sql = "SELECT

        *

        FROM events WHERE id=:event_id";

    } else {
        echo "RSVPCHECK ERROR";
        var_dump($rsvp_status);
        break;
    }

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("event_id", $id);
        $stmt->execute();
        $user = $stmt->fetchObject();
        $user->start_time = date('Y-m-d\TH:i:s', strtotime($user->start_time));
        $user->end_time = date('Y-m-d\TH:i:s', strtotime($user->end_time));
        $db = null;
        $user->status = $rsvp_status;
        echo json_encode($user);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

}

//Create a new event
function newEvent() {
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
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    if(!isset($session->user_id)){
        echo '{"error":{"text":"Token is not valid","errorid":"12"}}';
        exit;
    }

    $sql = "INSERT INTO events

        (host_id, public, name, category,
            description, start_time, end_time)

        VALUES

        (:host_id, :public, :name, :category,
            :description, :start_time, :end_time)";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("host_id", $session->user_id);
        $stmt->bindParam("public", $requestjson->public);
        $stmt->bindParam("name", $requestjson->name);
        $stmt->bindParam("category", $requestjson->category);
        $stmt->bindParam("description", $requestjson->description);
        $stmt->bindParam("start_time", $requestjson->start_time);
        $stmt->bindParam("end_time", $requestjson->end_time);
        $stmt->execute();
        $requestjson->id = $db->lastInsertId();
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    $rsvp_status = 6;

    $sql = "INSERT INTO event_attendees

    (event_id, attendee_id, status)
    VALUES
    (:event_id, :myuserid, :status)

    ";

    try {
        $stmt = $db->prepare($sql);

        $stmt->bindParam("event_id", $requestjson->id);
        $stmt->bindParam("myuserid", $session->user_id);
        $stmt->bindParam("status", $rsvp_status);

        $stmt->execute();
        $db = null;
        echo json_encode($requestjson);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}

//Update an existing event
function updateEvent($id) {
    $request = Slim::getInstance()->request();
    $body = $request->getBody();
    $requestjson = json_decode($body);

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
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    if(!isset($session->user_id)){
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
        $requestjson->id = $id;
        echo json_encode($requestjson);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}

//Delete an existing event
function deleteEvent($id) {
    $request = Slim::getInstance()->request();
    $body = $request->getBody();
    $requestjson = json_decode($body);

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
        echo json_encode($user);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    if(!isset($session->user_id)){
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

//Request to RSVP to an event
function requestRSVP(){
    $request = Slim::getInstance()->request();
    $body = $request->getBody();
    $requestjson = json_decode($body);

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
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    if(!isset($session->user_id)){
        echo '{"error":{"text":"Token is not valid","errorid":"12"}}';
        exit;
    }

    $sql = "SELECT status FROM event_attendees

    WHERE attendee_id=:myuserid AND event_id=:event_id

    ";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);

        $stmt->bindParam("myuserid", $session->user_id);
        $stmt->bindParam("event_id", $requestjson->event_id);

        $stmt->execute();
        $db = null;
        $rsvp_status = $stmt->fetchObject();
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }


    if($rsvp_status == false){
        $rsvp_status = 1;

        $sql = "INSERT INTO event_attendees

        (event_id, attendee_id, status)
        VALUES
        (:event_id, :myuserid, :status)

        ";

        try {
            $db = getConnection();
            $stmt = $db->prepare($sql);

            $stmt->bindParam("event_id", $requestjson->event_id);
            $stmt->bindParam("myuserid", $session->user_id);
            $stmt->bindParam("status", $rsvp_status);

            $stmt->execute();
            $db = null;
            echo json_encode($requestjson);
        } catch(PDOException $e) {
            echo '{"error":{"text":'. $e->getMessage() .'}}';
        }
    } else {
        echo '{"error":{"text":"Event already added","errorid":"233"}}';
    }
}

//Invite a user to an event
function inviteRSVP(){
  $request = Slim::getInstance()->request();
  $body = $request->getBody();
  $requestjson = json_decode($body);

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
  } catch(PDOException $e) {
      echo '{"error":{"text":'. $e->getMessage() .'}}';
  }

  if(!isset($session->user_id)){
      echo '{"error":{"text":"Token is not valid","errorid":"12"}}';
      exit;
  }

  $sql = "SELECT status FROM event_attendees

  WHERE attendee_id=:myuserid AND event_id=:event_id

  ";

  try {
      $db = getConnection();
      $stmt = $db->prepare($sql);

      $stmt->bindParam("friendid", $session->user_id);
      $stmt->bindParam("event_id", $requestjson->event_id);

      $stmt->execute();
      $db = null;
      $rsvp_status = $stmt->fetchObject();
  } catch(PDOException $e) {
      echo '{"error":{"text":'. $e->getMessage() .'}}';
  }

  if(rsvp_status != false && rsvp_status == 6){
    $sql = "SELECT status FROM event_attendees

    WHERE attendee_id=:friendid AND event_id=:event_id

    ";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);

        $stmt->bindParam("friendid", $requestjson->friend_id);
        $stmt->bindParam("event_id", $requestjson->event_id);

        $stmt->execute();
        $db = null;
        $rsvp_status = $stmt->fetchObject();
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    if($rsvp_status == false){
        $rsvp_status = 2;

        $sql = "INSERT INTO event_attendees

        (event_id, attendee_id, status)
        VALUES
        (:event_id, :friendid, :status)

        ";

        try {
            $db = getConnection();
            $stmt = $db->prepare($sql);

            $stmt->bindParam("event_id", $requestjson->event_id);
            $stmt->bindParam("myuserid", $requestjson->friend_id);
            $stmt->bindParam("status", $rsvp_status);

            $stmt->execute();
            $db = null;
            echo json_encode($requestjson);
        } catch(PDOException $e) {
            echo '{"error":{"text":'. $e->getMessage() .'}}';
        }
    } else {
        echo '{"error":{"text":"Event already added","errorid":"233"}}';
    }
  }
}

//Accept request to attend users event
function acceptRSVP(){
  $request = Slim::getInstance()->request();
  $body = $request->getBody();
  $requestjson = json_decode($body);

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
  } catch(PDOException $e) {
      echo '{"error":{"text":'. $e->getMessage() .'}}';
  }

  if(!isset($session->user_id)){
      echo '{"error":{"text":"Token is not valid","errorid":"12"}}';
      exit;
  }

  $sql = "SELECT status FROM event_attendees

  WHERE attendee_id=:myuserid AND event_id=:event_id

  ";

  try {
      $db = getConnection();
      $stmt = $db->prepare($sql);

      $stmt->bindParam("friendid", $session->user_id);
      $stmt->bindParam("event_id", $requestjson->event_id);

      $stmt->execute();
      $db = null;
      $rsvp_status = $stmt->fetchObject();
  } catch(PDOException $e) {
      echo '{"error":{"text":'. $e->getMessage() .'}}';
  }

  if($rsvp_status != false && $rsvp_status == 6){
    $rsvp_status = 5;
    $sql = "UPDATE event_attendees SET status=:rsvp_status

    WHERE attendee_id=:attendee_id AND event_id=:event_id

    ";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);

        $stmt->bindParam("attendee_id", $requestjson->attendee_id);
        $stmt->bindParam("event_id", $requestjson->event_id);
        $stmt->bindParam("rsvp_status", $rsvp_status);

        $stmt->execute();
        $db = null;
        $rsvp_status = $stmt->fetchObject();
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
  }

}

//Accept an invitation to attend an event
function acceptRSVPInvite(){
  $request = Slim::getInstance()->request();
  $body = $request->getBody();
  $requestjson = json_decode($body);

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
  } catch(PDOException $e) {
      echo '{"error":{"text":'. $e->getMessage() .'}}';
  }

  if(!isset($session->user_id)){
      echo '{"error":{"text":"Token is not valid","errorid":"12"}}';
      exit;
  }

  $rsvp_status = 5;
  $sql = "UPDATE event_attendees SET status=:rsvp_status

  WHERE attendee_id=:myuserid AND event_id=:event_id AND status=2

  ";

  try {
      $db = getConnection();
      $stmt = $db->prepare($sql);

      $stmt->bindParam("attendee_id", $session->user_id);
      $stmt->bindParam("event_id", $requestjson->event_id);
      $stmt->bindParam("rsvp_status", $rsvp_status);

      $stmt->execute();
      $db = null;
      $rsvp_status = $stmt->fetchObject();
  } catch(PDOException $e) {
      echo '{"error":{"text":'. $e->getMessage() .'}}';
  }
}

//Cancel an rsvp
function cancelRSVP(){
  $request = Slim::getInstance()->request();
  $body = $request->getBody();
  $requestjson = json_decode($body);

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
  } catch(PDOException $e) {
      echo '{"error":{"text":'. $e->getMessage() .'}}';
  }

  if(!isset($session->user_id)){
      echo '{"error":{"text":"Token is not valid","errorid":"12"}}';
      exit;
  }

  $sql = "DELETE FROM event_attendees
    WHERE attendee_id=:attendee_id AND event_id=:event_id
  ";

  if($requestjson->attendee_id == "me"){
    $requestjson->attendee_id = $session->user_id;
  }

  try {
      $db = getConnection();
      $stmt = $db->prepare($sql);

      $stmt->bindParam("attendee_id", $requestjson->attendee_id);
      $stmt->bindParam("event_id", $requestjson->event_id);

      $stmt->execute();
      $db = null;
      $rsvp_status = $stmt->fetchObject();
  } catch(PDOException $e) {
      echo '{"error":{"text":'. $e->getMessage() .'}}';
  }
}

//Get all current pending rsvps
function getRSVPs(){
    $request = Slim::getInstance()->request();
    $body = $request->getBody();
    $requestjson = json_decode($body);

    //Status 1 is requested, not accepted
    //Status 2 is invited, not accepted
    //Status 5 is valid and accepted

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
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    if(!isset($session->user_id)){
        echo '{"error":{"text":"Token is not valid","errorid":"12"}}';
        exit;
    }

    //-------------------------------------

    //Get current events
    $rsvp_status = 5;

    $sql = "SELECT * FROM event_attendees

    WHERE attendee_id=:myuserid AND status=:status

    ";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);

        $stmt->bindParam("myuserid", $session->user_id);
        $stmt->bindParam("status", $rsvp_status);

        $stmt->execute();
        $db = null;
        $raw_rsvp_current = $stmt->fetchAll();
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    //Get current event details
    $rsvp_status = 5;
    $i = 0;

    $rsvp_current = "";

    try {
        $db = getConnection();

        if (isset($raw_rsvp_current)) {

            foreach ($raw_rsvp_current as $raw_rsvp) {
                $sql = "SELECT * FROM events

                WHERE id=:eventid

                ";

                $stmt = $db->prepare($sql);

                $stmt->bindParam("eventid", $raw_rsvp["event_id"]);

                $stmt->execute();

                $store = "";
                $store = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $rsvp_current[$i] = $store;

                $i++;
            }

        }

        $db = null;

    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    //------------------------------------

    //Get my current pending rsvp requests
    $rsvp_status = 1;

    $sql = "SELECT * FROM event_attendees

    WHERE attendee_id=:myuserid AND status=:status

    ";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);

        $stmt->bindParam("myuserid", $session->user_id);
        $stmt->bindParam("status", $rsvp_status);

        $stmt->execute();
        $db = null;
        $raw_rsvp_sent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    //Get sent event rsvp details
    $rsvp_status = 1;
    $i = 0;

    $rsvp_sent = "";

    try {
        $db = getConnection();

        if (isset($raw_rsvp_sent)) {

            foreach ($raw_rsvp_sent as $raw_rsvp) {

                $sql = "SELECT * FROM events

                WHERE id=:eventid

                ";

                $stmt = $db->prepare($sql);

                $stmt->bindParam("eventid", $raw_rsvp["event_id"]);

                $stmt->execute();

                $store = "";
                $store = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $rsvp_sent[$i] = $store;

                $i++;

            }

        }

        $db = null;

    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    //------------------

    //Get rsvp invites from event hosts
    $rsvp_status = 2;

    $sql = "SELECT * FROM event_attendees

    WHERE attendee_id=:myuserid AND status=:status

    ";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);

        $stmt->bindParam("myuserid", $session->user_id);
        $stmt->bindParam("status", $rsvp_status);

        $stmt->execute();
        $db = null;
        $raw_rsvp_invited = $stmt->fetchAll();
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    //Get pending friend requests to others details
    $rsvp_status = 2;
    $i = 0;

    $rsvp_invited = "";

    try {
        $db = getConnection();

        if (isset($raw_rsvp_invited)) {

            foreach ($raw_rsvp_invited as $raw_rsvp) {

                $sql = "SELECT * FROM events

                WHERE id=:eventid

                ";

                $stmt = $db->prepare($sql);

                $stmt->bindParam("eventid", $raw_rsvp["event_id"]);

                $stmt->execute();

                $store = "";
                $store = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $rsvp_invited[$i] = $store;

                $i++;

            }

        }

        $db = null;

    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    //-----------------
    //Echo section
    $current_clean = utf8ize($rsvp_current);
    $sent_clean = utf8ize($rsvp_sent);
    $invited_clean = utf8ize($rsvp_invited);
    echo '{"current":' . json_encode($current_clean) . ', "sent":' . json_encode($sent_clean) . ', "invited":' . json_encode($invited_clean) . '}';

    //-----------------

}

//Currently incomplete
/* function findByParameter() {

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
} */

function utf8ize($mixed) {
    if (is_array($mixed)) {
        foreach ($mixed as $key => $value) {
            $mixed[$key] = utf8ize($value);
        }
    } else if (is_string ($mixed)) {
        return utf8_encode($mixed);
    }
    return $mixed;
}

?>
