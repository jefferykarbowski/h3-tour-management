<?php
/**
 * Tour Metadata Management Class
 * Manages tour metadata to decouple display names from URLs
 */
class H3TM_Tour_Metadata {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'h3tm_tour_metadata';
    }

    /**
     * Generate a unique tour ID
     * Format: 20250114_173045_8k3j9d2m (timestamp + 8-char random)
     *
     * @return string Unique tour ID
     */
    public function generate_tour_id() {
        $timestamp = date('Ymd_His');
        $random = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
        return $timestamp . '_' . $random;
    }

    /**
     * Create a new tour metadata entry
     *
     * @param array $data Tour metadata (tour_id, tour_slug, display_name, s3_folder, status, url_history)
     * @return int|false The inserted row ID or false on failure
     */
    public function create($data) {
        global $wpdb;

        $defaults = array(
            'tour_id' => null,
            'tour_slug' => '',
            'display_name' => '',
            's3_folder' => '',
            'status' => 'completed',
            'url_history' => json_encode(array()),
            'created_date' => current_time('mysql'),
            'updated_date' => current_time('mysql')
        );

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert(
            $this->table_name,
            $data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get tour metadata by tour_id
     *
     * @param string $tour_id Unique tour identifier
     * @return object|null
     */
    public function get_by_tour_id($tour_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE tour_id = %s",
            $tour_id
        ));
    }

    /**
     * Get tour metadata by slug
     *
     * @param string $tour_slug
     * @return object|null
     */
    public function get_by_slug($tour_slug) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE tour_slug = %s",
            $tour_slug
        ));
    }

    /**
     * Get tour metadata by display name
     *
     * @param string $display_name
     * @return object|null
     */
    public function get_by_display_name($display_name) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE display_name = %s",
            $display_name
        ));
    }

    /**
     * Resolve a tour record using the human display name, falling back to the current slug.
     *
     * @param string $display_name
     * @return object|null
     */
    public function resolve_by_display_name($display_name) {
        $tour = $this->get_by_display_name($display_name);

        if ($tour) {
            return $tour;
        }

        // Fall back to the sanitized slug representation (how URLs are generated)
        $candidate_slug = sanitize_title($display_name);

        if (!empty($candidate_slug)) {
            return $this->get_by_slug($candidate_slug);
        }

        return null;
    }

    /**
     * Get tour metadata by S3 folder
     *
     * @param string $s3_folder
     * @return object|null
     */
    public function get_by_s3_folder($s3_folder) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE s3_folder = %s",
            $s3_folder
        ));
    }

    /**
     * Find tour by checking url_history
     *
     * @param string $old_slug
     * @return object|null
     */
    public function find_by_old_slug($old_slug) {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE url_history IS NOT NULL"
        );

        foreach ($results as $tour) {
            $url_history = json_decode($tour->url_history, true);
            if (is_array($url_history) && in_array($old_slug, $url_history)) {
                return $tour;
            }
        }

        return null;
    }

    /**
     * Update tour metadata
     *
     * @param int $id Tour ID
     * @param array $data Data to update
     * @return bool
     */
    public function update($id, $data) {
        global $wpdb;

        $data['updated_date'] = current_time('mysql');

        return $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $id),
            null,
            array('%d')
        ) !== false;
    }

    /**
     * Update tour by slug
     *
     * @param string $tour_slug
     * @param array $data
     * @return bool
     */
    public function update_by_slug($tour_slug, $data) {
        global $wpdb;

        $data['updated_date'] = current_time('mysql');

        return $wpdb->update(
            $this->table_name,
            $data,
            array('tour_slug' => $tour_slug),
            null,
            array('%s')
        ) !== false;
    }

    /**
     * Rename tour - updates display name and adds old slug to url_history
     *
     * @param string $tour_slug Current tour slug
     * @param string $new_display_name New display name
     * @return bool
     */
    public function rename_tour($tour_slug, $new_display_name) {
        $tour = $this->get_by_slug($tour_slug);

        if (!$tour) {
            return false;
        }

        // Update display name only (URL slug and S3 folder stay the same)
        return $this->update_by_slug($tour_slug, array(
            'display_name' => $new_display_name
        ));
    }

    /**
     * Change tour URL slug - updates slug and adds old slug to url_history
     *
     * @param string $old_slug Current slug
     * @param string $new_slug New slug
     * @return bool
     */
    public function change_slug($old_slug, $new_slug) {
        $tour = $this->get_by_slug($old_slug);

        if (!$tour) {
            return false;
        }

        // Add old slug to url_history
        $url_history = json_decode($tour->url_history, true);
        if (!is_array($url_history)) {
            $url_history = array();
        }

        // Add old slug if not already in history
        if (!in_array($old_slug, $url_history)) {
            $url_history[] = $old_slug;
        }

        // Keep only last 10 slugs to prevent unlimited growth
        if (count($url_history) > 10) {
            $url_history = array_slice($url_history, -10);
        }

        global $wpdb;

        return $wpdb->update(
            $this->table_name,
            array(
                'tour_slug' => $new_slug,
                'url_history' => json_encode($url_history),
                'updated_date' => current_time('mysql')
            ),
            array('tour_slug' => $old_slug),
            array('%s', '%s', '%s'),
            array('%s')
        ) !== false;
    }

    /**
     * Delete tour metadata
     *
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        global $wpdb;

        return $wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        ) !== false;
    }

    /**
     * Delete by tour slug
     *
     * @param string $tour_slug
     * @return bool
     */
    public function delete_by_slug($tour_slug) {
        global $wpdb;

        return $wpdb->delete(
            $this->table_name,
            array('tour_slug' => $tour_slug),
            array('%s')
        ) !== false;
    }

    /**
     * Get all tours
     *
     * @return array
     */
    public function get_all() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY display_name ASC"
        );
    }

    /**
     * Check if tour slug exists
     *
     * @param string $tour_slug
     * @param int $exclude_id Optional ID to exclude from check
     * @return bool
     */
    public function slug_exists($tour_slug, $exclude_id = null) {
        global $wpdb;

        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE tour_slug = %s";
        $params = array($tour_slug);

        if ($exclude_id) {
            $query .= " AND id != %d";
            $params[] = $exclude_id;
        }

        $count = $wpdb->get_var($wpdb->prepare($query, $params));

        return $count > 0;
    }

    /**
     * Check if display name exists
     *
     * @param string $display_name
     * @param int $exclude_id Optional ID to exclude from check
     * @return bool
     */
    public function display_name_exists($display_name, $exclude_id = null) {
        global $wpdb;

        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE display_name = %s";
        $params = array($display_name);

        if ($exclude_id) {
            $query .= " AND id != %d";
            $params[] = $exclude_id;
        }

        $count = $wpdb->get_var($wpdb->prepare($query, $params));

        return $count > 0;
    }

    /**
     * Get or create tour metadata entry
     * For backward compatibility with existing tours
     *
     * @param string $tour_name Tour name from S3
     * @return object|null
     */
    public function get_or_create($tour_name) {
        // Try to find existing metadata
        $tour = $this->get_by_display_name($tour_name);

        if ($tour) {
            return $tour;
        }

        // Create new metadata entry
        $tour_slug = sanitize_title($tour_name);
        $s3_folder = 'tours/' . str_replace(' ', '-', $tour_name);

        $id = $this->create(array(
            'tour_slug' => $tour_slug,
            'display_name' => $tour_name,
            's3_folder' => $s3_folder,
            'url_history' => json_encode(array())
        ));

        if ($id) {
            return $this->get_by_slug($tour_slug);
        }

        return null;
    }
}
