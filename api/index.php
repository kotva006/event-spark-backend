<?php
// REST API for the Mobile App Challenge backend.

// See http://coenraets.org/blog/2011/12/restful-services-with-jquery-php-and-the-slim-framework/
// for an idea of how to implement GET/POST/etc handlers.

require 'Slim/Slim.php';
\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

//#################
//Select our DB table
//#################
$table = "table1";

$app->get('/events/:id', 'getEvent');
$app->get('/events/search/:query', 'getEventsByLocation');
$app->post('/events', 'createEvent');

$app->run();

function getEvent($id) {
  // Get information about a particular event. Necessary?
  // This may be useful for debugging but not used in practice.
}

// Returns an object/array of event objects based on the user's location.
//
// Parameters:
//   latitude:  The latitude of the user (float)
//   longitude: The longitude of the user (float)
//   type:      A filter for certain event types only (int\string?)
//
// Returns:
//   {
//    { event object }
//    { event object }
//    ...
//   }
function getEventsByLocation() {
  $request = Slim::getInstance()->request();
  $event = json_decode($request->getBody());

  // Simple error checking for valid inputs
  if (IsNullOrEmptyString($event['longitude']) || IsNullOrEmptyString($event['latitude'])) {
    echo '{"error":{"text":"invalid inputs for query"}}';
    die;
  }

  // This is implementing a search range.  Will need to fine tune.
  // Changed to ~5 mile radius.
  $latsma = $event['latitude'] - .074;
  $latbig = $event['latitude'] + .074;
  $lonsma = $event['longitude'] - .074;
  $lonbig = $event['longitude'] + .074;
  $type = $event['type'];

  // This is a long way to create our query
  // Query bindings would help avoid SQL injection.
  if (IsNullOrEmptyString($type) ){
    $query = "SELECT * "
           . "FROM $table "
           . "WHERE longitude BETWEEN :lonsma AND :lonbig "
           . "AND latitude BETWEEN :latsma AND :latbig";
  }
  else {
    $query = "SELECT * "
           . "FROM $table "
           . "WHERE type = :type "
           . "AND longitude BETWEEN :lonsma AND :lonbig "
           . "AND latitude BETWEEN :latsma AND :latbig";
  }

  try {
    $dbx = getConnection();
    $stmt = $dbx->prepare($query);
    $stmt->bindParam("lonsma", $lonsma);
    $stmt->bindParam("lonbig", $lonbig);
    $stmt->bindParam("latsma", $latsam);
    $stmt->bindParam("latbig", $latbig);
    $stmt->bindParam("type", $type);
    $stmt->execute();
    // Will need testing, but should give multiple objects in one.
    $events = $stmt->fetchAll(PDO::FETCH_OBJ);
    $dbx = NULL;
    echo '{"event":' . json_encode($events) .'}';
  }
  catch (PDOException $e) {
    echo '{"text":"'. $e->getMessage() .'"}';
    $dbx = NULL;
    die;
  }
}

// Assumes a json like above
function createEvent() {
  $request = Slim::getInstance()->request();
  $event = json_decode($request->getBody());

  // Add the event information into the SQL Database
  $query = "INSERT INTO $table (title, description, location, longitude, "
         . "latitude, type, startTime, endTime, date) "
         . "VALUES (:title, :description, :location, :longitude, :latitude, "
         . ":type, :startTime, :endTime, :date)";

  // Error checking for valid inputs
  if (IsNullOrEmptyString($event['title']) ||
      IsNullOrEmptyString($event['longitude']) ||
      IsNullOrEmptyString($event['latitude']) ||
      IsNullOrEmptyString($event['type'])) {
    echo '{"text":"invalid inputs for event creation."}';
    die;
  }

  //Process the time
  $starTime = "";
  $endTime  = "";
  $createDate = date("mdY");
  $am_pm    = "";
  $time = $event['time'];

  //Convert 12 hours to military time
  if(strlen($time) >6){
    $startTime = substr($time, 0, -8);
    $am_pm     = substr($time, 4, -6);
    if($am_pm=1){
      $am_pm = "";
    }
    else{$starTime + 1200;}
    $endTime = substr($time, 5, -2);
    $am_pm     = substr($time, 9);
    if($am_pm=1){
      $am_pm = "";
    }
    else{$endTime + 1200;}
  }
  else {
    $endTime = substr($time, 0, -2);
    $am_pm     = substr($time, 4, -6);
    if($am_pm=1){
      $am_pm = "";
    }
    else{$endTime + 1200;}
  }
  
  try {
    $dbx = getConnection();
    $state = $dbx->prepare($query);
    $state->bindParam("title", $event->title);
    $state->bindParam("description", $event->description);
    $state->bindParam("location", $event->location);
    $state->bindParam("longitude", $event->longitude);
    $state->bindParam("latitude", $event->latitude);
    $state->bindParam("startTime", $startTime);
    $state->bindParam("endTime", $endTime);
    $state->bindParam("date", $date);
    $state->bindParam("type", $event->type);

    // Returns an object with bool:true.
    echo '{"bool":"' . $state->execute() . '"}';
    $dbx = NULL;
  }
  catch (PDOException $e) {
    echo '{"text":"'. $e->getMessage() .'"}';
    $dbx = NULL;
    die;
  }
}

// Helper method for database connections.
function getConnection() {
  // The database credentials are kept out of revision control.
  include("/var/storage/settings.php");
  $dbh = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
  $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  unset ($db_user, $db_pass);
  return $dbh;
}

// Validation helper (String is present and neither empty nor only white space)
function IsNullOrEmptyString($field) {
  return (!isset($field) || trim($field) === '');
}

?>
