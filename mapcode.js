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
var ignorePrice = false;
var day = 86400000; // 1 day in milliseconds
var notBefore = 0;
var localities_received_flag = false;
var regions = {}; // { "Auckland": 1, "Bay Of Plenty": 2, "Canterbury": 3, "Gisborne": 4, "Hawkes Bay": 5, "Manawatu / Wanganui": 6, "Marlborough": 7, "Nelson / Tasman": 8, "Northland": 9, "Otago": 10, "Southland": 11, "Taranaki": 12, "Waikato": 14, "Wellington": 15, "West Coast": 16 };

var districtId, suburbId;
var suburbs = {};
var geo = new google.maps.Geocoder();
var tmp = {};

/**
 * Download the localities info from Trade Me
 * Async: false to stop the code until its complete
 * otherwise the drop-down boxes don't fill
 */
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


/**
 * Resize the map to full-screen whenever the window size is adjusted
 */
$(window).resize(function() {
    resize();
});


/**
 * Resize function - split out from window.resize so it can be called during init
 */
function resize() {
    var height = $(window).height();
    $('#map_canvas').css('height', height - 20);
    $('#footer').css('margin-top', height - 18);
}

/**
 * Initialise - set up the display
 */
function initialize() {
    resize();

    // Set up region dropdown, and cascade that to district and suburb
    for (key in regions) {
        $('#selectRegion').append($('<option>', {
            value: regions[key]['LocalityId'],
            text: key 
        }));
    }
    changeRegion();

    $('#help_button').click(function() {
        showHelp();
    });

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
    if ($('#adjacentSuburbs').is(':checked')) {
        thisUrl += '&adjacent_suburbs=true'
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
    if (response == null) { 
        window.console.log("Received an empty response from TM");
        $("#data").html("<b>ERROR.  TradeMe returned no result.  Please try again</b>");
        return;
    }
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
        var price = Math.floor(extractPrice(response.List[i].PriceDisplay) / 1000);
        window.console.log("Price $" + price)
        var pinColor = generatePinColor(price);
        var charColour = (pinColor == 'ffffff')? '000000' : 'ffffff';
        var iconChar = (price <= 0)? "X" : price.toString(); // 
        iconChar = (price > (priceMax / 1000 + 500)  && !ignorePrice)? "$" : iconChar; // Property possibly has a deceptive search price 
        iconChar = (price == -1)? "A" : iconChar; // Property is up for auction
        //var pinImage = generatePinImage('d_map_pin_letter', iconChar + "|" + pinColor, 21, 34, 10, 34);
        var pinImage = generatePinImage('d_map_spin',  "0.6|1|" + pinColor + "|10|_|" + iconChar, 21, 34, 10, 34);
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
    else if (price > priceMax / 1000 + 500) {
        pinColor = "ffffff";
    }
    else {
        price = Math.floor(price / 100);
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
        $('#selectSuburb').prop('disabled', true);
        $('#adjacentSuburbs').prop('checked', false);
        $('#adjacentSuburbs').attr("disabled", true);
        return;
    }
    $('#selectSuburb')[0].selectedIndex = 0;
    $('#selectSuburb').prop('disabled', false);
    $('#adjacentSuburbs').removeAttr('disabled');
    
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
    $("#help").animate({height: 'toggle', opacity:'toggle'}, 'slow');//slideDown("slow");
}
