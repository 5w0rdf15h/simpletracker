<?php

date_default_timezone_set('UTC');
$testDomains = array('test.com', 'private.com', 'facebook.com', 'twitter.com');

function readUa() {
    $allMatches = array();
    $f = fopen('ua.xml', 'r');
    while (($buffer = fgets($f, 4096)) !== false) {
        preg_match('~<useragent description="(.*)" useragent="(.*)"\s+appcodename~ims',
                   $buffer, $matches);
        if (count($matches)) {
            $ua = $matches[2];
            if (!in_array($ua, $allMatches)) {
                $allMatches[] = $ua;
            }
        }
    }
    fclose($f);
    return $allMatches;
}
$uaList = readUa();

function getRandomUa($uaList) {
    return $uaList[rand(0, count($uaList) -1)];
}

function getRandomIp() {
    $ip = rand(193, 250) . '.' . rand(1, 254) . '.' . rand(1, 254) . '.' . rand(1, 254);
    return $ip;
}

function getRandomUid() {
    return rand(1, 10000) . '-' . rand(40, 34343);
}

function getRandomDomain() {
    global $testDomains;
    return $testDomains[rand(0, count($testDomains) - 1)];
}

function getRandomUrl() {
    return 'http://' . getRandomDomain() . '/' . rand(1, 1000000) . '.php';
}

function getRandomReferer() {
    $r = array('http://www.google.com/', 'http://www.bing.com/',
        'http://www.yahoo.com/', '', 'http://cnn.com/');
    return $r[rand(0, count($r) - 1)];
}
function randomTitle() {
    return 'Page title ' . rand(1, 10000);
}

function randomRequestType() {
    $rt = array_fill(0, 95, 'hit');
    $rt[] = 'click';
    $rt[] = 'click';
    return $rt[rand(0, count($rt) - 1)];
}
$dateStart = strtotime('2016-09-01');
$dateEnd = strtotime('2016-09-21');
$numRecords = 100000;
$timestamps = array();
$resultLog = 'demo.log';
for ($i = 1; $i <= $numRecords; $i++) {
    $timestamps[] = rand($dateStart, $dateEnd);
}
sort($timestamps);

$f = fopen($resultLog, 'w');
foreach ($timestamps as $ts) {
    $ip_address = getRandomIp();
    $uuid = getRandomUid();
    $request_type = randomRequestType();
    $requested_url = getRandomUrl();
    $page_title = randomTitle();
    $user_agent = getRandomUa($uaList);
    $referer = getRandomReferer();
    $click_destination = ($request_type == 'hit') ? '' : getRandomUrl();
    $log_string = "{$ts}\t{$ip_address}\t{$uuid}\t{$request_type}\t"
    . "{$requested_url}\t{$page_title}\t{$user_agent}\t{$referer}"
    . "\t{$click_destination}\n";
    fwrite($f, $log_string);
}

fclose($f);