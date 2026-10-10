<?php
// Minimal MIME mail with attachments over SMTP (or PHP mail() when SMTP_HOST is empty).
require_once __DIR__ . '/config.php';

/**
 * @param array $attachments list of ['path' => absolute file path, 'name' => filename shown to recipient]
 * @throws RuntimeException when the message could not be handed to the mail server
 */
function sendMail(string $to, string $subject, string $html, array $attachments = []): void {
    $boundary = 'mk_' . bin2hex(random_bytes(12));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $from = '=?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?= <' . MAIL_FROM . '>';

    $headers = [
        "From: $from",
        'Reply-To: ' . MAIL_REPLY_TO,
        'MIME-Version: 1.0',
        "Content-Type: multipart/mixed; boundary=\"$boundary\"",
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . substr(strrchr(MAIL_FROM, '@'), 1) . '>',
    ];

    $body = "--$boundary\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($html)) . "\r\n";
    foreach ($attachments as $a) {
        $name = str_replace(['"', "\r", "\n"], '', $a['name']);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($a['path']) ?: 'application/octet-stream';
        $body .= "--$boundary\r\n"
               . "Content-Type: $mime; name=\"$name\"\r\n"
               . "Content-Transfer-Encoding: base64\r\n"
               . "Content-Disposition: attachment; filename=\"$name\"\r\n\r\n"
               . chunk_split(base64_encode(file_get_contents($a['path']))) . "\r\n";
    }
    $body .= "--$boundary--\r\n";

    if (SMTP_HOST === '') {
        if (!@mail($to, $encodedSubject, $body, implode("\r\n", $headers))) {
            throw new RuntimeException('mail() failed — configure SMTP in app/config.php');
        }
        return;
    }
    smtpSend($to, array_merge(["To: <$to>", "Subject: $encodedSubject"], $headers), $body);
}

function smtpSend(string $to, array $headers, string $body): void {
    $host = (SMTP_SECURE === 'ssl' ? 'ssl://' : '') . SMTP_HOST;
    $fp = @stream_socket_client("$host:" . SMTP_PORT, $errno, $errstr, 15);
    if (!$fp) throw new RuntimeException("SMTP connect failed: $errstr");
    stream_set_timeout($fp, 20);

    $expect = function (string $codes) use ($fp): string {
        $reply = '';
        while (($line = fgets($fp, 515)) !== false) {
            $reply .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        if (!in_array(substr($reply, 0, 3), explode(',', $codes), true)) {
            throw new RuntimeException('SMTP error: ' . trim($reply));
        }
        return $reply;
    };
    $cmd = function (string $line, string $codes) use ($fp, $expect): string {
        fwrite($fp, $line . "\r\n");
        return $expect($codes);
    };

    try {
        $expect('220');
        $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
        $cmd($ehlo, '250');
        if (SMTP_SECURE === 'tls') {
            $cmd('STARTTLS', '220');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP STARTTLS failed');
            }
            $cmd($ehlo, '250');
        }
        if (SMTP_USER !== '') {
            $cmd('AUTH LOGIN', '334');
            $cmd(base64_encode(SMTP_USER), '334');
            $cmd(base64_encode(SMTP_PASS), '235');
        }
        $cmd('MAIL FROM:<' . MAIL_FROM . '>', '250');
        $cmd("RCPT TO:<$to>", '250,251');
        $cmd('DATA', '354');
        // Dot-stuffing: a line starting with "." must be doubled.
        $data = preg_replace('/^\./m', '..', implode("\r\n", $headers) . "\r\n\r\n" . $body);
        $cmd($data . "\r\n.", '250');
        $cmd('QUIT', '221');
    } finally {
        fclose($fp);
    }
}
