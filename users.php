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

$app->post('/login', 'userLogin');
$app->post('/join', 'userJoin');

$app->post('/friend/add', 'addFriend');
$app->post('/friend/accept', 'acceptFriend');
$app->post('/friend', 'getFriends');

$app->delete('/user', 'deleteUser');
$app->put('/user', 'updateUser');
$app->get('/user', 'getUser');
$app->get('/user/:id', 'getUser');
//INCOMPLETE:
//$app->post('/users/search', 'findByParameter');

$app->run();

function addFriend() {
    $request = Slim::getInstance()->request();
    $body = $request->getBody();
    $requestjson = json_decode($body);

    //Status 1 is requested, not accepted
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

    $friend_status = 1;

    $sql = "INSERT INTO user_friends 
    
    (fromfriend, tofriend, status)
    VALUES
    (:fromfriend, :tofriend, :status)

    ";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);

        $stmt->bindParam("fromfriend", $session->user_id);
        $stmt->bindParam("tofriend", $requestjson->friend_id);
        $stmt->bindParam("status", $friend_status);
        
        $stmt->execute();
        $db = null;
        echo json_encode($requestjson);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}

function acceptFriend() {
    $request = Slim::getInstance()->request();
    $body = $request->getBody();
    $requestjson = json_decode($body);

    //Status 1 is requested, not accepted
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

    $friend_status = 1;

    $sql = "UPDATE user_friends 
    
    SET status=:status

    WHERE tofriend=:myuserid AND fromfriend=:fromfriend

    ";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);

        $stmt->bindParam("myuserid", $session->user_id);
        $stmt->bindParam("fromfriend", $requestjson->friend_id);
        $stmt->bindParam("status", $friend_status);
        
        $stmt->execute();
        $db = null;
        echo json_encode($requestjson);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}

function getFriends() {
    $request = Slim::getInstance()->request();
    $body = $request->getBody();
    $requestjson = json_decode($body);

    //echo $requestjson->session_token;

    //Status 1 is requested, not accepted
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

    //Get current friends
    $friend_status = 5;

    $sql = "SELECT * FROM user_friends 

    WHERE (tofriend=:myuserid OR fromfriend=:myuserid) AND status=:status

    ";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);

        $stmt->bindParam("myuserid", $session->user_id);
        $stmt->bindParam("status", $friend_status);
        
        $stmt->execute();
        $db = null;
        $raw_friends_current = $stmt->fetchAll();
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    //Get current friends details
    $friend_status = 5;
    $i = 0;

    $friends_current = "";

    try {
        $db = getConnection();

        if (isset($raw_friends_current)) {

            foreach ($raw_friends_current as $raw_friend) {
                $sql = "SELECT * FROM users

                WHERE id=:userid1 OR id=:userid2

                ";

                $stmt = $db->prepare($sql);
                
                $stmt->bindParam("userid1", $raw_friend['tofriend']);
                $stmt->bindParam("userid2", $raw_friend['fromfriend']);
                
                $stmt->execute();

                $friends_current = $stmt->fetchAll();
                
                $i++;

            }

        }

        $db = null;

    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
    
    //------------------------------------

    //Get friend requests to me
    $friend_status = 1;

    $sql = "SELECT * FROM user_friends 

    WHERE tofriend=:myuserid AND status=:status

    ";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);

        $stmt->bindParam("myuserid", $session->user_id);
        $stmt->bindParam("status", $friend_status);
        
        $stmt->execute();
        $db = null;
        $raw_friends_requestme = $stmt->fetchAll();
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    //Get friend requests to me details
    $friend_status = 1;
    $i = 0;

    $friends_requestme = "";

    try {
        $db = getConnection();

        if (isset($raw_friends_requestme)) {

            foreach ($raw_friends_requestme as $raw_friend) {

                $sql = "SELECT * FROM users

                WHERE id=:userid

                ";

                $stmt = $db->prepare($sql);

                $stmt->bindParam("userid", $raw_friend["fromfriend"]);
                
                $stmt->execute();

                $friends_requestme = $stmt->fetchAll();
                $i++;

            }

        }

        $db = null;

    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    //Get pending friend requests to others
    $friend_status = 1;

    $sql = "SELECT * FROM user_friends 

    WHERE fromfriend=:myuserid AND status=:status

    ";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);

        $stmt->bindParam("myuserid", $session->user_id);
        $stmt->bindParam("status", $friend_status);
        
        $stmt->execute();
        $db = null;
        $raw_friends_requested = $stmt->fetchAll();
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    //Get pending friend requests to others details
    $friend_status = 1;
    $i = 0;

    $friends_requested = "";

    try {
        $db = getConnection();

        if (isset($raw_friends_requested)) {

            foreach ($raw_friends_requested as $raw_friend) {

                $sql = "SELECT * FROM users

                WHERE id=:userid

                ";

                $stmt = $db->prepare($sql);

                $stmt->bindParam("userid", $raw_friend["tofriend"]);
                
                $stmt->execute();

                $friends_requested = $stmt->fetchAll();
                $i++;

            }

        }

        $db = null;

    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    //-----------------
    //Echo section
    $current_clean = utf8ize($friends_current);
    $requested_clean = utf8ize($friends_requested);
    $requestme_clean = utf8ize($friends_requestme);
    echo '{"friends":' . json_encode($current_clean) . ', "requested":' . json_encode($requested_clean) . ', "requestme":' . json_encode($requestme_clean) . '}';

    //-----------------

}

function userLogin() {
    $request = Slim::getInstance()->request();
    $user = json_decode($request->getBody());

    //Get Salt
    $sql = "SELECT

        salt

        FROM users WHERE username=:username LIMIT 1";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("username", $user->username);
        $stmt->execute();
        $response = $stmt->fetchObject();
        $db = null;
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
        exit;
    }

    //If user does not exist
    if(!isset($response->salt)){
        echo '{"error":{"text":"Username' . $user->username . ' does not exist","errorid":"23"}}';
        exit;
    }

    //Crypt salt and password
    $passwordcrypt = crypt($user->password, $response->salt);

    //Get ID
    $sql = "SELECT

        id

        FROM users WHERE username=:username AND password=:password LIMIT 1";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("username", $user->username);
        $stmt->bindParam("password", $passwordcrypt);
        $stmt->execute();
        $response = $stmt->fetchObject();
        $db = null;
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
        exit;
    }

    //If password is incorrect
    if(!isset($response->id)){
        echo '{"error":{"text":"Password is incorrect","errorid":"24"}}';
        exit;
    }

    //Generate a session token
    $length = 24;
    $randomstring = bin2hex(openssl_random_pseudo_bytes($length, $strong));
    if(!($strong = true)){
        echo '{"error":{"text":"Did not generate secure random session token"}}';
        exit;
    }

    //Insert session token
    $sql = "INSERT INTO sessions

        (user_id, token)

        VALUES

        (:user_id, :token)";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("user_id", $response->id);
        $stmt->bindParam("token", $randomstring);
        $stmt->execute();
        $response->session_token = $randomstring;
        $session_token = $randomstring;
        $db = null;
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
        exit;
    }

    //Echo session token
    echo '{"result":{"session_token":"'. $session_token .'"}}';
}

function userJoin() {
    $request = Slim::getInstance()->request();
    $user = json_decode($request->getBody());

    //Check if username exists
    $sql = "SELECT

        username

        FROM users WHERE username=:username LIMIT 1";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("username", $user->username);
        //$stmt->bindParam("password", $user->password);
        $stmt->execute();
        $usercheck = $stmt->fetchObject();
        $db = null;
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
        exit;
    }

    //If exists echo error and cancel
    if(isset($usercheck->username)){
        echo '{"error":{"text":"Username Already Exists","errorid":"22"}}';
        exit;
    }

    //Generate a salt
    $length = 24;
    $salt = bin2hex(openssl_random_pseudo_bytes($length));

    //Crypt salt and password
    $passwordcrypt = crypt($user->password, $salt);

    //Create user
    $sql = "INSERT INTO users

    (username, password, salt, name,
        phone, address1, address2,
        city, state, zip, profile)

    VALUES

    (:username, :password, :salt, :name,
        :phone, :address1, :address2,
        :city, :state, :zip, :profile)";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("username", $user->username);
        $stmt->bindParam("password", $passwordcrypt);
        $stmt->bindParam("salt", $salt);
        $stmt->bindParam("name", $user->name);
        $stmt->bindParam("phone", $user->phone);
        $stmt->bindParam("address1", $user->address1);
        $stmt->bindParam("address2", $user->address2);
        $stmt->bindParam("city", $user->city);
        $stmt->bindParam("state", $user->state);
        $stmt->bindParam("zip", $user->zip);
        $stmt->bindParam("profile", $user->profile);
        $stmt->execute();
        $newusrid = $db->lastInsertId();
        $db = null;
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
        exit;
    }

    //Generate a session token
    $length = 24;
    $randomstring = bin2hex(openssl_random_pseudo_bytes($length, $strong));
    if(!($strong = true)){
        echo '{"error":{"text":"Did not generate secure random session token"}}';
        exit;
    }

    //Insert session token
    $sql = "INSERT INTO sessions

        (user_id, token)

        VALUES

        (:user_id, :token)";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("user_id", $newusrid);
        $stmt->bindParam("token", $randomstring);
        $stmt->execute();
        $session_token = $randomstring;
        $db = null;
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
        exit;
    }

    echo '{"result":{ "session_token":"'. $randomstring .'"}}';
}

function getUser() {
    $request = Slim::getInstance()->request();
    $user = json_decode($request->getBody());

    $sql = "SELECT

        user_id

        FROM sessions WHERE token=:token LIMIT 1";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("token", $user->session_token);
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

	$sql = "SELECT

        username, firstname, lastname, phone, 
        email, address1, address2, city,
        state, zip, profile

        FROM users WHERE id=:id";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("id", $session->user_id);
        $stmt->execute();
        $user = $stmt->fetchObject();
        $db = null;
        echo json_encode($user);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}

function updateUser() {
	$request = Slim::getInstance()->request();
    $body = $request->getBody();
    $user = json_decode($body);

    $sql = "SELECT

        user_id

        FROM sessions WHERE token=:token LIMIT 1";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("token", $user->session_token);
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

    $sql = "UPDATE users 
    SET 
    
    username=:username,
    name=:name,
    phone=:phone, 
    address1=:address1,
    address2=:address2,
    city=:city,
    state=:state,
    zip=:zip,
    profile=:profile

    WHERE id=:id";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);

        $stmt->bindParam("id", $session->user_id);
        $stmt->bindParam("username", $user->username);
        $stmt->bindParam("name", $user->name);
        $stmt->bindParam("phone", $user->phone);
        $stmt->bindParam("address1", $user->address1);
        $stmt->bindParam("address2", $user->address2);
        $stmt->bindParam("city", $user->city);
        $stmt->bindParam("state", $user->state);
        $stmt->bindParam("zip", $user->zip);
        $stmt->bindParam("profile", $user->profile);
        
        $stmt->execute();
        $db = null;
        echo json_encode($user);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}

function deleteUser() {
    $request = Slim::getInstance()->request();
    $body = $request->getBody();
    $user = json_decode($body);

    $sql = "SELECT

        user_id

        FROM sessions WHERE token=:token LIMIT 1";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("token", $user->session_token);
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

	$sql = "DELETE FROM users WHERE id=:id";
    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("id", $session->user_id);
        $stmt->execute();
        $db = null;
        echo json_encode($user);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    $sql = "DELETE FROM sessions WHERE user_id=:user_id";
    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("user_id", $session->user_id);
        $stmt->execute();
        $db = null;
        echo json_encode($user);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

    $sql = "DELETE FROM events WHERE host_id=:host_id";
    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
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
