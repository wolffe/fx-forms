<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function fxforms_shortcode(array|string $atts): string
{
    $atts = is_array($atts) ? $atts : [];
    $form_id = isset($atts['id']) ? (int) $atts['id'] : 0;
    if (!$form_id && isset($atts[0])) {
        $form_id = (int) $atts[0];
    }
    if (!$form_id) {
        return '';
    }

    $post = get_post($form_id);
    if (!$post || $post->post_type !== FXFORMS_CPT || $post->post_status !== 'publish') {
        return '';
    }

    wp_enqueue_style('fxforms-form', FXFORMS_URL . 'assets/form.css', [], FXFORMS_VERSION);

    $config = fxforms_get_config($form_id);
    $status = fxforms_current_status($form_id);
    $values = ($status === 'error') ? fxforms_get_stashed_values() : [];

    return fxforms_render_form($form_id, $config, $status, $values);
}

function fxforms_current_status(int $form_id): string
{
    if (!isset($_GET['fxforms_form']) || (int) $_GET['fxforms_form'] !== $form_id) {
        return '';
    }
    $status = isset($_GET['fxforms_status']) ? sanitize_key((string) $_GET['fxforms_status']) : '';
    return in_array($status, ['success', 'error'], true) ? $status : '';
}

function fxforms_current_url(): string
{
    $req = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    return remove_query_arg(['fxforms_status', 'fxforms_form', 'fxforms_token'], home_url($req));
}

function fxforms_get_stashed_values(): array
{
    $token = isset($_GET['fxforms_token']) ? sanitize_key((string) $_GET['fxforms_token']) : '';
    if ($token === '') {
        return [];
    }
    $values = get_transient('fxforms_stash_' . $token);
    return is_array($values) ? $values : [];
}

function fxforms_render_form(int $form_id, array $config, string $status, array $values = []): string
{
    ob_start();
    ?>
    <form class="fxforms-form" id="fxforms-form-<?php echo (int) $form_id; ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" novalidate>
        <input type="hidden" name="action" value="fxforms_submit">
        <input type="hidden" name="form_id" value="<?php echo (int) $form_id; ?>">
        <input type="hidden" name="redirect_to" value="<?php echo esc_url(fxforms_current_url()); ?>">
        <?php wp_nonce_field('fxforms_submit_' . $form_id, 'fxforms_nonce'); ?>

        <div class="fxforms-honeypot" aria-hidden="true">
            <label>Leave this field empty <input type="text" name="fxforms_hp" value="" tabindex="-1" autocomplete="off"></label>
        </div>

        <?php if ($status === 'success'): ?>
            <div class="fxforms-status fxforms-status-success"><?php echo esc_html($config['success_message']); ?></div>
        <?php elseif ($status === 'error'): ?>
            <div class="fxforms-status fxforms-status-error"><?php echo esc_html($config['error_message']); ?></div>
        <?php endif; ?>

        <?php foreach ($config['fields'] as $field): ?>
            <?php echo fxforms_render_field($form_id, $field, $values); ?>
        <?php endforeach; ?>

        <p class="fxforms-actions">
            <button type="submit" class="fxforms-submit"><?php echo esc_html($config['submit_label']); ?></button>
        </p>
    </form>
    <?php
    return (string) ob_get_clean();
}

function fxforms_render_field(int $form_id, array $field, array $values = []): string
{
    $id       = 'fxforms_' . $form_id . '_' . $field['id'];
    $name     = $field['id'];
    $required = !empty($field['required']);
    $req_attr = $required ? ' required' : '';
    $req_mark = $required ? ' <span class="fxforms-required" aria-hidden="true">*</span>' : '';
    $width    = ($field['width'] ?? 'full') === 'half' ? 'half' : 'full';

    $label = esc_html($field['label']);
    $type  = $field['type'];
    $val   = $values[$field['id']] ?? null;

    ob_start();
    echo '<p class="fxforms-field fxforms-field-' . esc_attr($type) . ' fxforms-field-width-' . esc_attr($width) . '">';

    switch ($type) {
        case 'checkbox':
            $checked = !empty($val) ? ' checked' : '';
            echo '<label for="' . esc_attr($id) . '" class="fxforms-checkbox-label">';
            echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1"' . $req_attr . $checked . '>';
            echo '<span>' . $label . $req_mark . '</span>';
            echo '</label>';
            break;

        case 'textarea':
            echo '<label for="' . esc_attr($id) . '">' . $label . $req_mark . '</label>';
            echo '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" rows="5"' . $req_attr . '>' . esc_textarea(is_string($val) ? $val : '') . '</textarea>';
            break;

        case 'select':
            echo '<label for="' . esc_attr($id) . '">' . $label . $req_mark . '</label>';
            echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"' . $req_attr . '>';
            echo '<option value="">' . esc_html__('— Select —', 'fx-forms') . '</option>';
            foreach ($field['options'] as $opt) {
                $selected = (is_string($val) && $val === $opt) ? ' selected' : '';
                echo '<option value="' . esc_attr($opt) . '"' . $selected . '>' . esc_html($opt) . '</option>';
            }
            echo '</select>';
            break;

        case 'full_name':
            echo '<span class="fxforms-field-label">' . $label . $req_mark . '</span>';
            echo '<span class="fxforms-subfields">';
            foreach (['first' => __('First', 'fx-forms'), 'last' => __('Last', 'fx-forms')] as $part => $sublabel) {
                $sub_id  = $id . '_' . $part;
                $sub_val = is_array($val) ? esc_attr((string) ($val[$part] ?? '')) : '';
                echo '<span class="fxforms-subfield">';
                echo '<input type="text" id="' . esc_attr($sub_id) . '" name="' . esc_attr($name . '_' . $part) . '" value="' . $sub_val . '"' . $req_attr . '>';
                echo '<small><label for="' . esc_attr($sub_id) . '">' . esc_html($sublabel) . '</label></small>';
                echo '</span>';
            }
            echo '</span>';
            break;

        default:
            $input_type = in_array($type, ['text', 'email', 'tel', 'number', 'url', 'date', 'time'], true) ? $type : 'text';
            $input_val  = is_string($val) ? esc_attr($val) : '';
            echo '<label for="' . esc_attr($id) . '">' . $label . $req_mark . '</label>';
            echo '<input type="' . esc_attr($input_type) . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . $input_val . '"' . $req_attr . '>';
            break;
    }

    if (!empty($field['description'])) {
        echo '<small class="fxforms-field-description">' . esc_html((string) $field['description']) . '</small>';
    }

    echo '</p>';

    return (string) ob_get_clean();
}
