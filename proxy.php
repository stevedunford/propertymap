<?php
  $baseUrl = 'https://api.trademe.co.nz/v1/Search/Property/Residential.json?';
  foreach ($_GET as $key => $value) {
    $baseUrl .= "$key=$value";
    if (next($_GET)) $baseUrl .= '&'; // Don't add '&' for last item
  }
  echo file_get_contents($baseUrl);
?>
