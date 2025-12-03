<?php
// src/includes/email.php
// Функции для отправки email

/**
 * Отправка email через SMTP API (SendGrid) или встроенный mail()
 * 
 * @param string $to Email получателя
 * @param string $subject Тема письма
 * @param string $body HTML тело письма
 * @param string $textBody Текстовая версия (опционально)
 * @return array ['success' => bool, 'error' => string|null]
 */
function send_email(string $to, string $subject, string $body, string $textBody = ''): array
{
    // Вариант 1: SendGrid API (если задан SENDGRID_API_KEY)
    $sendgridKey = getenv('SENDGRID_API_KEY');
    if ($sendgridKey) {
        return send_email_sendgrid($to, $subject, $body, $textBody, $sendgridKey);
    }
    
    // Вариант 2: Gmail SMTP (если заданы SMTP настройки)
    $smtpHost = getenv('SMTP_HOST');
    if ($smtpHost) {
        return send_email_smtp($to, $subject, $body, $textBody);
    }
    
    // Вариант 3: Встроенный mail() PHP (fallback, может не работать на Render)
    return send_email_mail($to, $subject, $body, $textBody);
}

/**
 * Отправка через SendGrid API
 */
function send_email_sendgrid(string $to, string $subject, string $body, string $textBody, string $apiKey): array
{
    // Для SendGrid можно использовать любой email, но лучше верифицированный
    // Если не задан, используем email получателя как fallback (но это не идеально)
    $fromEmail = getenv('SENDGRID_FROM_EMAIL');
    if (empty($fromEmail)) {
        // Если не задан, можно использовать email получателя (но лучше задать в переменных окружения)
        $fromEmail = 'noreply@sendgrid.net'; // SendGrid позволяет использовать этот домен без верификации (но письма могут попадать в спам)
    }
    $fromName = getenv('SENDGRID_FROM_NAME') ?: 'MediaLib';
    
    // SendGrid требует: сначала text/plain, потом text/html (и text/plain должен быть всегда)
    // Если textBody пустой, создаем простую текстовую версию из HTML
    if (empty(trim($textBody))) {
        $textBody = strip_tags($body);
        $textBody = html_entity_decode($textBody, ENT_QUOTES, 'UTF-8');
        $textBody = preg_replace('/\s+/', ' ', $textBody); // Убираем лишние пробелы
        $textBody = trim($textBody);
    }
    
    // ВАЖНО: порядок должен быть строго text/plain ПЕРВЫМ, text/html ВТОРЫМ
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
        return ['success' => false, 'error' => 'SMTP error: ' . $error];
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'error' => null];
    }
    
    return ['success' => false, 'error' => 'SendGrid API error: HTTP ' . $httpCode . ($response ? ': ' . substr($response, 0, 200) : '')];
}

/**
 * Отправка через SMTP (Gmail и другие)
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
    
    // Используем встроенный mail() с дополнительными заголовками
    // Для полноценного SMTP нужна библиотека типа PHPMailer, но для простоты используем mail()
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
 * Отправка через встроенный mail() PHP (fallback)
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
    
    return ['success' => false, 'error' => 'mail() function failed - возможно, на сервере не настроена отправка email'];
}

