/** This is the proxy file to get data from trademe and pass it to the Javascript to get
    around Javascript security rules where a domain can only get from its own domain **/
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
  $url = rtrim($baseUrl, '&');

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  $retval = curl_exec($ch);
  curl_close($ch);
  error_log("TradeMe URL trying: ".$url, 0);
  error_log("Repsonse: $retval");
  //$retry = 0;
  //while ($retry < 10) {
  //  $retry = $retry + 1;
   // $retval = file_get_contents($url);
  //  if ($reval != null) {
  //    return $retval;
  //  }
  //}
  echo $retval;
?>
