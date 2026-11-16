<?php
/**
 * RV Badminton - configuration.
 *
 * Nothing private lives in this file (it is in Git). The app reads its settings from:
 *   1. rvbadminton/config.local.php   - optional, only if badminton needs different settings
 *   2. ../config.local.php         - the Cric-Scorer settings one folder up (database, Google, WhatsApp)
 * so on the club server there is nothing to set up: badminton shares the Cric-Scorer database and sign-in.
 */
if (file_exists(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';
if (file_exists(dirname(__DIR__) . '/config.local.php')) {
    // Names the badminton file already set stay as they are ("already defined" notices are silenced)
    set_error_handler(static function () { return true; });
    try { require dirname(__DIR__) . '/config.local.php'; } finally { restore_error_handler(); }
}

defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_PORT') || define('DB_PORT', '3306');
defined('DB_NAME') || define('DB_NAME', 'cric_scorer');
defined('DB_USER') || define('DB_USER', 'cric_user');
defined('DB_PASS') || define('DB_PASS', 'change_me');

defined('BD_NAME')       || define('BD_NAME', 'RV Badminton');
defined('BD_CLUB')       || define('BD_CLUB', 'RV Sports Club');
defined('BD_URL')        || define('BD_URL', 'https://rvbadminton.cricscorerapp.com');   // no slash at the end
defined('BD_MAIN_SITE')  || define('BD_MAIN_SITE', 'https://cricscorerapp.com');      // Terms and Privacy Policy live here

/* Club admins. An account becomes a badminton admin when its verified Google e-mail or verified WhatsApp number
   matches one of these SHA-256 hashes (hashes, so the address and number are not readable in the public repo).
   Add plain values in config.local.php instead if you prefer:  define('BD_ADMIN_EMAILS', 'a@x.com, b@y.com');
   define('BD_ADMIN_MOBILES', '9876543210');   More admins can also be added inside the app (Club → Admins). */
defined('BD_ADMIN_EMAILS')  || define('BD_ADMIN_EMAILS', '');
defined('BD_ADMIN_MOBILES') || define('BD_ADMIN_MOBILES', '');
defined('BD_ADMIN_HASHES')  || define('BD_ADMIN_HASHES',
    '8804e551627d3129d3833401ffc2e6c48f1a7ddef39e6342ac5567a3f17823e3,7fd9fda90023c424c77d120501930b9679c25cf9d093c3ce35fb15b2b3d9f8e5');

/* Sign in with Google: badminton uses the RV Tennis OAuth client in Google Cloud Console; https://rvbadminton.cricscorerapp.com
   must be listed among that client's Authorised JavaScript origins (or put a client of its own in config.local.php). A client ID is public - the browser sees it - so it can live here.
   Set BD_GOOGLE_CLIENT_ID to '' in config.local.php to fall back to Cric-Scorer's GOOGLE_CLIENT_ID. */
defined('BD_GOOGLE_CLIENT_ID')    || define('BD_GOOGLE_CLIENT_ID', '730719546750-li3ks3hkr887cbp577q7o1hivus9o8fo.apps.googleusercontent.com');
defined('GOOGLE_CLIENT_ID')       || define('GOOGLE_CLIENT_ID', '');
defined('GOOGLE_TOKENINFO_URL')   || define('GOOGLE_TOKENINFO_URL', 'https://oauth2.googleapis.com/tokeninfo');
defined('WHATSAPP_TOKEN')         || define('WHATSAPP_TOKEN', '');
defined('WHATSAPP_PHONE_ID')      || define('WHATSAPP_PHONE_ID', '');
defined('WHATSAPP_TEMPLATE')      || define('WHATSAPP_TEMPLATE', 'cricscorer_verify');
defined('WHATSAPP_TEMPLATE_LANG') || define('WHATSAPP_TEMPLATE_LANG', 'en');
defined('WHATSAPP_TEMPLATE_KIND') || define('WHATSAPP_TEMPLATE_KIND', 'link');
defined('WHATSAPP_API_URL')       || define('WHATSAPP_API_URL', 'https://graph.facebook.com/v21.0/');
defined('DEFAULT_COUNTRY_CODE')   || define('DEFAULT_COUNTRY_CODE', '91');
defined('OTP_MINUTES')            || define('OTP_MINUTES', 15);
defined('DEBUG')                  || define('DEBUG', false);

define('BD_VERSION', '1.0.0');
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Kolkata');
