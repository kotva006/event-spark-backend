<?php
// REST API for the Mobile App Challenge backend.

// This is a basic "Hello World" example usage of the Slim Framework.
// See http://coenraets.org/blog/2011/12/restful-services-with-jquery-php-and-the-slim-framework/
// for an idea of how to implement GET/POST/etc handlers.

require 'Slim/Slim.php';
\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

$app->get('/events/:id', 'getEvent');
$app->get('/events/search/:query', 'getEventsByLocation');
$app->post('/events', 'createEvent');

$app->run();

function getEvent($id) {
  // Get information about a particular event. Necessary?
  // This may be useful for debugging but not used in practice.
}

function getEventsByLocation() {
  $request = Slim::getInstance()->request();
  $event = json_decode($request->getBody());
  // Parse the long/lat and perform a SQL query.
}

function createEvent() {
  $request = Slim::getInstance()->request();
  $event = json_decode($request->getBody());
  // Add the event information into the SQL Database
}

// Helper method for database connections.
function getConnection() {
  // The database credentials are kept out of revision control.
  include("/location-outside-webroot/db_settings.php");
  $dbh = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
  $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  unset ($db_user, $db_pass);
  return $dbh;
}

?>
