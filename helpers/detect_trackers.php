<?php

// 
// Super basic detection for tracker scripts, TODO: needs improvements
// 

function detectAdwords($body)
{
    return preg_match('/googleadservices\.com/i', $body)
        || preg_match('/var google_conversion_/', $body)
        || preg_match('/("|\')AW-\d+\1/', $body);
}

function detectFBTracking($body)
{
    return preg_match('/fbq\(/', $body)
        || preg_match('/facebook\.com\/tr\?/', $body);
}
