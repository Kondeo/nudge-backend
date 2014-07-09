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

$app->get('/users/:id', 'getUser');
$app->put('/users/:id', 'updateUser');
$app->delete('/users/:id', 'deleteUser');
//INCOMPLETE:
//$app->post('/users/search', 'findByParameter');

$app->run();

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
        echo '{"error":{"text":"Username does not exist","errorid":"23"}}';
        exit;
    }

    //Crypt salt and password
    $passwordcrypt = crypt($user->password, $salt);

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
    $randomstring = openssl_random_pseudo_bytes($length, $strong);
    if(!($strong = true)){
        echo '{"error":{"text":"Did not generate secure random session token"}}';
        exit;
    }

    //Insert session token
    $sql = "INSERT INTO sessions

        (id, key)

        VALUES

        (:id, :key)";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("id", $response->id);
        $stmt->bindParam("key", $randomstring);
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
        $stmt->bindParam("password", $user->password);
        $stmt->execute();
        $usercheck = $stmt->fetchObject();
        $db = null;
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
        exit;
    }

    //If exists echo error and cancel
    if($usercheck->username = $user->username){
        echo '{"error":{"text":"Username Already Exists","errorid":"22"}}';
        exit;
    }

    //Generate a salt
    $length = 24;
    $salt = openssl_random_pseudo_bytes($length);

    //Crypt salt and password
    $passwordcrypt = crypt($user->password, $salt);

    //Create user
    $sql = "INSERT INTO users

    (username, password, firstname, lastname,
        phone, email, address1, address2,
        city, state, zip, profile)

    VALUES

    (:username, :password, :firstname, :lastname,
        :phone, :email, :address1, :address2,
        :city, :state, :zip, :profile)";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("username", $user->username);
        $stmt->bindParam("password", $passwordcrypt);
        $stmt->bindParam("salt", $salt);
        $stmt->bindParam("firstname", $user->firstname);
        $stmt->bindParam("lastname", $user->lastname);
        $stmt->bindParam("phone", $user->phone);
        $stmt->bindParam("email", $user->email);
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
    $randomstring = openssl_random_pseudo_bytes($length, $strong);
    if(!($strong = true)){
        echo '{"error":{"text":"Did not generate secure random session token"}}';
        exit;
    }

    //Insert session token
    $sql = "INSERT INTO sessions

        (id, key)

        VALUES

        (:id, :key)";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("id", $response->id);
        $stmt->bindParam("key", $randomstring);
        $stmt->execute();
        $response->session_token = $randomstring;
        $db = null;
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
        exit;
    }

    echo '{"result":{"session_token":"'. $randomstring .'"}}';

}

function getUser($id) {
    $request = Slim::getInstance()->request();
    $user = json_decode($request->getBody());

    $sql = "SELECT

        id

        FROM sessions WHERE key=:key LIMIT 1";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("key", $user->session_token);
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

	$sql = "SELECT

        username, firstname, lastname, phone, 
        email, address1, address2, city,
        state, zip, profile

        FROM users WHERE id=:id";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("id", $session->id);
        $stmt->execute();
        $user = $stmt->fetchObject();
        $db = null;
        echo json_encode($user);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}

function updateUser($id) {

    //Needs security

	$request = Slim::getInstance()->request();
    $body = $request->getBody();
    $user = json_decode($body);
    $sql = "UPDATE users 
    SET 
    
    firstname=:firstname,
    lastname=:lastname,
    phone=:phone, 
    email=:email,
    address1=:address1,
    address2=:address2,
    city=:city,
    state=:state,
    zip=:zip,
    profile=:profile

    WHERE id=:id AND password=:password";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);

        $stmt->bindParam("id", $user->id);
        $stmt->bindParam("password", $user->password);
        $stmt->bindParam("firstname", $user->firstname);
        $stmt->bindParam("lastname", $user->lastname);
        $stmt->bindParam("phone", $user->phone);
        $stmt->bindParam("email", $user->email);
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

function deleteUser($id) {

    //Needs security

    $request = Slim::getInstance()->request();
    $body = $request->getBody();
    $user = json_decode($body);
	$sql = "DELETE FROM users WHERE id=:id AND password=:password";
    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("id", $id);
        $stmt->bindParam("password", $user->$password);
        $stmt->execute();
        $db = null;
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
	$dbname="nudgedb";
	$dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);	
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return $dbh;
}

?>
