<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// ---------------------------------------------------------------------------
// Table helpers
// ---------------------------------------------------------------------------

function fxforms_log_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'fxforms_log';
}

/**
 * Create (or upgrade) the log table. Safe to call multiple times — uses dbDelta.
 */
function fxforms_create_log_table(): void
{
    global $wpdb;

    $table           = fxforms_log_table();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        form_id bigint(20) unsigned NOT NULL DEFAULT 0,
        form_title varchar(255) NOT NULL DEFAULT '',
        recipients text NOT NULL,
        subject varchar(500) NOT NULL DEFAULT '',
        body longtext NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'sent',
        sent_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY form_id (form_id),
        KEY sent_at (sent_at)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// ---------------------------------------------------------------------------
// Writing & pruning
// ---------------------------------------------------------------------------

/**
 * Append one entry to the log (no-op when logging is disabled).
 *
 * @param string[] $recipients
 */
function fxforms_log_email(
    int $form_id,
    string $form_title,
    array $recipients,
    string $subject,
    string $body,
    bool $ok
): void {
    if (!get_option('fxforms_log_enabled')) {
        return;
    }

    global $wpdb;

    $wpdb->insert(
        fxforms_log_table(),
        [
            'form_id'    => $form_id,
            'form_title' => $form_title,
            'recipients' => implode(', ', $recipients),
            'subject'    => $subject,
            'body'       => $body,
            'status'     => $ok ? 'sent' : 'failed',
            'sent_at'    => current_time('mysql'),
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
    );

    fxforms_prune_log();
}

/**
 * Delete the oldest rows that exceed the configured maximum.
 */
function fxforms_prune_log(): void
{
    global $wpdb;

    $table = fxforms_log_table();
    $max   = max(1, (int) get_option('fxforms_log_max', 1000));
    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");

    if ($count <= $max) {
        return;
    }

    $wpdb->query($wpdb->prepare(
        "DELETE FROM $table ORDER BY sent_at ASC, id ASC LIMIT %d",
        $count - $max
    ));
}

// ---------------------------------------------------------------------------
// Admin log page
// ---------------------------------------------------------------------------

function fxforms_render_log_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle "Clear log" action.
    if (
        isset($_POST['fxforms_clear_log'], $_POST['fxforms_log_nonce'])
        && wp_verify_nonce(sanitize_key((string) $_POST['fxforms_log_nonce']), 'fxforms_clear_log')
    ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query('TRUNCATE TABLE ' . fxforms_log_table());
        echo '<div class="notice notice-success is-dismissible"><p>'
            . esc_html__('Email log cleared.', 'fx-forms')
            . '</p></div>';
    }

    global $wpdb;

    $table    = fxforms_log_table();
    $per_page = 50;
    $page     = max(1, (int) ($_GET['paged'] ?? 1));
    $offset   = ($page - 1) * $per_page;
    $total    = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $rows     = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table ORDER BY sent_at DESC, id DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('FX Forms — Email Log', 'fx-forms'); ?></h1>

        <?php if ($total === 0): ?>
            <p><?php esc_html_e('No emails logged yet.', 'fx-forms'); ?></p>
        <?php else: ?>
            <form method="post" style="margin-bottom:1em;">
                <?php wp_nonce_field('fxforms_clear_log', 'fxforms_log_nonce'); ?>
                <input type="submit" name="fxforms_clear_log" class="button button-secondary"
                       value="<?php esc_attr_e('Clear log', 'fx-forms'); ?>"
                       onclick="return confirm('<?php esc_attr_e('Delete all log entries? This cannot be undone.', 'fx-forms'); ?>')">
                <span style="margin-left:1em;color:#666;">
                    <?php printf(
                        /* translators: 1: current entry count, 2: configured maximum */
                        esc_html__('%1$d entries (max %2$d)', 'fx-forms'),
                        $total,
                        max(1, (int) get_option('fxforms_log_max', 1000))
                    ); ?>
                </span>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'fx-forms'); ?></th>
                        <th><?php esc_html_e('Form', 'fx-forms'); ?></th>
                        <th><?php esc_html_e('Subject', 'fx-forms'); ?></th>
                        <th><?php esc_html_e('Recipients', 'fx-forms'); ?></th>
                        <th><?php esc_html_e('Status', 'fx-forms'); ?></th>
                        <th><?php esc_html_e('Body', 'fx-forms'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row->sent_at); ?></td>
                            <td>
                                <?php
                                $edit_link = $row->form_id ? get_edit_post_link((int) $row->form_id) : '';
                                if ($edit_link) {
                                    echo '<a href="' . esc_url($edit_link) . '">' . esc_html($row->form_title) . '</a>';
                                } else {
                                    echo esc_html($row->form_title);
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html($row->subject); ?></td>
                            <td><?php echo esc_html($row->recipients); ?></td>
                            <td>
                                <?php if ($row->status === 'sent'): ?>
                                    <span style="color:#2e7d32;">&#10003; <?php esc_html_e('Sent', 'fx-forms'); ?></span>
                                <?php else: ?>
                                    <span style="color:#c62828;">&#10007; <?php esc_html_e('Failed', 'fx-forms'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row->body !== ''): ?>
                                    <button type="button" class="button button-small fxforms-view-body"
                                            data-body="<?php echo esc_attr($row->body); ?>"
                                            data-subject="<?php echo esc_attr($row->subject); ?>">
                                        <?php esc_html_e('View', 'fx-forms'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total > $per_page): ?>
                <div class="tablenav bottom" style="margin-top:.5em;">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links([
                            'base'    => add_query_arg('paged', '%#%'),
                            'format'  => '',
                            'current' => $page,
                            'total'   => (int) ceil($total / $per_page),
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <dialog id="fxforms-log-dialog" style="padding:0;border:1px solid #c3c4c7;border-radius:4px;box-shadow:0 4px 24px rgba(0,0,0,.18);max-width:90vw;width:680px;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #c3c4c7;gap:16px;">
            <strong id="fxforms-log-dialog-subject" style="font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></strong>
            <button type="button" id="fxforms-log-dialog-close"
                    style="background:none;border:none;cursor:pointer;font-size:20px;line-height:1;padding:0;color:#666;"
                    aria-label="<?php esc_attr_e('Close', 'fx-forms'); ?>">&#x2715;</button>
        </div>
        <iframe id="fxforms-log-dialog-iframe" srcdoc="" sandbox="allow-same-origin"
                style="display:block;width:100%;height:420px;border:none;"></iframe>
    </dialog>

    <script>
    (function () {
        var dialog  = document.getElementById('fxforms-log-dialog');
        var iframe  = document.getElementById('fxforms-log-dialog-iframe');
        var subject = document.getElementById('fxforms-log-dialog-subject');

        document.querySelectorAll('.fxforms-view-body').forEach(function (btn) {
            btn.addEventListener('click', function () {
                subject.textContent = btn.dataset.subject || '';
                iframe.srcdoc = btn.dataset.body || '';
                dialog.showModal();
            });
        });

        document.getElementById('fxforms-log-dialog-close').addEventListener('click', function () {
            dialog.close();
        });

        // Close on backdrop click.
        dialog.addEventListener('click', function (e) {
            var rect = dialog.getBoundingClientRect();
            if (e.clientX < rect.left || e.clientX > rect.right ||
                e.clientY < rect.top  || e.clientY > rect.bottom) {
                dialog.close();
            }
        });
    }());
    </script>
    <?php
}
