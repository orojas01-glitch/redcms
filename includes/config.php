<?php 
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/public_route_compatibility_helpers.php';
red_start_session();

$redLocalConfig = [];
$redLocalConfigFile = __DIR__ . '/config.local.php';
if (is_file($redLocalConfigFile)) {
    $loadedConfig = require $redLocalConfigFile;
    if (is_array($loadedConfig)) {
        $redLocalConfig = $loadedConfig;
    }
}

if (!function_exists('red_config_value')) {
    function red_config_value($localKey, $envKeys, $default = '')
    {
        global $redLocalConfig;

        foreach ((array) $envKeys as $envKey) {
            $envValue = getenv($envKey);
            if ($envValue !== false && $envValue !== '') {
                return $envValue;
            }
            if (isset($_ENV[$envKey]) && $_ENV[$envKey] !== '') {
                return $_ENV[$envKey];
            }
            if (isset($_SERVER[$envKey]) && $_SERVER[$envKey] !== '') {
                return $_SERVER[$envKey];
            }
        }

        if (array_key_exists($localKey, $redLocalConfig)) {
            return $redLocalConfig[$localKey];
        }

        return $default;
    }
}

/**
 * Red Sphere - Unique php CMS
 * @version: 1.0 - (2012/02/25)
 * @version: 2.0 - (2014/02/25) 
 * @version: 3.0 - (2015/04/7)
 * @version: 4.0 - (2025/03/06)
 * @PHP 5.5.0
 * @author Oscar Rojas
 * Examples and documentation @: http://red-sphere.com/
 * Licensed under MIT licence:
 *   http://www.opensource.org/licenses/mit-license.php
**/

function getlocation($ip)
{
    // Initiate CURL and set options
    $access_key = red_config_value('IPSTACK_ACCESS_KEY', ['RED_IPSTACK_ACCESS_KEY', 'IPSTACK_ACCESS_KEY'], '');
    if ($access_key === '') {
        return '';
    }
    $ch = curl_init('http://api.ipstack.com/'.rawurlencode($ip).'?access_key='.rawurlencode($access_key));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $json = curl_exec($ch);
    curl_close($ch);
    $api_result = json_decode($json, true);
    return isset($api_result['city'], $api_result['country_name']) 
        ? $api_result['city'] . ',' . $api_result['country_name'] 
        : '';
}

function getRealIpAddr()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {  
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {  
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}


//DB CONNECTION
define('DBHOST', red_config_value('DBHOST', ['RED_DB_HOST', 'DBHOST'], 'localhost'));
define('DBUSER', red_config_value('DBUSER', ['RED_DB_USER', 'DBUSER'], ''));
define('DBPASS', red_config_value('DBPASS', ['RED_DB_PASS', 'DBPASS'], ''));
define('DBNAME', red_config_value('DBNAME', ['RED_DB_NAME', 'DBNAME'], ''));

// DEFINE URL
$URL = preg_replace("'<[^>]+>'U", "", $_SERVER['REQUEST_URI']);
define('URL', $URL);
define('BASE_URL', $_SERVER['HTTP_HOST']);

$pagebase = explode("?", URL, 2);
$page = explode("/", $pagebase[0]);

$rest = substr($pagebase[0], -1);
if ($rest !== '/') {
    $redPublicArticleSegment = isset($page[count($page) - 1]) ? $page[count($page) - 1] : '';
    define('article', red_public_route_article_alias($redPublicArticleSegment, count($page)));
    define('countpage', count($page));
} else {
    define('article', '');
    define('countpage', count($page));
}

// Safely assign section, category and subcategory based on URL parts
if (defined('countpage') && countpage === 2) {
    define('section', 'home');
} else {
    define('section', isset($page[1]) ? $page[1] : '');
}
define('category', isset($page[2]) ? $page[2] : '');
define('subcategory', isset($page[3]) ? $page[3] : '');

// DEFINE QUERYSTRING
if (isset($pagebase[1])) {
    $query = explode("=", $pagebase[1]);
    define('referral', isset($query[1]) ? $query[1] : '');
} else {
    define('referral', '');
}

// SET LOCATION / LANGUAGE
if (isset($query[0]) && $query[0] === 'l') { // LANGUAGE set by dropdown in header
    $_SESSION['language'] = isset($query[1]) ? $query[1] : 'sp';  
    define('language', $_SESSION['language']);
    header('Location: https://' . BASE_URL);
    exit;
} else {
    if (isset($_SESSION['language'])) {
        define('language', $_SESSION['language']);
    } else {
        define('language', 'sp');
        $_SESSION['language'] = 'sp';
    }
}
?>
