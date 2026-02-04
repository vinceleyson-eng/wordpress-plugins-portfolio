<?php
/**
 * Database Handler Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class HAM_Database {
    
    private $wpdb;
    private $charset_collate;
    
    // Table names
    public $memberships_table;
    public $transactions_table;
    public $invoices_table;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        
        // Set table names
        $this->memberships_table = $wpdb->prefix . 'ham_memberships';
        $this->transactions_table = $wpdb->prefix . 'ham_transactions';
        $this->invoices_table = $wpdb->prefix . 'ham_invoices';
    }
    
    /**
     * Create all plugin tables
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create memberships table
        $sql_memberships = "CREATE TABLE IF NOT EXISTS {$this->memberships_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            store_id bigint(20) NOT NULL,
            membership_type varchar(50) NOT NULL DEFAULT 'unverified',
            billing_cycle varchar(20) DEFAULT NULL,
            price decimal(10,2) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            start_date datetime NOT NULL,
            end_date datetime DEFAULT NULL,
            auto_renew tinyint(1) DEFAULT 1,
            stripe_customer_id varchar(100) DEFAULT NULL,
            stripe_subscription_id varchar(100) DEFAULT NULL,
            last_payment_date datetime DEFAULT NULL,
            next_billing_date datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY store_id (store_id),
            KEY membership_type (membership_type),
            KEY status (status)
        ) {$this->charset_collate};";
        
        // Create transactions table
        $sql_transactions = "CREATE TABLE IF NOT EXISTS {$this->transactions_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            membership_id bigint(20) DEFAULT NULL,
            user_id bigint(20) NOT NULL,
            store_id bigint(20) DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) DEFAULT 'USD',
            transaction_type varchar(50) NOT NULL,
            payment_method varchar(50) DEFAULT NULL,
            stripe_charge_id varchar(100) DEFAULT NULL,
            stripe_invoice_id varchar(100) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY membership_id (membership_id),
            KEY user_id (user_id),
            KEY status (status)
        ) {$this->charset_collate};";
        
        // Create invoices table
        $sql_invoices = "CREATE TABLE IF NOT EXISTS {$this->invoices_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            membership_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            invoice_number varchar(50) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) DEFAULT 'USD',
            status varchar(20) NOT NULL DEFAULT 'unpaid',
            due_date datetime NOT NULL,
            paid_date datetime DEFAULT NULL,
            invoice_url varchar(255) DEFAULT NULL,
            stripe_invoice_id varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY invoice_number (invoice_number),
            KEY membership_id (membership_id),
            KEY user_id (user_id)
        ) {$this->charset_collate};";
        
        // Execute table creation
        dbDelta($sql_memberships);
        dbDelta($sql_transactions);
        dbDelta($sql_invoices);
        
        // Update version
        update_option('ham_db_version', HAM_VERSION);
        
        return true;
    }
    
    /**
     * Get active membership for user
     */
    public function get_active_membership($user_id) {
        $sql = $this->wpdb->prepare(
            "SELECT m.*, p.post_title as store_name
            FROM {$this->memberships_table} m
            LEFT JOIN {$this->wpdb->posts} p ON m.store_id = p.ID
            WHERE m.user_id = %d AND m.status = 'active'
            ORDER BY m.created_at DESC
            LIMIT 1",
            $user_id
        );
        
        return $this->wpdb->get_row($sql);
    }
    
    /**
     * Create membership
     */
    public function create_membership($data) {
        return $this->wpdb->insert(
            $this->memberships_table,
            $data,
            array(
                '%d', '%d', '%s', '%s', '%f', '%s',
                '%s', '%s', '%d', '%s', '%s', '%s', '%s'
            )
        );
    }
    
    /**
     * Update membership
     */
    public function update_membership($id, $data) {
        return $this->wpdb->update(
            $this->memberships_table,
            $data,
            array('id' => $id),
            array('%s', '%s', '%f', '%s', '%s', '%s'), // Adjust based on fields
            array('%d')
        );
    }
    
    /**
     * Get membership by ID
     */
    public function get_membership($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->memberships_table} WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Create transaction
     */
    public function create_transaction($data) {
        return $this->wpdb->insert(
            $this->transactions_table,
            $data,
            array('%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get user transactions
     */
    public function get_user_transactions($user_id, $limit = 10) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->transactions_table}
            WHERE user_id = %d
            ORDER BY created_at DESC
            LIMIT %d",
            $user_id,
            $limit
        );
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Get all memberships (for admin)
     */
    public function get_all_memberships($status = 'active', $limit = 100, $offset = 0) {
        $sql = "SELECT m.*, u.user_email, u.display_name, p.post_title as store_name
                FROM {$this->memberships_table} m
                LEFT JOIN {$this->wpdb->users} u ON m.user_id = u.ID
                LEFT JOIN {$this->wpdb->posts} p ON m.store_id = p.ID";
        
        if ($status !== 'all') {
            $sql .= $this->wpdb->prepare(" WHERE m.status = %s", $status);
        }
        
        $sql .= " ORDER BY m.created_at DESC LIMIT %d OFFSET %d";
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $limit, $offset));
    }
    
    /**
     * Get membership statistics
     */
    public function get_statistics() {
        $stats = array();
        
        // Total active memberships by type
        $stats['memberships'] = $this->wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN membership_type = 'preferred' THEN 1 ELSE 0 END) as preferred,
                SUM(CASE WHEN membership_type = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN membership_type = 'unverified' THEN 1 ELSE 0 END) as unverified
            FROM {$this->memberships_table}
            WHERE status = 'active'
        ", ARRAY_A);
        
        // Monthly recurring revenue
        $stats['mrr'] = $this->wpdb->get_var("
            SELECT SUM(price)
            FROM {$this->memberships_table}
            WHERE status = 'active' AND billing_cycle = 'monthly'
        ");
        
        // Annual recurring revenue (divided by 12 for MRR equivalent)
        $arr = $this->wpdb->get_var("
            SELECT SUM(price)
            FROM {$this->memberships_table}
            WHERE status = 'active' AND billing_cycle = 'yearly'
        ");
        
        $stats['arr'] = $arr;
        $stats['total_mrr'] = $stats['mrr'] + ($arr / 12);
        
        // Total revenue (last 30 days)
        $stats['revenue_30d'] = $this->wpdb->get_var("
            SELECT SUM(amount)
            FROM {$this->transactions_table}
            WHERE status = 'completed'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        return $stats;
    }
    
    /**
     * Search memberships
     */
    public function search_memberships($search_term) {
        $search = '%' . $this->wpdb->esc_like($search_term) . '%';
        
        $sql = $this->wpdb->prepare("
            SELECT m.*, u.user_email, u.display_name, p.post_title as store_name
            FROM {$this->memberships_table} m
            LEFT JOIN {$this->wpdb->users} u ON m.user_id = u.ID
            LEFT JOIN {$this->wpdb->posts} p ON m.store_id = p.ID
            WHERE u.user_email LIKE %s
            OR u.display_name LIKE %s
            OR p.post_title LIKE %s
            ORDER BY m.created_at DESC
            LIMIT 50
        ", $search, $search, $search);
        
        return $this->wpdb->get_results($sql);
    }
}
