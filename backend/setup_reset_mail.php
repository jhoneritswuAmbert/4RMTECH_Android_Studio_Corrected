<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$directory = dirname(__DIR__, 2) . '/gadget_shop_private';
$path = $directory . '/mail_config.php';
if (is_file($path)) { echo "Configuration already exists; edit it instead of replacing it:\n" . $path . "\n"; exit; }
if (!is_dir($directory) && !mkdir($directory, 0700, true)) { fwrite(STDERR, "Could not create private config directory.\n"); exit(1); }
$config = [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'security' => 'tls',
    'username' => 'YOUR_SENDER@gmail.com',
    'password' => 'YOUR_APP_PASSWORD',
    'from_email' => 'YOUR_SENDER@gmail.com',
    'from_name' => '4RMTECH Gadget Shop',
    'encryption_key' => bin2hex(random_bytes(32))
];
if (file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX) === false) { fwrite(STDERR, "Could not save config.\n"); exit(1); }
chmod($path, 0600);
echo "Created private email configuration:\n" . $path . "\nEdit username, password and from_email locally. Keep encryption_key unchanged and do not share this file.\n";
