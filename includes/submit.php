<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function fxforms_handle_submit(): void
{
    $form_id  = isset($_POST['form_id']) ? (int) $_POST['form_id'] : 0;
    $redirect = isset($_POST['redirect_to']) ? esc_url_raw((string) wp_unslash($_POST['redirect_to'])) : home_url('/');

    if (!$form_id) {
        fxforms_redirect($redirect, 'error', 0);
    }

    if (
        !isset($_POST['fxforms_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fxforms_nonce'])), 'fxforms_submit_' . $form_id)
    ) {
        fxforms_redirect($redirect, 'error', $form_id);
    }

    if (!empty($_POST['fxforms_hp'])) {
        // Honeypot tripped. Pretend success so bots don't learn from the response.
        fxforms_redirect($redirect, 'success', $form_id);
    }

    $post = get_post($form_id);
    if (!$post || $post->post_type !== FXFORMS_CPT || $post->post_status !== 'publish') {
        fxforms_redirect($redirect, 'error', $form_id);
    }

    $config = fxforms_get_config($form_id);
    $values = fxforms_collect_values($config['fields'], $_POST);

    // Verify CAPTCHA before checking required fields so the stashed values
    // are already collected and can pre-fill the form on re-render.
    if (!empty($config['captcha'])) {
        $cap_token = isset($_POST['fxforms_captcha_token'])
            ? sanitize_key((string) $_POST['fxforms_captcha_token'])
            : '';
        $cap_input = isset($_POST['fxforms_captcha'])
            ? sanitize_text_field((string) wp_unslash($_POST['fxforms_captcha']))
            : '';
        if (!fxforms_verify_captcha($cap_token, $cap_input)) {
            $token = fxforms_stash_values($values);
            fxforms_redirect($redirect, 'error', $form_id, $token);
        }
    }

    if (fxforms_missing_required($config['fields'], $values)) {
        $token = fxforms_stash_values($values);
        fxforms_redirect($redirect, 'error', $form_id, $token);
    }

    $ok = fxforms_send_mail($form_id, $config, $values);
    fxforms_redirect($redirect, $ok ? 'success' : 'error', $form_id);
}

/**
 * @param array<int,array<string,mixed>> $fields
 * @param array<string,mixed>            $post
 * @return array<string,mixed>
 */
function fxforms_collect_values(array $fields, array $post): array
{
    $values = [];
    foreach ($fields as $field) {
        $name = (string) $field['id'];

        if ($field['type'] === 'full_name') {
            $values[$name] = [
                'first' => sanitize_text_field((string) wp_unslash($post[$name . '_first'] ?? '')),
                'last'  => sanitize_text_field((string) wp_unslash($post[$name . '_last']  ?? '')),
            ];
            continue;
        }

        $raw = $post[$name] ?? '';
        $raw = is_array($raw) ? '' : wp_unslash((string) $raw);

        $values[$name] = match ($field['type']) {
            'textarea' => sanitize_textarea_field((string) $raw),
            'email'    => sanitize_email((string) $raw),
            'url'      => esc_url_raw((string) $raw),
            'number'   => preg_replace('/[^\d.\-+e]/i', '', (string) $raw) ?? '',
            'checkbox' => !empty($raw),
            'select'   => fxforms_clamp_option((string) $raw, $field['options']),
            default    => sanitize_text_field((string) $raw),
        };
    }
    return $values;
}

function fxforms_clamp_option(string $value, array $options): string
{
    return in_array($value, $options, true) ? $value : '';
}

/**
 * @param array<int,array<string,mixed>> $fields
 * @param array<string,mixed>            $values
 */
function fxforms_missing_required(array $fields, array $values): bool
{
    foreach ($fields as $field) {
        if (empty($field['required'])) continue;
        $val = $values[$field['id']] ?? null;

        $missing = match ($field['type']) {
            'checkbox'  => !$val,
            'full_name' => !is_array($val) || empty($val['first']) || empty($val['last']),
            default     => !is_string($val) || $val === '',
        };

        if ($missing) return true;
    }
    return false;
}

/**
 * @param array<string,mixed> $config
 * @param array<string,mixed> $values
 */
function fxforms_send_mail(int $form_id, array $config, array $values): bool
{
    $recipients = fxforms_resolve_recipients((string) ($config['mail_to'] ?? ''));
    if (!$recipients) {
        return false;
    }

    $form_title = (string) get_the_title($form_id);

    $tokens = [
        '{form_id}'    => (string) $form_id,
        '{form_title}' => $form_title,
        '{data}'       => fxforms_format_data($config['fields'], $values),
    ];
    foreach ($config['fields'] as $field) {
        $val = $values[$field['id']] ?? '';

        if ($field['type'] === 'full_name' && is_array($val)) {
            $tokens['{' . $field['id'] . '_first}'] = (string) ($val['first'] ?? '');
            $tokens['{' . $field['id'] . '_last}']  = (string) ($val['last']  ?? '');
            $tokens['{' . $field['id'] . '}']       = trim(($val['first'] ?? '') . ' ' . ($val['last'] ?? ''));
            continue;
        }

        if ($field['type'] === 'checkbox') {
            $val = $val ? __('Yes', 'fx-forms') : __('No', 'fx-forms');
        }
        $tokens['{' . $field['id'] . '}'] = (string) $val;
    }

    $subject = strtr((string) $config['mail_subject'], $tokens);
    $body    = wpautop(strtr((string) $config['mail_body'], $tokens));

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $reply   = fxforms_first_email_value($config['fields'], $values);
    if ($reply !== null) {
        $headers[] = 'Reply-To: ' . $reply;
    }

    $ok = fxforms_dispatch_mail($recipients, $subject, $body, $headers);

    fxforms_log_email($form_id, $form_title, $recipients, $subject, $body, $ok);

    return $ok;
}

/**
 * @return string[]
 */
function fxforms_resolve_recipients(string $raw): array
{
    $out = [];
    foreach (preg_split('/[,;]/', $raw) ?: [] as $piece) {
        $email = sanitize_email(trim($piece));
        if ($email !== '' && is_email($email) && !in_array($email, $out, true)) {
            $out[] = $email;
        }
    }
    if (!$out) {
        $admin = (string) get_option('admin_email');
        if ($admin !== '' && is_email($admin)) {
            $out[] = $admin;
        }
    }
    return $out;
}

/**
 * @param array<int,array<string,mixed>> $fields
 * @param array<string,mixed>            $values
 */
function fxforms_format_data(array $fields, array $values): string
{
    $lines = [];
    foreach ($fields as $field) {
        $val = $values[$field['id']] ?? '';

        $display = match (true) {
            $field['type'] === 'checkbox' => $val ? __('Yes', 'fx-forms') : __('No', 'fx-forms'),
            $field['type'] === 'full_name' && is_array($val) => trim(($val['first'] ?? '') . ' ' . ($val['last'] ?? '')),
            default => (string) $val,
        };

        $lines[] = '<p><strong>' . esc_html((string) $field['label']) . ':</strong> '
            . nl2br(esc_html($display)) . '</p>';
    }
    return implode("\n", $lines);
}

function fxforms_first_email_value(array $fields, array $values): ?string
{
    foreach ($fields as $field) {
        if ($field['type'] !== 'email') continue;
        $val = $values[$field['id']] ?? '';
        if (is_string($val) && $val !== '' && is_email($val)) {
            return $val;
        }
    }
    return null;
}

function fxforms_stash_values(array $values): string
{
    $token = wp_generate_uuid4();
    set_transient('fxforms_stash_' . $token, $values, 5 * MINUTE_IN_SECONDS);
    return $token;
}

function fxforms_redirect(string $redirect, string $status, int $form_id, string $token = ''): never
{
    $args = [
        'fxforms_status' => $status,
        'fxforms_form'   => $form_id,
    ];
    if ($token !== '') {
        $args['fxforms_token'] = $token;
    }
    $url = add_query_arg($args, $redirect);

    if ($form_id) {
        $url .= '#fxforms-form-' . $form_id;
    }

    wp_safe_redirect($url);
    exit;
}
