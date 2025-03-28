<?php
/**
 * Classe per la gestione dei cloni
 * 
 * Si occupa della creazione, modifica ed eliminazione delle pagine clone
 * 
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

class PNRR_Clone {
    
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
     * Dati dei cloni
     * 
     * @var array
     */
    private $clone_data = [];
    
    /**
     * Costruttore
     */
    public function __construct() {
        // Carica le opzioni dalle impostazioni centralizzate
        $this->master_page_id = pnrr_get_option('master_page_id', null);
        $this->source_page_name = pnrr_get_option('source_page_name', 'pnrr');
        $this->number_of_clones = pnrr_get_option('number_of_clones', 75);
        
        // Caricamento dei dati di configurazione per i cloni
        $this->load_clone_data();
    }
    
    /**
     * Carica i dati di configurazione per tutti i cloni
     */
    private function load_clone_data() {
        // Qui puoi caricare i dati da un file CSV o da un'opzione di WordPress
        
        // Per ora, creiamo dati di esempio
        for ($i = 1; $i <= $this->number_of_clones; $i++) {
            $this->clone_data[] = [
                'slug' => 'pnrr-clone-' . $i,
                'title' => 'PNRR Clone ' . $i,
                'logo_url' => 'https://esempio.it/loghi/logo' . $i . '.png',
                'footer_text' => 'Footer per Clone ' . $i,
                'home_url' => 'https://example.com/home' . $i
            ];
        }
    }
    
    /**
     * Ottiene i dati dei cloni
     * 
     * @return array Dati dei cloni
     */
    public function get_clone_data() {
        return $this->clone_data;
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
     * @return WP_Post|null Oggetto pagina o null
     */
    public function get_master_page() {
        if ($this->master_page_id) {
            return get_post($this->master_page_id);
        }
        
        // Retrocompatibilità con la vecchia logica basata sul nome
        return get_page_by_path($this->source_page_name);
    }
    
    /**
     * Clona una singola pagina
     * 
     * @param WP_Post $source_page Pagina sorgente
     * @param array $clone_data Dati del clone
     * @return int|WP_Error ID della pagina clonata o oggetto di errore
     */
    public function clone_single_page($source_page, $clone_data) {
        // Verifica se esiste già una pagina con lo stesso slug
        $existing_page = get_page_by_path($clone_data['slug']);
        if ($existing_page) {
            // Aggiorna la pagina esistente
            $page_id = $existing_page->ID;
            $update_args = array(
                'ID' => $page_id,
                'post_title' => $clone_data['title'],
                'post_content' => $source_page->post_content,
            );
            wp_update_post($update_args);
        } else {
            // Crea una nuova pagina
            $page_args = array(
                'post_title' => $clone_data['title'],
                'post_name' => $clone_data['slug'],
                'post_content' => $source_page->post_content,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => get_current_user_id(),
                'page_template' => get_page_template_slug($source_page->ID)
            );
            $page_id = wp_insert_post($page_args);
            
            if (is_wp_error($page_id)) {
                return $page_id;
            }
        }
        
        // Aggiungi meta per identificare questa pagina come clone
        update_post_meta($page_id, '_pnrr_is_clone', 'yes');
        update_post_meta($page_id, '_pnrr_clone_data', $clone_data);
        update_post_meta($page_id, '_pnrr_source_page_id', $source_page->ID);
        update_post_meta($page_id, '_pnrr_clone_batch', date('Y-m-d H:i:s'));
        update_post_meta($page_id, '_pnrr_clone_version', PNRR_VERSION);
        
        // Copia i meta dati di Elementor
        $this->copy_elementor_data($source_page->ID, $page_id, $clone_data);
        
        return $page_id;
    }
    
    /**
     * Copia i meta dati di Elementor
     * 
     * @param int $source_id ID della pagina sorgente
     * @param int $target_id ID della pagina di destinazione
     * @param array $clone_data Dati del clone
     */
    private function copy_elementor_data($source_id, $target_id, $clone_data) {
        // Copia il meta _elementor_data
        $elementor_data = get_post_meta($source_id, '_elementor_data', true);
        if (!empty($elementor_data)) {
            // Converte in array per manipolazione
            $elementor_array = json_decode($elementor_data, true);
            
            if (is_array($elementor_array)) {
                // Modifica gli elementi specifici
                $elementor_array = $this->modify_elementor_elements($elementor_array, $clone_data);
                
                // Salva i dati modificati
                update_post_meta($target_id, '_elementor_data', wp_slash(json_encode($elementor_array)));
            }
        }
        
        // Copia altri meta necessari per Elementor
        $elementor_meta_keys = array(
            '_elementor_edit_mode',
            '_elementor_template_type',
            '_elementor_version',
            '_elementor_css'
        );
        
        foreach ($elementor_meta_keys as $meta_key) {
            $meta_value = get_post_meta($source_id, $meta_key, true);
            if (!empty($meta_value)) {
                update_post_meta($target_id, $meta_key, $meta_value);
            }
        }
        
        // Rigenera CSS per la pagina clonata
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
    }
    
    /**
     * Modifica gli elementi specifici di Elementor
     * 
     * @param array $elements Elementi Elementor
     * @param array $clone_data Dati del clone
     * @return array Elementi modificati
     */
    private function modify_elementor_elements($elements, $clone_data) {
        foreach ($elements as &$element) {
            // Modifica logo se è un widget immagine
            if (isset($element['widgetType']) && $element['widgetType'] === 'image') {
                if (isset($element['settings']['_element_id']) && $element['settings']['_element_id'] === 'logo') {
                    $element['settings']['image']['url'] = $clone_data['logo_url'];
                }
            }
            
            // Modifica link home se è un widget pulsante
            if (isset($element['widgetType']) && $element['widgetType'] === 'button') {
                if (isset($element['settings']['_element_id']) && $element['settings']['_element_id'] === 'home-button') {
                    $element['settings']['link']['url'] = $clone_data['home_url'];
                }
            }
            
            // Modifica testo footer se è un widget di testo
            if (isset($element['widgetType']) && $element['widgetType'] === 'text-editor') {
                if (isset($element['settings']['_element_id']) && $element['settings']['_element_id'] === 'footer-text') {
                    $element['settings']['editor'] = $clone_data['footer_text'];
                }
            }
            
            // Ricorsione per sezioni e colonne
            if (isset($element['elements']) && is_array($element['elements'])) {
                $element['elements'] = $this->modify_elementor_elements($element['elements'], $clone_data);
            }
        }
        
        return $elements;
    }
}
