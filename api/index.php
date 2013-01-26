<?php
// REST API for the Mobile App Challenge backend.

// See http://coenraets.org/blog/2011/12/restful-services-with-jquery-php-and-the-slim-framework/
// for an idea of how to implement GET/POST/etc handlers.

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

require 'Slim/Slim.php';
\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

$app->get('/events/:id', 'getEvent');
$app->get('/events/search/', 'getEventsByLocation');
$app->post('/events', 'createEvent');

$app->run();

function getEvent($id) {
  // Get information about a particular event. Necessary?
  // This may be useful for debugging but not used in practice.
}

// Returns an object/array of event objects based on the user's location.
// Time is returned using the POSIX standard
//
// Parameters:
//   latitude:  The latitude of the user (float)
//   longitude: The longitude of the user (float)
//
// Returns:
//   {
//     { event object }
//     { event object }
//     ...
//   }
function getEventsByLocation() {
  $request = \Slim\Slim::getInstance()->request();

  $latitude = $request->get('latitude');
  $longitude = $request->get('longitude');

  // Simple error checking for valid inputs
  if (isNullOrEmptyString($longitude) || isNullOrEmptyString($latitude)) {
    echo '{"text": "invalid inputs for query"}';
    die;
  }

  // This is implementing a search range.  Will need to fine tune.
  // Changed to ~5 mile radius.
  $locationRadius = .074;
  $latsmall = $latitude - $locationRadius;
  $latbig = $latitude + $locationRadius;
  $lonsmall = $longitude - $locationRadius;
  $lonbig = $longitude + $locationRadius;

  try {
    $dbx = getConnection();

    // Creation the SQL query string.
    $query = "SELECT * "
           . "FROM " . $GLOBALS['table'] . " "
           . "WHERE longitude BETWEEN :lonsmall AND :lonbig "
           . "AND latitude BETWEEN :latsmall AND :latbig";

    $stmt = $dbx->prepare($query);
    $stmt->bindParam("lonsmall", $lonsmall);
    $stmt->bindParam("lonbig", $lonbig);
    $stmt->bindParam("latsmall", $latsmall);
    $stmt->bindParam("latbig", $latbig);
    //$stmt->bindParam("type", $type);

    $stmt->execute();

    // Will need testing, but should give multiple objects in one.
    $events = $stmt->fetchAll(PDO::FETCH_OBJ);

    $dbx = NULL;
    echo '{"events":' . json_encode($events) . '}';
  }
  catch (PDOException $e) {
    echo '{"text": "' . $e->getMessage() . '"}';
    $dbx = NULL;
    die;
  }
}

// Assumes a json like above
// Recieves time based on based on POSIX time standard
function createEvent() {
  $request = \Slim\Slim::getInstance()->request();
  $event = json_decode($request->getBody(),  true);

  // Error checking for valid inputs
  if (isNullOrEmptyString($event['title']) ||
      isNullOrEmptyString($event['longitude']) ||
      isNullOrEmptyString($event['latitude']) ||
      isNullOrEmptyString($event['end_date'])) {
    echo '{"text": "invalid inputs for event creation."}';
    die;
  }

  try {
    $dbx = getConnection();

    // Add the event information into the SQL Database
    $query = "INSERT INTO " . $GLOBALS['table'] . " (title, description, longitude, latitude, start_date, end_date) "
           . "VALUES (:title, :description, :longitude, :latitude, :start_date, :end_date)";

    $state = $dbx->prepare($query);
    $state->bindParam("title", $event['title']);
    $state->bindParam("description", $event['description']);
    //$state->bindParam("location", $event['location']);
    $state->bindParam("longitude", $event['longitude']);
    $state->bindParam("latitude", $event['latitude']);
    $state->bindParam("start_date", $event['start_date']);
    $state->bindParam("end_date", $event['end_date']);
    // Returns an object with bool:true.
    echo '{"bool": "' . $state->execute() . '"}';
    $dbx = NULL;
  }
  catch (PDOException $e) {
    echo '{"text": "' . $e->getMessage() . '"}';
    $dbx = NULL;
    die;
  }
}

// Helper method for database connections.
// Also include variable $table in settings.php
function getConnection() {
  // The database credentials are kept out of revision control.
  include("$_SERVER[DOCUMENT_ROOT]/../settings.php");

  // Keep the table variable available globally.
  $GLOBALS["table"] = $table;

  $dbh = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
  $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  unset ($db_user, $db_pass);
  return $dbh;
}

// Validation helper (String is present and neither empty nor only white space)
function isNullOrEmptyString($field) {
  return (!isset($field) || trim($field) === '');
}

?>
