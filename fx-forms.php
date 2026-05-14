<?php
/**
 * Plugin Name:       FX Forms
 * Description:       Simple contact-form plugin for ClassicPress. Define forms, drop them via [fxform id=N], get an email when someone submits.
 * Version:           2.0.0
 * Requires at least: 5.3
 * Requires PHP:      8.1
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fx-forms
 *
 * @package fx-forms
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const FXFORMS_VERSION = '2.0.0';
const FXFORMS_CPT     = 'fxform';
const FXFORMS_META    = '_fxforms_config';

define('FXFORMS_FILE', __FILE__);
define('FXFORMS_DIR', plugin_dir_path(__FILE__));
define('FXFORMS_URL', plugin_dir_url(__FILE__));

require_once FXFORMS_DIR . 'includes/cpt.php';
require_once FXFORMS_DIR . 'includes/admin.php';
require_once FXFORMS_DIR . 'includes/render.php';
require_once FXFORMS_DIR . 'includes/submit.php';

add_action('init', 'fxforms_register_cpt');

add_action('add_meta_boxes', 'fxforms_register_metabox');
add_action('edit_form_after_title', 'fxforms_display_shortcode');
add_action('save_post_' . FXFORMS_CPT, 'fxforms_save_post');
add_action('admin_enqueue_scripts', 'fxforms_admin_assets');

add_shortcode('fxform', 'fxforms_shortcode');

add_action('admin_post_fxforms_submit', 'fxforms_handle_submit');
add_action('admin_post_nopriv_fxforms_submit', 'fxforms_handle_submit');
