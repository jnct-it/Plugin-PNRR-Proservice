<?php
/**
 * Classe per la gestione dei cloni
 * 
 * Si occupa della creazione, modifica ed eliminazione delle pagine clone
 * coordinando le operazioni con classi specializzate
 * 
 * @since 1.0.0
 * @refactored 1.1.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

class PNRR_Clone {
    /**
     * Gestore dei dati dei cloni
     * 
     * @var PNRR_Clone_Data_Manager
     */
    private $data_manager;
    
    /**
     * Gestore delle pagine
     * 
     * @var PNRR_Page_Handler
     */
    private $page_handler;
    
    /**
     * Gestore di Elementor
     * 
     * @var PNRR_Elementor_Handler
     */
    private $elementor_handler;
    
    /**
     * ID della pagina master da clonare
     * 
     * @var int|null
     */
    private $master_page_id;
    
    /**
     * Nome della pagina sorgente (retrocompatibilità)
     * 
     * @var string
     */
    private $source_page_name;
    
    /**
     * Numero di cloni da creare
     * 
     * @var int
     */
    private $number_of_clones;
    
    /**
     * Costruttore
     */
    public function __construct() {
        // Carica le opzioni dalle impostazioni centralizzate
        $this->master_page_id = pnrr_get_option('master_page_id', null);
        $this->source_page_name = pnrr_get_option('source_page_name', 'pnrr');
        $this->number_of_clones = pnrr_get_option('number_of_clones', 75);
        
        // Inizializza i gestori
        $this->init_handlers();
        
        // Caricamento dei dati di configurazione per i cloni
        $this->load_clone_data();
    }
    
    /**
     * Inizializza i gestori utilizzati dalla classe
     */
    private function init_handlers() {
        $this->data_manager = new PNRR_Clone_Data_Manager();
        $this->page_handler = new PNRR_Page_Handler();
        $this->elementor_handler = new PNRR_Elementor_Handler();
    }
    
    /**
     * Ottiene il numero di cloni da generare
     * 
     * @return int Numero di cloni
     */
    public function get_number_of_clones() {
        // Rileggi sempre l'opzione per avere il valore aggiornato
        $old_value = $this->number_of_clones;
        $this->number_of_clones = pnrr_get_option('number_of_clones', 75);
        
        // Aggiungi un log per tracciare i cambiamenti
        if ($old_value !== $this->number_of_clones) {
            pnrr_debug_log("Numero di cloni cambiato da {$old_value} a {$this->number_of_clones}", 'info');
        }
        
        return $this->number_of_clones;
    }
    
    /**
     * Carica i dati di configurazione per tutti i cloni
     *
     * @return bool True se i dati sono stati caricati con successo
     */
    private function load_clone_data() {
        $num_clones = $this->get_number_of_clones();
        pnrr_debug_log("Caricamento dati clone per {$num_clones} cloni", 'info');
        $result = $this->data_manager->load_data($num_clones);
        pnrr_debug_log("Risultato caricamento dati: " . ($result ? 'successo' : 'fallimento'), 'info');
        return $result;
    }
    
    /**
     * Ottiene i dati di tutti i cloni con opzioni di filtro
     * 
     * @param bool $only_enabled Se true, restituisce solo i cloni abilitati
     * @param bool $include_deleted Se false, esclude i cloni eliminati
     * @return array Dati dei cloni filtrati
     */
    public function get_clone_data($only_enabled = false, $include_deleted = true) {
        // Delega completamente al data manager
        return $this->data_manager->get_all_clones($only_enabled, $include_deleted);
    }
    
    /**
     * Aggiorna i dati di un singolo clone
     * 
     * @param int $index Indice del clone nell'array
     * @param array $data Nuovi dati per il clone
     * @return bool|WP_Error Esito dell'operazione
     */
    public function update_clone_data($index, $data) {
        // Validazione dell'indice
        if (!$this->data_manager->is_valid_index($index)) {
            return new WP_Error('invalid_index', 'Indice clone non valido');
        }
        
        // Sanitizzazione dei dati
        $sanitized_data = $this->sanitize_clone_data($data);
        
        // Aggiungi la data di aggiornamento
        $sanitized_data['last_updated'] = current_time('mysql');
        
        // Delega l'aggiornamento al data manager
        return $this->data_manager->update_clone($index, $sanitized_data);
    }
    
    /**
     * Sanitizza i dati del clone
     *
     * @param array $data Dati da sanitizzare
     * @return array Dati sanitizzati
     */
    private function sanitize_clone_data($data) {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'slug':
                    $sanitized[$key] = sanitize_title($value);
                    break;
                case 'title':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                case 'logo_url':
                case 'home_url':
                    $sanitized[$key] = esc_url_raw($value);
                    break;
                case 'footer_text':
                    $sanitized[$key] = wp_kses_post($value);
                    break;
                case 'enabled':
                    $sanitized[$key] = (bool)$value;
                    break;
                default:
                    $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Disabilita un clone senza eliminarlo
     * 
     * @param int $index Indice del clone
     * @return bool|WP_Error Esito dell'operazione
     */
    public function disable_clone($index) {
        // Validazione dell'indice
        if (!$this->data_manager->is_valid_index($index)) {
            return new WP_Error('invalid_index', 'Indice clone non valido');
        }
        
        return $this->update_clone_data($index, ['enabled' => false]);
    }
    
    /**
     * Ottiene l'ID della pagina master
     * 
     * @return int|null ID della pagina master
     */
    public function get_master_page_id() {
        return $this->master_page_id;
    }
    
    /**
     * Imposta l'ID della pagina master
     *
     * @param int $page_id Nuovo ID della pagina master
     * @return bool Esito dell'operazione
     */
    public function set_master_page_id($page_id) {
        if (!$page_id || !is_numeric($page_id)) {
            return false;
        }
        
        $this->master_page_id = (int)$page_id;
        return pnrr_update_option('master_page_id', $this->master_page_id);
    }
    
    /**
     * Ottiene il nome della pagina sorgente (retrocompatibilità)
     * 
     * @return string Nome della pagina sorgente
     */
    public function get_source_page_name() {
        // Se è impostato un ID pagina master, ottieni il nome della pagina
        if ($this->master_page_id) {
            $page = get_post($this->master_page_id);
            if ($page) {
                return $page->post_name;
            }
        }
        
        // Altrimenti restituisci il valore di default
        return $this->source_page_name;
    }
    
    /**
     * Ottiene la pagina master
     * 
     * @return WP_Post|WP_Error Oggetto pagina o errore
     */
    public function get_master_page() {
        if ($this->master_page_id) {
            $page = get_post($this->master_page_id);
            if ($page) {
                return $page;
            }
        }
        
        // Retrocompatibilità con la vecchia logica basata sul nome
        $page = get_page_by_path($this->source_page_name);
        if ($page) {
            return $page;
        }
        
        return new WP_Error('page_not_found', 'Pagina master non trovata');
    }
    
    /**
     * Clona una singola pagina
     * 
     * @param WP_Post $source_page Pagina sorgente
     * @param array $clone_data Dati del clone
     * @return int|WP_Error ID della pagina clonata o oggetto di errore
     */
    public function clone_single_page($source_page, $clone_data) {
        // Validazione preliminare dei dati del clone
        if (!isset($clone_data['slug']) || !isset($clone_data['title'])) {
            return new WP_Error('missing_data', 'Dati essenziali mancanti (slug o titolo)');
        }
        
        // Validazione della pagina sorgente
        if (!is_object($source_page) || !isset($source_page->ID)) {
            return new WP_Error('invalid_source', 'Pagina sorgente non valida');
        }
        
        // Preparazione dei dati del clone
        $prepared_data = $this->prepare_clone_data($clone_data);
        
        // Creazione o aggiornamento della pagina
        $page_id = $this->page_handler->create_or_update_page($source_page, $prepared_data);
        if (is_wp_error($page_id)) {
            return $page_id;
        }
        
        // Aggiungi meta per identificare questa pagina come clone
        $this->page_handler->setup_page_meta($page_id, $source_page->ID, $prepared_data);
        
        // Copia i meta dati di Elementor
        $this->elementor_handler->copy_elementor_data($source_page->ID, $page_id, $prepared_data);
        
        // Aggiorna il dato page_id nel clone
        $prepared_data['page_id'] = $page_id;
        
        // Aggiorna o aggiungi i dati del clone nell'elenco completo
        $this->data_manager->update_clone_in_list($prepared_data);
        
        return $page_id;
    }
    
    /**
     * Prepara i dati del clone assicurando che tutti i campi richiesti siano presenti
     *
     * @param array $clone_data Dati iniziali del clone
     * @return array Dati completi del clone
     */
    private function prepare_clone_data($clone_data) {
        // Genera un ID univoco per questo clone se non esiste già
        if (!isset($clone_data['clone_uuid'])) {
            $clone_data['clone_uuid'] = 'pnrr_' . uniqid();
        }
        
        // Sanitizza i dati
        $clone_data = $this->sanitize_clone_data($clone_data);
        
        // Assicura che i campi obbligatori siano impostati
        $default_data = array(
            'enabled' => true,
            'page_exists' => true,
            'status' => 'active',
            'last_updated' => current_time('mysql')
        );
        
        // Combina i dati di input con i default
        return array_merge($default_data, $clone_data);
    }
    
    /**
     * Sincronizza i dati dei cloni con le pagine effettivamente esistenti
     * 
     * @param bool $mark_only Se true, marca solo come "eliminato" invece di rimuovere i record
     * @return array Statistiche sulla sincronizzazione
     */
    public function sync_clone_data($mark_only = true) {
        // Ottieni tutti i cloni esistenti
        $existing_clones = $this->data_manager->get_all_clones();
        
        // Ottieni tutte le pagine clone dal database
        $clone_pages = $this->page_handler->get_clone_pages();
        
        // Esegui la sincronizzazione
        $result = $this->data_manager->sync_clones_with_pages($existing_clones, $clone_pages, $mark_only);
        
        return $result;
    }
    
    /**
     * Ottiene il nome dell'opzione per i dati dei cloni
     * 
     * @return string Nome dell'opzione
     */
    public function get_option_name() {
        return $this->data_manager->get_option_name();
    }
    
    /**
     * Ricarica i dati dei cloni dalle opzioni
     * 
     * @return boolean Esito del ricaricamento
     */
    public function reload_data() {
        // Rileggi il numero di cloni dalle opzioni
        $old_value = $this->number_of_clones;
        $this->number_of_clones = pnrr_get_option('number_of_clones', 75);
        
        pnrr_debug_log("reload_data chiamato. Numero cloni: {$this->number_of_clones} (era: {$old_value})", 'info');
        
        // Carica i dati con il numero aggiornato di cloni
        $result = $this->load_clone_data();
        pnrr_debug_log("Risultato reload_data: " . ($result ? 'successo' : 'fallimento'), 'info');
        
        return $result;
    }
}