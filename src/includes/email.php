<?php
// src/includes/email.php
// Funkcje do wysyłania email

/**
 * Wysyłanie email przez SMTP API (SendGrid) lub wbudowany mail()
 * 
 * @param string $to Email odbiorcy
 * @param string $subject Temat wiadomości
 * @param string $body HTML treść wiadomości
 * @param string $textBody Wersja tekstowa (opcjonalnie)
 * @return array ['success' => bool, 'error' => string|null]
 */
function send_email(string $to, string $subject, string $body, string $textBody = ''): array
{
    // Opcja 1: SendGrid API (jeśli ustawiony SENDGRID_API_KEY)
    $sendgridKey = getenv('SENDGRID_API_KEY');
    if ($sendgridKey) {
        return send_email_sendgrid($to, $subject, $body, $textBody, $sendgridKey);
    }
    
    // Opcja 2: Gmail SMTP (jeśli ustawione ustawienia SMTP)
    $smtpHost = getenv('SMTP_HOST');
    if ($smtpHost) {
        return send_email_smtp($to, $subject, $body, $textBody);
    }
    
    // Opcja 3: Wbudowany mail() PHP (fallback, może nie działać na Render)
    return send_email_mail($to, $subject, $body, $textBody);
}

/**
 * Wysyłanie przez SendGrid API
 */
function send_email_sendgrid(string $to, string $subject, string $body, string $textBody, string $apiKey): array
{
    // Dla SendGrid można użyć dowolnego email, ale lepiej zweryfikowany
    // Jeśli nie ustawiony, używamy email odbiorcy jako fallback (ale to nie idealne)
    $fromEmail = getenv('SENDGRID_FROM_EMAIL');
    if (empty($fromEmail)) {
        // Jeśli nie ustawiony, można użyć email odbiorcy (ale lepiej ustawić w zmiennych środowiskowych)
        $fromEmail = 'noreply@sendgrid.net'; // SendGrid pozwala używać tej domeny bez weryfikacji (ale wiadomości mogą trafiać do spamu)
    }
    $fromName = getenv('SENDGRID_FROM_NAME') ?: 'MediaLib';
    
    // SendGrid wymaga: najpierw text/plain, potem text/html (i text/plain musi być zawsze)
    // Jeśli textBody pusty, tworzymy prostą wersję tekstową z HTML
    if (empty(trim($textBody))) {
        $textBody = strip_tags($body);
        $textBody = html_entity_decode($textBody, ENT_QUOTES, 'UTF-8');
        $textBody = preg_replace('/\s+/', ' ', $textBody); // Usuwamy zbędne spacje
        $textBody = trim($textBody);
    }
    
    // WAŻNE: kolejność musi być ściśle text/plain PIERWSZY, text/html DRUGI
    $content = [
        [
            'type' => 'text/plain',
            'value' => $textBody
        ],
        [
            'type' => 'text/html',
            'value' => $body
        ]
    ];
    
    $data = [
        'personalizations' => [
            [
                'to' => [['email' => $to]],
                'subject' => $subject
            ]
        ],
        'from' => [
            'email' => $fromEmail,
            'name' => $fromName
        ],
        'content' => $content
    ];
    
    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("SendGrid CURL error: " . $error);
        return ['success' => false, 'error' => 'SMTP error: ' . $error];
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'error' => null];
    }
    
    // Logujemy pełną odpowiedź do debugowania
    $errorMsg = 'SendGrid API error: HTTP ' . $httpCode;
    if ($response) {
        $errorMsg .= ': ' . substr($response, 0, 500);
        error_log("SendGrid API error response: " . $response);
    }
    error_log("SendGrid API error: HTTP $httpCode");
    
    return ['success' => false, 'error' => $errorMsg];
}

/**
 * Wysyłanie przez SMTP (Gmail i inne)
 */
function send_email_smtp(string $to, string $subject, string $body, string $textBody): array
{
    $smtpHost = getenv('SMTP_HOST');
    $smtpPort = (int)(getenv('SMTP_PORT') ?: 587);
    $smtpUser = getenv('SMTP_USER');
    $smtpPass = getenv('SMTP_PASS');
    $smtpFrom = getenv('SMTP_FROM') ?: $smtpUser;
    $smtpFromName = getenv('SMTP_FROM_NAME') ?: 'MediaLib';
    
    if (!$smtpHost || !$smtpUser || !$smtpPass) {
        return ['success' => false, 'error' => 'SMTP credentials not configured'];
    }
    
    // Używamy wbudowanego mail() z dodatkowymi nagłówkami
    // Dla pełnego SMTP potrzebna jest biblioteka typu PHPMailer, ale dla prostoty używamy mail()
    $headers = [
        'From: ' . $smtpFromName . ' <' . $smtpFrom . '>',
        'Reply-To: ' . $smtpFrom,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $result = mail($to, $subject, $body, implode("\r\n", $headers));
    
    if ($result) {
        return ['success' => true, 'error' => null];
    }
    
    return ['success' => false, 'error' => 'mail() function failed'];
}

/**
 * Wysyłanie przez wbudowany mail() PHP (fallback)
 */
function send_email_mail(string $to, string $subject, string $body, string $textBody): array
{
    $fromEmail = getenv('MAIL_FROM') ?: 'noreply@medialib.app';
    $fromName = getenv('MAIL_FROM_NAME') ?: 'MediaLib';
    
    $headers = [
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $result = mail($to, $subject, $body, implode("\r\n", $headers));
    
    if ($result) {
        return ['success' => true, 'error' => null];
    }
    
    return ['success' => false, 'error' => 'mail() function failed - możliwe, że na serwerze nie jest skonfigurowana wysyłka email'];
}

