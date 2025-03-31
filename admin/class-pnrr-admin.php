<?php
/**
 * Classe per la gestione dell'interfaccia amministrativa (Wrapper)
 * 
 * Questo file serve da wrapper per mantenere la compatibilità con il codice esistente
 * delegando le responsabilità ai nuovi sotto-moduli
 * 
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

// Carica i file dei sotto-moduli
require_once PNRR_PLUGIN_DIR . 'admin/class-pnrr-admin-main.php';
require_once PNRR_PLUGIN_DIR . 'admin/class-pnrr-admin-ajax.php';
require_once PNRR_PLUGIN_DIR . 'admin/class-pnrr-admin-display.php';

class PNRR_Admin {
    
    /**
     * Istanza della classe principale amministrativa
     * 
     * @var PNRR_Admin_Main
     */
    private $main;
    
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
        // Inizializza le istanze
        $this->main = new PNRR_Admin_Main();
        $this->ajax_handler = new PNRR_Admin_Ajax();
        $this->display_handler = new PNRR_Admin_Display();
        
        // Mantiene la compatibilità con il passato delegando metodi all'handler main
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Delega le chiamate AJAX all'handler specifico
        $this->delegate_ajax_actions();
    }
    
    /**
     * Delega le chiamate AJAX all'handler specifico
     */
    private function delegate_ajax_actions() {
        // Le azioni AJAX sono già gestite dal costruttore di PNRR_Admin_Ajax
        // Questo metodo è qui solo per chiarezza e futura estendibilità
    }
    
    /**
     * Delega la creazione del menu all'handler main
     */
    public function add_admin_menu() {
        $this->main->add_admin_menu();
    }
    
    /**
     * Delega il caricamento degli script all'handler main
     */
    public function enqueue_admin_scripts($hook) {
        $this->main->enqueue_admin_scripts($hook);
    }
    
    /**
     * Delega la visualizzazione della pagina admin
     */
    public function admin_page_display() {
        $this->main->display_admin_page();
    }
    
    /**
     * Restituisce l'istanza dell'handler display
     * 
     * @return PNRR_Admin_Display
     */
    public function get_display_handler() {
        return $this->display_handler;
    }
    
    /**
     * Delega i metodi AJAX all'handler specifico
     * 
     * Intercetta le chiamate ai metodi non definiti in questa classe
     * e le passa all'handler AJAX
     * 
     * @param string $name Nome del metodo chiamato
     * @param array $arguments Argomenti passati al metodo
     * @return mixed
     */
    public function __call($name, $arguments) {
        // Se il metodo inizia con 'ajax_' o contiene '_ajax_' lo deleghiamo all'handler AJAX
        if (strpos($name, 'ajax_') === 0 || strpos($name, '_ajax_') !== false) {
            if (method_exists($this->ajax_handler, $name)) {
                return call_user_func_array(array($this->ajax_handler, $name), $arguments);
            }
        }
        
        // Metodi non gestiti generano un errore
        trigger_error("Chiamata al metodo non definito $name in " . get_class($this), E_USER_ERROR);
    }
}
