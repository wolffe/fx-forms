<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate a new CAPTCHA challenge.
 *
 * Stores the answer in a short-lived transient (no PHP sessions required).
 *
 * @return array{token: string, code: string}
 */
function fxforms_generate_captcha(): array
{
    $code  = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6);
    $token = wp_generate_uuid4();
    set_transient('fxforms_cap_' . $token, $code, 10 * MINUTE_IN_SECONDS);
    return ['token' => $token, 'code' => $code];
}

/**
 * Verify a CAPTCHA submission and consume the transient (one-time use).
 */
function fxforms_verify_captcha(string $token, string $input): bool
{
    if ($token === '' || $input === '') {
        return false;
    }

    $key    = 'fxforms_cap_' . $token;
    $stored = get_transient($key);
    delete_transient($key);

    if (!is_string($stored) || $stored === '') {
        return false;
    }

    return strtoupper(trim($input)) === $stored;
}

/**
 * Render the CAPTCHA field HTML (generates a fresh challenge on every call).
 */
function fxforms_render_captcha_field(int $form_id): string
{
    ['token' => $token, 'code' => $code] = fxforms_generate_captcha();
    $input_id = 'fxforms_' . $form_id . '_captcha';

    ob_start();
    echo '<p class="fxforms-field fxforms-field-captcha fxforms-field-width-full">';
    echo '<label for="' . esc_attr($input_id) . '">';
    echo esc_html__('Verification code', 'fx-forms');
    echo ' <span class="fxforms-required" aria-hidden="true">*</span>';
    echo '</label>';
    echo '<span class="fxforms-captcha-code" aria-hidden="true">' . esc_html($code) . '</span>';
    echo '<input type="text" id="' . esc_attr($input_id) . '" name="fxforms_captcha"'
        . ' autocomplete="off" maxlength="6" required>';
    echo '<input type="hidden" name="fxforms_captcha_token" value="' . esc_attr($token) . '">';
    echo '<small class="fxforms-field-description">'
        . esc_html__('Type the characters shown above (not case-sensitive).', 'fx-forms')
        . '</small>';
    echo '</p>';

    return (string) ob_get_clean();
}
