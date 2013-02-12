<?php
// REST API for the Mobile App Challenge backend.

// See http://coenraets.org/blog/2011/12/restful-services-with-jquery-php-and-the-slim-framework/
// for an idea of how to implement GET/POST/etc handlers.

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

require 'Slim/Slim.php';
\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

$app->get('/events/:id', function($id) {
  // Makes a call to getEvent; this allows it to be used both as an internal
  // method and as a public api url.
  echo getEvent($id);
});
$app->get('/events/search/', 'getEventsByLocation');
$app->post('/events', 'createEvent');
$app->post('/events/attend/', 'attendEvent');
$app->get('/events/getAttend/', 'getAttending');
$app->post('/events/report/', 'reportEvent');

$app->run();

// Retrieves a particular event by ID.
function getEvent($id) {
  try {
    $dbx = getConnection();

    // Creation the SQL query string.
    $query = "SELECT * FROM " . $GLOBALS['table'] . " WHERE id=:id";

    $stmt = $dbx->prepare($query);
    $stmt->bindParam("id", $id);

    $stmt->execute();
    $event = $stmt->fetchObject();
    $dbx = NULL;
    if ($event == false)
      return '{"error": "No event found for given ID."}';
    return '{"event":' . json_encode($event) . '}';
  }
  catch (PDOException $e) {
    return '{"error": "' . $e->getMessage() . '"}';
    $dbx = NULL;
    die;
  }
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
//     "events": [
//       { event object }
//       { event object }
//       ...
//     ]
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

    $stmt->execute();
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

// Adds an event to the database.
//
// POST Parameters:
//   title: The title of the event.
//   description: A longer description of the event.
//   type: An integer constant defining the type of event.
//     1: Academics
//     2: Athletics
//     3: Entertainment
//     4: Promotions
//     5: Social
//     0: Other
//   latitude: The latitude of the event location.
//   longitude: The longitude of the event location.
//   start_date: POSIX date when event begins.
//   end_date: POSIX date when event ends.
//
// Returns:
// (Success)
//   {"event": { event object } }
//
// (Failure)
//   {"error": <message describing error>}
function createEvent() {
  $request = \Slim\Slim::getInstance()->request();

  $title = $request->post('title');
  $description = $request->post('description');
  $type = $request->post('type');
  $latitude = $request->post('latitude');
  $longitude = $request->post('longitude');
  $start = $request->post('start_date');
  $end = $request->post('end_date');
  $attending = 1; // TODO: Can we leave this blank and let MySQL default it?

  // Validate inputs
  if (isNullOrEmptyString($title)) {
    echo '{"error": "No title given for the new event."}'; die;
  }
  if (isNullOrEmptyString($type)) {
    echo '{"error": "Type must be an integer constant."}'; die;
  }
  if (isNullOrEmptyString($latitude) || isNullOrEmptyString($longitude)) {
    echo '{"error": "Both a latitude and longitude parameter are required."}'; die;
  }
  if (isNullOrEmptyString($start) || isNullOrEmptyString($end)) {
    echo '{"error": "Both a start and end date in seconds are required."}'; die;
  }

  try {
    $dbx = getConnection();

    // Add the event information into the SQL Database
    $query = "INSERT INTO " . $GLOBALS['table'] . " (title, description, longitude, "
           . "latitude, start_date, end_date, type, attending) "
           . "VALUES (:title, :description, :longitude, :latitude, :start_date, "
           . ":end_date, :type, :attending)";


    $state = $dbx->prepare($query);
    $state->bindParam("title", $title);
    $state->bindParam("description", $description);
    $state->bindParam("longitude", $longitude);
    $state->bindParam("latitude", $latitude);
    $state->bindParam("start_date", $start);
    $state->bindParam("end_date", $end);
    $state->bindParam("type", $type);
    $state->bindParam("attending", $attending);
    $state->execute();
    $id = $dbx->lastInsertId();
    //echo getEvent($id);
    echo '{"event": { "id":"'         .   $id       . '",'
                  .   '"title":"'     .   $title    . '",'
                  .   '"description":"' . $description . '",'
                  .   '"longitude":"' .   $longitude .   '",'
                  .   '"latitude":"'  .   $latitude  .   '",'
                  .   '"start_date":"'.   $start     .   '",'
                  .   '"end_date":"'  .   $end       .   '",'
                  .   '"type":"'      .   $type      .   '",'
                  .   '"attending":"'  .   $attending .   '"}}';
                  
    $dbx = NULL;
  }
  catch (PDOException $e) {
    echo '{"error": "' . $e->getMessage() . '"}';
    $dbx = NULL;
    die;
  }
}

function attendEvent() {
  $request = \Slim\Slim::getInstance()->request();
  $id = $request->post('id');

  if (isNullOrEmptyString($id)) {
    echo '{"error": "An ID number is required"}';
  }

  try {
    $dbx = getConnection();

    $query = "UPDATE " . $GLOBALS['table'] . " SET attending = attending + 1 "
         . "WHERE id=:id";
    $state = $dbx->prepare($query);
    $state->bindParam("id", $id);
    $state->execute();
    echo '{"text":"success"}';
    $dbx = NULL;
  }
  catch (PDOException $e) {
    echo '{"error": "' . $e->getMessage() . '"}';
    $dbx = NULL;
  }
}

function getAttending() {
  $request = \Slim\Slim::getInstance()->request();
  $id = $request->get('id');

  if (isNullOrEmptyString($id)) {
    echo '{"error": "An ID number is required"}';
  }
  
  try {
    $dbx = getConnection();
    $query = "SELECT attending FROM " . $GLOBALS['table'] . " WHERE id=:id";
    $state = $dbx->prepare($query);
    $state->bindParam("id", $id);
    $state->execute();
    $attending = $state->fetch(PDO::FETCH_OBJ);

    echo json_encode($attending);
    $dbx = NULL;
  }
  catch (PDOException $e) {
    echo '{"error": "' . $e->getMessage() . '"}';
  }
}

function reportEvent() {
  $request = \Slim\Slim::getInstance()->request();
  $id = $request->post('id');

  try {
    $dbx = getConnection();
    $query = "UPDATE " . $GLOBALS['table'] 
             . " SET report=report+1 WHERE id=:id";
    $state = $dbx->prepare($query);
    $state->bindParam('id', $id);
    $state->execute();
    echo '{"text":"success"}';
    $dbx = NULL;
  }
  catch (PDOException $e) {
    echo '{"error": "' . $e->getMessage() . '"}';
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
