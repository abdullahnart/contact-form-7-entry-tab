function create_cf7_custom_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cf7_submissions';
    
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        form_title VARCHAR(255) NOT NULL,
        submitted_data LONGTEXT NOT NULL,
        submit_time DATETIME NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
add_action('after_setup_theme', 'create_cf7_custom_table');





add_action('wpcf7_before_send_mail', 'save_cf7_submission_to_db');

function save_cf7_submission_to_db($contact_form) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cf7_submissions';

    // Get form title
    $form_title = $contact_form->title();

    // Get submitted data
    $submission = WPCF7_Submission::get_instance();
    if ($submission) {
        $data = $submission->get_posted_data();
        
        // Remove unwanted CF7 fields like _wpcf7 or _wpcf7_version
        unset($data['_wpcf7']);
        unset($data['_wpcf7_version']);
        unset($data['_wpcf7_locale']);
        unset($data['_wpcf7_unit_tag']);
        unset($data['_wpcf7_container_post']);

        $wpdb->insert(
            $table_name,
            [
                'form_title' => $form_title,
                'submitted_data' => maybe_serialize($data), // store array safely
                'submit_time' => current_time('mysql')
            ]
        );
    }
}



// Shortcode to display CF7 submissions
function cf7_submissions_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cf7_submissions';

    // Optional: filter by form title
    $atts = shortcode_atts( [
        'form' => '', // CF7 form title
        'limit' => 20, // number of submissions to show
    ], $atts );

    $query = "SELECT * FROM $table_name";
    $where = [];

    if (!empty($atts['form'])) {
        $where[] = $wpdb->prepare("form_title = %s", $atts['form']);
    }

    if ($where) {
        $query .= " WHERE " . implode(' AND ', $where);
    }

    $query .= " ORDER BY submit_time DESC LIMIT %d";
    $query = $wpdb->prepare($query, $atts['limit']);

    $results = $wpdb->get_results($query);

    if (!$results) {
        return "<p>No submissions found.</p>";
    }

    // Build output HTML
    $output = '<div class="cf7-submissions">';
    foreach ($results as $row) {
        $data = maybe_unserialize($row->submitted_data);
        $output .= '<div class="cf7-entry" style="margin-bottom:20px; padding:10px; border:1px solid #ddd;">';
        $output .= '<strong>Form:</strong> ' . esc_html($row->form_title) . '<br>';
        $output .= '<strong>Time:</strong> ' . esc_html($row->submit_time) . '<br>';
        $output .= '<strong>Data:</strong><br>';
        $output .= '<ul>';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $output .= '<li><strong>' . esc_html($key) . ':</strong> ' . esc_html($value) . '</li>';
        }
        $output .= '</ul>';
        $output .= '</div>';
    }
    $output .= '</div>';

    return $output;
}
add_shortcode('cf7_submissions', 'cf7_submissions_shortcode');
