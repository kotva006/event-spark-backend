<?php
// REST API for the Mobile App Challenge backend.

// See http://coenraets.org/blog/2011/12/restful-services-with-jquery-php-and-the-slim-framework/
// for an idea of how to implement GET/POST/etc handlers.

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

require 'Slim/Slim.php';
require 'display.php';
\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

$app->get('/events/:id', function($id) {
  // Makes a call to getEvent; this allows it to be used both as an internal
  // method and as a public api url.
  echo getEvent($id);
});
$app->get('/events/search/', 'getEventsByLocation');
$app->post('/events', 'createEvent');
$app->delete('/events/:id', 'deleteEvent');
$app->put('/events/:id', 'updateEvent');
$app->post('/events/attend/', 'attendEvent');
$app->get('/events/getAttend/', 'getAttending');
$app->post('/events/report/', 'reportEvent');
$app->get('/displayEvent/:id', 'displayEvent');

$app->run();

// Retrieves a particular event by ID.
function getEvent($id) {
  try {
    $dbx = getConnection();

    // Creation the SQL query string.
    $query = "SELECT e.id, "
                  . "e.title, "
                  . "e.description, "
                  . "e.longitude, "
                  . "e.latitude, "
                  . "e.start_date, "
                  . "e.end_date, "
                  . "e.type, "
                  . "(SELECT COUNT(*) from attending a WHERE a.id=:id) AS attending "
           . "FROM " . $GLOBALS["event_t"] . " AS e WHERE e.id=:id";

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
    $query = "SELECT e.id, "
                  . "e.title, "
                  . "e.description, "
                  . "e.longitude, "
                  . "e.latitude, "
                  . "e.start_date, "
                  . "e.end_date, "
                  . "e.type, "
                  . "(SELECT COUNT(*) FROM attending a WHERE a.id=e.id) AS attending "
           . "FROM " . $GLOBALS["event_t"] . " AS e "
           . "WHERE longitude BETWEEN :lonsmall AND :lonbig "
           . "AND latitude BETWEEN :latsmall AND :latbig "
           . "AND UNIX_TIMESTAMP(NOW()) < end_date "
           . "AND (start_date - 10800) < UNIX_TIMESTAMP(NOW())";
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
  $user_id = substr($request->post('user_id'), 0, 22);

  // Determine the request IP for use in spam prevention (TODO: Implement protection)
  $ip = $request->getIp();

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
  if (isNullOrEmptyString($user_id)) {
    echo '{"error": "A user_id must be provided to give event ownership."}'; die;
  }

  try {
    $dbx = getConnection();

    // Generate a unique secret_id for this new event.
    $secret_id = sha1(uniqid('', true) . $GLOBALS["salt"]);

    // Add the event information into the SQL Database
    $query = "INSERT INTO " . $GLOBALS["event_t"] . " (title, description, longitude, "
           . "latitude, start_date, end_date, type, ip, secret_id, user_id) "
           . "VALUES (:title, :description, :longitude, :latitude, :start_date, "
           . ":end_date, :type, INET_ATON(:ip), :secret_id, :user_id)";

    $state = $dbx->prepare($query);
    $state->bindParam("title", $title);
    $state->bindParam("description", $description);
    $state->bindParam("longitude", $longitude);
    $state->bindParam("latitude", $latitude);
    $state->bindParam("start_date", $start);
    $state->bindParam("end_date", $end);
    $state->bindParam("type", $type);
    $state->bindParam("ip", $ip);
    $state->bindParam("secret_id", $secret_id);
    $state->bindParam("user_id", $user_id);
    $state->execute();
    $id = $dbx->lastInsertId();
    //echo getEvent($id);

    echo '{"secret_id": "'             . $secret_id    . '",'
         .'"event": {"id":"'           . $id          . '",'
                  .  '"title":"'       . $title       . '",'
                  .  '"description":"' . $description . '",'
                  .  '"longitude":"'   . $longitude   . '",'
                  .  '"latitude":"'    . $latitude    . '",'
                  .  '"start_date":"'  . $start       . '",'
                  .  '"end_date":"'    . $end         . '",'
                  .  '"type":"'        . $type        . '",'
                  .  '"attending":"0"}}';
    $dbx = NULL;
    addWebPage($id, $title);
  }
  catch (PDOException $e) {
    echo '{"error": "' . $e->getMessage() . '"}';
    $dbx = NULL;
    die;
  }
}
// Every Post in facebook is treated as an object.
// Facebook references static webpages when it links our objects and posts
// Every time we create an event a static webpage will be created for it in
// the Facebook directory
//This is an internal function only called by the createEvent function
function addWebPage($id, $title) {

  if (isNullOrEmptyString($id) || isNullOrEmptyString($title)) {
    echo '{"error":"invalid arguments to server processing"}';
    die;
  }

  $fileLocation = "../facebook/" . $id . '.html';
  $handle = fopen($fileLocation, 'w'); 
  if ($handle) {
    echo '{"error":"Failed to open webpage"}';
    die;
  }
  $wrtieString = '<html><head prefix="og: http://ogp.me/ns# fb: '
                 . 'http://ogp.me/ns/fb# appchallenge_arrows: '
                 . 'http://ogp.me/ns/fb/appchallenge_arrows#"> '
                 . '<meta property="fb:app_id" content="631935130155263" /> '
                 . '<meta property="og:type"   content="appchallenge_arrows:event" /> '
                 . '<meta property="og:url" content="http://saypoint.dreamhosters.com/api'
                 . 'eventDisplay/' . $id . ' />'
                 . '<meta property="og:title"  content="' . $title . '" /> '
                 . '<meta property="og:image"  content='
                 . '"http://saypoint.dreamhosters.com/facebook/arrowsLogo.png" />'
                 . '<body></body></html>';
  fwrite($handle, $writeString);
  $fclose($handle);
}
  

// This function deletes the event from the event table
// Returns text on success error otherwise
// Lets the cron job clean up the other tables
function deleteEvent($id) {
  $request = \Slim\Slim::getInstance()->request();

  if (!$request->isDelete()) {
    echo '{"error": "REST call must be from an HTTP DELETE routing."}'; die;
  }

  $user_id = substr($request->params('user_id'), 0, 22);
  $secret_id = $request->params('secret_id');

  if (isNullOrEmptyString($id)) {
    echo '{"error": "Provide an id to delete an event."}'; die;
  }
  if (isNullOrEmptyString($user_id)) {
    echo '{"error": "Provide a valid user_id to delete an event."}'; die;
  }
  if (isNullOrEmptyString($secret_id)) {
    echo '{"error": "Provide a valid secret_id to delete an event."}'; die;
  }

  try {
    $dbx = getConnection();
    $query_del = 'DELETE FROM ' . $GLOBALS['event_t']
                                . ' WHERE id=:id AND user_id=:user_id AND secret_id=:secret_id';
    $state = $dbx->prepare($query_del);
    $state->bindParam("id", $id);
    $state->bindParam("user_id", $user_id);
    $state->bindParam("secret_id", $secret_id);
    $state->execute();

    $count = $state->rowCount();
    if ($count < 1) {
      echo '{"error": "The event could not be deleted."}';
      $dbx = NULL;
      die;
    }
    $dbx = NULL;
    echo '{"result": "OK"}';
  }
  catch (PDOException $e) {
    echo '{"error":"' . $e->getMessage() . '"}';
    $dbx = null;
    die;
  }
}

// Takes in an event id and new info and will update the event
// Returns the new event on success
function updateEvent($id) {
  $request = \Slim\Slim::getInstance()->request();

  if (!$request->isPut()) {
    echo '{"error": "REST call must be from an HTTP PUT routing."}'; die;
  }

  $user_id = substr($request->put('user_id'), 0, 22);
  $secret_id = $request->put('secret_id');

  $title = $request->put('title');
  $description = $request->put('description');
  $type = $request->put('type');
  $startDate = $request->put('start_date');
  $endDate = $request->put('end_date');

  // Check for the required parameters for updating.
  if (isNullOrEmptyString($id)) {
    echo '{"error": "Updating an event requires an id."}'; die;
  }
  if (isNullOrEmptyString($user_id)) {
    echo '{"error": "Please submit with a user_id parameter."}'; die;
  }
  if (isNullOrEmptyString($secret_id)) {
    echo '{"error": "Please submit with a secret_id parameter."}'; die;
  }

  // Ensure that some parameter has been updated.
  if (isNullOrEmptyString($title) && isNullOrEmptyString($description) &&
      isNullOrEmptyString($type) && isNullOrEmptyString($startDate) &&
      isNullOrEmptyString($endDate)) {
    echo '{"error": "Please provide some updated data for the event."}'; die;
  }

  try {
    $dbx = getConnection();

    // Build the query based on what properties have been updated.
    $query_update = 'UPDATE ' . $GLOBALS['event_t'] . ' SET ';
    if (!isNullOrEmptyString($title))
      $query_update .= 'title=:title, ';
    if (!isNullOrEmptyString($description))
      $query_update .= 'description=:description, ';
    if (!isNullOrEmptyString($type))
      $query_update .= 'type=:type, ';
    if (!isNullOrEmptyString($startDate))
      $query_update .= 'start_date=:start_date, ';
    if (!isNullOrEmptyString($endDate))
      $query_update .= 'end_date=:end_date ';

    // Trim a trailing comma, if necessary.
    if (endsWith($query_update, ', '))
      $query_update = substr($query_update, 0, -2) . ' ';

    $query_update .= 'WHERE id=:id AND user_id=:user_id AND secret_id=:secret_id';

    $state = $dbx->prepare($query_update);
    $state->bindParam("id", $id);
    $state->bindParam("user_id", $user_id);
    $state->bindParam("secret_id", $secret_id);
    if (!isNullOrEmptyString($title))
      $state->bindParam("title", $title);
    if (!isNullOrEmptyString($description))
      $state->bindParam("description", $description);
    if (!isNullOrEmptyString($type))
      $state->bindParam("type", $type);
    if (!isNullOrEmptyString($startDate))
      $state->bindParam("start_date", $startDate);
    if (!isNullOrEmptyString($endDate))
      $state->bindParam("end_date", $endDate);

    $state->execute();

    // Verify that an event was actually updated.
    $count = $state->rowCount();
    if ($count < 1) {
      echo '{"error": "The event was not updated."}';
      $dbx = NULL;
      die;
    }
    $dbx = null;

    // Return the changed values as JSON.
    $ret = '{"changes": {';
    if (!isNullOrEmptyString($title))
      $ret .= '"title":"' . $title . '",';
    if (!isNullOrEmptyString($description))
      $ret .= '"description":"' . $description . '",';
    if (!isNullOrEmptyString($type))
      $ret .= '"type":"' . $type . '",';
    if (!isNullOrEmptyString($startDate))
      $ret .= '"start_date":"' . $startDate . '",';
    if (!isNullOrEmptyString($endDate))
      $ret .= '"end_date":"' . $endDate . '"}}';

    // Trim a trailing comma, if necessary.
    if (endsWith($ret, ','))
      $ret = substr($ret, 0, -1) . '}}';

    echo $ret;
  }
  catch (PDOException $e) {
    echo '{"error":"' . $e->getMessage() . '"}';
    $dbx = null;
    die;
  }
}

function attendEvent() {
  $request = \Slim\Slim::getInstance()->request();
  $id = $request->post('id');
  $user_id = substr($request->post('user_id'), 0, 22);
  $ip = $request->getIp();

  if (isNullOrEmptyString($id)) {
    echo '{"error": "An ID number is required"}'; die;
  }
  if (isNullOrEmptyString($user_id)) {
    echo '{"error": "Internal user_id required."}'; die;
  }

  try {
    $dbx = getConnection();

    // See if the user has already attended.
    $queryCheck = "SELECT COUNT(*) FROM " . $GLOBALS['attend_t'] . " "
                . "WHERE id=:id AND user_id LIKE :user_id";
    $state = $dbx->prepare($queryCheck);
    $state->bindParam("id", $id);
    $state->bindParam("user_id", $user_id);
    $state->execute();
    $userAttendCount = (int)$state->fetchColumn();

    // If we have more than one row for a particular id and user, something is wrong.
    if ($userAttendCount > 1) {
      echo '{"error": "Internal server error."}';
      $dbx = NULL; die;
    }

    // The user has already attended the event if we have an attendance record.
    if ($userAttendCount == 1) {
      echo '{"result": "PREVIOUSLY_ATTENDED"}';
      $dbx = NULL; die;
    }

    // Verify that the same IP has not been spamming the same event with different user_ids.
    $queryIP = "SELECT COUNT(*) FROM " . $GLOBALS['attend_t'] . " "
             . "WHERE id=:id AND ip=INET_ATON(:ip)";
    $state = $dbx->prepare($queryIP);
    $state->bindParam("id", $id);
    $state->bindParam("ip", $ip);
    $state->execute();
    $ipAttendCount = (int)$state->fetchColumn();

    if ($ipAttendCount > 3) {
      echo '{"error": "Too many requests from the same IP."}';
      $dbx = NULL; die;
    }

    // Add an entry to remember that the user attended.
    $query = "INSERT INTO " . $GLOBALS['attend_t'] . " (id, user_id, ip) "
           . "VALUES (:id, :user_id, INET_ATON(:ip))";
    $state = $dbx->prepare($query);
    $state->bindParam("id", $id);
    $state->bindParam("user_id", $user_id);
    $state->bindParam("ip", $ip);
    $state->execute();

    echo '{"result": "OK"}';
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
    echo '{"error": "An ID number is required"}'; die;
  }

  try {
    $dbx = getConnection();

    // The number of selected rows indicates how many users have attended.
    $query = "SELECT * FROM " . $GLOBALS["attend_t"] . " WHERE id=:id";
    $state = $dbx->prepare($query);
    $state->bindParam("id", $id);
    $state->execute();
    $attendCount = $state->rowCount();

    echo '{"attending": "' . $attendCount . '"}';
    $dbx = NULL;
  }
  catch (PDOException $e) {
    echo '{"error": "' . $e->getMessage() . '"}';
    $dbx = NULL;
  }
}

// We allow users to report events for various reasons.
// The following `reason` codes are understood:
//   0. Inaccurate details
//   1. Offensive details
//   2. Promotes illegal activity
function reportEvent() {
  $request = \Slim\Slim::getInstance()->request();
  $id = $request->post('id');
  $user_id = substr($request->post('user_id'), 0, 22);
  $ip = $request->getIp();
  $reason = $request->post('reason');

  if (isNullOrEmptyString($id)) {
    echo '{"error": "An ID number is required."}'; die;
  }
  if (isNullOrEmptyString($user_id)) {
    echo '{"error": "Internal user_id required."}'; die;
  }
  if (isNullOrEmptyString($reason)) {
    echo '{"error": "A report reason code is required."}'; die;
  }

  try {
    $dbx = getConnection();

    // See if the user has already reported the event.
    $queryCount = "SELECT COUNT(*) FROM " . $GLOBALS['report_t'] . " "
                . "WHERE id=:id AND user_id LIKE :user_id";
    $state = $dbx->prepare($queryCount);
    $state->bindParam("id", $id);
    $state->bindParam("user_id", $user_id);
    $state->execute();
    $userReportCount = (int)$state->fetchColumn();

    // If we have more than one row for a particular id and user, something is wrong.
    if ($userReportCount > 1) {
      echo '{"error": "Internal server error."}';
      $dbx = NULL; die;
    }

    // The user has already reported the event if we have a report.
    if ($userReportCount == 1) {
      echo '{"result": "PREVIOUSLY_REPORTED"}';
      $dbx = NULL; die;
    }

    // Verify that the same IP has not been report spamming.
    $queryIP = "SELECT COUNT(*) FROM " . $GLOBALS['report_t'] . " "
             . "WHERE id=:id AND ip=INET_ATON(:ip)";
    $state = $dbx->prepare($queryIP);
    $state->bindParam("id", $id);
    $state->bindParam("ip", $ip);
    $state->execute();
    $ipReportCount = (int)$state->fetchColumn();

    if ($ipReportCount > 3) {
      echo '{"error": "Too many requests from the same IP."}';
      $dbx = NULL; die;
    }

    // Add an entry to remember that the user reported.
    $query = "INSERT INTO " . $GLOBALS['report_t'] . " (id, user_id, ip, reason) "
           . "VALUES (:id, :user_id, INET_ATON(:ip), :reason)";
    $state = $dbx->prepare($query);
    $state->bindParam("id", $id);
    $state->bindParam("user_id", $user_id);
    $state->bindParam("ip", $ip);
    $state->bindParam("reason", $reason);
    $state->execute();

    echo '{"result": "OK"}';
    $dbx = NULL;
  }
  catch (PDOException $e) {
    echo '{"error": "' . $e->getMessage() . '"}';
    $dbx = NULL;
  }
}
// This is used for Facebook Integration.
// Will display a webpage based on what event is queried
// Never called by phone is called by posts to persons wall
function displayEvent($id) {

  if (isNullOrEmptyString($id)) {
    echo 'Invalid Arguments';
    die;
  }

  try {
    $dbx = getConnection();

    $query = 'SELECT * FROM ' . $GLOBALS['event_t'] . ' WHERE id=:id';
    $state = $dbx->prepare($query);
    $state->bindParam("id", $id);
    $state->execute();
    
    $result = $state->fetch(PDO::FETCH_ASSOC);
    $dbx = NULL;
    display($result);
    }
  catch (PDOException $e) {
    $dbx = NULL;
    display("error");
  }
}


// Helper method for database connections.
// Also include variable $table in settings.php
function getConnection() {
  // The database credentials are kept out of revision control.
  include("$_SERVER[DOCUMENT_ROOT]/../settings.php");

  // Keep the table and salt variables available globally.
  $GLOBALS["event_t"] = $event_t;
  $GLOBALS["attend_t"] = $attend_t;
  $GLOBALS["report_t"] = $report_t;
  $GLOBALS["salt"] = $salt;

  $dbh = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
  $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  unset ($db_user, $db_pass);
  return $dbh;
}

// Validation helper (String is present and neither empty nor only white space)
function isNullOrEmptyString($field) {
  return (!isset($field) || trim($field) === '');
}

// Tests whether a string ends with a certain string.
function endsWith($input, $ending) {
  $length = strlen($ending);
  if ($length == 0)
    return true;
  return (substr($input, -$length) === $ending);
}

?>
