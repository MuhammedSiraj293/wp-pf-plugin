<?php
/**
 * Handles the Settings Page for Property Finder Sync.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TCA_RE_PF_Settings
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_sync_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_sync_menu()
    {
        add_submenu_page(
            'edit.php?post_type=tca_unit',
            'PF Sync Settings',
            'PF Sync',
            'manage_options',
            'tca-pf-sync',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings()
    {
        register_setting('tca_pf_sync_group', 'tca_pf_api_key');
        register_setting('tca_pf_sync_group', 'tca_pf_api_secret');
        register_setting('tca_pf_sync_group', 'tca_pf_sandbox_mode');

        // Register Ajax for Sync
        add_action('wp_ajax_tca_pf_run_sync', [$this, 'ajax_run_sync']);
        add_action('wp_ajax_tca_pf_sample_payload', [$this, 'ajax_sample_payload']);
    }

    public function ajax_sample_payload()
    {
        check_ajax_referer('tca_pf_test_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (!class_exists('TCA_RE_PF_Sync_Engine')) {
            wp_send_json_error('Sync engine class not found.');
        }

        $connector = new TCA_RE_PF_Connector();
        $engine = new TCA_RE_PF_Sync_Engine($connector);
        $payload = $engine->get_sample_payload();

        if (is_wp_error($payload)) {
            wp_send_json_error($payload->get_error_message());
        }

        // Return pretty printed JSON
        wp_send_json_success('<pre style="background:#f0f0f0; padding:15px; border-radius:5px; max-height:400px; overflow:auto; text-align:left;">' . esc_html(json_encode($payload, JSON_PRETTY_PRINT)) . '</pre>');
    }

    public function ajax_run_sync()
    {
        check_ajax_referer('tca_pf_test_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Prevent PHP from timing out during heavy image downloads
        set_time_limit(0);

        // Must include the engine, which is loaded globally in the main plugin file
        if (!class_exists('TCA_RE_PF_Sync_Engine')) {
            wp_send_json_error('Sync engine class not found.');
        }

        $connector = new TCA_RE_PF_Connector();
        $engine = new TCA_RE_PF_Sync_Engine($connector);
        $result = $engine->run_manual_sync();

        if ( $result['success'] ) {
            // Pass the full result object so JS can read .total, .current, .finished, .message
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    public function render_settings_page()
    {
        ?>
        <div class="wrap">
            <h1>Property Finder Sync Settings</h1>
            <p>Configure your PF Expert API credentials below. Use <strong>Sandbox Mode</strong> for testing.</p>

            <form method="post" action="options.php">
                <?php settings_fields('tca_pf_sync_group'); ?>
                <?php do_settings_sections('tca_pf_sync_group'); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">API Key</th>
                        <td><input type="password" name="tca_pf_api_key"
                                value="<?php echo esc_attr(get_option('tca_pf_api_key')); ?>" class="regular-text" /></td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">API Secret</th>
                        <td><input type="password" name="tca_pf_api_secret"
                                value="<?php echo esc_attr(get_option('tca_pf_api_secret')); ?>" class="regular-text" /></td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">Sandbox Mode</th>
                        <td>
                            <input type="checkbox" name="tca_pf_sandbox_mode" value="1" <?php checked(1, get_option('tca_pf_sandbox_mode'), true); ?> />
                            <span class="description">Enable this to use the Atlas Sandbox environment.</span>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>

            <h2>Automated Chain Sync</h2>
            <p>This will automatically download all properties 5 at a time, pausing for 10 seconds between batches to protect
                your server.</p>
            <div id="tca-sync-results" style="margin-bottom: 20px;">
                <button type="button" id="tca-sample-payload-btn" class="button button-secondary button-hero"
                    style="margin-right: 15px;">🔍 View Sample Payload</button>
                <button type="button" id="tca-start-sync-btn" class="button button-primary button-hero"
                    style="margin-right: 10px;">▶️ Start Auto-Sync</button>
                <button type="button" id="tca-stop-sync-btn" class="button button-secondary button-hero"
                    style="display:none; color: red;">⏹️ Stop Auto-Sync</button>

                <div style="margin-top: 20px; max-width: 600px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span id="tca-progress-text" style="font-weight: bold;">Ready to sync.</span>
                        <span id="tca-progress-count" style="font-weight: bold;">0 / 0</span>
                    </div>
                    <progress id="tca-progress-bar" value="0" max="100" style="width: 100%; height: 25px;"></progress>
                </div>

                <div style="margin-top: 15px;">
                    <span id="tca-sync-status" style="font-size: 14px; font-weight: normal; color: #666;"></span>
                    <div id="tca-payload-display" style="margin-top: 15px;"></div>
                </div>
            </div>

            <hr>

            <h2>Connection Test</h2>
            <div id="tca-connection-test-results">
                <button type="button" id="tca-test-connection-btn" class="button button-secondary">Test API Connection</button>
                <span id="tca-test-status"></span>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                $('#tca-test-connection-btn').on('click', function () {
                    var btn = $(this);
                    var status = $('#tca-test-status');

                    btn.prop('disabled', true).text('Testing...');
                    status.text('').css('color', 'inherit');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'tca_pf_test_connection',
                            _ajax_nonce: '<?php echo wp_create_nonce("tca_pf_test_nonce"); ?>'
                        },
                        success: function (response) {
                            if (response.success) {
                                status.text('✅ Success: ' + response.data).css('color', 'green');
                            } else {
                                status.text('❌ Failed: ' + response.data).css('color', 'red');
                            }
                        },
                        error: function () {
                            status.text('❌ Server error during test.').css('color', 'red');
                        },
                        complete: function () {
                            btn.prop('disabled', false).text('Test API Connection');
                        }
                    });
                });

                // Chain Sync Logic
                var isSyncing = false;
                var syncTimer = null;

                $('#tca-start-sync-btn').on('click', function () {
                    isSyncing = true;
                    $(this).hide();
                    $('#tca-stop-sync-btn').show();
                    $('#tca-progress-text').text('Syncing... Please do not close this tab.').css('color', 'blue');
                    $('#tca-sync-status').text('');
                    $('#tca-payload-display').html('');

                    runNextBatch();
                });

                $('#tca-stop-sync-btn').on('click', function () {
                    isSyncing = false;
                    clearTimeout(syncTimer);
                    $(this).hide();
                    $('#tca-start-sync-btn').show().text('▶️ Resume Auto-Sync');
                    $('#tca-progress-text').text('Sync Paused.').css('color', 'orange');
                });

                function runNextBatch() {
                    if (!isSyncing) return;

                    $('#tca-sync-status').text('Downloading batch...').css('color', '#666');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'tca_pf_run_sync',
                            _ajax_nonce: '<?php echo wp_create_nonce("tca_pf_test_nonce"); ?>'
                        },
                        success: function (response) {
                            if (response.success) {
                                var data = response.data;
                                $('#tca-sync-status').html('✅ ' + data.message).css('color', 'green');

                                // Update Progress Bar
                                if (data.total > 0) {
                                    var percentage = (data.current / data.total) * 100;
                                    $('#tca-progress-bar').val(percentage);
                                    $('#tca-progress-count').text(data.current + ' / ' + data.total);

                                    var left = data.total - data.current;
                                    $('#tca-progress-text').text(data.current + ' Imported | ' + left + ' Remaining').css('color', 'blue');
                                }

                                if (data.finished) {
                                    isSyncing = false;
                                    $('#tca-stop-sync-btn').hide();
                                    $('#tca-start-sync-btn').show().text('▶️ Start Auto-Sync');
                                    $('#tca-progress-text').text('Sync Complete! 100% Finished.').css('color', 'green');
                                } else {
                                    if (isSyncing) {
                                        $('#tca-sync-status').append('<br><em>Waiting 5 seconds before next batch...</em>');
                                        syncTimer = setTimeout(runNextBatch, 5000); // 5 second gap
                                    }
                                }
                            } else {
                                isSyncing = false;
                                $('#tca-stop-sync-btn').hide();
                                $('#tca-start-sync-btn').show().text('▶️ Retry Auto-Sync');
                                $('#tca-sync-status').text('❌ Failed: ' + response.data).css('color', 'red');
                                $('#tca-progress-text').text('Sync Failed. Check status below.').css('color', 'red');
                            }
                        },
                        error: function () {
                            isSyncing = false;
                            $('#tca-stop-sync-btn').hide();
                            $('#tca-start-sync-btn').show().text('▶️ Retry Auto-Sync');
                            $('#tca-sync-status').text('❌ Server error during sync.').css('color', 'red');
                            $('#tca-progress-text').text('Server Timeout. Click Resume to continue.').css('color', 'orange');
                        }
                    });
                }

                // Sample Payload Button
                $('#tca-sample-payload-btn').on('click', function () {
                    var btn = $(this);
                    var display = $('#tca-payload-display');
                    var status = $('#tca-sync-status');

                    btn.prop('disabled', true).text('Loading Payload...');
                    status.text('');
                    display.html('<p>Connecting to Property Finder Atlas API...</p>');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'tca_pf_sample_payload',
                            _ajax_nonce: '<?php echo wp_create_nonce("tca_pf_test_nonce"); ?>'
                        },
                        success: function (response) {
                            if (response.success) {
                                display.html(response.data);
                            } else {
                                display.html('<p style="color:red;">❌ Error: ' + response.data + '</p>');
                            }
                        },
                        error: function () {
                            display.html('<p style="color:red;">❌ Server error during payload fetch.</p>');
                        },
                        complete: function () {
                            btn.prop('disabled', false).text('🔍 View Sample Payload');
                        }
                    });
                });
            });
        </script>
        <?php
    }
}
