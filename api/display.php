<?php

function display($object) {
  if ($object == "error") {
    echo "<html><title>Arrows</title><body><h1>"
         ."An Error Happened Please Try Again</h1></body></html>" ;
    die;
  }

}

?>
