<?php
/**
 * Plugin Name: Global Hill View RWA Portal
 * Description: Voter list search and RWA membership application portal for Global Hill View Apartment Owners Welfare Association.
 * Version: 1.0.0
 * Author: Global Hill View RWA
 * Text Domain: ghv-rwa-portal
 */

if (!defined('ABSPATH')) { exit; }

class GHV_RWA_Portal {
    const VERSION = '1.1.0';
    const NONCE = 'ghv_rwa_nonce';

    private static $instance = null;
    private $voters_table;
    private $apps_table;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->voters_table = $wpdb->prefix . 'ghv_voters';
        $this->apps_table = $wpdb->prefix . 'ghv_applications';

        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin'));
        add_shortcode('rwa_voter_portal', array($this, 'shortcode'));

        add_action('wp_ajax_ghv_search_voter', array($this, 'ajax_search_voter'));
        add_action('wp_ajax_nopriv_ghv_search_voter', array($this, 'ajax_search_voter'));
        add_action('wp_ajax_ghv_submit_application', array($this, 'ajax_submit_application'));
        add_action('wp_ajax_nopriv_ghv_submit_application', array($this, 'ajax_submit_application'));

        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_post_ghv_import_csv', array($this, 'handle_import_csv'));
        add_action('admin_post_ghv_update_application_status', array($this, 'handle_status_update'));
        add_action('admin_post_ghv_export_applications', array($this, 'handle_export_applications'));
        add_action('admin_post_ghv_export_voters', array($this, 'handle_export_voters'));
    }

    public function init() {
        $installed = get_option('ghv_rwa_version');
        if ($installed !== self::VERSION) {
            self::activate();
        }
    }

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $voters = $wpdb->prefix . 'ghv_voters';
        $apps = $wpdb->prefix . 'ghv_applications';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql1 = "CREATE TABLE $voters (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tower VARCHAR(50) NOT NULL DEFAULT '',
            flat VARCHAR(50) NOT NULL DEFAULT '',
            first_owner VARCHAR(191) NOT NULL DEFAULT '',
            second_owner VARCHAR(191) NOT NULL DEFAULT '',
            membership_no VARCHAR(100) NOT NULL DEFAULT '',
            eligible_voter VARCHAR(50) NOT NULL DEFAULT '',
            discrepancy TEXT NULL,
            voted_earlier VARCHAR(50) NOT NULL DEFAULT '',
            raw_data LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY tower (tower),
            KEY flat (flat),
            KEY first_owner (first_owner),
            KEY second_owner (second_owner)
        ) $charset_collate;";

        $sql2 = "CREATE TABLE $apps (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            application_no VARCHAR(50) NOT NULL DEFAULT '',
            application_type VARCHAR(50) NOT NULL DEFAULT 'membership',
            voter_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            requested_update TEXT NULL,
            full_name VARCHAR(191) NOT NULL DEFAULT '',
            father_husband VARCHAR(191) NOT NULL DEFAULT '',
            mobile VARCHAR(50) NOT NULL DEFAULT '',
            email VARCHAR(191) NOT NULL DEFAULT '',
            tower VARCHAR(50) NOT NULL DEFAULT '',
            flat VARCHAR(50) NOT NULL DEFAULT '',
            address TEXT NULL,
            aadhaar VARCHAR(50) NOT NULL DEFAULT '',
            ownership_type VARCHAR(50) NOT NULL DEFAULT '',
            transaction_id VARCHAR(100) NOT NULL DEFAULT '',
            aadhaar_front_url TEXT NULL,
            aadhaar_back_url TEXT NULL,
            payment_receipt_url TEXT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            admin_notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY application_no (application_no),
            KEY application_type (application_type),
            KEY voter_id (voter_id),
            KEY mobile (mobile),
            KEY flat (flat),
            KEY status (status)
        ) $charset_collate;";

        dbDelta($sql1);
        dbDelta($sql2);
        update_option('ghv_rwa_version', self::VERSION);
        self::import_bundled_csv_if_empty();
    }

    private static function import_bundled_csv_if_empty() {
        global $wpdb;
        $table = $wpdb->prefix . 'ghv_voters';
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count > 0) return;
        $file = plugin_dir_path(__FILE__) . 'data/portal_rows.csv';
        if (file_exists($file)) self::import_csv_file($file);
    }

    private static function import_csv_file($file) {
        global $wpdb;
        $table = $wpdb->prefix . 'ghv_voters';
        if (!file_exists($file) || !is_readable($file)) return 0;
        $handle = fopen($file, 'r');
        if (!$handle) return 0;
        $headers = fgetcsv($handle);
        if (!$headers) { fclose($handle); return 0; }
        $map = array_flip($headers);
        $inserted = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $json = isset($map['row_data']) && isset($row[$map['row_data']]) ? $row[$map['row_data']] : '';
            $data = json_decode($json, true);
            if (!is_array($data)) continue;
            $wpdb->insert($table, array(
                'tower' => sanitize_text_field(isset($data['Tower']) ? $data['Tower'] : ''),
                'flat' => sanitize_text_field(isset($data['Flat']) ? (string)$data['Flat'] : ''),
                'first_owner' => sanitize_text_field(isset($data['First Owner Name']) ? $data['First Owner Name'] : ''),
                'second_owner' => sanitize_text_field(isset($data['Second Owner Name']) ? $data['Second Owner Name'] : ''),
                'membership_no' => sanitize_text_field(isset($data['Membership Card & Share Certificate No']) ? (string)$data['Membership Card & Share Certificate No'] : ''),
                'eligible_voter' => sanitize_text_field(isset($data['Elegible Voter']) ? $data['Elegible Voter'] : (isset($data['Eligible Voter']) ? $data['Eligible Voter'] : '')),
                'discrepancy' => sanitize_textarea_field(isset($data['Discrepency']) ? $data['Discrepency'] : ''),
                'voted_earlier' => sanitize_text_field(isset($data['Voted Earlier']) ? $data['Voted Earlier'] : ''),
                'raw_data' => wp_json_encode($data),
            ));
            if ($wpdb->insert_id) $inserted++;
        }
        fclose($handle);
        return $inserted;
    }

    public function enqueue_frontend() {
        wp_register_style('ghv-rwa-portal', plugins_url('assets/frontend.css', __FILE__), array(), self::VERSION);
        wp_register_script('ghv-rwa-portal', plugins_url('assets/frontend.js', __FILE__), array('jquery'), self::VERSION, true);
        wp_localize_script('ghv-rwa-portal', 'GHVRWA', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE),
        ));
    }

    public function enqueue_admin($hook) {
        if (strpos($hook, 'ghv-rwa') === false) return;
        wp_enqueue_style('ghv-rwa-admin', plugins_url('assets/admin.css', __FILE__), array(), self::VERSION);
    }

    public function shortcode($atts) {
        wp_enqueue_style('ghv-rwa-portal');
        wp_enqueue_script('ghv-rwa-portal');
        ob_start(); ?>
        <div class="ghv-wrap">
            <div class="ghv-card ghv-hero">
                <h2>Global Hill View RWA Voter Search</h2>
                <p>Search your name in the voter list by owner name, tower, or flat number.</p>
                <form id="ghv-search-form" class="ghv-search-form">
                    <input type="text" id="ghv-search-query" name="query" placeholder="Enter name / flat / tower" required>
                    <button type="submit">Search</button>
                </form>
                <div id="ghv-search-result"></div>
            </div>
            <div id="ghv-application-card" class="ghv-card" style="display:none;">
                <h3 id="ghv-application-title">RWA Membership Application</h3>
                <p id="ghv-application-intro" class="ghv-muted">Your name was not found. Please submit details with membership fee payment proof.</p>
                <div id="ghv-bank-section" class="ghv-bank-box">
                    <strong>Membership Fee: ₹1100</strong><br>
                    GLOBAL HILL VIEW APARTMENT OWNERS WEL AS<br>
                    Current Account: <strong>50200061399909</strong><br>
                    HDFC Bank | IFSC: <strong>HDFC0003648</strong><br>
                    Branch: JMD Megapolis Sohna Road
                </div>
                <form id="ghv-application-form" enctype="multipart/form-data">
                    <input type="hidden" name="application_type" id="ghv-application-type" value="membership">
                    <input type="hidden" name="voter_id" id="ghv-voter-id" value="0">
                    <div class="ghv-grid">
                        <label>Full Name *<input name="full_name" id="ghv-full-name" required></label>
                        <label>Father's/Husband's Name<input name="father_husband"></label>
                        <label>Mobile *<input name="mobile" required></label>
                        <label>Email<input type="email" name="email"></label>
                        <label>Tower *<input name="tower" id="ghv-tower" required></label>
                        <label>Flat No. *<input name="flat" id="ghv-flat" required></label>
                        <label>Ownership Type<select name="ownership_type"><option>Owner</option><option>Tenant</option></select></label>
                        <label>Aadhaar Number *<input name="aadhaar" required maxlength="20"></label>
                    </div>
                    <label>Address<textarea name="address" rows="3"></textarea></label>
                    <label id="ghv-update-box" style="display:none;">What details need correction/update? *<textarea name="requested_update" id="ghv-requested-update" rows="4" placeholder="Example: Please correct second owner name / mobile / spelling / tower-flat details."></textarea></label>
                    <div class="ghv-grid">
                        <label>Aadhaar Front *<input type="file" name="aadhaar_front" accept="image/*,.pdf" required></label>
                        <label>Aadhaar Back *<input type="file" name="aadhaar_back" accept="image/*,.pdf" required></label>
                        <label class="ghv-payment-field">Transaction ID *<input name="transaction_id" id="ghv-transaction-id" required></label>
                        <label class="ghv-payment-field">Payment Receipt *<input type="file" name="payment_receipt" id="ghv-payment-receipt" accept="image/*,.pdf" required></label>
                    </div>
                    <label class="ghv-check"><input type="checkbox" name="declaration" value="1" required> I declare that the information submitted is true and request RWA membership/detail update as applicable.</label>
                    <button type="submit">Submit Application</button>
                    <div id="ghv-application-result"></div>
                </form>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    public function ajax_search_voter() {
        check_ajax_referer(self::NONCE, 'nonce');
        global $wpdb;
        $q = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        if (strlen($q) < 2) wp_send_json_error(array('message' => 'Please enter at least 2 characters.'));
        $like = '%' . $wpdb->esc_like($q) . '%';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->voters_table} WHERE first_owner LIKE %s OR second_owner LIKE %s OR flat LIKE %s OR tower LIKE %s OR membership_no LIKE %s ORDER BY tower, flat LIMIT 20", $like, $like, $like, $like, $like), ARRAY_A);
        if (!$rows) wp_send_json_success(array('found' => false, 'message' => 'No record found.'));
        wp_send_json_success(array('found' => true, 'rows' => $rows));
    }

    private function upload_file($field) {
        if (empty($_FILES[$field]['name'])) return '';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $allowed = array('jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'pdf' => 'application/pdf');
        $overrides = array('test_form' => false, 'mimes' => $allowed);
        $movefile = wp_handle_upload($_FILES[$field], $overrides);
        if ($movefile && empty($movefile['error'])) return esc_url_raw($movefile['url']);
        return '';
    }

    public function ajax_submit_application() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (empty($_POST['declaration'])) wp_send_json_error(array('message' => 'Declaration is required.'));
        global $wpdb;
        $application_type = sanitize_text_field(wp_unslash($_POST['application_type'] ?? 'membership'));
        if (!in_array($application_type, array('membership','update'), true)) $application_type = 'membership';
        $prefix = $application_type === 'update' ? 'GHV-UPD-' : 'GHV-MEM-';
        $app_no = $prefix . date('Y') . '-' . strtoupper(wp_generate_password(6, false, false));
        $data = array(
            'application_no' => $app_no,
            'application_type' => $application_type,
            'voter_id' => absint($_POST['voter_id'] ?? 0),
            'requested_update' => sanitize_textarea_field(wp_unslash($_POST['requested_update'] ?? '')),
            'full_name' => sanitize_text_field(wp_unslash($_POST['full_name'] ?? '')),
            'father_husband' => sanitize_text_field(wp_unslash($_POST['father_husband'] ?? '')),
            'mobile' => sanitize_text_field(wp_unslash($_POST['mobile'] ?? '')),
            'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')),
            'tower' => sanitize_text_field(wp_unslash($_POST['tower'] ?? '')),
            'flat' => sanitize_text_field(wp_unslash($_POST['flat'] ?? '')),
            'address' => sanitize_textarea_field(wp_unslash($_POST['address'] ?? '')),
            'aadhaar' => sanitize_text_field(wp_unslash($_POST['aadhaar'] ?? '')),
            'ownership_type' => sanitize_text_field(wp_unslash($_POST['ownership_type'] ?? '')),
            'transaction_id' => sanitize_text_field(wp_unslash($_POST['transaction_id'] ?? '')),
            'aadhaar_front_url' => $this->upload_file('aadhaar_front'),
            'aadhaar_back_url' => $this->upload_file('aadhaar_back'),
            'payment_receipt_url' => $this->upload_file('payment_receipt'),
            'status' => 'pending',
        );
        if (!$data['full_name'] || !$data['mobile'] || !$data['tower'] || !$data['flat'] || !$data['aadhaar']) {
            wp_send_json_error(array('message' => 'Please fill all required fields.'));
        }
        if ($application_type === 'membership' && !$data['transaction_id']) {
            wp_send_json_error(array('message' => 'Transaction ID is required for new membership.'));
        }
        if ($application_type === 'update' && !$data['requested_update']) {
            wp_send_json_error(array('message' => 'Please mention what details need correction/update.'));
        }
        $ok = $wpdb->insert($this->apps_table, $data);
        if (!$ok) wp_send_json_error(array('message' => 'Could not save application. Please contact admin.'));
        wp_mail(get_option('admin_email'), ($application_type === 'update' ? 'RWA Detail Update Request' : 'New RWA Membership Application'), 'Application Type: ' . ucfirst($application_type) . "\n" . 'Application No: ' . $app_no . "\nName: " . $data['full_name'] . "\nFlat: " . $data['tower'] . '-' . $data['flat']);
        wp_send_json_success(array('message' => 'Application submitted successfully. Your application number is ' . $app_no));
    }

    public function admin_menu() {
        add_menu_page('RWA Portal', 'RWA Portal', 'manage_options', 'ghv-rwa', array($this, 'admin_dashboard'), 'dashicons-groups', 26);
        add_submenu_page('ghv-rwa', 'Dashboard', 'Dashboard', 'manage_options', 'ghv-rwa', array($this, 'admin_dashboard'));
        add_submenu_page('ghv-rwa', 'Voter List', 'Voter List', 'manage_options', 'ghv-rwa-voters', array($this, 'admin_voters'));
        add_submenu_page('ghv-rwa', 'Applications', 'Applications', 'manage_options', 'ghv-rwa-applications', array($this, 'admin_applications'));
        add_submenu_page('ghv-rwa', 'Import CSV', 'Import CSV', 'manage_options', 'ghv-rwa-import', array($this, 'admin_import'));
    }

    private function admin_header($title) { echo '<div class="wrap ghv-admin"><h1>' . esc_html($title) . '</h1>'; }
    private function admin_footer() { echo '</div>'; }

    public function admin_dashboard() {
        global $wpdb;
        $voters = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->voters_table}");
        $apps = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->apps_table}");
        $pending = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->apps_table} WHERE status='pending'");
        $approved = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->apps_table} WHERE status='approved'");
        $this->admin_header('Global Hill View RWA Portal');
        echo '<div class="ghv-stats"><div><strong>'.$voters.'</strong><span>Voters</span></div><div><strong>'.$apps.'</strong><span>Applications</span></div><div><strong>'.$pending.'</strong><span>Pending</span></div><div><strong>'.$approved.'</strong><span>Approved</span></div></div>';
        echo '<p>Use shortcode <code>[rwa_voter_portal]</code> on any page.</p>';
        $this->admin_footer();
    }

    public function admin_voters() {
        global $wpdb;
        $s = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $where = '';
        $params = array();
        if ($s) { $like = '%' . $wpdb->esc_like($s) . '%'; $where = 'WHERE first_owner LIKE %s OR second_owner LIKE %s OR flat LIKE %s OR tower LIKE %s'; $params = array($like,$like,$like,$like); }
        $sql = "SELECT * FROM {$this->voters_table} $where ORDER BY id DESC LIMIT 200";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        $this->admin_header('Voter List');
        echo '<form method="get"><input type="hidden" name="page" value="ghv-rwa-voters"><input name="s" value="'.esc_attr($s).'" placeholder="Search voter"><button class="button">Search</button> <a class="button" href="'.esc_url(admin_url('admin-post.php?action=ghv_export_voters&_wpnonce='.wp_create_nonce('ghv_export_voters'))).'">Export CSV</a></form>';
        echo '<table class="widefat striped"><thead><tr><th>Tower</th><th>Flat</th><th>First Owner</th><th>Second Owner</th><th>Membership No</th><th>Eligible</th><th>Voted Earlier</th></tr></thead><tbody>';
        foreach ($rows as $r) echo '<tr><td>'.esc_html($r['tower']).'</td><td>'.esc_html($r['flat']).'</td><td>'.esc_html($r['first_owner']).'</td><td>'.esc_html($r['second_owner']).'</td><td>'.esc_html($r['membership_no']).'</td><td>'.esc_html($r['eligible_voter']).'</td><td>'.esc_html($r['voted_earlier']).'</td></tr>';
        echo '</tbody></table>';
        $this->admin_footer();
    }

    public function admin_applications() {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->apps_table} ORDER BY id DESC LIMIT 300", ARRAY_A);
        $this->admin_header('Membership Applications');
        echo '<p><a class="button button-primary" href="'.esc_url(admin_url('admin-post.php?action=ghv_export_applications&_wpnonce='.wp_create_nonce('ghv_export_applications'))).'">Export Applications CSV</a></p>';
        echo '<table class="widefat striped"><thead><tr><th>App No</th><th>Type</th><th>Name</th><th>Mobile</th><th>Tower/Flat</th><th>Txn / Update Requested</th><th>Files</th><th>Status</th><th>Action</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $action_url = admin_url('admin-post.php');
            echo '<tr><td>'.esc_html($r['application_no']).'</td><td>'.esc_html(ucfirst($r['application_type'] ?? 'membership')).'</td><td>'.esc_html($r['full_name']).'</td><td>'.esc_html($r['mobile']).'</td><td>'.esc_html($r['tower'].'-'.$r['flat']).'</td><td>'.esc_html($r['transaction_id']).'<br><small>'.esc_html($r['requested_update'] ?? '').'</small></td><td>';
            if ($r['aadhaar_front_url']) echo '<a target="_blank" href="'.esc_url($r['aadhaar_front_url']).'">Aadhaar Front</a><br>';
            if ($r['aadhaar_back_url']) echo '<a target="_blank" href="'.esc_url($r['aadhaar_back_url']).'">Aadhaar Back</a><br>';
            if ($r['payment_receipt_url']) echo '<a target="_blank" href="'.esc_url($r['payment_receipt_url']).'">Payment</a>';
            echo '</td><td>'.esc_html(ucfirst($r['status'])).'</td><td><form method="post" action="'.esc_url($action_url).'"><input type="hidden" name="action" value="ghv_update_application_status"><input type="hidden" name="id" value="'.esc_attr($r['id']).'">'.wp_nonce_field('ghv_status_'.$r['id'], '_wpnonce', true, false).'<select name="status"><option value="pending">Pending</option><option value="approved">Approve</option><option value="rejected">Reject</option></select> <button class="button">Update</button></form></td></tr>';
        }
        echo '</tbody></table>';
        $this->admin_footer();
    }

    public function admin_import() {
        $this->admin_header('Import Voter CSV');
        echo '<p>Upload CSV with a <code>row_data</code> JSON column, like your portal_rows.csv.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" enctype="multipart/form-data"><input type="hidden" name="action" value="ghv_import_csv">'.wp_nonce_field('ghv_import_csv','_wpnonce',true,false).'<p><label><input type="checkbox" name="clear_existing" value="1"> Clear existing voter list before import</label></p><p><input type="file" name="csv_file" accept=".csv" required></p><p><button class="button button-primary">Import CSV</button></p></form>';
        $this->admin_footer();
    }

    public function handle_import_csv() {
        if (!current_user_can('manage_options') || !check_admin_referer('ghv_import_csv')) wp_die('Not allowed');
        global $wpdb;
        if (!empty($_POST['clear_existing'])) $wpdb->query("TRUNCATE TABLE {$this->voters_table}");
        if (empty($_FILES['csv_file']['tmp_name'])) wp_safe_redirect(admin_url('admin.php?page=ghv-rwa-import&import=missing'));
        $inserted = self::import_csv_file($_FILES['csv_file']['tmp_name']);
        wp_safe_redirect(admin_url('admin.php?page=ghv-rwa-import&imported=' . intval($inserted)));
        exit;
    }

    public function handle_status_update() {
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id || !current_user_can('manage_options') || !check_admin_referer('ghv_status_'.$id)) wp_die('Not allowed');
        global $wpdb;
        $status = sanitize_text_field($_POST['status'] ?? 'pending');
        if (!in_array($status, array('pending','approved','rejected'), true)) $status = 'pending';
        $wpdb->update($this->apps_table, array('status'=>$status, 'updated_at'=>current_time('mysql')), array('id'=>$id));
        wp_safe_redirect(admin_url('admin.php?page=ghv-rwa-applications'));
        exit;
    }

    private function export_csv($filename, $rows) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        $out = fopen('php://output', 'w');
        if (!empty($rows)) { fputcsv($out, array_keys($rows[0])); foreach ($rows as $row) fputcsv($out, $row); }
        fclose($out); exit;
    }
    public function handle_export_applications() { if (!current_user_can('manage_options') || !check_admin_referer('ghv_export_applications')) wp_die('Not allowed'); global $wpdb; $this->export_csv('ghv-applications.csv', $wpdb->get_results("SELECT * FROM {$this->apps_table} ORDER BY id DESC", ARRAY_A)); }
    public function handle_export_voters() { if (!current_user_can('manage_options') || !check_admin_referer('ghv_export_voters')) wp_die('Not allowed'); global $wpdb; $this->export_csv('ghv-voters.csv', $wpdb->get_results("SELECT * FROM {$this->voters_table} ORDER BY id ASC", ARRAY_A)); }
}

register_activation_hook(__FILE__, array('GHV_RWA_Portal', 'activate'));
GHV_RWA_Portal::instance();
