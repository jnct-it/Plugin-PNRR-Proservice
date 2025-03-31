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
        
        // Aggiunta delle azioni e dei filtri per l'admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Aggiunge la voce di menu nella dashboard di WordPress
     */
    public function add_admin_menu() {
        add_menu_page(
            'PNRR Page Cloner',
            'PNRR Cloner',
            'manage_options',
            'pnrr-page-cloner',
            array($this, 'display_admin_page'),
            'dashicons-admin-page',
            30
        );
    }
    
    /**
     * Carica gli script e i CSS per la pagina di amministrazione
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook != 'toplevel_page_pnrr-page-cloner') {
            return;
        }
        
        // Utilizzo i percorsi aggiornati per i file assets
        wp_enqueue_style('pnrr-admin-css', PNRR_PLUGIN_URL . 'assets/css/admin.css', array(), PNRR_VERSION);
        wp_enqueue_script('pnrr-admin-js', PNRR_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), PNRR_VERSION, true);
        
        // Includi i script di WordPress Media per il selettore immagine
        wp_enqueue_media();
        
        wp_localize_script('pnrr-admin-js', 'pnrr_cloner', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pnrr_cloner_nonce'),
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