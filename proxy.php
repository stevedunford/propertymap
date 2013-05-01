<?php
  $baseUrl = 'https://api.trademe.co.nz/v1/Search/Property/';
  foreach ($_GET as $key => $value) {
    if ($key == "type") {
        $baseUrl .= $value . "?";
    }
    else {
        $baseUrl .= "$key=$value";
        if (next($_GET)) $baseUrl .= '&'; // Don't add '&' for last item
    }
  }
  $retval = file_get_contents($baseUrl);
  return $retval;
?>
