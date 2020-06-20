<?php

// get this data by logging into icloud.com on the calendars page and looking at the dev tools
// as per https://translate.google.com/translate?sl=de&tl=en&js=y&prev=_t&hl=de&ie=UTF-8&u=http%3A%2F%2Fnico-beuermann.de%2Fblogging%2Farchives%2F115-Zugriff-auf-iCloud-Kalender-mit-Thunderbird.html&edit-text=&act=url
$account = array(
    'server'   => 'p21', // note, this will be p12 or something, not P0; see the server that iclod.com serves json from
    'icloudid' => '1045960302', // the "dsid"
    'appleid'  => 'matthewblymer@gmail.com', // your Apple ID; will be an email address
    'pass'     => 'llij-fjrq-exxt-wydz', // password for your Apple ID
    'calid'    => 'e9aa082c16127852e658edde43361ce98108e20df37a428d8a261f45b1cc24f8' // the "pGuid"
);

$url = 'https://' . $account['server'] . '-caldav.icloud.com:443/' .
    $account['icloudid']. '/calendars/' . $account['calid'] . '/';

//curl -s -X PROPFIND -u "matthewblymer@gmail.com" -H "Depth: 1"  --data "<propfind xmlns='DAV:'><prop><displayname/></prop></propfind>"  https://p21-caldav.icloud.com:443/1045960302/calendars/e9aa082c16127852e658edde43361ce98108e20df37a428d8a261f45b1cc24f8/
//https://p42-caldav.icloud.com:443/347723822/calendars/086e6106-1c0d-4301-a99d-5c5ebbcc5079/


//llij-fjrq-exxt-wydz

//curl -s -X PROPFIND -u "matthewblymer@gmail.com" -H "Depth: 0"  --data "<propfind xmlns='DAV:' xmlns:cd='urn:ietf:params:xml:ns:caldav'><prop><cd:calendar-home-set/></prop></propfind>" https://caldav.icloud.com/1045960302/principal/

//curl -s -X PROPFIND -u "matthewblymer@gmail.com" -H "Depth: 1"  --data "<propfind xmlns='DAV:'><prop><displayname/></prop></propfind>"  https://p21-caldav.icloud.com:443/1045960302/calendars/

$headers = array(
    'Content-Type: text/xml',
    'Depth: 1'
);

//$body = "<propfind xmlns='DAV:'><prop><calendar-data xmlns='urn:ietf:params:xml:ns:caldav'/></prop></propfind>";
$body = "<propfind xmlns='DAV:' xmlns:cd='urn:ietf:params:xml:ns:caldav'><prop><cd:calendar-home-set/></prop></propfind>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, $account["appleid"] . ":" . $account["pass"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_USERAGENT, 'curl/7.35.0');
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PROPFIND");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$data = curl_exec($ch);
curl_close($ch);

// suppress warnings because PHP < 5.3 complains about not being able to parse xmlns DAV
@$xml = simplexml_load_string($data);
$events = array();
$timezone = null;
$header = "BEGIN:VCALENDAR\nCALSCALE:GREGORIAN\nPRODID:-//Apple Inc.//iOS 9.1//EN\nVERSION:2.0";
$footer = "END:VCALENDAR\n";

var_dump($xml->response );
die();

// combine all the separate iCal files into one big iCal file
foreach($xml->response as $response) {
    foreach($response->propstat as $propstat) {
        $caldata = $propstat->prop->{'calendar-data'};
        if (strlen($caldata) == 0) { continue; }
        $accum_timezone = array();
        $in_timezone = false;
        $accum_event = array();
        $in_event = false;
        foreach(explode("\n", $caldata) as $line) {
            if ($line == "BEGIN:VTIMEZONE" && is_null($timezone)) { $in_timezone = true; }
            if ($line == "BEGIN:VEVENT") { $in_event = true; }
            if ($in_timezone) { $accum_timezone[] = $line; }
            if ($in_event) { $accum_event[] = $line; }
            if ($line == "END:VTIMEZONE") {
                $in_timezone = false;
                if (count($accum_timezone) > 0) {
                    $timezone = join("\n", $accum_timezone);
                }
            }
            if ($line == "END:VEVENT") {
                $in_event = false;
                if (count($accum_event) > 0) {
                    $events[] = join("\n", $accum_event);
                }
            }
        }
    }
}
//header("Content-Type: text/calendar");
echo $timezone . "\n" . join("\n", $events);
?>
