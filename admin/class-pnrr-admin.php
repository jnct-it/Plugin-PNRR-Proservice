<?php
/**
 * Classe per la gestione dell'interfaccia amministrativa
 * 
 * Gestisce il menu, le pagine e le funzionalità amministrative del plugin
 * 
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

class PNRR_Admin {
    
    /**
     * Costruttore
     */
    public function __construct() {
        // Aggiunta delle azioni e dei filtri per l'admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_clone_pnrr_pages', array($this, 'clone_pages_ajax_handler'));
        add_action('wp_ajax_pnrr_save_master_page', array($this, 'save_master_page_ajax_handler'));
        add_action('wp_ajax_pnrr_delete_all_clones', array($this, 'ajax_delete_all_clones'));
        add_action('wp_ajax_pnrr_mark_existing_clones', array($this, 'ajax_mark_existing_clones'));
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
            array($this, 'admin_page_display'),
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
        
        // Utilizzo i nomi dei file standardizzati
        wp_enqueue_style('pnrr-admin-css', PNRR_PLUGIN_URL . 'css/admin.css', array(), PNRR_VERSION);
        wp_enqueue_script('pnrr-admin-js', PNRR_PLUGIN_URL . 'js/admin-script.js', array('jquery'), PNRR_VERSION, true);
        
        wp_localize_script('pnrr-admin-js', 'pnrr_cloner', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pnrr_cloner_nonce'),
        ));
    }
    
    /**
     * Visualizza la pagina di amministrazione
     */
    public function admin_page_display() {
        // Include il template dalla directory partials
        if (file_exists(PNRR_PLUGIN_DIR . 'admin/partials/dashboard-display.php')) {
            require_once PNRR_PLUGIN_DIR . 'admin/partials/dashboard-display.php';
        } else {
            echo '<div class="error"><p>Errore: Template dashboard-display.php non trovato.</p></div>';
        }
    }
    
    /**
     * Gestisce la richiesta Ajax per la clonazione delle pagine
     */
    public function clone_pages_ajax_handler() {
        // Verifica di sicurezza
        check_ajax_referer('pnrr_cloner_nonce', 'nonce');
        
        // Verifica dei permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        global $pnrr_plugin;
        
        // Controlla se l'istanza del clone manager è disponibile
        if (!isset($pnrr_plugin['clone_manager']) || !is_object($pnrr_plugin['clone_manager'])) {
            wp_send_json_error(array('message' => 'Errore: Gestore dei cloni non trovato'));
            return;
        }
        
        $clone_manager = $pnrr_plugin['clone_manager'];
        
        // Ottieni la pagina master
        $source_page = $clone_manager->get_master_page();
        if (!$source_page) {
            wp_send_json_error(array('message' => 'Pagina master non trovata'));
            return;
        }
        
        $clone_data = $clone_manager->get_clone_data();
        $clone_index = isset($_POST['clone_index']) ? intval($_POST['clone_index']) : 0;
        
        if ($clone_index >= count($clone_data)) {
            wp_send_json_success(array('completed' => true));
            return;
        }
        
        // Clona la pagina
        $result = $clone_manager->clone_single_page($source_page, $clone_data[$clone_index]);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'clone_index' => $clone_index
            ));
            return;
        }
        
        wp_send_json_success(array(
            'completed' => false,
            'clone_index' => $clone_index + 1,
            'total' => count($clone_data),
            'page_id' => $result,
            'page_title' => $clone_data[$clone_index]['title'],
            'page_url' => get_permalink($result)
        ));
    }
    
    /**
     * Gestisce la richiesta Ajax per salvare la pagina master
     */
    public function save_master_page_ajax_handler() {
        // Verifica di sicurezza
        check_ajax_referer('pnrr_cloner_nonce', 'nonce');
        
        // Verifica dei permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        global $pnrr_plugin;
        
        // Controlla se l'istanza principale è disponibile
        if (!isset($pnrr_plugin['core']) || !is_object($pnrr_plugin['core'])) {
            wp_send_json_error(array('message' => 'Errore: Istanza principale del plugin non trovata'));
            return;
        }
        
        $core = $pnrr_plugin['core'];
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        
        // Verifica che la pagina esista e sia una pagina Elementor
        if (!$core->is_valid_elementor_page($page_id)) {
            wp_send_json_error(array('message' => 'La pagina selezionata non è valida o non usa Elementor'));
            return;
        }
        
        // Salva l'ID della pagina master
        $result = $core->set_master_page_id($page_id);
        
        if ($result) {
            $page = get_post($page_id);
            wp_send_json_success(array(
                'message' => 'Pagina master "' . esc_html($page->post_title) . '" salvata con successo',
                'page_id' => $page_id,
                'page_title' => $page->post_title
            ));
        } else {
            wp_send_json_error(array('message' => 'Errore durante il salvataggio delle impostazioni'));
        }
    }
    
    /**
     * Gestisce la richiesta Ajax per eliminare tutte le pagine clone
     */
    public function ajax_delete_all_clones() {
        // Verifica di sicurezza
        check_ajax_referer('pnrr_cloner_nonce', 'nonce');
        
        // Verifica dei permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        global $pnrr_plugin;
        
        // Controlla se l'istanza principale è disponibile
        if (!isset($pnrr_plugin['core']) || !is_object($pnrr_plugin['core'])) {
            wp_send_json_error(array('message' => 'Errore: Istanza principale del plugin non trovata'));
            return;
        }
        
        $core = $pnrr_plugin['core'];
        
        // Esegui l'eliminazione
        $result = $core->delete_all_clones(true);
        
        if ($result['deleted'] > 0 || empty($result['errors'])) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array(
                'message' => 'Nessuna pagina è stata eliminata',
                'details' => $result
            ));
        }
    }
    
    /**
     * Gestisce la richiesta Ajax per identificare e marcare pagine clone esistenti
     */
    public function ajax_mark_existing_clones() {
        // Verifica di sicurezza
        check_ajax_referer('pnrr_cloner_nonce', 'nonce');
        
        // Verifica dei permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        global $pnrr_plugin;
        
        // Controlla se l'istanza principale è disponibile
        if (!isset($pnrr_plugin['core']) || !is_object($pnrr_plugin['core'])) {
            wp_send_json_error(array('message' => 'Errore: Istanza principale del plugin non trovata'));
            return;
        }
        
        $core = $pnrr_plugin['core'];
        
        // Esegui l'identificazione e marcatura
        $result = $core->mark_existing_clones();
        
        // Invia risposta
        wp_send_json_success($result);
    }
}
