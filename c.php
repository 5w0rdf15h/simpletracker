<?php

error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('UTC');

# Change this setting to point somewhere outside from web accessible path.
define('LOG_DIRECTORY', '');
define('LOG_FILENAME', 'counter.log');

function standart_filter($string) {
    $string = str_replace('"', "'", str_replace("\t", ' ',
                str_replace("\n", ' ', str_replace("\r\n", ' ', $string))));
    return $string;
}

/**
 * @author Tom Worster <fsb@thefsb.org>
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license 3-Clause BSD. See XTRAS.md in this Gist.
 * Generates specified number of random bytes. Output is binary string, not ASCII.
 *
 * @param integer $length the number of bytes to generate
 *
 * @return string the generated random bytes
 * @throws \Exception
 */
function randomBytes($length) {
    if (function_exists('random_bytes')) {
        return random_bytes($length);
    }
    if (!is_int($length) || $length < 1) {
        throw new \Exception('Invalid first parameter ($length)');
    }
    // The recent LibreSSL RNGs are faster and likely better than /dev/urandom.
    // Parse OPENSSL_VERSION_TEXT because OPENSSL_VERSION_NUMBER is no use for LibreSSL.
    // https://bugs.php.net/bug.php?id=71143
    static $libreSSL;
    if ($libreSSL === null) {
        $libreSSL = defined('OPENSSL_VERSION_TEXT') && preg_match('{^LibreSSL (\d\d?)\.(\d\d?)\.(\d\d?)$}', OPENSSL_VERSION_TEXT, $matches) && (10000 * $matches[1]) + (100 * $matches[2]) + $matches[3] >= 20105;
    }
    // Since 5.4.0, openssl_random_pseudo_bytes() reads from CryptGenRandom on Windows instead
    // of using OpenSSL library. Don't use OpenSSL on other platforms.
    if ($libreSSL === true || (DIRECTORY_SEPARATOR !== '/' && PHP_VERSION_ID >= 50400 && substr_compare(PHP_OS, 'win', 0, 3, true) === 0 && function_exists('openssl_random_pseudo_bytes'))
    ) {
        $key = openssl_random_pseudo_bytes($length, $cryptoStrong);
        if ($cryptoStrong === false) {
            throw new Exception(
            'openssl_random_pseudo_bytes() set $crypto_strong false. Your PHP setup is insecure.'
            );
        }
        if ($key !== false && mb_strlen($key, '8bit') === $length) {
            return $key;
        }
    }
    // mcrypt_create_iv() does not use libmcrypt. Since PHP 5.3.7 it directly reads
    // CrypGenRandom on Windows. Elsewhere it directly reads /dev/urandom.
    if (PHP_VERSION_ID >= 50307 && function_exists('mcrypt_create_iv')) {
        $key = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
        if (mb_strlen($key, '8bit') === $length) {
            return $key;
        }
    }
    // If not on Windows, try a random device.
    if (DIRECTORY_SEPARATOR === '/') {
        // urandom is a symlink to random on FreeBSD.
        $device = PHP_OS === 'FreeBSD' ? '/dev/random' : '/dev/urandom';
        // Check random device for speacial character device protection mode. Use lstat()
        // instead of stat() in case an attacker arranges a symlink to a fake device.
        $lstat = @lstat($device);
        if ($lstat !== false && ($lstat['mode'] & 0170000) === 020000) {
            $key = @file_get_contents($device, false, null, 0, $length);
            if ($key !== false && mb_strlen($key, '8bit') === $length) {
                return $key;
            }
        }
    }
    throw new \Exception('Unable to generate a random key');
}

/**
 * @author Tom Worster <fsb@thefsb.org>
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license 3-Clause BSD. See XTRAS.md in this Gist.
 * Generates a random UUID using the secure RNG.
 *
 * Returns Version 4 UUID format: xxxxxxxx-xxxx-4xxx-Yxxx-xxxxxxxxxxxx where x is
 * any random hex digit and Y is a random choice from 8, 9, a, or b.
 *
 * @return string the UUID
 */
function randomUuid() {
    $bytes = randomBytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $id = str_split(bin2hex($bytes), 4);
    return "{$id[0]}{$id[1]}-{$id[2]}-{$id[3]}-{$id[4]}-{$id[5]}{$id[6]}{$id[7]}";
}

/**
 * @author Tom Worster <fsb@thefsb.org>
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license 3-Clause BSD. See XTRAS.md in this Gist.
 * Returns a random integer in the range $min through $max inclusive.
 *
 * @param int $min Minimum value of the returned integer.
 * @param int $max Maximum value of the returned integer.
 *
 * @return int The generated random integer.
 * @throws \Exception
 */
function randomInt($min, $max) {
    if (function_exists('random_int')) {
        return random_int($min, $max);
    }
    if (!is_int($min)) {
        throw new \Exception('First parameter ($min) must be an integer');
    }
    if (!is_int($max)) {
        throw new \Exception('Second parameter ($max) must be an integer');
    }
    if ($min > $max) {
        throw new \Exception('First parameter ($min) must be no greater than second parameter ($max)');
    }
    if ($min === $max) {
        return $min;
    }
    // $range is a PHP float if the expression exceeds PHP_INT_MAX.
    $range = $max - $min + 1;
    if (is_float($range)) {
        $mask = null;
    } else {
        // Make a bit mask of (the next highest power of 2 >= $range) minus one.
        $mask = 1;
        $shift = $range;
        while ($shift > 1) {
            $shift >>= 1;
            $mask = ($mask << 1) | 1;
        }
    }
    $tries = 0;
    do {
        $bytes = randomBytes(PHP_INT_SIZE);
        // Convert byte string to a signed int by shifting each byte in.
        $value = 0;
        for ($pos = 0; $pos < PHP_INT_SIZE; $pos += 1) {
            $value = ($value << 8) | ord($bytes[$pos]);
        }
        if ($mask === null) {
            // Use all bits in $bytes and check $value against $min and $max instead of $range.
            if ($value >= $min && $value <= $max) {
                return $value;
            }
        } else {
            // Use only enough bits from $bytes to cover the $range.
            $value &= $mask;
            if ($value < $range) {
                return $value + $min;
            }
        }
        $tries += 1;
    } while ($tries < 123);
    // Worst case: this is as likely as 123 heads in as many coin tosses.
    throw new \Exception('Unable to generate random int after 123 tries');
}

function getOrSetUuid() {
    $uuid = isset($_COOKIE['uuid']) ? $_COOKIE['uuid'] : null;
    if (is_null($uuid)) {
        $uuid = randomUuid();
        setcookie('uuid', $uuid, time() + 315569260);
    }
    return $uuid;
}
$request_ts = time();
$ip_address = $_SERVER['REMOTE_ADDR'];
$page_title = isset($_REQUEST['pt']) ? standart_filter($_REQUEST['pt']) : '';
$requested_url = isset($_REQUEST['u']) ? standart_filter($_REQUEST['u']) : '';
$request_type = isset($_REQUEST['rt']) ? 'click' : 'hit';
$user_agent = standart_filter($_SERVER['HTTP_USER_AGENT']);
$referer = isset($_REQUEST['r']) ? standart_filter($_REQUEST['r']) : '';
$click_destination = isset($_REQUEST['cd']) ? standart_filter($_REQUEST['cd']) : '';
$uuid = standart_filter(getOrSetUuid());
$variable_1 = isset($_REQUEST['v_1']) ? standart_filter($_REQUEST['v_1']) : '';
$variable_2 = isset($_REQUEST['v_2']) ? standart_filter($_REQUEST['v_2']) : '';
$variable_3 = isset($_REQUEST['v_3']) ? standart_filter($_REQUEST['v_3']) : '';
$variable_4 = isset($_REQUEST['v_4']) ? standart_filter($_REQUEST['v_4']) : '';
$variable_5 = isset($_REQUEST['v_5']) ? standart_filter($_REQUEST['v_5']) : '';

$file_path = join(DIRECTORY_SEPARATOR, array(LOG_DIRECTORY, LOG_FILENAME));
if (!is_writable($file_path)) {
    die();
}
$fh = fopen($file_path, 'a');
$log_string = "{$request_ts} {$ip_address} \"{$uuid}\" {$request_type} "
. "\"{$requested_url}\" \"{$page_title}\" \"{$user_agent}\" \"{$referer}\" "
. "\"{$click_destination}\" \"{$variable_1}\" \"{$variable_2}\" "
. " \"{$variable_3}\" \"{$variable_4}\" \"{$variable_5}\"\n";
fwrite($fh, $log_string);
fclose($fh);
