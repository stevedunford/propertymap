<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">

<!-- NZ Property For-Sale Finder

    Copyright 2013 Steve Dunford.  
    
    See the end of this document (or the bottom of the page if you are 
    viewing it in a browser) for licensing details.

    I'd also appreciate it if you would please contact me 
    (steve@essentialtech.co.nz) to let me know what you are using it for,
    and where.  

    Finally, this code has an additional beerware licence - if you use
    it then I'd appreciate it if you somehow bought me a beer : ) 
-->


<!-- ?php
  // OAuth code by James Sleeman, Gogo Internet Services Limited
  // https://github.com/sleemanj/gogoTradeMe
  ini_set('display_errors', 'On');

  require_once('include/init.php');   

  if(!isset($_REQUEST['Reauthorise']))
  {        
    $RequestToken = trademe()->get_request_token();
    store_token($RequestToken);
    $RedirectURL = trademe()->get_authorize_url();
    header('location: '.$RedirectURL );
    exit;
  }
  else {
    $_REQUEST['Authorise'] = 1;
  }
? -->

<html>
  <head>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBrxdl_paLNP-w55wXbwK11tvJ3UQf2u_E&sensor=false" type="text/javascript"></script>
    <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
    <script type="text/javascript" src="http://code.jquery.com/ui/1.9.2/jquery-ui.js"></script>
    <script type="text/javascript" language="javascript" src="lytebox.js"></script>
    <link rel="stylesheet" href="lytebox.css" type="text/css" media="screen" />
    <link rel="stylesheet" href="http://code.jquery.com/ui/1.9.2/themes/base/jquery-ui.css">
    <link href='http://fonts.googleapis.com/css?family=Milonga' rel='stylesheet' type='text/css'>
    <link rel="stylesheet" href="style.css">
    <script type="text/javascript" src="mapcode.js"></script>
  </head>
  <body onload="initialize()" style="background-repeat: no-repeat;">
    <div id="help">
        <div style="float: right"><input type="button" onclick="showHelp()" id="help_hide_button" value="Hide Help"></div><div><p class="heading">NZ Property Search Help</p></div>
        <strong>Residential / Lifestyle:</strong> Select property in town, or in the country<br />
        <strong>Sections / Bedrooms:</strong> Select sections or the minimum number of bedrooms you are looking for<br />
        <strong>Area to search:</strong> Select the region, district (optional - select 'All' to show the whole region), and suburb (again, optional)<br />
        <strong>Price:</strong> Select the price range with the two sliders ($0 - $1 million), or tick 'Any' to search any price.<br />
        <strong>Date:</strong> Choose only properties that were listed in the last 'x' days by ticking the box and choosing the appropriate number of days<br />
        <br />Hover over the marker for details, or click it for a popup window with the listing.  The price search only works if the person who made the listing set a proper search price value - if, like many agents, they listed with a very low search price* then the property will show up almost regardless of the price setting.  I have attempted to indicate this by showing the property with a white marker and a '$'.  The other problem relates to properties which are going to auction, and more commonly properties with 'Price by negotiation'*.  These show up as a green marker with an 'A' and a red marker with an 'X' respectively.</strong>
        <p><b>*</b><i> May the fleas of 1000 camels infest these peoples armpits forever.  They are perfectly able to tell you the price if you ask, but for some reason appear unwilling to share it in the place it would be most useful to people looking for a property - the listing itself.  And in the case of the search price being set far too low: they seem to think people on a lower budget would be interested in wading through multi-million-dollar mansions.  Idiots.</i></p>
        <a rel="license" href="http://creativecommons.org/licenses/by-nc/3.0/deed.en_US"><img alt="Creative Commons License" style="border-width:0" src="http://i.creativecommons.org/l/by-nc/3.0/88x31.png" /></a> Licensed under a <a rel="license" href="http://creativecommons.org/licenses/by-nc/3.0/deed.en_US">Creative Commons Attribution-NonCommercial 3.0 Unported License</a>.
    </div>
    <div id="help_button"<strong>Property Search Help</strong></div>
    <h1 class="transparent30" unselectable="on">New Zealand Property Search</h1>
    <div id="thumb"></div>
    <div id="details"></div>
    <div id="footer">
        <div style="float: left; border: 1px solid black; width: 102px; height: 10px; margin: 4px 5px 0 0;">
            <div style="float: left; width: 0px; height: 8px; background-color: #333; margin: 1px 0 0 1px;" id="progress"></div>
        </div>
        <div style="float: left" id="data"></div>
        <div id="feedback" style="float: right; text-align: right;">Feedback or comments to <a href="mailto:propertysearch@essentialtech.co.nz">propertysearch@essentialtech.co.nz</a></div>
        </div>
    <div id="map_wrapper">
      <div id="controls" class="transparent90">
          <select id="selectRegion" name="selectRegion" onchange="changeRegion()">
          </select>
          <select id="selectDistrict" name="selectDistrict" onchange="changeDistrict()">
          </select>
          <select multiple id="selectSuburb" name="selectSuburb" onchange="changeSuburb()">
          </select>
          <input type="checkbox" id="adjacentSuburbs"> Also search surrounding suburbs</input>
          <hr>
          <select id="propertyType" onchange="changeType()">
            <option value="Residential">Residential</option>
            <option value="Lifestyle">Lifestyle</option>
          </select>
          <select id="numRooms">
            <option value="0">Include Sections</option>
            <option value="-1">Only Sections</option>
            <option value="1">1 Bedrooms</option>
            <option value="2">2 Bedrooms</option>
            <option value="3">3 Bedrooms</option>
            <option value="4">4 Bedrooms</option>
            <option value="5">5+ Bedrooms</option>
          </select>
          <hr>
          <div id="pricing" style="vertical-align: middle">
            Price: (<input type="checkbox" name="ignorePrice" onclick="changeIgnorePrice(this)">Any)</input>
            <div id="slider"></div>
            <div id="price_range" style="width: 200px; height: auto; padding: 5px; display: inline-block"></div>
            
          </div>
          <hr>
          <input type="checkbox" id="dateLimit" onclick="setDateLimit(this.checked)">Listed in the last <input id="spinner" name="days" style="width: 30px" /> days</input>
          <input type="button" onclick="getData()" id="search_button" value="Search Now!">&nbsp;&nbsp;
          <input type="button" onclick="initialize()" value="Reset Map">
      </div> <!-- end controls -->
    
        <div id="map_canvas"></div>
    </div>
    <br />
    
</body>
</html>

