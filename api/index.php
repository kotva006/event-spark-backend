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

function getEventsByLocation() {
  $request = Slim::getInstance()->request();
  $event = json_decode($request->getBody());

//The following use of $event expects json_decode to return as follows:
//[latitude=>"the latitude", longitude=>"the longitue", type=>"the type"]
//Simple error checking for valid inputs
  if ($event['longitude'] == NULL || $event['latitude'] == NULL){
		echo '{"error":{"text":"invalid inputs for query"}}';
    die;
  }

  //This is implementing a search range.  Will need to fine tune.
  $latsma = $event['latitude'] - .5;
  $latbig = $event['latitude'] + .5;
  $lonsma = $event['longitude'] - .5;
  $lonbig = $evnet['longitude'] + .5;
	$type = $event['type'];

  //This is a long wany to create our query
	if ($type == NULL){
		$query = "select * from $table where longitude<$lonbig and longitude>$lonsma";
		$query = $query . " and latitude<$latbig and latitude>$latsma";
	}
	else{
		$query = "select * from $table where type=$type and longitude<$lonbig and";
		$query = $query . " longitude>$lonsma and latitude<$latbig and latitude>$latsma";
  }

	try {

		$dbx = getConnection();
		$dbx->preapre($query);
		$dbx->execute();
		while($row = $dbx->fetch()){
			echo json_encode($row);
			//returns multiple json_objects instead of one.
			//need to create a name for our location objects if we wish
			//to return an object of our objects
    }
    $dbx=Null;
  }
  catch (PDOException $e){
		echo '{"error":{"text":'. $e->getMessage() . '}}';
    $dbx=Null;
    die;
  }
}



//Assumes a json like above
function createEvent() {
  $request = Slim::getInstance()->request();
  $event = json_decode($request->getBody());
  // Add the event information into the SQL Database
  $query = "insert into $table set (title, description, location, longitude, latitude, type, time)";
	$query = $query . ' values (:title, :description, :location, :longitude, :latitude, :type, :time)';

	//Error checking for valid inputs
	if ($event['title'] == Null || $event['longitude'] == Null || $event['latitude'] == Null){
		echo '{"error":{"text":"invalid inputs for create"}}';
    die;
	}


  try {

    $dbx = getConnection();
    $state = $dbx->preapre($query);
		$state->bindParam("title", $event->title);
		$state->bindParam("description", $event->description);
		$state->bindParam("location", $event->location);
		$state->bindParam("longitude", $event->longitude);
		$state->bindParam("latitude", $event->latitude);
		$state->bindParam("type", $event->type);
		$state->bindParam("time", $event->time);
		//return if succeeds
    echo '{"bool":' . $state->execute() . '}';
    $dbx=Null;
  }
  catch (PDOException $e){
		echo '{"error":{"text":'. $e->getMessage() . '}}';
    $dbx=Null;
    die;
  }
}

// Helper method for database connections.
function getConnection() {
  // The database credentials are kept out of revision control.
  include("/path_to_db_settibgs.php");
  $dbh = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
  $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  unset ($db_user, $db_pass);
  return $dbh;
}

?>
