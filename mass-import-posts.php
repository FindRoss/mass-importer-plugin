<?php
/**
 * Plugin Name: Mass Import Posts (JSON) - Simple
 * Description: Upload a JSON file to create posts with title and content.
 * Version: 1.0
 * Author: Ross Findlay
 */

if (!defined('ABSPATH')) exit;

class Mass_Import_Posts_Simple {

    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_post_mip_upload', array($this, 'handle_upload'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    public function admin_menu() {
        add_management_page('Mass Import Posts', 'Mass Import Posts', 'manage_options', 'mass-import-posts', array($this, 'admin_page'));
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        ?>
        <div class="wrap">
            <h1>Mass Import Posts</h1>
            <p>Upload a JSON file containing an array of post objects with <code>title</code> and <code>content</code> fields.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('mip_upload'); ?>
                <input type="hidden" name="action" value="mip_upload">

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="mip_file">JSON file</label></th>
                        <td><input type="file" id="mip_file" name="mip_file" accept="application/json" required></td>
                    </tr>
                </table>

                <p><input type="submit" class="button button-primary" value="Import Posts"></p>
            </form>

            <h2>JSON Example</h2>
            <pre>
            [
                {
                    "title": "My First Post",
                    "content": "<p>This is the post content</p>"
                },
                {
                    "title": "My Second Post",
                    "content": "<p>More content here</p>"
                }
            ]
        </pre>
        </div>

    <?php }

    public function handle_upload() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        check_admin_referer('mip_upload');

        if (empty($_FILES['mip_file']) || $_FILES['mip_file']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(add_query_arg('mip_msg', 'nofile', admin_url('tools.php?page=mass-import-posts')));
            exit;
        }

        $tmp = $_FILES['mip_file']['tmp_name'];
        $raw = file_get_contents($tmp);
        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            wp_redirect(add_query_arg('mip_msg', 'jsonerror', admin_url('tools.php?page=mass-import-posts')));
            exit;
        }

        $created = 0;
        $failed = 0;

        foreach ($data as $item) {
            if (!is_array($item)) {
                $failed++;
                continue;
            }

            $title = trim($item['title'] ?? '');
            $content = $item['content'] ?? '';

            if (empty($title)) {
                $failed++;
                continue;
            }

            $post_id = wp_insert_post(array(
                'post_title' => $title,
                'post_content' => $content,
                'post_type' => 'post',
                'post_status' => 'publish',
            ), true);

            if (is_wp_error($post_id)) {
                $failed++;
            } else {
                $created++;
            }
        }

        wp_redirect(add_query_arg(array('mip_msg' => 'done', 'mip_created' => $created, 'mip_failed' => $failed), admin_url('tools.php?page=mass-import-posts')));
        exit;
    }

    public function admin_notices() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'mass-import-posts') return;

        if (isset($_GET['mip_msg'])) {
            $msg = sanitize_text_field($_GET['mip_msg']);
            
            if ($msg === 'nofile') {
                echo '<div class="notice notice-error"><p>No file uploaded.</p></div>';
            }
            if ($msg === 'jsonerror') {
                echo '<div class="notice notice-error"><p>Uploaded file is not valid JSON.</p></div>';
            }
            if ($msg === 'done') {
                $created = intval($_GET['mip_created'] ?? 0);
                $failed = intval($_GET['mip_failed'] ?? 0);
                echo "<div class=\"notice notice-success\"><p>Import complete! Created: $created, Failed: $failed</p></div>";
            }
        }
    }
}

new Mass_Import_Posts_Simple();