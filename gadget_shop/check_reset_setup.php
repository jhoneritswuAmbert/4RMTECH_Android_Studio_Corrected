<?php
if (PHP_SAPI !== 'cli') {
    exit;
}

function checkSetting($label, $passed) {
    echo $label . ': ' . ($passed ? 'OK' : 'FIX NEEDED') . PHP_EOL;
}

$helper = __DIR__ . '/reset_helpers.php';

checkSetting(
    'Reset helper has content',
    is_file($helper) && filesize($helper) > 0
);

if (is_file($helper)) {
    require_once $helper;
}

checkSetting('resetConfig function exists', function_exists('resetConfig'));

$path = 'C:/xampp/gadget_shop_private/mail_config.php';
checkSetting('Private config exists', is_file($path));

try {
    $config = is_file($path) ? require $path : null;

    checkSetting('Config returns an array', is_array($config));

    if (!is_array($config)) {
        exit;
    }

    checkSetting('Sender email valid', (bool) filter_var(
        $config['from_email'] ?? '',
        FILTER_VALIDATE_EMAIL
    ));

    checkSetting('Username filled', !empty($config['username'])
        && !str_contains($config['username'], 'YOUR_'));

    checkSetting('App password filled', !empty($config['password'])
        && !str_contains($config['password'], 'YOUR_'));

    checkSetting('Encryption key valid', (bool) preg_match(
        '/^[a-f0-9]{64}$/i',
        $config['encryption_key'] ?? ''
    ));

    checkSetting('SMTP host filled', !empty($config['host']));

    checkSetting('Security valid', in_array(
        $config['security'] ?? '',
        ['tls', 'ssl'],
        true
    ));
} catch (Throwable $e) {
    echo 'Config could not load. Check its PHP syntax.' . PHP_EOL;
}