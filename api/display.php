<?php

function display($object) {
  echo "<html><title>Event Spark</title><body>";
  if ($object == "error") {
    echo "<h1>An Error Happened Please Try Again</h1>";
    end_html();
  }
  if ($object == "no_event") {
    echo "<h1>No event found<h1>";
    end_html();
  }
  $array = json_decode($object);
  echo '<h1>Title: ' . $array->{'title'} . '</h1>\n';

}

function end_html() {
  echo "</body></html>";
  die;
}

?>
