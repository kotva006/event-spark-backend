<?php

function display($object, $attending) {
$header = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">'
         .'<html><head><title>Event Spark: Placing events at your finger tips</title>'
         .'<link rel="stylesheet" type="text/css" href="http://saypoint.dreamhosters.com/api/display.css"></head>';
		 
$bodyStart = '<body><a href="http://saypoint.dreamhosters.com"><img id="header"'
             .'src="http://saypoint.dreamhosters.com/images/banner.png">'
            .'</a><div id="text">';
		 
$htmlEnd = '</div></div></body></html>'; 
		 
$typeArray = array(1 => "Academics", 2 => "Athletics", 
                   3 => "Entertainment", 4 => "Promotions", 
				   5 => "Social", 0 => "Other");
				   
if ($object == "error") {
    $title = "<h1>An Error Happened Please Try Again</h1>";
    echo $header,$bodyStart,$title,$htmlEnd;
    die;	
  }
if ($object == "no_event") {
    $title= "<h1>No event found<h1>";
    echo $header,$bodyStart,$title,$htmlEnd;
    die;
  }
$array = json_decode($object);
$title = '<h1>' . $array->{'title'} . '</h1>';
$map = '<div id="map">'
	  .'<a href="https://maps.google.com/?ll='.$array->{'latitude'}.','.$array->{'longitude'}.'&marker='.$array->{'latitude'}.','.$array->{'longitude'}.'" target="_blank">'
	  .'<img src="http://maps.googleapis.com/maps/api/staticmap?center='.$array->{'latitude'}.','.$array->{'longitude'}.'&size=300x200'
	  .'&zoom=17&sensor=true&markers=icon:http://saypoint.dreamhosters.com/Markers/'
	  .$typeArray[$array->{'type'}] . '.png%7Cshadow:true%7C'.$array->{'latitude'}.','.$array->{'longitude'}.'"></a></div>';

$type = '<p>Type: '.$typeArray[$array->{'type'}].'</p>';
$start = '<p>Start Time: '.date('g\:i A F j\, Y', $array->{'start_date'}).'</p>';
$end =	 '<p>End Time: '.date('g\:i A F j\, Y', $array->{'end_date'}). '</p>';
$attend = '<p> Number of Attending: ' . $attending . '</p>';

echo $header,$bodyStart,$title,$map,$type,$start,$end,$attend,$htmlEnd;

}

?>