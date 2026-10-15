<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reset_helpers.php';
require_once __DIR__ . '/vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/PHPMailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
try { $config = resetConfig(); }
catch (Throwable $e) { fwrite(STDERR, "Configure the private mail_config.php first.\n"); exit(1); }
$watch = in_array('--watch', $argv, true);
echo "Gadget Shop mailer started. Keep this window open for reset emails.\n";
do {
    $job = null;
    try {
        $conn->beginTransaction();
        $stmt = $conn->query("SELECT * FROM api_mail_outbox WHERE (status = 'pending' AND available_at <= CURRENT_TIMESTAMP) OR (status = 'sending' AND lease_until < CURRENT_TIMESTAMP) ORDER BY mail_id LIMIT 1 FOR UPDATE SKIP LOCKED");
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($job) {
            $stmt = $conn->prepare("UPDATE api_mail_outbox SET status = 'sending', lease_until = CURRENT_TIMESTAMP + INTERVAL '2 minutes', attempts = attempts + 1 WHERE mail_id = :id");
            $stmt->execute([':id' => $job['mail_id']]);
        }
        $conn->commit();
        if (!$job) { if ($watch) sleep(3); continue; }
        $payload = resetOpen($job['payload'], $config);
        if ($payload['type'] === 'reset') {
            $stmt = $conn->prepare('SELECT request_id FROM api_password_resets WHERE request_id = :id AND used_at IS NULL AND verified_at IS NULL AND expires_at > CURRENT_TIMESTAMP');
            $stmt->execute([':id' => $job['request_id']]);
            if (!$stmt->fetchColumn()) {
                $stmt = $conn->prepare("UPDATE api_mail_outbox SET status = 'cancelled', payload = NULL WHERE mail_id = :id");
                $stmt->execute([':id' => $job['mail_id']]);
                continue;
            }
        }
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['security'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)$config['port'];
        $mail->Timeout = 20;
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($config['from_email'], $config['from_name'] ?? '4RMTECH Gadget Shop');
        $mail->addAddress($payload['email']);
        $mail->isHTML(false);
        if ($payload['type'] === 'reset') {
            $mail->Subject = 'Your 4RMTECH password reset code';
            $mail->Body = "Your password reset code is: " . $payload['code'] . "\n\nIt expires 10 minutes after your request and can only be used once. Enter it in the app or website where you requested it.\n\nIf you did not request this, ignore this email. Never share your code.\n\n4RMTECH Gadget Shop";
        } else {
            $mail->Subject = 'Your 4RMTECH password was changed';
            $mail->Body = "Your customer account password was changed. Existing login sessions have been signed out.\n\nIf this was not you, request a new password reset or contact the shop.\n\n4RMTECH Gadget Shop";
        }
        $mail->send();
        $stmt = $conn->prepare("UPDATE api_mail_outbox SET status = 'sent', payload = NULL, lease_until = NULL WHERE mail_id = :id");
        $stmt->execute([':id' => $job['mail_id']]);
        echo 'Mail job ' . $job['mail_id'] . " sent.\n";
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        if ($job) {
            $attempts = (int)$job['attempts'] + 1;
            $stmt = $conn->prepare("UPDATE api_mail_outbox SET status = :status, available_at = CURRENT_TIMESTAMP + INTERVAL '30 seconds', lease_until = NULL, payload = CASE WHEN :discard = 1 THEN NULL ELSE payload END WHERE mail_id = :id");
            $stmt->execute([':status' => $attempts >= 3 ? 'failed' : 'pending', ':discard' => $attempts >= 3 ? 1 : 0, ':id' => $job['mail_id']]);
            fwrite(STDERR, 'Mail job ' . $job['mail_id'] . ": delivery failed. Check SMTP settings, network access, and your provider's app password.\n");
        } else {
            fwrite(STDERR, "Queue unavailable. Check PostgreSQL and the reset migration.\n");
            if ($watch) sleep(5);
        }
    }
} while ($watch || $job);
