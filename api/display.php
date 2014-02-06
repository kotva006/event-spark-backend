<?php

// Create an HTML page for a given event.
function display($object, $attending = 0) {
  $header = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">'
          . '<html><head><title>Event Spark: Placing events at your finger tips</title>'
          . '<meta http-equiv="Content-type" content="text/html;charset=UTF-8">'
          . '<link rel="stylesheet" type="text/css" href="' . getAPIURL() . '/display.css"></head>';

  $bodyStart = '<body><a href="' . getBaseURL() . '"><img id="header" '
             . 'src="' . getBaseURL() . '/images/banner.png" />'
             . '</a><div id="text">';

  $htmlEnd = '</div></body></html>';

  $typeArray = array(1 => "Academics", 2 => "Athletics", 3 => "Entertainment",
                     4 => "Promotions", 5 => "Social", 0 => "Other");

  if ($object == "error") {
    $title = "<h1>An error has occurred, please try again.</h1>";
    echo $header,$bodyStart,$title,$htmlEnd;
    die;
  }
  if ($object == "no_event") {
    $title= "<h1>No event found.<h1>";
    echo $header,$bodyStart,$title,$htmlEnd;
    die;
  }

  $array = json_decode($object);
  $title = '<h1>' . $array->{'title'} . '</h1>';
  $map = '<div id="map">'
       . '<a href="https://maps.google.com/?ll=' . $array->{'latitude'} . ','
                                                 . $array->{'longitude'}
                                                 .'&marker=' . $array->{'latitude'} . ','
                                                 . $array->{'longitude'} . '" target="_blank">'
       . '<img src="http://maps.googleapis.com/maps/api/staticmap?center='
       . $array->{'latitude'} . ',' . $array->{'longitude'} . '&size=300x200'
       . '&zoom=17&sensor=true&markers=icon:' . getBaseURL() . '/Markers/'
       . $typeArray[$array->{'type'}] . '.png%7Cshadow:true%7C' . $array->{'latitude'} . ','
       . $array->{'longitude'} . '"></a></div>';

  $type = '<p>Type: ' . $typeArray[$array->{'type'}] . '</p>';
  $start = '<p>Start Time: ' . date("g\:i A F j\, Y", $array->{'start_date'}) . '</p>';
  $end =	 '<p>End Time: ' . date("g\:i A F j\, Y", $array->{'end_date'}) . '</p>';

  // If the attendance parameter is not passed in or is zero, do not display attendance.
  $attend = '';
  if ($attending > 0)
    $attend = '<p> Number of Attending: ' . $attending . '</p>';

  echo $header,$bodyStart,$map,$title,$type,$start,$end,$attend,$htmlEnd;
}

?>
