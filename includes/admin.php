<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const FXFORMS_FIELD_TYPES = [
    'text'      => 'Text',
    'full_name' => 'Full Name',
    'email'     => 'Email',
    'tel'       => 'Phone',
    'number'    => 'Number',
    'url'       => 'URL',
    'date'      => 'Date',
    'time'      => 'Time',
    'textarea'  => 'Textarea',
    'select'    => 'Select',
    'checkbox'  => 'Checkbox',
];

const FXFORMS_FIELD_WIDTHS = ['full' => 'Full width', 'half' => 'Half width'];

function fxforms_default_config(): array
{
    return [
        'fields'          => [
            ['id' => 'name',    'label' => 'Your Name', 'type' => 'full_name', 'required' => true, 'options' => [], 'width' => 'full', 'description' => ''],
            ['id' => 'email',   'label' => 'Email',     'type' => 'email',     'required' => true, 'options' => [], 'width' => 'full', 'description' => ''],
            ['id' => 'message', 'label' => 'Message',   'type' => 'textarea',  'required' => true, 'options' => [], 'width' => 'full', 'description' => ''],
        ],
        'submit_label'    => 'Send',
        'success_message' => 'Thanks! Your message has been sent.',
        'error_message'   => 'Something went wrong. Please check the form and try again.',
        'mail_to'         => '',
        'mail_subject'    => 'New submission on {form_title}',
        'mail_body'       => "A new submission has arrived:\n\n{data}",
    ];
}

function fxforms_get_config(int $form_id): array
{
    $stored = get_post_meta($form_id, FXFORMS_META, true);
    if (!is_array($stored)) {
        return fxforms_default_config();
    }
    $merged = array_replace(fxforms_default_config(), $stored);
    if (!isset($merged['fields']) || !is_array($merged['fields']) || !$merged['fields']) {
        $merged['fields'] = fxforms_default_config()['fields'];
    }
    return $merged;
}

function fxforms_register_metabox(): void
{
    add_meta_box(
        'fxforms-builder',
        __('Form Builder', 'fx-forms'),
        'fxforms_render_metabox',
        FXFORMS_CPT,
        'normal',
        'high'
    );
}

function fxforms_display_shortcode(WP_Post $post): void
{
    if ($post->post_type !== FXFORMS_CPT || !$post->ID) {
        return;
    }

    $code = sprintf('[fxform id=%d]', $post->ID);
    ?>
    <div class="fxforms-shortcode-bar">
        <strong><?php esc_html_e('Shortcode:', 'fx-forms'); ?></strong>
        <code class="fxforms-shortcode-value"><?php echo esc_html($code); ?></code>
        <button type="button" class="button fxforms-shortcode-copy" data-clipboard="<?php echo esc_attr($code); ?>"><?php esc_html_e('Copy', 'fx-forms'); ?></button>
        <span class="fxforms-shortcode-feedback" aria-live="polite"></span>
    </div>
    <?php
}

function fxforms_render_metabox(WP_Post $post): void
{
    $config = fxforms_get_config($post->ID);
    wp_nonce_field('fxforms_save_' . $post->ID, 'fxforms_nonce');
    ?>
    <div class="fxforms-builder"
         data-config="<?php echo esc_attr(wp_json_encode($config)); ?>"
         data-types="<?php echo esc_attr(wp_json_encode(FXFORMS_FIELD_TYPES)); ?>"
         data-widths="<?php echo esc_attr(wp_json_encode(FXFORMS_FIELD_WIDTHS)); ?>">

        <input type="hidden" name="fxforms_fields" id="fxforms_fields" value="">

        <h3><?php esc_html_e('Fields', 'fx-forms'); ?></h3>
        <ul class="fxforms-builder-fields"></ul>
        <p><button type="button" class="button" id="fxforms-add-field"><?php esc_html_e('Add field', 'fx-forms'); ?></button></p>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="fxforms_submit_label"><?php esc_html_e('Submit button label', 'fx-forms'); ?></label></th>
                <td><input type="text" name="fxforms_submit_label" id="fxforms_submit_label" class="regular-text" value="<?php echo esc_attr($config['submit_label']); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="fxforms_success_message"><?php esc_html_e('Success message', 'fx-forms'); ?></label></th>
                <td><input type="text" name="fxforms_success_message" id="fxforms_success_message" class="large-text" value="<?php echo esc_attr($config['success_message']); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="fxforms_error_message"><?php esc_html_e('Error message', 'fx-forms'); ?></label></th>
                <td><input type="text" name="fxforms_error_message" id="fxforms_error_message" class="large-text" value="<?php echo esc_attr($config['error_message']); ?>"></td>
            </tr>
        </table>

        <h3><?php esc_html_e('Email notification', 'fx-forms'); ?></h3>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="fxforms_mail_to"><?php esc_html_e('Send to', 'fx-forms'); ?></label></th>
                <td>
                    <input type="text" name="fxforms_mail_to" id="fxforms_mail_to" class="large-text"
                           value="<?php echo esc_attr($config['mail_to']); ?>"
                           placeholder="you@example.com, other@example.com">
                    <p class="description"><?php esc_html_e('Comma- or semicolon-separated. Falls back to the site admin email if blank.', 'fx-forms'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fxforms_mail_subject"><?php esc_html_e('Subject', 'fx-forms'); ?></label></th>
                <td><input type="text" name="fxforms_mail_subject" id="fxforms_mail_subject" class="large-text" value="<?php echo esc_attr($config['mail_subject']); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="fxforms_mail_body"><?php esc_html_e('Body', 'fx-forms'); ?></label></th>
                <td>
                    <textarea name="fxforms_mail_body" id="fxforms_mail_body" rows="6" class="large-text"><?php echo esc_textarea($config['mail_body']); ?></textarea>
                    <p class="description"><?php esc_html_e('Placeholders: {data} = all fields, {form_title}, {form_id}, and any field id like {email} or {message}.', 'fx-forms'); ?></p>
                </td>
            </tr>
        </table>
    </div>
    <?php
}

function fxforms_admin_assets(string $hook): void
{
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== FXFORMS_CPT) {
        return;
    }

    $deps = [];
    if (wp_script_is('sortablejs', 'registered')) {
        wp_enqueue_script('sortablejs');
        $deps[] = 'sortablejs';
    }

    wp_enqueue_style('fxforms-admin', FXFORMS_URL . 'assets/admin.css', [], FXFORMS_VERSION);
    wp_enqueue_script('fxforms-admin', FXFORMS_URL . 'assets/admin.js', $deps, FXFORMS_VERSION, true);
}

function fxforms_save_post(int $post_id): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (
        !isset($_POST['fxforms_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fxforms_nonce'])), 'fxforms_save_' . $post_id)
    ) {
        return;
    }

    $config = fxforms_default_config();

    if (isset($_POST['fxforms_fields'])) {
        $raw = (string) wp_unslash($_POST['fxforms_fields']);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $config['fields'] = fxforms_sanitize_fields($decoded);
        }
    }
    if (empty($config['fields'])) {
        $config['fields'] = fxforms_default_config()['fields'];
    }

    if (isset($_POST['fxforms_submit_label'])) {
        $config['submit_label'] = sanitize_text_field((string) wp_unslash($_POST['fxforms_submit_label']));
    }
    if (isset($_POST['fxforms_success_message'])) {
        $config['success_message'] = sanitize_text_field((string) wp_unslash($_POST['fxforms_success_message']));
    }
    if (isset($_POST['fxforms_error_message'])) {
        $config['error_message'] = sanitize_text_field((string) wp_unslash($_POST['fxforms_error_message']));
    }
    if (isset($_POST['fxforms_mail_to'])) {
        $config['mail_to'] = fxforms_sanitize_recipient_list((string) wp_unslash($_POST['fxforms_mail_to']));
    }
    if (isset($_POST['fxforms_mail_subject'])) {
        $config['mail_subject'] = sanitize_text_field((string) wp_unslash($_POST['fxforms_mail_subject']));
    }
    if (isset($_POST['fxforms_mail_body'])) {
        $config['mail_body'] = sanitize_textarea_field((string) wp_unslash($_POST['fxforms_mail_body']));
    }

    update_post_meta($post_id, FXFORMS_META, $config);
}

function fxforms_sanitize_fields(array $raw): array
{
    $clean    = [];
    $seen_ids = [];

    foreach ($raw as $item) {
        if (!is_array($item)) continue;

        $label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
        if ($label === '') continue;

        $type = isset($item['type']) ? (string) $item['type'] : 'text';
        if (!array_key_exists($type, FXFORMS_FIELD_TYPES)) {
            $type = 'text';
        }

        $id = isset($item['id']) ? sanitize_key((string) $item['id']) : '';
        if ($id === '' || isset($seen_ids[$id])) {
            $id = fxforms_unique_field_id($label, $seen_ids);
        }
        $seen_ids[$id] = true;

        $options = [];
        if ($type === 'select' && isset($item['options']) && is_array($item['options'])) {
            foreach ($item['options'] as $opt) {
                $opt = sanitize_text_field((string) $opt);
                if ($opt !== '' && !in_array($opt, $options, true)) {
                    $options[] = $opt;
                }
            }
        }

        $width = isset($item['width']) && array_key_exists((string) $item['width'], FXFORMS_FIELD_WIDTHS)
            ? (string) $item['width']
            : 'full';

        $description = isset($item['description'])
            ? sanitize_text_field((string) $item['description'])
            : '';

        $clean[] = [
            'id'          => $id,
            'label'       => $label,
            'type'        => $type,
            'required'    => !empty($item['required']),
            'options'     => $options,
            'width'       => $width,
            'description' => $description,
        ];
    }

    return $clean;
}

function fxforms_unique_field_id(string $label, array $existing): string
{
    $base = sanitize_title($label);
    $base = $base !== '' ? str_replace('-', '_', $base) : 'field';

    $id = $base;
    $i  = 2;
    while (isset($existing[$id])) {
        $id = $base . '_' . $i++;
    }
    return $id;
}

function fxforms_sanitize_recipient_list(string $raw): string
{
    if (trim($raw) === '') return '';

    $emails = [];
    foreach (preg_split('/[,;]/', $raw) ?: [] as $piece) {
        $email = sanitize_email(trim($piece));
        if ($email !== '' && is_email($email) && !in_array($email, $emails, true)) {
            $emails[] = $email;
        }
    }
    return implode(', ', $emails);
}
