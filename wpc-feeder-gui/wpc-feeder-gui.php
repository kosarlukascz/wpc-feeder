<?php
/**
 * Plugin Name: WP CLI Dashboard Commands
 */

add_action('wp_dashboard_setup', 'wpcli_dashboard_widget');

function wpcli_dashboard_widget()
{
    wp_add_dashboard_widget(
        'wpcli_dashboard_widget',         // Widget ID
        'WPC Feeder executer',                // Widget Name
        'wpcli_dashboard_widget_content'  // Callback function
    );
}

function wpcli_dashboard_widget_content()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $status = get_transient('wpc_feeder_gui');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('run_wpcli_command');

        if ($status !== 'running') {
            $command_option = sanitize_text_field($_POST['wpcli_command_option']);

            switch ($command_option) {
                case 'update_meta':
                    $command = 'wp wpc-feeder-meta';
                    break;
                case 'generate_all':
                    $command = 'wp wpc-feeder --generate=all';
                    break;
                case 'generate_one':
                    $command = 'wp wpc-feeder --generate=one';
                    break;
                case 'generate_zero':
                    $command = 'wp wpc-feeder --generate=zero';
                    break;
                default:
                    echo "Invalid command selected.";
                    return;
            }

            set_transient('wpc_feeder_gui', 'running', 3600 * 5);

            shell_exec('cd /home/master/applications/' . DB_NAME . '/public_html && /usr/local/bin/' . $command . ' > /dev/null 2>&1 &');

            echo "<p>Command started: $command. It is running in the background.</p>";
        } else {
            echo "<p>A command is already running. Please wait.</p>";
        }

        return;
    }


    $can_run = read_wpc_feeder_files();

    echo '<form method="post" action="">';
    wp_nonce_field('run_wpcli_command');

    if ($can_run) {
        echo '<select name="wpcli_command_option">';
        echo '<option value="-">Select action</option>';
        echo '<option value="update_meta">Update sales</option>';
        echo '<option value="generate_all">Generate all product</option>';
        echo '<option value="generate_one">Generate products with one+ sales</option>';
        echo '<option value="generate_zero">Generate products with zero sales</option>';
        echo '</select>';
        echo '<input type="submit" value="Run Command" />';
    } else {
        echo 'Operation in progress. Please wait to end before running another command.';
    }
    echo '</form>';
}

function read_wpc_feeder_files()
{
    $can_run = true;
    $upload_dir = wp_upload_dir();
    $folder_path = $upload_dir['basedir'] . '/wpc-feeder/';

    if (!is_dir($folder_path)) {
        echo "<p>The directory 'wpc-feeder' does not exist in the uploads folder.</p>";
        return;
    }

    $files = glob($folder_path . '*.xml');

    if (empty($files)) {
        echo "<p>No XML files found in the 'wpc-feeder' directory.</p>";
        return;
    }
    $minutes_left = get_transient('_transient_wpc_feeder_expected_finish');

    echo '<table class="wpc-feeder">';
    echo '<tr><th>File Name</th><th>Size (GB)</th><th>Last Edit Time</th><th>Estimate to end</th></tr>';

    foreach ($files as $file) {
        $minutes_left = 'done';
        $filename = basename($file);
        $filesize = filesize($file) / (1024 * 1024 * 1024); // size in GB
        $filetime = date("d.m.Y H:i:s", filemtime($file));
        if (strpos($filename, 'temp') !== false) {
            $minutes_left = get_transient('wpc_feeder_expected_finish') . ' min';
            $can_run = false;
        }
        echo '<tr>';
        echo "<td><a href='" . home_url() . "/wp-content/uploads/wpc-feeder/" . $filename . "' target='blank'>$filename</a></td>";
        echo '<td>' . number_format($filesize, 4) . ' GB</td>';
        echo "<td>$filetime</td>";
        echo "<td>$minutes_left </td>";
        echo '</tr>';
    }

    echo '</table>';
    return $can_run;
}

function wpcli_dashboard_widget_styles()
{
    echo '
    <style>
        .wpc-feeder {
            width: 100%;
            max-width: 100%;
            margin-bottom: 1rem;
            background-color: transparent;
            border-collapse: collapse;
            border-spacing: 0;
        }
        .wpc-feeder th, .wpc-feeder td {
            padding: 0.75rem;
            vertical-align: top;
            border-top: 1px solid #dee2e6;
        }
        .wpc-feeder thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
        }
        .wpc-feeder tbody + tbody {
            border-top: 2px solid #dee2e6;
        }
        .wpc-feeder .wpcli-table-active {
            background-color: rgba(0, 123, 255, 0.05);
        }
        .wpc-feeder .wpcli-table-active > td, .wpc-feeder .wpcli-table-active > th {
            background-color: rgba(0, 123, 255, 0.075);
        }
        .wpc-feeder .wpcli-table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.05);
        }
    </style>
    ';
}

add_action('admin_head', 'wpcli_dashboard_widget_styles');


?>
