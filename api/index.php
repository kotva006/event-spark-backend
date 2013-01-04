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
$table = "table_name";

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
  if (IsNullOrEmptyString($event['longitude']) || IsNullOrEmptyString($event['latitude']) {
    echo '{"error":{"text":"invalid inputs for query"}}';
    die;
  }

  // This is implementing a search range.  Will need to fine tune.
  // 1 degree of latitude/longitude is ~ 111.2 km or 69 miles.
  $latsma = $event['latitude'] - .5;
  $latbig = $event['latitude'] + .5;
  $lonsma = $event['longitude'] - .5;
  $lonbig = $event['longitude'] + .5;
	$type = $event['type'];

  // This is a long way to create our query
  // Query bindings would help avoid SQL injection.
  if (IsNullOrEmptyString($type) {
    $query = "SELECT * "
           . "FROM $table "
           . "WHERE longitude BETWEEN $lonsma AND $lonbig "
           . "AND latitude BETWEEN $latsma AND $latbig";
  }
  else {
    $query = "SELECT * "
           . "FROM $table "
           . "WHERE type = :type "
           . "AND longitude BETWEEN $lonsma AND $lonbig "
           . "AND latitude BETWEEN $latsma AND $latbig";
  }

  try {
    $dbx = getConnection();
    $stmt = $dbx->prepare($query);
    $stmt->bindParam("type", $type);
    $stmt->execute();
    // Will need testing, but should give multiple objects in one.
    $events = $stmt->fetchAll(PDO::FETCH_OBJ);
    $dbx = NULL;
    echo '{"event": ' . json_encode($events) . '}';
  }
  catch (PDOException $e) {
    echo '{"error":{"text":'. $e->getMessage() .'}}';
    $dbx = NULL;
    die;
  }
}

// Assumes a json like above
function createEvent() {
  $request = Slim::getInstance()->request();
  $event = json_decode($request->getBody());

  // Add the event information into the SQL Database
  $query = "INSERT INTO $table (title, description, location, longitude, latitude, type, time) "
         . "VALUES (:title, :description, :location, :longitude, :latitude, :type, :time)";

  // Error checking for valid inputs
  if (IsNullOrEmptyString($event['title']) ||
      IsNullOrEmptyString($event['longitude']) ||
      IsNullOrEmptyString($event['latitude']) {
    echo '{"error":{"text":"invalid inputs for event creation."}}';
    die;
  }

  try {
    $dbx = getConnection();
    $state = $dbx->prepare($query);
    $state->bindParam("title", $event->title);
    $state->bindParam("description", $event->description);
    $state->bindParam("location", $event->location);
    $state->bindParam("longitude", $event->longitude);
    $state->bindParam("latitude", $event->latitude);
    $state->bindParam("type", $event->type);
    $state->bindParam("time", $event->time);

    // Returns an object with bool:true.
    // Maybe return the new event itself?
    echo '{"bool":' . $state->execute() . '}';
    $dbx = NULL;
  }
  catch (PDOException $e) {
    echo '{"error":{"text":'. $e->getMessage() .'}}';
    $dbx = NULL;
    die;
  }
}

// Helper method for database connections.
function getConnection() {
  // The database credentials are kept out of revision control.
  include("/path_to_db_settings.php");
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
