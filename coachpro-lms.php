<?php
/**
 * CoachPro LMS — Non-AI core. Admin/Frontend scaffolding, DB schema, roles/caps, AJAX/REST hooks, templates, enqueue.
 * Version: 1.0.0
 * Author: CoachPro Team
 *
 * Plugin Name: CoachPro LMS
 * Description: Non-AI Coaching LMS: Programs, Sessions (notes/chat), Progress, Assessments, Reports. Ready for future AI switch without API keys.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 8.0
 * Text Domain: coachpro-lms
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('CoachPro_LMS')) {
final class CoachPro_LMS {

    const VERSION = '1.0.0';
    const TD = 'coachpro-lms';
    private static ?CoachPro_LMS $instance = null;

    /** Singleton */
    public static function instance(): CoachPro_LMS {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // Hooks
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'register_post_types']);
        add_action('admin_menu', [$this, 'admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_front_assets']);
        add_action('rest_api_init', [$this, 'register_rest_endpoints']);

        // AJAX
        add_action('wp_ajax_coachpro_enroll_program', [$this, 'ajax_enroll_program']);
        add_action('wp_ajax_nopriv_coachpro_enroll_program', [$this, 'ajax_forbidden']);
        add_action('wp_ajax_coachpro_start_session', [$this, 'ajax_start_session']);
        add_action('wp_ajax_nopriv_coachpro_start_session', [$this, 'ajax_forbidden']);
        add_action('wp_ajax_coachpro_send_message', [$this, 'ajax_send_message']);
        add_action('wp_ajax_nopriv_coachpro_send_message', [$this, 'ajax_forbidden']);
        add_action('wp_ajax_coachpro_get_progress', [$this, 'ajax_get_progress']);
        add_action('wp_ajax_nopriv_coachpro_get_progress', [$this, 'ajax_forbidden']);

        // Shortcodes (frontend)
        add_shortcode('coachpro_programs', [$this, 'sc_programs']);
        add_shortcode('coachpro_chat', [$this, 'sc_chat']);
        add_shortcode('coachpro_dashboard', [$this, 'sc_dashboard']);
        add_shortcode('coachpro_progress', [$this, 'sc_progress']);
        add_shortcode('coachpro_coaches', [$this, 'sc_coaches']);
    }

    /** i18n */
    public function load_textdomain() {
        load_plugin_textdomain(self::TD, false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /** Post Types: Program/Module/Lesson/Coach as CPTs for SEO & templates */
    public function register_post_types() {
        register_post_type('cpl_program', [
            'label' => __('Programs', self::TD),
            'public' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'coaching-programs'],
            'show_in_rest' => true,
        ]);
        register_post_type('cpl_module', [
            'label' => __('Modules', self::TD),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor'],
            'show_in_rest' => true,
        ]);
        register_post_type('cpl_lesson', [
            'label' => __('Lessons', self::TD),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor'],
            'show_in_rest' => true,
        ]);
        register_post_type('cpl_coach', [
            'label' => __('Coaches', self::TD),
            'public' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'coaches'],
            'show_in_rest' => true,
        ]);
        // Taxonomy for Program categories
        register_taxonomy('cpl_program_cat', ['cpl_program'], [
            'label' => __('Program Categories', self::TD),
            'public' => true,
            'hierarchical' => true,
            'show_in_rest' => true
        ]);
    }

    /** Centralized table names */
    public static function table_names(): array {
        global $wpdb;
        $p = $wpdb->prefix;
        return [
            'profiles'      => "{$p}coachproai_profiles",
            'sessions'      => "{$p}coachproai_ai_sessions",
            'progress'      => "{$p}coachproai_learning_progress",
            'recs'          => "{$p}coachproai_recommendations",
            'analytics'     => "{$p}coachproai_analytics",
            'assessments'   => "{$p}coachproai_assessments",
            'responses'     => "{$p}coachproai_assessments_responses",
            'enrollments'   => "{$p}coachproai_enrollments",
        ];
    }

    /** Admin menus */
    public function admin_menus() {
        $cap_manage = 'manage_coachpro';
        add_menu_page(
            __('CoachPro LMS', self::TD),
            __('CoachPro LMS', self::TD),
            $cap_manage,
            'coachpro-lms',
            [$this, 'render_admin_app'],
            'dashicons-welcome-learn-more',
            26
        );
        add_submenu_page('coachpro-lms', __('Dashboard', self::TD), __('Dashboard', self::TD), $cap_manage, 'coachpro-lms', [$this, 'render_admin_app']);
        add_submenu_page('coachpro-lms', __('Programs', self::TD), __('Programs', self::TD), 'edit_coachpro', 'coachpro-programs', [$this, 'render_admin_app']);
        add_submenu_page('coachpro-lms', __('Students', self::TD), __('Students', self::TD), 'edit_coachpro', 'coachpro-students', [$this, 'render_admin_app']);
        add_submenu_page('coachpro-lms', __('Sessions', self::TD), __('Sessions', self::TD), 'edit_coachpro', 'coachpro-sessions', [$this, 'render_admin_app']);
        add_submenu_page('coachpro-lms', __('Assessments', self::TD), __('Assessments', self::TD), 'edit_coachpro', 'coachpro-assessments', [$this, 'render_admin_app']);
        add_submenu_page('coachpro-lms', __('Reports', self::TD), __('Reports', self::TD), 'view_coachpro', 'coachpro-reports', [$this, 'render_admin_app']);
        add_submenu_page('coachpro-lms', __('Settings', self::TD), __('Settings', self::TD), $cap_manage, 'coachpro-settings', [$this, 'render_admin_app']);
    }

    /** Admin screen container + Template blocks */
    public function render_admin_app() {
        if (!current_user_can('view_coachpro') && !current_user_can('edit_coachpro') && !current_user_can('manage_coachpro')) {
            wp_die(__('You do not have permission to access CoachPro LMS.', self::TD));
        }
        $screen = isset($_GET['page']) ? sanitize_key($_GET['page']) : 'coachpro-lms';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(__('CoachPro LMS', self::TD)); ?></h1>
            <div id="ssm-admin-screen" class="ssm-root" data-screen="<?php echo esc_attr($screen); ?>"></div>

            <?php $this->print_admin_templates(); ?>
        </div>
        <?php
    }

    /** Print all admin <template> blocks once */
    private function print_admin_templates() {
        ?>
        <!-- Dashboard Template -->
        <template id="ssm-tpl-dashboard">
            <section class="ssm ssm-dashboard">
                <header class="ssm-header">
                    <h2><?php echo esc_html(__('Dashboard', self::TD)); ?></h2>
                    <div class="ssm-actions">
                        <button class="button button-primary" data-action="new-program"><?php echo esc_html(__('Add Program', self::TD)); ?></button>
                        <button class="button" data-action="export-csv"><?php echo esc_html(__('Export CSV', self::TD)); ?></button>
                    </div>
                </header>
                <div class="ssm-kpis">
                    <div class="ssm-kpi"><strong data-kpi="total_programs">0</strong><span><?php _e('Programs', self::TD); ?></span></div>
                    <div class="ssm-kpi"><strong data-kpi="active_students">0</strong><span><?php _e('Active Students', self::TD); ?></span></div>
                    <div class="ssm-kpi"><strong data-kpi="open_sessions">0</strong><span><?php _e('Open Sessions', self::TD); ?></span></div>
                    <div class="ssm-kpi"><strong data-kpi="avg_score">0%</strong><span><?php _e('Avg Score', self::TD); ?></span></div>
                </div>
                <div class="ssm-table-wrap">
                    <table class="widefat">
                        <thead><tr>
                            <th><?php _e('Student', self::TD); ?></th>
                            <th><?php _e('Program', self::TD); ?></th>
                            <th><?php _e('Status', self::TD); ?></th>
                            <th><?php _e('Updated', self::TD); ?></th>
                        </tr></thead>
                        <tbody data-list="recent_enrollments"></tbody>
                    </table>
                </div>
            </section>
        </template>

        <!-- Programs Template -->
        <template id="ssm-tpl-programs">
            <section class="ssm ssm-programs">
                <header class="ssm-header">
                    <h2><?php _e('Programs', self::TD); ?></h2>
                    <div class="ssm-actions">
                        <input type="search" placeholder="<?php esc_attr_e('Search…', self::TD); ?>" data-ref="search">
                        <button class="button button-primary" data-action="add-program"><?php _e('Add Program', self::TD); ?></button>
                    </div>
                </header>
                <div class="ssm-table-wrap">
                    <table class="widefat">
                        <thead><tr>
                            <th><?php _e('Title', self::TD); ?></th>
                            <th><?php _e('Category', self::TD); ?></th>
                            <th><?php _e('Price', self::TD); ?></th>
                            <th><?php _e('Enrollments', self::TD); ?></th>
                            <th><?php _e('Actions', self::TD); ?></th>
                        </tr></thead>
                        <tbody data-list="programs"></tbody>
                    </table>
                    <div class="tablenav"><div class="tablenav-pages" data-ref="pagination"></div></div>
                </div>
            </section>
        </template>

        <!-- Students Template -->
        <template id="ssm-tpl-students">
            <section class="ssm ssm-students">
                <header class="ssm-header">
                    <h2><?php _e('Students', self::TD); ?></h2>
                    <div class="ssm-actions">
                        <input type="search" placeholder="<?php esc_attr_e('Search by name/email…', self::TD); ?>" data-ref="search">
                    </div>
                </header>
                <div class="ssm-table-wrap">
                    <table class="widefat">
                        <thead><tr>
                            <th><?php _e('Name', self::TD); ?></th>
                            <th><?php _e('Email', self::TD); ?></th>
                            <th><?php _e('Enrolled', self::TD); ?></th>
                            <th><?php _e('Avg Score', self::TD); ?></th>
                            <th><?php _e('Actions', self::TD); ?></th>
                        </tr></thead>
                        <tbody data-list="students"></tbody>
                    </table>
                    <div class="tablenav"><div class="tablenav-pages" data-ref="pagination"></div></div>
                </div>
            </section>
        </template>

        <!-- Sessions Template -->
        <template id="ssm-tpl-sessions">
            <section class="ssm ssm-sessions">
                <header class="ssm-header">
                    <h2><?php _e('Sessions (Notes/Chat)', self::TD); ?></h2>
                    <div class="ssm-actions">
                        <select data-ref="student"></select>
                        <select data-ref="program"></select>
                        <button class="button button-primary" data-action="start-session"><?php _e('Start Session', self::TD); ?></button>
                    </div>
                </header>
                <div class="ssm-session">
                    <div class="ssm-messages" data-list="messages"></div>
                    <form data-ref="composer">
                        <label>
                            <span class="screen-reader-text"><?php _e('Message', self::TD); ?></span>
                            <textarea rows="3" data-ref="message"></textarea>
                        </label>
                        <div class="ssm-row">
                            <input type="file" data-ref="file" />
                            <button class="button button-primary" data-action="send"><?php _e('Send', self::TD); ?></button>
                        </div>
                    </form>
                </div>
            </section>
        </template>

        <!-- Assessments Template -->
        <template id="ssm-tpl-assessments">
            <section class="ssm ssm-assessments">
                <header class="ssm-header">
                    <h2><?php _e('Assessments', self::TD); ?></h2>
                    <div class="ssm-actions">
                        <button class="button button-primary" data-action="new-assessment"><?php _e('New Assessment', self::TD); ?></button>
                    </div>
                </header>
                <div class="ssm-table-wrap">
                    <table class="widefat">
                        <thead><tr>
                            <th><?php _e('Title', self::TD); ?></th>
                            <th><?php _e('Questions', self::TD); ?></th>
                            <th><?php _e('Submissions', self::TD); ?></th>
                            <th><?php _e('Actions', self::TD); ?></th>
                        </tr></thead>
                        <tbody data-list="assessments"></tbody>
                    </table>
                </div>
            </section>
        </template>

        <!-- Reports Template -->
        <template id="ssm-tpl-reports">
            <section class="ssm ssm-reports">
                <header class="ssm-header">
                    <h2><?php _e('Reports', self::TD); ?></h2>
                    <div class="ssm-actions">
                        <input type="date" data-ref="from">
                        <input type="date" data-ref="to">
                        <button class="button" data-action="run"><?php _e('Run', self::TD); ?></button>
                        <button class="button" data-action="export"><?php _e('Export CSV', self::TD); ?></button>
                    </div>
                </header>
                <div class="ssm-table-wrap">
                    <table class="widefat">
                        <thead><tr>
                            <th><?php _e('Program', self::TD); ?></th>
                            <th><?php _e('Enrollments', self::TD); ?></th>
                            <th><?php _e('Completion %', self::TD); ?></th>
                            <th><?php _e('Avg Score', self::TD); ?></th>
                        </tr></thead>
                        <tbody data-list="reports"></tbody>
                    </table>
                </div>
            </section>
        </template>

        <!-- Settings Template -->
        <template id="ssm-tpl-settings">
            <section class="ssm ssm-settings">
                <header class="ssm-header">
                    <h2><?php _e('Settings', self::TD); ?></h2>
                </header>
                <form data-ref="settings-form">
                    <fieldset>
                        <legend><?php _e('General', self::TD); ?></legend>
                        <label><?php _e('Currency', self::TD); ?>
                            <input type="text" data-ref="currency" value="<?php echo esc_attr(get_option('cpl_currency', 'USD')); ?>">
                        </label>
                        <label><?php _e('Default Program Page', self::TD); ?>
                            <input type="text" data-ref="program_page" value="<?php echo esc_attr(get_option('cpl_program_page', '')); ?>">
                        </label>
                    </fieldset>
                    <fieldset>
                        <legend><?php _e('WooCommerce', self::TD); ?></legend>
                        <label><input type="checkbox" data-ref="woo_enable" <?php checked((bool)get_option('cpl_woo_enable', false)); ?>> <?php _e('Enable WooCommerce Integration', self::TD); ?></label>
                    </fieldset>
                    <fieldset>
                        <legend><?php _e('Rule-based Recommendations', self::TD); ?></legend>
                        <textarea rows="6" data-ref="rules_json" placeholder='[{"when":{"avg_score":{"lt":60}},"then":{"recommend":"Lesson A"}}]'><?php echo esc_textarea(get_option('cpl_rules_json', '[]')); ?></textarea>
                    </fieldset>
                    <div>
                        <button class="button button-primary" data-action="save-settings"><?php _e('Save Settings', self::TD); ?></button>
                    </div>
                </form>
            </section>
        </template>
        <?php
    }

    /** Enqueue Admin */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'coachpro') === false) return;
        $this->enqueue_shared_assets(true);
    }

    /** Enqueue Frontend */
    public function enqueue_front_assets() {
        // Load on our shortcodes or CPTs
        if (is_singular(['cpl_program']) || has_shortcode(get_post_field('post_content', get_the_ID() ?: 0), 'coachpro_')) {
            $this->enqueue_shared_assets(false);
        }
    }

    /** Shared enqueue + localize */
    private function enqueue_shared_assets(bool $is_admin) {
        $ver = self::VERSION;
        $base = plugin_dir_url(__FILE__);
        wp_enqueue_style('coachpro-lms', $base . 'assets/css/coachpro-admin.css', [], $ver);
        wp_enqueue_script('coachpro-lms', $base . 'assets/js/coachpro-admin.js', ['jquery'], $ver, true);

        $caps = [
            'manage' => current_user_can('manage_coachpro'),
            'edit'   => current_user_can('edit_coachpro'),
            'view'   => current_user_can('view_coachpro'),
        ];
        wp_localize_script('coachpro-lms', 'ssmData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('cpl_ajax'),
            'caps'     => $caps,
            'is_admin' => $is_admin,
            'strings'  => [
                'error' => __('An error occurred. Please try again.', self::TD),
                'saved' => __('Saved successfully.', self::TD),
            ],
        ]);
    }

    /** REST Endpoints registered later in Part 4 */
    public function register_rest_endpoints() {
        // Placeholder method to be filled in Part 4 (already loaded below). Intentionally left callable.
    }

    /** AJAX guard */
    public function ajax_forbidden() {
        wp_send_json_error(['message' => __('Authentication required.', self::TD)], 401);
    }

    /** Utility: get user id safely */
    private static function uid(): int {
        return get_current_user_id() ?: 0;
    }

    /** ACTIVATION: DB + Roles + Options */
    public static function activate() {
        // Roles/Caps
        self::install_caps();

        // DB
        self::install_db();

        // Options
        add_option('coachpro_lms_version', self::VERSION);
        add_option('cpl_currency', 'USD');
        add_option('cpl_woo_enable', false);
        add_option('cpl_rules_json', '[]');
    }

    /** ROLES/CAPS */
    private static function install_caps() {
        $roles = [
            'administrator' => ['manage_coachpro', 'edit_coachpro', 'view_coachpro'],
            'editor'        => ['edit_coachpro', 'view_coachpro'],
            'author'        => ['view_coachpro'],
            'coachpro_student' => [],
            'coachpro_coach'   => ['edit_coachpro', 'view_coachpro'],
            'coachpro_admin'   => ['manage_coachpro', 'edit_coachpro', 'view_coachpro'],
        ];

        // Ensure custom roles exist
        if (!get_role('coachpro_student')) add_role('coachpro_student', __('CoachPro Student', self::TD), []);
        if (!get_role('coachpro_coach')) add_role('coachpro_coach', __('CoachPro Coach', self::TD), []);
        if (!get_role('coachpro_admin')) add_role('coachpro_admin', __('CoachPro Admin', self::TD), []);

        foreach ($roles as $role_key => $caps) {
            $role = get_role($role_key);
            if (!$role) continue;
            foreach ($caps as $cap) {
                $role->add_cap($cap);
            }
        }
    }

    /** DB: create tables using dbDelta */
    private static function install_db() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $t = self::table_names();

        $sql = [];

        // Profiles
        $sql[] = "CREATE TABLE {$t['profiles']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            preferences TEXT NULL,
            goals TEXT NULL,
            tags TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Sessions (non-AI notes/chat)
        $sql[] = "CREATE TABLE {$t['sessions']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            coach_id BIGINT UNSIGNED NOT NULL,
            program_id BIGINT UNSIGNED NOT NULL,
            message LONGTEXT NULL,
            attachment_url TEXT NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY student_id (student_id),
            KEY program_id (program_id)
        ) $charset_collate;";

        // Progress
        $sql[] = "CREATE TABLE {$t['progress']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            program_id BIGINT UNSIGNED NOT NULL,
            lessons_total INT UNSIGNED DEFAULT 0,
            lessons_done INT UNSIGNED DEFAULT 0,
            avg_score DECIMAL(5,2) DEFAULT 0,
            last_active DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY student_program (student_id, program_id)
        ) $charset_collate;";

        // Recommendations (rule-based)
        $sql[] = "CREATE TABLE {$t['recs']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            program_id BIGINT UNSIGNED NOT NULL,
            rule_json LONGTEXT NOT NULL,
            output_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY student_program (student_id, program_id)
        ) $charset_collate;";

        // Analytics snapshots
        $sql[] = "CREATE TABLE {$t['analytics']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            snapshot_date DATE NOT NULL,
            program_id BIGINT UNSIGNED NOT NULL,
            enrollments INT UNSIGNED DEFAULT 0,
            completion_rate DECIMAL(5,2) DEFAULT 0,
            avg_score DECIMAL(5,2) DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY program_date (program_id, snapshot_date)
        ) $charset_collate;";

        // Assessments
        $sql[] = "CREATE TABLE {$t['assessments']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            program_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            config_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY program_id (program_id)
        ) $charset_collate;";

        // Assessment responses
        $sql[] = "CREATE TABLE {$t['responses']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            assessment_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            answers_json LONGTEXT NOT NULL,
            score DECIMAL(5,2) DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY assessment_id (assessment_id),
            KEY student_id (student_id)
        ) $charset_collate;";

        // Enrollments
        $sql[] = "CREATE TABLE {$t['enrollments']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            program_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'enrolled',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY student_program (student_id, program_id)
        ) $charset_collate;";

        foreach ($sql as $q) {
            dbDelta($q);
        }
    }
}
// End class

// Bootstrap
add_action('plugins_loaded', function() {
    CoachPro_LMS::instance();
});

// Activation hook
register_activation_hook(__FILE__, ['CoachPro_LMS', 'activate']);
}


