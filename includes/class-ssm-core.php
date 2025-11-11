<?php
// 🇵🇰 PHP Phase Start: Core Class 🇵🇰
/**
 * Main Core Class: Handles loading dependencies, setting up hooks, and running the plugin.
 */
class SSM_Core {

    protected $loader;
    protected $plugin_slug = 'ssm-gpt-launcher';

    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        // Core dependencies
        require_once SSM_GPT_PATH . 'includes/admin/class-ssm-cpt-manager.php';
        require_once SSM_GPT_PATH . 'includes/class-ssm-ajax-handler.php';
    }

    private function define_admin_hooks() {
        // Register Custom Post Type and Taxonomy
        add_action( 'init', array( 'SSM_CPT_Manager', 'register_cpt' ) );
        add_action( 'init', array( 'SSM_CPT_Manager', 'register_taxonomy' ) );

        // Add CPT Meta Boxes
        require_once SSM_GPT_PATH . 'includes/admin/class-ssm-meta-boxes.php';
        $meta_boxes = new SSM_Meta_Boxes( $this->plugin_slug );
        add_action( 'add_meta_boxes', array( $meta_boxes, 'add_gpt_meta_boxes' ) );
        add_action( 'save_post_custom_gpts', array( $meta_boxes, 'save_gpt_meta_data' ), 10, 2 );

        // Enqueue Admin Assets
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        
        // Add Admin Menu Page (For settings/dashboard)
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    }

    private function define_public_hooks() {
        // Enqueue Public Assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );

        // Register Shortcode
        add_shortcode( 'gpt_showcase', array( $this, 'render_gpt_showcase' ) );

        // AJAX Hooks (for handling form data, although we'll mostly use JS on frontend)
        $ajax_handler = new SSM_AJAX_Handler( $this->plugin_slug );
        // No PHP AJAX needed for this specific prompt-copy logic, but we define the handler class for future use
        // add_action( 'wp_ajax_ssm_gpt_get_form', array( $ajax_handler, 'handle_get_form' ) );
        // add_action( 'wp_ajax_nopriv_ssm_gpt_get_form', array( $ajax_handler, 'handle_get_form' ) );
    }

    public function add_admin_menu() {
        add_menu_page(
            __( 'GPT Launcher', 'ssm-gpt-launcher' ),
            __( 'GPT Launcher', 'ssm-gpt-launcher' ),
            'manage_options',
            $this->plugin_slug . '-dashboard',
            array( $this, 'render_admin_dashboard' ),
            'dashicons-superhero',
            6
        );
    }

    public function render_admin_dashboard() {
        // Root element for the dashboard
        ?>
        <div class="wrap">
            <h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
            <div id="ssm-dashboard-root" class="ssm-root" data-screen="dashboard">
                <p><?php esc_html_e( 'Welcome to the Custom GPT Launcher. Manage your GPTs under the "GPTs" custom post type menu.', 'ssm-gpt-launcher' ); ?></p>
                <p><?php esc_html_e( 'To display the showcase, use the shortcode:', 'ssm-gpt-launcher' ); ?> <code>[gpt_showcase]</code></p>
            </div>
        </div>
        <?php
    }

    public function enqueue_admin_assets( $hook ) {
        // Check if we are on the CPT editor screen or our dashboard page
        if ( $hook === 'post.php' || $hook === 'post-new.php' || strpos( $hook, $this->plugin_slug . '-dashboard' ) !== false ) {
            wp_enqueue_style( $this->plugin_slug . '-admin-style', SSM_GPT_URL . 'assets/css/admin.css', array(), '1.0.0' );
            wp_enqueue_script( $this->plugin_slug . '-admin-script', SSM_GPT_URL . 'assets/js/admin.js', array( 'jquery' ), '1.0.0', true );
        }
    }

    public function enqueue_public_assets() {
        // Enqueue public CSS for cards and modal
        wp_enqueue_style( $this->plugin_slug . '-frontend-style', SSM_GPT_URL . 'assets/css/frontend-style.css', array(), '1.0.0' );
        
        // Enqueue public JS for modal and prompt generation
        wp_enqueue_script( $this->plugin_slug . '-frontend-script', SSM_GPT_URL . 'assets/js/frontend-launcher.js', array( 'jquery' ), '1.0.0', true );
        
        // Pass necessary data to the JS file
        wp_localize_script( $this->plugin_slug . '-frontend-script', 'ssm_gpt_vars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ssm_gpt_frontend_nonce' ) // Nonce for future AJAX use
        ));
    }

    public function render_gpt_showcase( $atts ) {
        ob_start();
        // CPT Query
        $args = array(
            'post_type'      => 'custom_gpts',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'order'          => 'ASC',
        );

        $gpts_query = new WP_Query( $args );

        ?>
        <div id="ssm-frontend-root" class="ssm-root ssm-gpt-showcase">
            <div class="ssm-gpt-grid">
                <?php
                if ( $gpts_query->have_posts() ) {
                    while ( $gpts_query->have_posts() ) {
                        $gpts_query->the_post();
                        $post_id   = get_the_ID();
                        $gpt_url   = esc_url( get_post_meta( $post_id, '_ssm_gpt_url', true ) );
                        $form_html = get_post_meta( $post_id, '_ssm_gpt_form_html', true ); // The HTML for the form fields
                        $icon_url  = esc_url( get_post_meta( $post_id, '_ssm_gpt_icon_url', true ) );
                        $prompt_template  = esc_html( get_post_meta( $post_id, '_ssm_gpt_prompt_template', true ) );

                        // Load the Card Template
                        include SSM_GPT_PATH . 'templates/shortcode-card.php';
                    }
                } else {
                    echo '<p>' . esc_html__( 'No Custom GPTs found.', 'ssm-gpt-launcher' ) . '</p>';
                }
                wp_reset_postdata();
                ?>
            </div>
            <?php 
            // Load the Modal Template once outside the loop
            include SSM_GPT_PATH . 'templates/shortcode-modal.php';
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function run() {
        // The hooks are defined in the constructor, so this method simply keeps the class structure standard.
    }
}
// 🇵🇰 PHP Phase End: Core Class 🇵🇰
