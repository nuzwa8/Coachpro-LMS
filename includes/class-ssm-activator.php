<?php
// 🇵🇰 PHP Phase Start: Activator Class (DB and CPT Setup) 🇵🇰
/**
 * The plugin activation class.
 */
class SSM_Activator {

    public static function activate() {
        // We only need to register CPT and flush rewrite rules on activation.
        // CPT registration logic is handled in SSM_CPT_Manager but we call it here to ensure it's loaded.
        
        // Temporarily load CPT Manager class to ensure CPTs are registered before flushing rules
        if ( ! class_exists( 'SSM_CPT_Manager' ) ) {
            require_once SSM_GPT_PATH . 'includes/admin/class-ssm-cpt-manager.php';
        }
        SSM_CPT_Manager::register_cpt(); 
        
        // Flush rewrite rules to ensure the CPT URLs work immediately
        flush_rewrite_rules();

        // Database Table for storing form field configurations (If we chose to use DB instead of Meta Boxes)
        // For simplicity, we are heavily relying on Post Meta and CPT, so no custom DB table needed right now.
        // However, if complex settings were needed, we would use dbDelta() here.
        
        // Add a version option
        add_option( 'ssm_gpt_launcher_version', '1.0.0' );
    }
}
// 🇵🇰 PHP Phase End: Activator Class 🇵🇰
