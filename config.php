<?php
/**
 * Cric-Scorer by Aaryush - configuration
 *
 * DO NOT put your real database password in this file - it is in Git.
 * Instead, copy config.local.sample.php to config.local.php on the server
 * and put your real settings there. config.local.php is ignored by Git,
 * so updates (git pull or zip upload) never overwrite it.
 */
if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// Defaults (used only for anything config.local.php does not define)
defined('DB_HOST')     || define('DB_HOST', 'localhost');
defined('DB_PORT')     || define('DB_PORT', '3306');
defined('DB_NAME')     || define('DB_NAME', 'cric_scorer');
defined('DB_USER')     || define('DB_USER', 'cric_user');
defined('DB_PASS')     || define('DB_PASS', 'change_me');

defined('APP_NAME')    || define('APP_NAME', 'Cric-Scorer');
defined('APP_TAGLINE') || define('APP_TAGLINE', 'by Aaryush');

// Sign in with Google - paste your OAuth "Web application" Client ID in config.local.php ('' = off)
defined('GOOGLE_CLIENT_ID')     || define('GOOGLE_CLIENT_ID', '');
// true = anyone with a Google account can create a new player; false = Google only for players who linked it
defined('GOOGLE_ALLOW_SIGNUP')  || define('GOOGLE_ALLOW_SIGNUP', true);

// Google reCAPTCHA on login and registration ('' = off). Version 'v2' = "I'm not a robot" tick box, 'v3' = invisible.
defined('RECAPTCHA_SITE_KEY')   || define('RECAPTCHA_SITE_KEY', '');
defined('RECAPTCHA_SECRET_KEY') || define('RECAPTCHA_SECRET_KEY', '');
defined('RECAPTCHA_VERSION')    || define('RECAPTCHA_VERSION', 'v2');
defined('RECAPTCHA_MIN_SCORE')  || define('RECAPTCHA_MIN_SCORE', 0.5);   // v3 only: 0.0 (bot) … 1.0 (human)

// Website & legal details (used for SEO, share previews, sitemap, Privacy Policy and Terms)
defined('SITE_URL')           || define('SITE_URL', 'https://cricscorerapp.com');      // main address, no slash at the end
defined('CONTACT_EMAIL')      || define('CONTACT_EMAIL', 'privacy@cricscorerapp.com');
defined('ORG_NAME')           || define('ORG_NAME', 'Unical Systems Private Limited');
defined('ORG_ADDRESS')        || define('ORG_ADDRESS', '206, 3rd Floor, DSL Abacus IT Park, IDA Uppal, Hyderabad, Telangana 500039, India');
defined('GRIEVANCE_OFFICER')  || define('GRIEVANCE_OFFICER', 'Shiva Kaluvakota');
defined('POLICY_UPDATED')     || define('POLICY_UPDATED', '14 November 2026');

// Set to true only while troubleshooting - shows database error details.
defined('DEBUG')       || define('DEBUG', false);

// Timezone used for match dates
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Kolkata');
