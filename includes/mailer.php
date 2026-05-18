<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** Human-readable labels for the mailer dropdown. */
const FXFORMS_MAILERS = [
    'default' => 'WordPress default (wp_mail)',
    'smtp2go' => 'SMTP2Go',
];

/**
 * Send a mail via whichever mailer is configured in the plugin settings.
 *
 * @param string[] $recipients
 * @param string[] $headers
 */
function fxforms_dispatch_mail(array $recipients, string $subject, string $html_body, array $headers): bool
{
    $mailer = (string) get_option('fxforms_mailer', 'default');

    if ($mailer === 'smtp2go') {
        return fxforms_send_smtp2go($recipients, $subject, $html_body, $headers);
    }

    return (bool) wp_mail($recipients, $subject, $html_body, $headers);
}

/**
 * Send via the SMTP2Go REST API.
 *
 * @param string[] $recipients
 * @param string[] $headers   WP-style header strings, e.g. "Reply-To: foo@bar.com"
 */
function fxforms_send_smtp2go(array $recipients, string $subject, string $html_body, array $headers): bool
{
    $api_key = (string) get_option('fxforms_smtp2go_key', '');
    if ($api_key === '') {
        return false;
    }

    $site_name  = (string) get_option('blogname', '');
    $site_email = (string) get_option('admin_email', '');
    $sender     = $site_name !== '' ? $site_name . ' <' . $site_email . '>' : $site_email;

    // Extract Reply-To values from WP-style header strings.
    $reply_to = [];
    foreach ($headers as $header) {
        if (stripos($header, 'Reply-To:') === 0) {
            $reply_to[] = trim(substr($header, 9));
        }
    }

    $data = [
        'api_key'   => $api_key,
        'sender'    => $sender,
        'to'        => $recipients,
        'subject'   => $subject,
        'html_body' => $html_body,
        'text_body' => wp_strip_all_tags($html_body),
    ];
    if ($reply_to) {
        $data['reply_to'] = $reply_to;
    }

    $response = wp_remote_post('https://api.smtp2go.com/v3/email/send', [
        'headers'     => ['Content-Type' => 'application/json'],
        'body'        => wp_json_encode($data),
        'timeout'     => 15,
        'data_format' => 'body',
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $result = json_decode(wp_remote_retrieve_body($response), true);

    return is_array($result)
        && isset($result['data']['succeeded'])
        && (int) $result['data']['succeeded'] > 0;
}
