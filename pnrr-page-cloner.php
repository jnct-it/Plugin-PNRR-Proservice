<?php
/**
 * Plugin Name: PNRR Page Cloner e manager
 * Plugin URI: 
 * Description: Plugin per clonare la pagina PNRR e creare 75 versioni con percorsi e contenuti personalizzati
 * Version: 1.0
 * Author: Andrea Gouchon
 * Author URI: 
 * License: GPL2
 * Text Domain: pnrr-page-cloner
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

// Definizione delle costanti del plugin
define('PNRR_VERSION', '1.0.0');
define('PNRR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PNRR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PNRR_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('PNRR_OPTION_NAME', 'pnrr_plugin_options');

// Dichiarazione della variabile globale per contenere tutte le istanze principali
global $pnrr_plugin;
$pnrr_plugin = array(
    'core' => null,
    'admin' => null,
    'clone_manager' => null
);

/**
 * Carica le classi del plugin
 */
// Verifica se i file esistono prima di includerli
if (file_exists(PNRR_PLUGIN_DIR . 'includes/class-pnrr-core.php')) {
    require_once PNRR_PLUGIN_DIR . 'includes/class-pnrr-core.php';
}
if (file_exists(PNRR_PLUGIN_DIR . 'includes/class-pnrr-clone.php')) {
    require_once PNRR_PLUGIN_DIR . 'includes/class-pnrr-clone.php';
}
if (file_exists(PNRR_PLUGIN_DIR . 'admin/class-pnrr-admin.php')) {
    require_once PNRR_PLUGIN_DIR . 'admin/class-pnrr-admin.php';
}

/**
 * Carica il file delle funzioni di utilità
 */
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
