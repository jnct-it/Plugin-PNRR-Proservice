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
     * Nome dell'opzione per i dati dei cloni
     * 
     * @var string
     */
    private $clones_option_name = 'pnrr_clones_data';
    
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
     * Carica i dati di configurazione per tutti i cloni da WordPress options
     */
    private function load_clone_data() {
        // Carica i dati delle opzioni di WordPress
        $saved_data = get_option($this->clones_option_name, array());
        
        // Se ci sono dati salvati, usali
        if (!empty($saved_data)) {
            $this->clone_data = $saved_data;
        } else {
            // Altrimenti, crea dati di esempio e salvali
            $this->clone_data = $this->generate_default_clone_data();
            $this->save_clone_data();
        }
    }
    
    /**
     * Genera dati di default per i cloni
     * 
     * @return array Dati di default
     */
    private function generate_default_clone_data() {
        $data = array();
        
        for ($i = 1; $i <= $this->number_of_clones; $i++) {
            $data[] = [
                'slug' => 'pnrr-clone-' . $i,
                'title' => 'PNRR Clone ' . $i,
                'logo_url' => '',
                'footer_text' => 'Footer per Clone ' . $i,
                'home_url' => '',
                'enabled' => true,
                'last_updated' => ''
            ];
        }
        
        return $data;
    }
    
    /**
     * Salva i dati dei cloni nelle opzioni di WordPress
     * 
     * @return bool Esito dell'operazione
     */
    public function save_clone_data() {
        return update_option($this->clones_option_name, $this->clone_data);
    }
    
    /**
     * Aggiorna i dati di un singolo clone
     * 
     * @param int $index Indice del clone nell'array
     * @param array $data Nuovi dati per il clone
     * @return bool Esito dell'operazione
     */
    public function update_clone_data($index, $data) {
        if ($index < 0 || $index >= count($this->clone_data)) {
            return false;
        }
        
        // Aggiorna solo i campi forniti
        foreach ($data as $key => $value) {
            if (isset($this->clone_data[$index][$key])) {
                $this->clone_data[$index][$key] = $value;
            }
        }
        
        // Registra la data di ultimo aggiornamento
        $this->clone_data[$index]['last_updated'] = current_time('mysql');
        
        return $this->save_clone_data();
    }
    
    /**
     * Disabilita un clone senza eliminarlo
     * 
     * @param int $index Indice del clone
     * @return bool Esito dell'operazione
     */
    public function disable_clone($index) {
        if ($index < 0 || $index >= count($this->clone_data)) {
            return false;
        }
        
        $this->clone_data[$index]['enabled'] = false;
        return $this->save_clone_data();
    }
    
    /**
     * Ottiene i dati di tutti i cloni
     * 
     * @param bool $only_enabled Se true, restituisce solo i cloni abilitati
     * @param bool $include_deleted Se false, esclude i cloni eliminati
     * @return array Dati dei cloni
     */
    public function get_clone_data($only_enabled = false, $include_deleted = true) {
        $result = $this->clone_data;
        
        // Filtra per rimuovere i cloni eliminati se richiesto
        if (!$include_deleted) {
            $result = array_filter($result, function($clone) {
                return !isset($clone['status']) || $clone['status'] !== 'deleted';
            });
        }
        
        // Filtra solo i cloni abilitati se richiesto
        if ($only_enabled) {
            $result = array_filter($result, function($clone) {
                return isset($clone['enabled']) && $clone['enabled'] === true;
            });
        }
        
        return $result;
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
        // Assicurati che i dati essenziali siano presenti
        if (!isset($clone_data['slug']) || !isset($clone_data['title'])) {
            return new WP_Error('missing_data', 'Dati essenziali mancanti (slug o titolo)');
        }
        
        // Genera un ID univoco per questo clone se non esiste già
        if (!isset($clone_data['clone_uuid'])) {
            $clone_data['clone_uuid'] = 'pnrr_' . uniqid();
        }
        
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
        
        // Assicurati che lo stato sia impostato su 'attivo'
        $clone_data['enabled'] = true;
        $clone_data['page_exists'] = true;
        $clone_data['status'] = 'active';
        $clone_data['page_id'] = $page_id; // Salva l'ID della pagina nei dati del clone
        $clone_data['last_updated'] = current_time('mysql');
        
        // Aggiungi meta per identificare questa pagina come clone
        update_post_meta($page_id, '_pnrr_is_clone', 'yes');
        update_post_meta($page_id, '_pnrr_clone_data', $clone_data);
        update_post_meta($page_id, '_pnrr_source_page_id', $source_page->ID);
        update_post_meta($page_id, '_pnrr_clone_batch', date('Y-m-d H:i:s'));
        update_post_meta($page_id, '_pnrr_clone_version', PNRR_VERSION);
        update_post_meta($page_id, '_pnrr_clone_uuid', $clone_data['clone_uuid']);
        
        // Copia i meta dati di Elementor
        $this->copy_elementor_data($source_page->ID, $page_id, $clone_data);
        
        // Aggiorna o aggiungi i dati del clone nell'elenco completo
        $this->update_clone_data_in_list($clone_data);
        
        return $page_id;
    }
    
    /**
     * Aggiorna o aggiunge i dati di un clone nell'elenco completo
     * 
     * @param array $clone_data Dati del clone da aggiornare o aggiungere
     * @return bool Esito dell'operazione
     */
    private function update_clone_data_in_list($clone_data) {
        // Cerca se esiste già un clone con lo stesso slug
        $found = false;
        $index = null;
        
        foreach ($this->clone_data as $i => $existing_clone) {
            // Confronta prima per UUID se disponibile
            if (isset($clone_data['clone_uuid']) && isset($existing_clone['clone_uuid']) && 
                $clone_data['clone_uuid'] === $existing_clone['clone_uuid']) {
                $found = true;
                $index = $i;
                break;
            }
            
            // Se non c'è UUID, confronta per slug
            if ($existing_clone['slug'] === $clone_data['slug']) {
                $found = true;
                $index = $i;
                break;
            }
        }
        
        if ($found) {
            // Aggiorna i dati esistenti
            $this->clone_data[$index] = array_merge($this->clone_data[$index], $clone_data);
        } else {
            // Aggiungi nuovo clone
            $this->clone_data[] = $clone_data;
        }
        
        // Salva i dati aggiornati nelle opzioni
        return $this->save_clone_data();
    }
    
    /**
     * Sincronizza i dati dei cloni con le pagine effettivamente esistenti
     * 
     * @param bool $mark_only Se true, marca solo come "eliminato" invece di rimuovere i record
     * @return array Statistiche sulla sincronizzazione
     */
    public function sync_clone_data($mark_only = true) {
        $result = array(
            'total' => count($this->clone_data),
            'existing' => 0,
            'missing' => 0,
            'updated' => 0,
            'discovered' => 0,
            'details' => array()
        );
        
        $updated_data = [];
        
        // 1. Prima fase: verifica l'esistenza delle pagine per i cloni già registrati
        foreach ($this->clone_data as $index => $clone) {
            // Salva i dati originali per i dettagli
            $clone_detail = array(
                'slug' => $clone['slug'],
                'title' => $clone['title'],
                'status' => 'esistente',
                'action' => 'nessuna'
            );
            
            // Verifica se la pagina esiste - prima per UUID, poi per slug
            $page = null;
            if (isset($clone['clone_uuid'])) {
                // Query per trovare pagine con questo UUID
                $uuid_query = new WP_Query([
                    'post_type' => 'page',
                    'post_status' => ['publish', 'draft', 'pending'],
                    'posts_per_page' => 1,
                    'meta_query' => [
                        [
                            'key' => '_pnrr_clone_uuid',
                            'value' => $clone['clone_uuid'],
                            'compare' => '='
                        ]
                    ]
                ]);
                
                if ($uuid_query->have_posts()) {
                    $page = $uuid_query->posts[0];
                }
                wp_reset_postdata();
            }
            
            // Se non trovato per UUID, cerca per slug
            if (!$page) {
                $page = get_page_by_path($clone['slug']);
            }
            
            if ($page) {
                // La pagina esiste, aggiorna i dati
                $clone['page_exists'] = true;
                $clone['page_id'] = $page->ID;
                $clone['last_checked'] = current_time('mysql');
                
                // Assicurati che la pagina sia correttamente marcata come clone
                update_post_meta($page->ID, '_pnrr_is_clone', 'yes');
                if (isset($clone['clone_uuid'])) {
                    update_post_meta($page->ID, '_pnrr_clone_uuid', $clone['clone_uuid']);
                }
                
                $updated_data[] = $clone;
                $result['existing']++;
                $result['details'][] = $clone_detail;
            } else {
                // La pagina non esiste
                $result['missing']++;
                $clone_detail['status'] = 'mancante';
                $clone_detail['action'] = $mark_only ? 'contrassegnato' : 'rimosso';
                
                if ($mark_only) {
                    // Contrassegna come eliminato ma mantieni il record
                    $clone['page_exists'] = false;
                    $clone['enabled'] = false;
                    $clone['status'] = 'deleted';
                    $clone['last_checked'] = current_time('mysql');
                    $updated_data[] = $clone;
                    $result['updated']++;
                }
                
                $result['details'][] = $clone_detail;
            }
        }
        
        // 2. Seconda fase: cerca pagine clonate che esistono ma non sono nei dati
        $args = array(
            'post_type' => 'page',
            'post_status' => array('publish', 'draft', 'pending'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_pnrr_is_clone',
                    'value' => 'yes',
                    'compare' => '='
                )
            )
        );
        
        $clone_pages = get_posts($args);
        
        foreach ($clone_pages as $page) {
            $page_id = $page->ID;
            
            // Controlla se questa pagina è già nei dati aggiornati
            $found = false;
            
            // Cerca prima per ID pagina
            foreach ($updated_data as $clone) {
                if (isset($clone['page_id']) && $clone['page_id'] == $page_id) {
                    $found = true;
                    break;
                }
            }
            
            // Se non trovato per ID, cerca per UUID
            if (!$found) {
                $clone_uuid = get_post_meta($page_id, '_pnrr_clone_uuid', true);
                if ($clone_uuid) {
                    foreach ($updated_data as $clone) {
                        if (isset($clone['clone_uuid']) && $clone['clone_uuid'] === $clone_uuid) {
                            $found = true;
                            break;
                        }
                    }
                }
            }
            
            // Se non è stato trovato, è una pagina clone che non è nei nostri dati
            if (!$found) {
                // Recupera i dati dalla pagina stessa o usa valori predefiniti
                $existing_clone_data = get_post_meta($page_id, '_pnrr_clone_data', true);
                $clone_uuid = get_post_meta($page_id, '_pnrr_clone_uuid', true);
                
                if (!$clone_uuid) {
                    $clone_uuid = 'pnrr_' . uniqid();
                    update_post_meta($page_id, '_pnrr_clone_uuid', $clone_uuid);
                }
                
                $new_clone_data = array(
                    'slug' => $page->post_name,
                    'title' => $page->post_title,
                    'page_id' => $page_id,
                    'clone_uuid' => $clone_uuid,
                    'enabled' => true,
                    'page_exists' => true,
                    'status' => 'active',
                    'discovered' => true,
                    'last_updated' => current_time('mysql'),
                    'last_checked' => current_time('mysql')
                );
                
                // Incorpora eventuali dati esistenti dal meta
                if (is_array($existing_clone_data)) {
                    // Prendi dati specifici come logo_url, home_url, footer_text se esistono
                    foreach (['logo_url', 'home_url', 'footer_text'] as $field) {
                        if (isset($existing_clone_data[$field])) {
                            $new_clone_data[$field] = $existing_clone_data[$field];
                        }
                    }
                }
                
                // Aggiungi ai dati aggiornati
                $updated_data[] = $new_clone_data;
                $result['discovered']++;
                
                // Aggiungi ai dettagli
                $result['details'][] = array(
                    'slug' => $new_clone_data['slug'],
                    'title' => $new_clone_data['title'],
                    'status' => 'scoperto',
                    'action' => 'aggiunto'
                );
            }
        }
        
        // Aggiorna i dati in memoria e salva nelle opzioni
        $this->clone_data = $updated_data;
        $this->save_clone_data();
        
        return $result;
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
    
    /**
     * Ottiene il nome dell'opzione per i dati dei cloni
     * 
     * @return string Nome dell'opzione
     */
    public function get_option_name() {
        return $this->clones_option_name;
    }
    
    /**
     * Ricarica i dati dei cloni dalle opzioni
     * 
     * @return boolean Esito del ricaricamento
     */
    public function reload_data() {
        $saved_data = get_option($this->clones_option_name, array());
        
        if (!empty($saved_data)) {
            $this->clone_data = $saved_data;
            return true;
        }
        
        return false;
    }
}
