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
function pnrr_load_core_classes() {
    // File core del plugin
    $core_files = [
        'core/class-pnrr-core.php',
        'core/class-pnrr-clone-data-manager.php',
        'core/class-pnrr-clone.php',
        'core/class-pnrr-page-handler.php',
        'core/class-pnrr-elementor-handler.php'
    ];
    
    // Carica i file core
    foreach ($core_files as $file) {
        $file_path = PNRR_PLUGIN_DIR . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
    
    // Carica i file helper
    require_once PNRR_PLUGIN_DIR . 'helpers/import-export.php';
    require_once PNRR_PLUGIN_DIR . 'helpers/elementor-helper.php';
    require_once PNRR_PLUGIN_DIR . 'helpers/shortcodes.php';
    
    // Carica il file di debug se WP_DEBUG è attivato
    if (defined('WP_DEBUG') && WP_DEBUG) {
        require_once PNRR_PLUGIN_DIR . 'includes/debug-log.php';
    }
}

// Sostituisci il vecchio caricamento esplicito
pnrr_load_core_classes();

/**
 * Registra gli script e gli stili del plugin
 */
function pnrr_register_assets() {
    // Versione per cache busting
    $version = PNRR_VERSION;
    
    // Registra CSS
    wp_register_style(
        'pnrr-admin-css', 
        PNRR_PLUGIN_URL . 'assets/css/admin.css', 
        array(), 
        $version
    );
    
    // Registra JS
    wp_register_script(
        'pnrr-admin-js', 
        PNRR_PLUGIN_URL . 'assets/js/admin.js', 
        array('jquery'), 
        $version, 
        true
    );
}
add_action('admin_init', 'pnrr_register_assets');

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
    $old_value = isset($options[$key]) ? $options[$key] : null;
    $options[$key] = $value;
    $result = update_option(PNRR_OPTION_NAME, $options);
    
    // Aggiungi un log sempre, indipendentemente da WP_DEBUG
    $log_message = "Aggiornamento opzione '{$key}': da " . var_export($old_value, true) . " a " . var_export($value, true) . " - Risultato: " . ($result ? 'successo' : 'fallimento');
    pnrr_debug_log($log_message);
    
    return $result;
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

/**
 * Rimuove il prefisso "PNRR -" da un titolo
 * 
 * @param string $title Il titolo da elaborare
 * @return string Il titolo senza prefisso
 */
function pnrr_remove_title_prefix($title) {
    if (substr($title, 0, 7) === 'PNRR - ') {
        return substr($title, 7);
    }
    return $title;
}

/**
 * Filtro per rimuovere il prefisso "PNRR - " dai titoli delle pagine
 */
function pnrr_filter_page_title($title, $post_id = null) {
    // Non modificare titoli nell'admin
    if (is_admin()) {
        return $title;
    }
    
    // Se non abbiamo un ID, otteniamo quello corrente
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    // Verifica se è una pagina clone
    $clone_uuid = get_post_meta($post_id, '_pnrr_clone_uuid', true);
    if (!empty($clone_uuid)) {
        // Controlla se esiste un titolo pulito personalizzato
        $clean_title = get_post_meta($post_id, '_pnrr_clean_title', true);
        if (!empty($clean_title)) {
            return $clean_title;
        }
    }
    
    // Rimozione generica del prefisso per qualsiasi titolo
    return pnrr_remove_title_prefix($title);
}

// Applica il filtro per i titoli in vari contesti
add_filter('the_title', 'pnrr_filter_page_title', 10, 2);
add_filter('single_post_title', 'pnrr_filter_page_title', 10, 2);
add_filter('wp_title', 'pnrr_filter_page_title', 10, 2);

/**
 * Filtro per document_title_parts
 *
 * @param array $title_parts Parti del titolo del documento
 * @return array Parti del titolo modificate
 */
function pnrr_filter_document_title_parts($title_parts) {
    if (!is_admin() && isset($title_parts['title'])) {
        $title_parts['title'] = pnrr_remove_title_prefix($title_parts['title']);
    }
    return $title_parts;
}

/**
 * Filtro per il contenuto generato da Elementor
 */
function pnrr_filter_elementor_content($content) {
    // Cerca e sostituisci esplicitamente "PNRR - " nel contenuto
    $content = str_replace('">[PNRR - ', '">', $content);
    $content = str_replace('">PNRR - ', '">', $content);
    
    return $content;
}
add_filter('the_content', 'pnrr_filter_elementor_content');
