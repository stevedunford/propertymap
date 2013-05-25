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
    <script type="text/javascript">


    var map;
    var baseUrl='proxy.php?';
    var localities = {};
    var region, district, suburb;
    var priceMin = 0;
    var priceMax = 400000;
    var rows = 25;
    var page = 1;
    var entryCount = 0;
    var landType = "Residential";
    var bedrooms;
    var error = 0;
    var ignorePrice = false;
    var day = 86400000; // 1 day in milliseconds
    var notBefore = 0;
    var localities_received_flag = false;
    var regions = {}; // { "Auckland": 1, "Bay Of Plenty": 2, "Canterbury": 3, "Gisborne": 4, "Hawkes Bay": 5, "Manawatu / Wanganui": 6, "Marlborough": 7, "Nelson / Tasman": 8, "Northland": 9, "Otago": 10, "Southland": 11, "Taranaki": 12, "Waikato": 14, "Wellington": 15, "West Coast": 16 };
    
    var districtId, suburbId;
    var suburbs = {};
    var districtList = [];
    var regionList = [];
    var geo = new google.maps.Geocoder();
    var tmp = {};


    $(window).resize(function() {
        resize();
    });

    function resize() {
        var height = $(window).height();
        $('#map_canvas').css('height', height - 20);
        $('#footer').css('margin-top', height - 18);
    }
    
    $('#message').html('<b>Loading location data from Trade Me<br />Please Wait</b>');

    jQuery.ajax({
        url: 'http://api.trademe.co.nz/v1/Localities.json', 
        dataType: 'json',
        async: false,
        success: function(data) {
            localities_received_flag = true;
            localities = eval(data);
            for (i in localities) {
                var region = localities[i];
                if (region['Name'] != 'All') {
                    regions[region['Name']] = region;
                }
            }
            window.console.log(regions);
        },
        error: function(data) {
            window.console.log("Failed to get localities from TM, can go no further...");
            window.console.log(data);
        }
    });

    $('#message').css('visibility', 'hidden');

    function initialize() {
        resize();

        $('#message').css('visibility', '');
        for (key in regions) {
            regionList.push(key);
        }

        $('#help_button').click(function() {
            showHelp();
        });
        $('body').css('background-image', ''); // Reset background in case spinner still running
        page = 1;
        entryCount = 0;
        var myOptions = {
            mapTypeId: google.maps.MapTypeId.ROADMAP
        };
        
        map = new google.maps.Map(document.getElementById('map_canvas'), myOptions);
        
        $("#spinner").spinner({
            min: 1,
            max: 14,
            disabled: true,
            change: function (event, ui) { setDateLimit($("#dateLimit").val()); }
        });
        $("#spinner").spinner("value", 3);
        $("#spinner").spinner("disable");

        $("#slider").slider({
            min: 0,
            max: 1000000,
            step: 50000,
            range: true,
            values: [priceMin, priceMax],
            create: function (event, ui) { setPrice(); },
            change: function (event, ui) { setPrice(); },
            slide: function (event, ui) {  priceMin = ui.values[0]; priceMax = ui.values[1]; setPrice(); }
        });
        $("#dateLimit").prop("checked", false);

        // Populate the region selector list if its not already
        if ($('#selectRegion')[0].options.length == 0) {
            $.each (regionList, function (index, value) {
                $('#selectRegion').append($('<option>', {
                    value: regions[value],
                    text: value }));
            });
            changeRegion();
        }
        else {
            changeDistrict();
        }
    }


    /**
     * Display the price range selected by the slider
     */ 
    function setPrice() {
        var prices = 'Between ';
        if (priceMin < 1000000) {
            prices += priceMin / 1000 + "k and ";
        }
        else {
            prices += priceMin / 1000000 + "M and ";
        }
        if (priceMax < 1000000) {
            prices += priceMax / 1000 + "k.";
        }
        else {
            prices += priceMax / 1000000 + "M.";
        }
        $("#price_range").html("<span id=\"dollars\">" + prices + "</span>");
    }


    /**
     * Generate a JSON request URL and submit it.
     * jsonError() is called if the GET fails
     * requestComplete() is called on success
     */
    function getData() {
        // First start the spinner to show we're busy
        $('body').css('background-image', 'url("working.gif")');

        // Then generate the URL ()
        baseUrl = (landType == "Lifestyle")? 'proxy.php?type=Lifestyle.json' : 'proxy.php?type=Residential.json';

        $("#data").html("<b>...populating, please wait...</b>");

        var thisUrl = baseUrl + '&region=' + regions[region]['LocalityId']
        if (districtId >= 0) {
            thisUrl += '&district=' + districtId;
        }
        if (suburbId.length > 0) {
            thisUrl += '&suburb=' + suburbId;
        }
        if (priceMin > 0) {
            thisUrl += '&price_min=' + priceMin;
        }
        if (priceMax > 0 && !ignorePrice) {
            thisUrl += '&price_max=' + priceMax;
        }
        bedrooms = parseInt(numRooms.options[numRooms.selectedIndex].value);
        if (bedrooms > 0) {
            thisUrl += '&bedrooms_min=' + bedrooms;
        }
        if (bedrooms == -1) {
            thisUrl += (landType == "Lifestyle")? '&property_type=BareLand' : '&property_type=section';
        }
        if ($("#dateLimit").is(":checked")) {
            thisUrl += '&date_from=' + notBefore;
        }
        thisUrl += '&page=' + page + '&rows=' + rows;
        window.console.log("URL = " + thisUrl);

        jQuery.ajax({
            url: thisUrl, 
            dataType: 'json',
            success: requestComplete,
            error: jsonError
        });
    }


    function requestComplete(response) {
        jQuery.extend(true, tmp, response); // Make tmp copy of the results for testing
        while (error < 3) { // Retry up to 3 times when null reponses received 
            if (response == null) { 
                error++;
                window.console.log("Received an empty response from TM?! (Retry " + error + ")");
                getData();
            }
            else { // Finally got a proper response
                break;
            }
            // Failed during retries, turn off the spinner and admit defeat
            
        }
        if (error >=3) {
            $('body').css('background-image', '');
            $("#data").html("<b>ERROR.  TradeMe returned no result.  Please try again</b>");
            return;
        }
        error = 0;
        var max = response.TotalCount
        var len = response.List.length;
        var startListing = (response.Page - 1) * rows
        window.console.log("Returning properties " + startListing + " to " + (startListing + len) + " of " + max);
        var progress = Math.floor((startListing + len) / max * 100);
        $('#progress').css('width', progress.toString());
        for (var i = 0; i < len; i++) {
            // Add some jitter to the location to allow for multiple listings on the same spot
            var latitude  = null;
            var longitude = null;
            var latJitter = (Math.random() - 0.5) / 5000;
            var lonJitter = (Math.random() - 0.5) / 5000;
            try {
                latitude  = response.List[i].GeographicLocation.Latitude;
                longitude = response.List[i].GeographicLocation.Longitude;
            }
            catch(err) { // The property listing has no lat or lng provided
                var retval = geo.geocode({ 
                    'address': response.List[i].Address, 
                    'region': 'NZ'
                }, function(results, status) {
                    if (status == google.maps.GeocoderStatus.OK) {
                        latitude  = results[0].geometry.location.jb;
                        longitude = results[0].geometry.location.kb;
                    }
                    else { // No lat or lng could be deduced, log error and continue
                        window.console.log("Failed to geocode an address");
                    }
                });
            }
            if (latitude == null || longitude == null) {
                continue; // Deal with no lat or lng provided and a failed geocode
            }
            var id = response.List[i].ListingId
            var tl = response.List[i].Title
            var ad = response.List[i].Address
            var pd = response.List[i].PriceDisplay;
            var title  = (id == null)? "" : id + "\n";
            title += (tl == null)? "" : tl + "\n";
            title += (ad == null)? "" : ad + "\n";
            title += (pd == null)? "" : pd;
            var price = Math.floor(extractPrice(response.List[i].PriceDisplay) / 100000);
            var pinColor = generatePinColor(price);
            var iconChar = (price <= 0)? "X" : price.toString(); // 
            iconChar = (price > priceMax / 100000 && !ignorePrice)? "$" : iconChar; // Property possibly has a deceptive search price 
            iconChar = (price == -1)? "A" : iconChar; // Property is up for auction
            var pinImage = generatePinImage('d_map_pin_letter', iconChar + "|" + pinColor, 21, 34, 10, 34);
            var pinShadow = generatePinImage('d_map_pin_shadow', '', 40, 37, 12, 35);
            var options = {
                position: new google.maps.LatLng(latitude + latJitter, longitude + lonJitter),
                optimized: true,
                title: title,
                icon: pinImage,
                shadow: pinShadow
            };
            var marker = new google.maps.Marker(options);
            marker.setMap(map);
            var image = response.List[i].PictureHref; // Preview thumbnail
            google.maps.event.addListener(marker, 'click', showListing(marker));
            google.maps.event.addListener(marker, 'mouseover', showThumb(title, image)); 
            google.maps.event.addListener(marker, 'mouseout', function() { $("#thumb").html(null); $("#details").html(null); });
        }
        entryCount += response.PageSize;
        if (entryCount < max) { // There is still more data to download
            page ++;
            getData();
        }
        else {
            // We've got all the data
            $('body').css('background-image', '');
            page = 1;  // reset the page and entry count
            entryCount = 0;
            $("#data").html("<b>Finished! Hover over markers for more info, click them for the page.</b>");
        }
    }


    /**
     * Pins are light-blue to dark blue for cheap to expensive properties respectively
     * Red indicates no price provided in listing
     * Green indicates property is up for auction
     */
    function generatePinColor(price) {
        var pinColor;
        if (price == 0) {
            pinColor = "ff0000";
        }
        else if (price == -1) {
            pinColor = "00ff00";
        }
        else if (price > priceMax / 100000) {
            pinColor = "ffffff";
        }
        else {
            var cl = (price > 15)? 0 : 15 - price; 
            var color = cl.toString(16); // convert to single-digit hex value
            pinColor = color + color + color + color + "ff";
        }
        return pinColor;
    }


    /**
     * Creat the pin of the required type with required size and offset
     * using the Google Dynamic Icon library (deprecated but still functioning)
     * https://developers.google.com/chart/infographics/docs/dynamic_icons#pins
     */
    function generatePinImage(chst, chld, s1, s2, p1, p2) {
        var addr = "http://chart.apis.google.com/chart?chst=" + chst;
        addr += (chld == '')? '' : ('&chld=' + chld);
        return new google.maps.MarkerImage(addr,
            new google.maps.Size(s1, s2),
            new google.maps.Point(0, 0),
            new google.maps.Point(p1, p2));
    }


    function jsonError(response) {
        window.console.log("Data Request Fail (is your internet connected?)\n");
        window.console.log(response);
    }


    /**
     * Takes the price field from the auction data and 
     * returns the int price value, -1 if the property
     * is up for auction or 0 for no price given (or
     * unable to determine)
     */
    function extractPrice(price) {
        var dollarIndex = price.indexOf('$');
        if (dollarIndex > 0) {
            price = price.substr(dollarIndex + 1);
            while (price.indexOf(',') >= 0) {
                price = price.replace(",", "");
            }
            try {
                price = parseInt(price);
            } catch (err) { // This shouldn't happen, but just in case...
                window.console.log("Price could not be determined");
                price = 0;
            }
        }
        else if (price.indexOf("auction") > 0) {
            price = -1; // property to be auctioned
        }
        else {
            price = 0; // price by f#$%ing negotiation
        }
        return price;
    }

 
    /**
     * Pops up a window with the property details 
     */
    function popitup(url) {
        newwindow = window.open(url, 'Property', 'height=900, width=1000, scrollbars=1, location=1, status=1, resizable=1');
        if (window.focus) {
            newwindow.focus()
        }
        return false;
    }


    /**
     * Uses the Google Geocode API to look up the area
     * selected by the region/district dropdowns
     * return values are non-functional hence the map
     * manipulation internally
     */
    function geocode(address) {
        var retval = geo.geocode({ 
            'address': address, 
            'region': 'NZ'
        }, function(results, status) {
            if (status == google.maps.GeocoderStatus.OK) {
                map.fitBounds(results[0].geometry.bounds);
                window.console.log(results[0]);
                return results
            }
            else {
                window.console.log(status);
            }
        });
        return retval;
    }


    function changeSuburb() {
        suburb = $('#selectSuburb').find(":selected").text();
        suburbs = $('#selectSuburb').val(); // Get selected suburb(s)
        suburbId = "";
        if (suburbs.length > 0) {
            $.each(suburbs, function(index, value) {
                suburbId += value + ","; // Add each, comma separated for TM query
            });
            suburbId = suburbId.slice(0, -1); // Remove last comma
        }
        window.console.log("Suburb ID(s) = " + suburbId);
        
    }


    function changeDistrict() {
        district = $('#selectDistrict option:selected').text();
        districtId = $('#selectDistrict option:selected').val();
        window.console.log("District ID = " + districtId);
        window.console.log("Geocoding " + district);
        var results = geocode((district == "All")? region : district + " District, New Zealand");
        // Ugly step through to find right district
        $('#selectSuburb').empty(); // Clear the list first
        $('#selectSuburb').append($('<option>', {
                        value: -1,
                        text: 'All Suburbs' }));
        if (districtId == -1) {
            $('#selectSuburb').prop('disabled', true); //css('visibility', 'hidden');
            return;
        }
        $('#selectSuburb').prop('disabled', false); //css('visibility', 'visible');
        
        for (index in regions[region]['Districts']) {
            if (regions[region]['Districts'][index]['Name'] == district) {
                $.each(regions[region]['Districts'][index]['Suburbs'], function (index, value) {
                    $('#selectSuburb').append($('<option>', {
                        value: value['SuburbId'],
                        text: value['Name'] }));
                });
            }
        }
        
        changeSuburb()
    }


    function changeRegion() {
        region = $('#selectRegion').find(":selected").text();
        $('#selectDistrict').empty(); // Clear the list first
        $('#selectDistrict').append($('<option>', {
                        value: -1,
                        text: 'All' }));
        $.each (regions[region]['Districts'], function (index, value) {
            window.console.log("Adding " + value['Name'] + " as value " + value['DistrictId']);
            $('#selectDistrict').append($('<option>', {
                value: value['DistrictId'],
                text: value['Name'] 
            }));
        });
        changeDistrict(); // Update the map based on district choice
    }


    function changeType() {
        landType = propertyType.options[propertyType.selectedIndex].value;
        window.console.log('Land type is ' + landType);
    }


    function showListing(id) {
        return function() {
            id.setIcon('http://www.google.com/intl/en_us/mapfiles/ms/micons/blue-dot.png');
            popitup('http://www.trademe.co.nz/Browse/Listing.aspx?id=' + id.getTitle().split('\n')[0])
        };
    }


    function showThumb(detail, image) {
        return function() {
            $("#thumb").html("<img src=" + image + " style=\"width: 85px;\">");
            var details = detail.split('\n'); // var details = id.getTitle().split('\n')
            details[2] = (details[2] == null)? "" : details[2];
            details[3] = (details[3] == null)? "" : details[3];
            $("#details").html("<b>" + details[1] + "</b><br />" + details[2] + "<br />" + details[3]);
        };
    }


    function changeIgnorePrice(checkbox) {
        ignorePrice = checkbox.checked;
        $("#slider").slider({ disabled: ignorePrice });
        var clr = (ignorePrice)? "silver" : "black";
        $("#dollars").css({ "color" : clr });
    }


    function setDateLimit(checked) {
        $("#spinner").spinner("option", "disabled", !checked);
        var thisDate = (new Date().valueOf()) - (day * ($("#spinner").spinner("value")));
        var date = new Date(thisDate);
        notBefore = date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate();
    }


    function showHelp() {
        if ($("#help").is(":hidden")) {
            $("#help").slideDown("slow");
        }
        else {
            $("#help").slideUp("slow");
        }
    }

    </script>
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
          <select id="selectRegion" name="selectRegion" onchange="changeRegion()">
          </select>
          <select id="selectDistrict" name="selectDistrict" onchange="changeDistrict()">
          </select>
          <select multiple id="selectSuburb" name="selectSuburb" onchange="changeSuburb()">
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

