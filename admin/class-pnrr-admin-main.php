<?php
/**
 * Classe principale per l'interfaccia amministrativa
 * 
 * Coordina tutte le funzionalità dell'interfaccia amministrativa del plugin
 * 
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

class PNRR_Admin_Main {
    
    /**
     * Istanza della classe per gestire le chiamate AJAX
     * 
     * @var PNRR_Admin_Ajax
     */
    private $ajax_handler;
    
    /**
     * Istanza della classe per la visualizzazione dell'interfaccia
     * 
     * @var PNRR_Admin_Display
     */
    private $display_handler;
    
    /**
     * Costruttore
     */
    public function __construct() {
        // Nota: non carichiamo le classi qui per evitare dipendenze circolari
        // Le classi sono già caricate dal wrapper PNRR_Admin
        
        // Inizializza le istanze se le classi sono disponibili
        if (class_exists('PNRR_Admin_Ajax')) {
            $this->ajax_handler = new PNRR_Admin_Ajax();
        }
        
        if (class_exists('PNRR_Admin_Display')) {
            $this->display_handler = new PNRR_Admin_Display();
        }
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Aggiunge la voce di menu nella dashboard di WordPress
     */
    public function add_admin_menu() {
        // Aggiungi pagina principale
        add_menu_page(
            'PNRR Page Cloner',
            'PNRR Page Cloner',
            'manage_options',
            'pnrr-page-cloner',
            array($this, 'display_admin_page'),
            'dashicons-admin-page',
            30
        );
        
        // Aggiungi sottopagina dashboard come alias della principale
        add_submenu_page(
            'pnrr-page-cloner',
            'PNRR Dashboard',
            'Dashboard',
            'manage_options',
            'pnrr-page-cloner',
            array($this, 'display_admin_page')
        );
        
        // Aggiungi sottopagina per la documentazione degli shortcode
        add_submenu_page(
            'pnrr-page-cloner',
            'Documentazione Shortcode',
            'Documentazione',
            'manage_options',
            'pnrr-shortcode-docs',
            array($this, 'display_shortcode_docs')
        );
    }
    
    /**
     * Carica script e stili per l'interfaccia di amministrazione
     *
     * @param string $hook Hook corrente
     */
    public function enqueue_admin_scripts($hook) {
        // Carica gli script solo nelle pagine del plugin
        if (strpos($hook, 'pnrr') === false) {
            return;
        }

        // Registra e carica gli stili CSS
        wp_enqueue_style('pnrr-admin-css', PNRR_PLUGIN_URL . 'assets/css/admin.css', array(), PNRR_VERSION);
        wp_enqueue_style('pnrr-diagnostics-css', PNRR_PLUGIN_URL . 'assets/css/diagnostics.css', array(), PNRR_VERSION);
        
        // Registra e carica gli script JS
        wp_enqueue_media();
        wp_enqueue_script('jquery');
        
        // Carica i moduli JS dalla directory assets/js (percorso corretto)
        wp_enqueue_script('pnrr-master-page', PNRR_PLUGIN_URL . 'assets/js/master-page.js', array('jquery'), PNRR_VERSION, true);
        wp_enqueue_script('pnrr-clone-process', PNRR_PLUGIN_URL . 'assets/js/clone-process.js', array('jquery'), PNRR_VERSION, true);
        wp_enqueue_script('pnrr-delete-process', PNRR_PLUGIN_URL . 'assets/js/delete-process.js', array('jquery'), PNRR_VERSION, true);
        wp_enqueue_script('pnrr-identify-process', PNRR_PLUGIN_URL . 'assets/js/identify-process.js', array('jquery'), PNRR_VERSION, true);
        wp_enqueue_script('pnrr-import-export', PNRR_PLUGIN_URL . 'assets/js/import-export.js', array('jquery'), PNRR_VERSION, true);
        wp_enqueue_script('pnrr-table-management', PNRR_PLUGIN_URL . 'assets/js/table-management.js', array('jquery'), PNRR_VERSION, true);
        wp_enqueue_script('pnrr-media-selector', PNRR_PLUGIN_URL . 'assets/js/media-selector.js', array('jquery', 'wp-media-utils'), PNRR_VERSION, true);
        wp_enqueue_script('pnrr-sync-process', PNRR_PLUGIN_URL . 'assets/js/sync-process.js', array('jquery'), PNRR_VERSION, true);
        wp_enqueue_script('pnrr-general-settings', PNRR_PLUGIN_URL . 'assets/js/general-settings.js', array('jquery'), PNRR_VERSION, true);
        
        // Carica il nuovo script per le funzionalità del modale di modifica
        wp_enqueue_script(
            'pnrr-edit-clone-modal',
            PNRR_PLUGIN_URL . 'assets/js/edit-clone-modal.js',
            array('jquery'),
            PNRR_VERSION,
            true
        );
        
        // Carica il file principale per ultimo (dipende da tutti i moduli)
        wp_enqueue_script('pnrr-admin', PNRR_PLUGIN_URL . 'assets/js/pnrr-admin.js', array(
            'jquery', 
            'pnrr-master-page', 
            'pnrr-clone-process', 
            'pnrr-delete-process', 
            'pnrr-identify-process', 
            'pnrr-import-export', 
            'pnrr-table-management', 
            'pnrr-media-selector', 
            'pnrr-sync-process', 
            'pnrr-general-settings',
            'pnrr-edit-clone-modal'
        ), PNRR_VERSION, true);
        
        // Passa i dati al JavaScript
        wp_localize_script('pnrr-admin', 'pnrr_cloner', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pnrr_cloner_nonce'),
            'plugin_url' => PNRR_PLUGIN_URL
        ));
    }
    
    /**
     * Visualizza la pagina di amministrazione
     */
    public function display_admin_page() {
        // Esegui sincronizzazione automatica prima di visualizzare la pagina
        $sync_results = $this->perform_auto_sync();
        
        // Delega la visualizzazione al display handler
        $this->display_handler->render_admin_page($sync_results);
    }
    
    /**
     * Visualizza la pagina di documentazione degli shortcode
     */
    public function display_shortcode_docs() {
        echo '<div class="wrap">';
        echo '<h1>Documentazione Shortcode PNRR Cloner</h1>';
        if (file_exists(PNRR_PLUGIN_DIR . 'admin/partials/shortcode-instructions.php')) {
            include PNRR_PLUGIN_DIR . 'admin/partials/shortcode-instructions.php';
        } else {
            echo '<div class="notice notice-error"><p>File di documentazione non trovato.</p></div>';
        }
        echo '</div>';
    }
    
    /**
     * Esegue una sincronizzazione automatica prima di mostrare la dashboard
     * 
     * @return array Risultati della sincronizzazione
     */
    private function perform_auto_sync() {
        global $pnrr_plugin;
        
        // Opzione per disabilitare la sincronizzazione automatica
        $auto_sync_enabled = apply_filters('pnrr_enable_auto_sync', true);
        if (!$auto_sync_enabled) {
            return array();
        }
        
        if (!isset($pnrr_plugin['clone_manager']) || !is_object($pnrr_plugin['clone_manager'])) {
            return array();
        }
        
        // Esegui la sincronizzazione con opzione di solo marcatura (non rimuovere dati)
        return $pnrr_plugin['clone_manager']->sync_clone_data(true);
    }
}