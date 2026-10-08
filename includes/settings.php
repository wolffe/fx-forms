<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// ---------------------------------------------------------------------------
// Admin menu
// ---------------------------------------------------------------------------

function fxforms_admin_menu(): void
{
    add_submenu_page(
        'edit.php?post_type=fxform',
        __('FX Forms Settings', 'fx-forms'),
        __('Settings', 'fx-forms'),
        'manage_options',
        'fxforms-settings',
        'fxforms_render_settings_page'
    );

    add_submenu_page(
        'edit.php?post_type=fxform',
        __('FX Forms Email Log', 'fx-forms'),
        __('Email Log', 'fx-forms'),
        'manage_options',
        'fxforms-log',
        'fxforms_render_log_page'
    );
}

// ---------------------------------------------------------------------------
// Settings page
// ---------------------------------------------------------------------------

function fxforms_render_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle test email.
    $test_notice = '';
    if (
        isset($_POST['fxforms_test_nonce'])
        && wp_verify_nonce(sanitize_key((string) $_POST['fxforms_test_nonce']), 'fxforms_send_test')
    ) {
        $test_to = isset($_POST['fxforms_test_to'])
            ? sanitize_email((string) wp_unslash($_POST['fxforms_test_to']))
            : '';

        if (!$test_to || !is_email($test_to)) {
            $test_notice = '<div class="notice notice-error is-dismissible"><p>'
                . esc_html__('Please enter a valid email address.', 'fx-forms')
                . '</p></div>';
        } else {
            $site    = (string) get_option('blogname', 'FX Forms');
            $subject = sprintf(
                /* translators: %s: site name */
                __('[%s] FX Forms test email', 'fx-forms'),
                $site
            );
            $body = '<p>' . esc_html(sprintf(
                /* translators: %s: mailer label */
                __('This is a test email sent via FX Forms using the "%s" mailer.', 'fx-forms'),
                FXFORMS_MAILERS[(string) get_option('fxforms_mailer', 'default')] ?? 'default'
            )) . '</p>'
                . '<p>' . esc_html__('If you received this, your email configuration is working correctly.', 'fx-forms') . '</p>';

            $ok = fxforms_dispatch_mail(
                [$test_to],
                $subject,
                $body,
                ['Content-Type: text/html; charset=UTF-8']
            );

            fxforms_log_email(0, __('Test email', 'fx-forms'), [$test_to], $subject, $body, $ok);

            if ($ok) {
                $test_notice = '<div class="notice notice-success is-dismissible"><p>'
                    . esc_html(sprintf(
                        /* translators: %s: recipient address */
                        __('Test email sent to %s.', 'fx-forms'),
                        $test_to
                    ))
                    . '</p></div>';
            } else {
                $test_notice = '<div class="notice notice-error is-dismissible"><p>'
                    . esc_html__('Test email failed. Check your mailer settings and try again.', 'fx-forms')
                    . '</p></div>';
            }
        }
    }

    // Handle save.
    if (
        isset($_POST['fxforms_settings_nonce'])
        && wp_verify_nonce(sanitize_key((string) $_POST['fxforms_settings_nonce']), 'fxforms_save_settings')
    ) {
        $mailer_raw = isset($_POST['fxforms_mailer'])
            ? sanitize_key(wp_unslash($_POST['fxforms_mailer']))
            : '';
        $mailer = array_key_exists($mailer_raw, FXFORMS_MAILERS) ? $mailer_raw : 'default';
        update_option('fxforms_mailer', $mailer);

        $api_key = isset($_POST['fxforms_smtp2go_key'])
            ? sanitize_text_field((string) wp_unslash($_POST['fxforms_smtp2go_key']))
            : '';
        update_option('fxforms_smtp2go_key', $api_key);

        $log_enabled = !empty($_POST['fxforms_log_enabled']) ? '1' : '';
        update_option('fxforms_log_enabled', $log_enabled);

        $log_max = isset($_POST['fxforms_log_max']) ? max(1, absint(wp_unslash($_POST['fxforms_log_max']))) : 1000;
        update_option('fxforms_log_max', $log_max);

        echo '<div class="notice notice-success is-dismissible"><p>'
            . esc_html__('Settings saved.', 'fx-forms')
            . '</p></div>';
    }

    $mailer      = (string) get_option('fxforms_mailer', 'default');
    $smtp2go_key = (string) get_option('fxforms_smtp2go_key', '');
    $log_enabled = (bool) get_option('fxforms_log_enabled', false);
    $log_max     = max(1, (int) get_option('fxforms_log_max', 1000));
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('FX Forms — Settings', 'fx-forms'); ?></h1>

        <?php echo wp_kses_post($test_notice); ?>

        <form method="post">
            <?php wp_nonce_field('fxforms_save_settings', 'fxforms_settings_nonce'); ?>

            <h2 class="title"><?php esc_html_e('Sending method', 'fx-forms'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="fxforms_mailer"><?php esc_html_e('Mailer', 'fx-forms'); ?></label>
                    </th>
                    <td>
                        <select name="fxforms_mailer" id="fxforms_mailer">
                            <?php foreach (FXFORMS_MAILERS as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($mailer, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fxforms_smtp2go_key"><?php esc_html_e('SMTP2Go API key', 'fx-forms'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="fxforms_smtp2go_key" id="fxforms_smtp2go_key"
                               class="regular-text" value="<?php echo esc_attr($smtp2go_key); ?>">
                        <p class="description">
                            <?php esc_html_e('Required when SMTP2Go is selected as the mailer.', 'fx-forms'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h2 class="title"><?php esc_html_e('Email log', 'fx-forms'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable log', 'fx-forms'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="fxforms_log_enabled" value="1"
                                   <?php checked($log_enabled); ?>>
                            <?php esc_html_e('Store a copy of every sent email in the database', 'fx-forms'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fxforms_log_max"><?php esc_html_e('Max log entries', 'fx-forms'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="fxforms_log_max" id="fxforms_log_max"
                               class="small-text" min="1" value="<?php echo esc_attr((string) $log_max); ?>">
                        <p class="description">
                            <?php esc_html_e('The oldest entries are pruned automatically once this limit is reached. Default: 1000.', 'fx-forms'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <hr>

        <h2 class="title"><?php esc_html_e('Send a test email', 'fx-forms'); ?></h2>
        <p><?php
            printf(
                /* translators: %s: currently active mailer label */
                esc_html__('Sends a test message using the currently active mailer (%s) to verify your configuration.', 'fx-forms'),
                '<strong>' . esc_html(FXFORMS_MAILERS[(string) get_option('fxforms_mailer', 'default')] ?? 'default') . '</strong>'
            );
        ?></p>

        <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
            <?php wp_nonce_field('fxforms_send_test', 'fxforms_test_nonce'); ?>
            <div>
                <label for="fxforms_test_to" style="display:block;margin-bottom:4px;font-weight:600;">
                    <?php esc_html_e('Send to', 'fx-forms'); ?>
                </label>
                <input type="email" name="fxforms_test_to" id="fxforms_test_to"
                       class="regular-text"
                       value="<?php echo esc_attr((string) get_option('admin_email', '')); ?>"
                       required>
            </div>
            <div>
                <?php submit_button(__('Send test email', 'fx-forms'), 'secondary', 'fxforms_send_test_submit', false); ?>
            </div>
        </form>
    </div>
    <?php
}
