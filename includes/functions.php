<?php
/**
 * Funzioni di utilità per il plugin PNRR Page Cloner
 *
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Funzione di autoload per le classi del plugin
 */
spl_autoload_register('pnrr_autoload');

function pnrr_autoload($class_name) {
    // Verifica se la classe appartiene al plugin
    if (strpos($class_name, 'PNRR_') !== 0) {
        return;
    }
    
    // Converti il nome della classe in nome del file
    $class_file = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
    
    // Cerca il file nelle directory del plugin
    $directories = array('core', 'includes', 'admin', 'helpers');
    foreach ($directories as $directory) {
        $file_path = PNRR_PLUGIN_DIR . $directory . '/' . $class_file;
        if (file_exists($file_path)) {
            require_once $file_path;
            return;
        }
    }
}

/**
 * Carica le classi principali del plugin e i file helper
 */
require_once PNRR_PLUGIN_DIR . 'core/class-pnrr-core.php';
require_once PNRR_PLUGIN_DIR . 'core/class-pnrr-clone.php';
require_once PNRR_PLUGIN_DIR . 'admin/class-pnrr-admin.php';

// Carica i file helper
require_once PNRR_PLUGIN_DIR . 'helpers/import-export.php';
require_once PNRR_PLUGIN_DIR . 'helpers/elementor-helper.php';

/**
 * Ottiene le opzioni del plugin
 *
 * @param string $key Chiave dell'opzione da recuperare
 * @param mixed $default Valore di default se l'opzione non esiste
 * @return mixed Valore dell'opzione o array completo se $key è null
 */
function pnrr_get_option($key = null, $default = null) {
    $options = get_option(PNRR_OPTION_NAME, array());
    
    if (is_null($key)) {
        return $options;
    }
    
    return isset($options[$key]) ? $options[$key] : $default;
}

/**
 * Aggiorna un'opzione del plugin
 *
 * @param string $key Chiave dell'opzione da aggiornare
 * @param mixed $value Nuovo valore
 * @return bool Esito dell'operazione
 */
function pnrr_update_option($key, $value) {
    $options = get_option(PNRR_OPTION_NAME, array());
    $options[$key] = $value;
    return update_option(PNRR_OPTION_NAME, $options);
}

/**
 * Crea le directory necessarie al plugin
 */
function pnrr_create_required_directories() {
    // Crea le directory necessarie se non esistono
    $directories = [
        'admin/partials',
        'core',
        'helpers',
        'assets/css',
        'assets/js'
    ];
    
    foreach ($directories as $dir) {
        $full_path = PNRR_PLUGIN_DIR . $dir;
        if (!file_exists($full_path)) {
            wp_mkdir_p($full_path);
        }
    }
}
