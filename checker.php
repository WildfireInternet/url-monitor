<?php

use CsvParser\Parser;
use RollingCurl\Request;
use RollingCurl\RollingCurl;
use SebastianBergmann\Timer\Timer;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/helpers/get_google_client.php';
require_once __DIR__ . '/helpers/detect_cms.php';
require_once __DIR__ . '/helpers/detect_trackers.php';

// get the folder ID
if (! file_exists(__DIR__ . '/.spreadsheetId') || ! ($spreadsheetId = file_get_contents(__DIR__ . '/.spreadsheetId'))) {
    die("Please setup the .spreadsheetId file with the Google Spreadsheed file ID\n");
}

// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Drive($client);

// download the file from google spreadsheets
$service = new Google_Service_Sheets($client);
$columnRange = 'A:R';
$response = $service->spreadsheets_values->get($spreadsheetId, $columnRange);

// map the google spreadsheet data to a key value format we can work with
$keys = [];
$sites = [];
$rows = array_filter($response->values, function ($row) {
    return $row && $row[0];
});
foreach ($rows as $i => $line) {
    if ($i == 0) {
        // first row => keys
        $keys = array_filter($line);
    } else {
        $site = [];
        foreach ($keys as $index => $key) {
            $site[$key] = $line[$index] ?? '';
        }
        $sites[] = $site;
    }
}

// start up rolling curl
$rollingCurl = new RollingCurl;
$rollingCurl->setOptions(array(
    CURLOPT_HEADER => 1,
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_HTTPHEADER => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9', 
        // 'Accept-Encoding: gzip, deflate, br', <- temp no gzip support, TODO
        'Accept-Language: en-GB,en;q=0.9,en-US;q=0.8,de;q=0.7,da;q=0.6,fr;q=0.5', 
        'Sec-Fetch-Dest: document', 
        'Sec-Fetch-Mode: navigate', 
        'Sec-Fetch-Site: none', 
        'Upgrade-Insecure-Requests: 1', 
        'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.116 Safari/537.36', 
        'Referer: https://www.google.co.uk/',
    ],
    CURLOPT_TIMEOUT => 5, // short timeouts
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_FOLLOWLOCATION => 1,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_SSL_VERIFYPEER => false, // crawl sites with bad ssl ;)
    CURLOPT_SSL_VERIFYHOST => 0,
));

// add in each site url to crawl
foreach ($sites as $site) {
    $rollingCurl->get($site['url']);
    echo '.'; // show a loading dot for each site added
}
echo "\n";

// todays date for later
$today = date('Y-m-d H:i:s');

// set callback handler for each request
$results = [];
$rollingCurl->setCallback(function(Request $req) use (&$results, $sites, $today) {

    $url = $req->getUrl();
    $responseInfo = $req->getResponseInfo();

    // lookup previous data
    $prevKey = array_search($url, array_column($sites, 'url'));
    $prev = $sites[$prevKey];
    if (! $prev) {
        echo "\n!! ERROR: \"{$url}\" not found in sites array !!\n";
    }

    // find page title
    $body = $req->getResponseText();
    $title = '';
    if ($body && preg_match('/<title>(.*?)<\/title>/i', $body, $matches)) {
        $title = $matches[1];
    }

    // is https?
    $isHTTPS = $responseInfo['ssl_verify_result'] == 0 && preg_match('/^https:\/\//i', $responseInfo['url']);

    // platform
    $platform = detectCMS($body);

    // Uses adwords?
    $usesAdwords = 0;
    if ($body && detectAdwords($body)) {
        $usesAdwords = 1;
    }

    // Uses facebook tracking pixel?
    $usesFBTracking = 0;
    if ($body && detectFBTracking($body)) {
        $usesFBTracking = 1;
    }

    $results[] = [
        'url' => $url,

        'prev_http_code' => $prev['http_code'] != $responseInfo['http_code'] ? $prev['http_code'] : $prev['prev_http_code'],
        'http_code' => $responseInfo['http_code'],
        'http_code_last_changed' => $prev['http_code'] != $responseInfo['http_code'] ? $today : $prev['http_code_last_changed'],

        'prev_is_https' => $prev['is_https'] != $isHTTPS ? $prev['is_https'] : $prev['prev_is_https'],
        'is_https' => $isHTTPS,
        'is_https_last_changed' => $prev['is_https'] != $isHTTPS ? $today : $prev['is_https_last_changed'],

        'prev_platform' => $prev['platform'] != $platform ? $prev['platform'] : $prev['prev_platform'],
        'platform' => $platform,
        'platform_last_changed' => $prev['platform'] != $platform ? $today : $prev['platform_last_changed'],

        'prev_uses_adwords' => $prev['uses_adwords'] != $usesAdwords ? $prev['uses_adwords'] : (int) $prev['prev_uses_adwords'],
        'uses_adwords' => $usesAdwords,
        'uses_adwords_last_changed' => $prev['uses_adwords'] != $usesAdwords ? $today : $prev['uses_adwords_last_changed'],

        'prev_uses_fb_tracking' => $prev['uses_fb_tracking'] != $usesFBTracking ? $prev['uses_fb_tracking'] : (int) $prev['prev_uses_fb_tracking'],
        'uses_fb_tracking' => $usesFBTracking,
        'uses_fb_tracking_last_changed' => $prev['uses_fb_tracking'] != $usesFBTracking ? $today : $prev['uses_fb_tracking_last_changed'],

        // a few vars to help us debug & might also be useful in the spreadsheet anyway :)
        'debug_req_time' => $responseInfo['total_time'],
        'debug_title' => $title,
    ];

    echo "@"; // show a loading @ for each site parsed
});

$rollingCurl->setSimultaneousLimit(20);
$rollingCurl->execute();
echo "!\n"; // show to say it's finished the crawling

// sort results alphabetically by site
usort($results, function ($a, $b) {
    return $a['url'] <=> $b['url'];
});

// update the previous response values
$keys = array_keys($results[0]);
$spreadsheetValues = [
    $keys, // first line is the column keys
];
foreach ($results as $site) {
    $line = [];
    foreach ($keys as $key) {
        $line[] = (string) $site[$key];
    }
    $spreadsheetValues[] = $line;
}
// var_dump($spreadsheetValues[0], $spreadsheetValues[1], count($sites), count($results));

// & update the google spreadsheet file
$response->setValues($spreadsheetValues);
$response->setRange($columnRange);
$response = $service->spreadsheets_values->update($spreadsheetId, $columnRange, $response, ["valueInputOption" => "RAW"]);

echo 'Google API response:';
var_dump($response);

// show timer & resource usage
echo Timer::resourceUsage(), "\n";
