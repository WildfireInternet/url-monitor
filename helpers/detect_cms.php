<?php

// 
// a simple cms/platform detection
// 
// TODO: convert to a Wappalyzer apps.json parser but only reading the cms/platofrm category
// 
function detectCMS(string $html) : ?string
{
    $searches = [
        'woocommerce' => [
            '/wp-content\/plugins\/woocommerce/i',
        ],
        'wordpress' => [
            '/wp-content/i',
            '/wp-includes/i',
        ],
        'magento' => [
            '/skin\/frontend/i',
            '/static\/frontend/i',
            '/static\/version\d+\/frontend\//i',
            '/\/mage\//i',
        ],
        'shopify' => [
            '/cdn.shopify.com\//i',
        ],
        'rec+' => [
            '/css\/master-v\d+\.css/i',
        ],
        'joomla' => [
            '/joomla/i'
        ],
        'opencart' => [
            '/opencart/i'
        ],
        'websphere' => [ // IBM WebSphere Commerce
            '/wcsstore/i'
        ],
        'cs-cart' => [
            '/cs-cart/i'
        ],
        'down-for-maintenance' => [
            '/MaintenancePage/i',
            '/down for Maintenance/i',
            '/work in progress/i',
            '/redeveloping our website/i',
            '/be right back/i',
            '/reserved for future use/i',
            '/there is a problem with the website/i',
        ],
    ];

    $platform = null;
    foreach ($searches as $name => $search) {
        foreach ($search as $pattern) {
            if (preg_match($pattern, $html)) {
                $platform = $name;
                break;
            }
        }
        if ($platform) {
            break;
        }
    }
    return $platform;
}
