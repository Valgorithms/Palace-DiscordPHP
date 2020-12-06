<?php
/*
<Address ID="1">
<FirmName>XXXY COMP</FirmName>
<Address2>8 WILDWOOD DR</Address2>
<City>OLD LYME</City>
<State>CT</State>
<Urbanization>YES</Urbanization>
<Zip5>06371</Zip5>
<Zip4>1844</Zip4>
</Address>
</ZipCodeLookupResponse>
*/
$userid = "315VALZA8001";
$address2 = "6406 Ivy Lane";
$city = "Greenbelt";
$state = "MD";
$urlstring = "http://production.shippingapis.com/ShippingAPITest.dll?API=ZipCodeLookup&XML=";
$xmlstring = "<ZipCodeLookupRequest USERID='$userid'><Address ID='0'><Address1></Address1><Address2>$address2</Address2><City>$city</City><State>$state</State></Address></ZipCodeLookupRequest>";
//print($xml->asXML());
//$author_channel->sendMessage($urlstring . urlencode($xmlstring));
?>