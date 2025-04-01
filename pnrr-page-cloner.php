<?php
/**
 * Plugin Name: PNRR Page Cloner e manager
 * Plugin URI: 
 * Description: Plugin per clonare la pagina PNRR e creare 75 versioni con percorsi e contenuti personalizzati
 * Version: 1.2
 * Author: Andrea Gouchon
 * Author URI: 
 * License: GPL2
 * Text Domain: pnrr-page-cloner
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

// Definisci una costante per disabilitare il codice legacy
define('PNRR_DISABLED_LEGACY', true);

// Carica la configurazione del plugin
require_once plugin_dir_path(__FILE__) . 'includes/config.php';

// Carica il file delle funzioni di utilità
require_once PNRR_PLUGIN_DIR . 'includes/functions.php';

/**
 * Inizializza il plugin
 */
function pnrr_init() {
    global $pnrr_plugin;
    
    // Inizializza la classe principale
    if (class_exists('PNRR_Core')) {
        $pnrr_plugin['core'] = new PNRR_Core();
        $pnrr_plugin['clone_manager'] = $pnrr_plugin['core']->get_clone_manager();
        
        // Inizializza anche il gestore di Elementor per centralizzare
        if (class_exists('PNRR_Elementor_Handler')) {
            $pnrr_plugin['elementor_handler'] = new PNRR_Elementor_Handler();
        }
    }
    
    // Inizializza l'amministrazione solo nel backend
    if (is_admin() && class_exists('PNRR_Admin')) {
        $pnrr_plugin['admin'] = new PNRR_Admin();
    }
}

// Hook per inizializzare il plugin
add_action('plugins_loaded', 'pnrr_init');

/**
 * Registra funzioni di attivazione e disattivazione
 */
function pnrr_activate() {
    // Inizializza le opzioni di default
    $default_options = array(
        'version' => PNRR_VERSION,
        'number_of_clones' => 75,
        'source_page_name' => 'pnrr'
    );
    
    // Salva le opzioni solo se non esistono già
    if (!get_option(PNRR_OPTION_NAME)) {
        add_option(PNRR_OPTION_NAME, $default_options);
    }
    
    // Crea le directory necessarie
    pnrr_create_required_directories();
}
register_activation_hook(__FILE__, 'pnrr_activate');

function pnrr_deactivate() {
    // Operazioni da eseguire durante la disattivazione del plugin
}
register_deactivation_hook(__FILE__, 'pnrr_deactivate');

// Aggiungi un filtro per debug per verificare il funzionamento
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', 'pnrr_debug_title_info');
    
    function pnrr_debug_title_info() {
        if (!is_admin() && is_singular('page')) {
            $post_id = get_the_ID();
            $title = get_the_title($post_id);
            $clean_title = get_post_meta($post_id, '_pnrr_clean_title', true);
            $original_title = get_post_meta($post_id, '_pnrr_title', true);
            $clone_uuid = get_post_meta($post_id, '_pnrr_clone_uuid', true);
            
            if (!empty($clone_uuid)) {
                echo '<!-- PNRR DEBUG INFO:
                Page ID: ' . $post_id . '
                Original Title from DB: ' . $title . '
                Meta Title: ' . $original_title . '
                Clean Title Meta: ' . $clean_title . '
                Clone UUID: ' . $clone_uuid . '
                -->';
            }
        }
    }
}
