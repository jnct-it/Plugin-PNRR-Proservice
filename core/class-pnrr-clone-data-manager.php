<?php
/**
 * Gestore dei dati dei cloni
 * 
 * Si occupa del caricamento, salvataggio e manipolazione dei dati dei cloni
 * 
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

class PNRR_Clone_Data_Manager {
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
        // Inizializzazione
    }
    
    /**
     * Carica i dati di configurazione per tutti i cloni da WordPress options
     * 
     * @param int $number_of_clones Numero di cloni da creare se non esistono dati
     * @return bool True se i dati sono stati caricati con successo
     */
    public function load_data($number_of_clones = 75) {
        // Carica i dati dalle opzioni di WordPress
        $saved_data = get_option($this->clones_option_name, array());
        
        // Se ci sono dati salvati, usali
        if (!empty($saved_data)) {
            $this->clone_data = $saved_data;
            
            // Assicura che ci siano abbastanza cloni secondo l'impostazione attuale
            if (count($this->clone_data) < $number_of_clones) {
                $additional = $this->generate_default_clone_data($number_of_clones - count($this->clone_data), count($this->clone_data) + 1);
                $this->clone_data = array_merge($this->clone_data, $additional);
                $this->save_data();
            }
            // Se ci sono più cloni rispetto all'impostazione attuale, non li rimuoviamo
            // per non perdere dati configurati, ma lasciamo che la UI ne mostri solo il numero configurato
            
            return true;
        } else {
            // Altrimenti, crea dati di esempio e salvali
            $this->clone_data = $this->generate_default_clone_data($number_of_clones);
            return $this->save_data();
        }
    }
    
    /**
     * Genera dati di default per i cloni
     * 
     * @param int $number_of_clones Numero di cloni da generare
     * @param int $start_index Indice di partenza (default: 1)
     * @return array Dati di default
     */
    public function generate_default_clone_data($number_of_clones, $start_index = 1) {
        $data = array();
        
        for ($i = $start_index; $i < $start_index + $number_of_clones; $i++) {
            $data[] = [
                'slug' => 'pnrr-clone-' . $i,
                'title' => 'PNRR Clone ' . $i,
                'logo_url' => '',
                'footer_text' => 'Footer per Clone ' . $i,
                'home_url' => '',
                'enabled' => true,
                'clone_uuid' => 'pnrr_' . uniqid(),
                'last_updated' => current_time('mysql')
            ];
        }
        
        return $data;
    }
    
    /**
     * Salva i dati dei cloni nelle opzioni di WordPress
     * 
     * @return bool Esito dell'operazione
     */
    public function save_data() {
        return update_option($this->clones_option_name, $this->clone_data);
    }
    
    /**
     * Verifica se un indice è valido per l'array dei cloni
     * 
     * @param int $index Indice da verificare
     * @return bool True se l'indice è valido
     */
    public function is_valid_index($index) {
        return is_numeric($index) && $index >= 0 && $index < count($this->clone_data);
    }
    
    /**
     * Ottiene i dati di tutti i cloni con opzioni di filtro
     * 
     * @param bool $only_enabled Se true, restituisce solo i cloni abilitati
     * @param bool $include_deleted Se false, esclude i cloni eliminati
     * @return array Dati dei cloni filtrati
     */
    public function get_all_clones($only_enabled = false, $include_deleted = true) {
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
     * Ottiene un singolo clone per indice
     *
     * @param int $index Indice del clone
     * @return array|null Dati del clone o null se non trovato
     */
    public function get_clone_by_index($index) {
        if ($this->is_valid_index($index)) {
            return $this->clone_data[$index];
        }
        return null;
    }
    
    /**
     * Ottiene un clone tramite UUID
     *
     * @param string $uuid UUID del clone da cercare
     * @return array|null Dati del clone o null se non trovato
     */
    public function get_clone_by_uuid($uuid) {
        foreach ($this->clone_data as $clone) {
            if (isset($clone['clone_uuid']) && $clone['clone_uuid'] === $uuid) {
                return $clone;
            }
        }
        return null;
    }
    
    /**
     * Aggiorna i dati di un singolo clone
     * 
     * @param int $index Indice del clone nell'array
     * @param array $data Nuovi dati per il clone
     * @return bool Esito dell'operazione
     */
    public function update_clone($index, $data) {
        if (!$this->is_valid_index($index)) {
            return false;
        }
        
        // Aggiorna solo i campi forniti
        foreach ($data as $key => $value) {
            $this->clone_data[$index][$key] = $value;
        }
        
        return $this->save_data();
    }
    
    /**
     * Aggiorna o aggiunge i dati di un clone nell'elenco completo
     * 
     * @param array $clone_data Dati del clone da aggiornare o aggiungere
     * @param int|null $page_id ID della pagina associata al clone
     * @return bool Esito dell'operazione
     */
    public function update_clone_in_list($clone_data, $page_id = null) {
        // Se fornito l'ID pagina, aggiungilo ai dati
        if ($page_id !== null) {
            $clone_data['page_id'] = $page_id;
        }
        
        // Cerca se esiste già un clone con lo stesso UUID o slug
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
            if (isset($clone_data['slug']) && isset($existing_clone['slug']) && 
                $clone_data['slug'] === $existing_clone['slug']) {
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
        return $this->save_data();
    }
    
    /**
     * Sincronizza i dati dei cloni con le pagine effettivamente esistenti
     * 
     * @param array $existing_clones Array con i dati attuali dei cloni
     * @param array $clone_pages Array con oggetti WP_Post delle pagine clone
     * @param bool $mark_only Se true, marca solo come "eliminato" invece di rimuovere i record
     * @return array Statistiche sulla sincronizzazione
     */
    public function sync_clones_with_pages($existing_clones, $clone_pages, $mark_only = true) {
        $result = array(
            'total' => count($existing_clones),
            'existing' => 0,
            'missing' => 0,
            'updated' => 0,
            'discovered' => 0,
            'details' => array()
        );
        
        $updated_data = [];
        
        // Mappa degli ID pagina alle pagine
        $page_map = [];
        foreach ($clone_pages as $page) {
            $page_map[$page->ID] = $page;
        }
        
        // Mappa degli UUID alle pagine
        $uuid_map = [];
        foreach ($clone_pages as $page) {
            $uuid = get_post_meta($page->ID, '_pnrr_clone_uuid', true);
            if ($uuid) {
                $uuid_map[$uuid] = $page;
            }
        }
        
        // Fase 1: Verifica l'esistenza delle pagine per i cloni già registrati
        foreach ($existing_clones as $clone) {
            $clone_found = false;
            $page = null;
            
            // Cerca prima per UUID
            if (isset($clone['clone_uuid']) && isset($uuid_map[$clone['clone_uuid']])) {
                $page = $uuid_map[$clone['clone_uuid']];
                $clone_found = true;
            }
            // Poi cerca per ID pagina
            elseif (isset($clone['page_id']) && isset($page_map[$clone['page_id']])) {
                $page = $page_map[$clone['page_id']];
                $clone_found = true;
            }
            // Infine cerca per slug
            elseif (!empty($clone['slug'])) {
                foreach ($clone_pages as $candidate_page) {
                    if ($candidate_page->post_name === $clone['slug']) {
                        $page = $candidate_page;
                        $clone_found = true;
                        break;
                    }
                }
            }
            
            $clone_detail = array(
                'slug' => $clone['slug'],
                'title' => $clone['title'],
                'status' => 'esistente',
                'action' => 'nessuna'
            );
            
            if ($clone_found && $page) {
                // La pagina esiste, aggiorna i dati
                $clone['page_exists'] = true;
                $clone['page_id'] = $page->ID;
                $clone['last_checked'] = current_time('mysql');
                
                $updated_data[] = $clone;
                $result['existing']++;
                $result['details'][] = $clone_detail;
                
                // Rimuovi questa pagina dalle mappe per evitare duplicati
                if (isset($page_map[$page->ID])) {
                    unset($page_map[$page->ID]);
                }
                if (isset($clone['clone_uuid']) && isset($uuid_map[$clone['clone_uuid']])) {
                    unset($uuid_map[$clone['clone_uuid']]);
                }
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
        
        // Fase 2: Processa le pagine clone rimanenti che non sono nei dati
        foreach ($page_map as $page_id => $page) {
            // Recupera i dati dalla pagina stessa
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
                foreach (['logo_url', 'home_url', 'footer_text'] as $field) {
                    if (isset($existing_clone_data[$field])) {
                        $new_clone_data[$field] = $existing_clone_data[$field];
                    }
                }
            }
            
            $updated_data[] = $new_clone_data;
            $result['discovered']++;
            
            $result['details'][] = array(
                'slug' => $new_clone_data['slug'],
                'title' => $new_clone_data['title'],
                'status' => 'scoperto',
                'action' => 'aggiunto'
            );
        }
        
        // Aggiorna i dati e salva
        $this->clone_data = $updated_data;
        $this->save_data();
        
        return $result;
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
    
    /**
     * Ottiene il nome dell'opzione per i dati dei cloni
     * 
     * @return string Nome dell'opzione
     */
    public function get_option_name() {
        return $this->clones_option_name;
    }
}
