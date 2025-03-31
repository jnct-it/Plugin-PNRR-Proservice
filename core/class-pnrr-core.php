<?php
/**
 * Classe principale del plugin
 * 
 * Gestisce le funzionalità core del plugin
 * 
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

class PNRR_Core {

    /**
     * Istanza della classe PNRR_Clone
     * 
     * @var PNRR_Clone
     */
    private $clone_manager;

    /**
     * Costruttore
     */
    public function __construct() {
        // Inizializzazione del gestore dei cloni
        if (class_exists('PNRR_Clone')) {
            $this->clone_manager = new PNRR_Clone();
            
            // Setup dei filtri e delle azioni
            $this->setup_hooks();
        }
    }

    /**
     * Configura gli hook principali
     */
    private function setup_hooks() {
        // Registra hook per la parte pubblica (frontend) se necessario
        // add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
        
        // Registra shortcode se necessario
        // add_shortcode('pnrr_content', array($this, 'shortcode_handler'));
    }

    /**
     * Ottiene l'istanza del gestore dei cloni
     * 
     * @return PNRR_Clone|null
     */
    public function get_clone_manager() {
        return $this->clone_manager;
    }
    
    /**
     * Carica script e stili per il frontend (se necessario)
     */
    public function enqueue_public_scripts() {
        // wp_enqueue_style('pnrr-public-css', PNRR_PLUGIN_URL . 'assets/css/public.css', array(), PNRR_VERSION);
        // wp_enqueue_script('pnrr-public-js', PNRR_PLUGIN_URL . 'assets/js/public.js', array('jquery'), PNRR_VERSION, true);
    }

    /**
     * Ottiene tutte le pagine che utilizzano Elementor
     * Utilizza la funzione helper
     *
     * @return array Array associativo di ID => titolo pagina
     */
    public function get_elementor_pages() {
        return pnrr_get_elementor_pages();
    }
    
    /**
     * Ottiene l'ID della pagina master selezionata
     *
     * @return int|null ID della pagina o null se non selezionata
     */
    public function get_master_page_id() {
        return pnrr_get_option('master_page_id', null);
    }
    
    /**
     * Imposta l'ID della pagina master
     *
     * @param int $page_id ID della pagina
     * @return bool Esito dell'operazione
     */
    public function set_master_page_id($page_id) {
        return pnrr_update_option('master_page_id', $page_id);
    }
    
    /**
     * Verifica se la pagina esiste e usa Elementor
     * Utilizza la funzione helper
     *
     * @param int $page_id ID della pagina
     * @return bool True se la pagina è valida
     */
    public function is_valid_elementor_page($page_id) {
        return pnrr_is_valid_elementor_page($page_id);
    }

    /**
     * Elimina tutte le pagine clone
     * 
     * @param bool $force_delete Se true, elimina permanentemente le pagine
     * @param bool $update_clone_data Se true, aggiorna i dati dei cloni
     * @param bool $remove_clone_data Se true, rimuove i record dei cloni eliminati invece di marcarli
     * @return array Risultato dell'operazione con contatore e errori
     */
    public function delete_all_clones($force_delete = true, $update_clone_data = true, $remove_clone_data = false) {
        $result = array(
            'deleted' => 0,
            'errors' => array(),
            'skipped' => 0,
            'data_updated' => false
        );
        
        // Query per ottenere tutte le pagine con il meta _pnrr_is_clone
        $args = array(
            'post_type' => 'page',
            'post_status' => array('publish', 'draft', 'trash'),
            'posts_per_page' => -1, // Ottieni tutte le pagine
            'meta_query' => array(
                array(
                    'key' => '_pnrr_is_clone',
                    'value' => 'yes',
                    'compare' => '='
                )
            )
        );
        
        $query = new WP_Query($args);
        $deleted_page_slugs = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $post_title = get_the_title();
                $post_slug = get_post_field('post_name', $post_id);
                
                // Esegui l'eliminazione definitiva se richiesto, altrimenti sposta nel cestino
                $deleted = wp_delete_post($post_id, $force_delete);
                
                if ($deleted) {
                    $result['deleted']++;
                    $deleted_page_slugs[] = $post_slug;
                    
                    // Log dell'operazione
                    $this->log_action('delete', $post_id, $post_title);
                } else {
                    $result['errors'][] = sprintf('Impossibile eliminare la pagina "%s" (ID: %d)', $post_title, $post_id);
                    $result['skipped']++;
                }
            }
            wp_reset_postdata();
        }
        
        // Aggiorna i dati dei cloni se richiesto
        if ($update_clone_data && !empty($deleted_page_slugs) && isset($this->clone_manager)) {
            if ($remove_clone_data) {
                // Rimuovi effettivamente i record dei cloni eliminati
                $result['data_updated'] = $this->update_clone_data_after_deletion($deleted_page_slugs, true);
            } else {
                // Marca i cloni come eliminati ma mantieni i record
                $result['data_updated'] = $this->update_clone_data_after_deletion($deleted_page_slugs, false);
            }
        }
        
        return $result;
    }
    
    /**
     * Aggiorna i dati dei cloni dopo l'eliminazione delle pagine
     *
     * @param array $deleted_slugs Array con gli slug delle pagine eliminate
     * @param bool $remove_data Se true rimuove i record, altrimenti li marca come eliminati
     * @return bool Esito dell'operazione
     */
    private function update_clone_data_after_deletion($deleted_slugs, $remove_data = false) {
        if (!isset($this->clone_manager) || empty($deleted_slugs)) {
            return false;
        }
        
        $clone_data = $this->clone_manager->get_clone_data();
        $updated_data = array();
        $updated = false;
        
        foreach ($clone_data as $clone) {
            if (in_array($clone['slug'], $deleted_slugs)) {
                if (!$remove_data) {
                    // Marca come eliminato ma mantieni il record
                    $clone['page_exists'] = false;
                    $clone['enabled'] = false;
                    $clone['status'] = 'deleted';
                    $updated_data[] = $clone;
                    $updated = true;
                }
                // Se remove_data è true, semplicemente non aggiungiamo questo clone all'array aggiornato
            } else {
                // Mantieni il clone intatto
                $updated_data[] = $clone;
            }
        }
        
        if ($updated || $remove_data) {
            // Aggiorna i dati solo se ci sono stati cambiamenti
            return update_option($this->clone_manager->get_option_name(), $updated_data);
        }
        
        return false;
    }

    /**
     * Registra un'azione nel log del plugin
     * 
     * @param string $action Azione eseguita
     * @param int $post_id ID del post coinvolto
     * @param string $post_title Titolo del post
     */
    public function log_action($action, $post_id, $post_title) {
        $logs = get_option('pnrr_action_logs', array());
        
        $logs[] = array(
            'action' => $action,
            'post_id' => $post_id,
            'post_title' => $post_title,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql')
        );
        
        // Limita il numero di log salvati
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }
        
        update_option('pnrr_action_logs', $logs);
    }

    /**
     * Identifica e marca le pagine clone esistenti
     *
     * Cerca pagine che sembrano essere cloni in base a pattern nel titolo o nello slug
     * e aggiunge loro il meta '_pnrr_is_clone' = 'yes'
     *
     * @return array Statistiche sulle pagine identificate e aggiornate
     */
    public function mark_existing_clones() {
        $result = array(
            'identified' => 0,
            'updated' => 0,
            'skipped' => 0,
            'details' => array()
        );
        
        // Pattern per identificare le pagine clone
        $title_patterns = array(
            'PNRR Clone',
            'PNRR - Clone',
            'PNRR_Clone',
            'PNRR copy',
            'Copy of PNRR'
        );
        
        $slug_patterns = array(
            'pnrr-clone',
            'pnrr_clone',
            'pnrr-copy',
            'copy-of-pnrr'
        );
        
        // Costruisci la query per trovare potenziali pagine clone
        $title_clauses = array();
        foreach ($title_patterns as $pattern) {
            $title_clauses[] = "post_title LIKE '%" . esc_sql($pattern) . "%'";
        }
        
        $slug_clauses = array();
        foreach ($slug_patterns as $pattern) {
            $slug_clauses[] = "post_name LIKE '%" . esc_sql($pattern) . "%'";
        }
        
        // Query personalizzata per trovare potenziali pagine clone
        global $wpdb;
        $query = "
            SELECT ID, post_title, post_name
            FROM {$wpdb->posts}
            WHERE post_type = 'page'
            AND post_status IN ('publish', 'draft')
            AND ((" . implode(' OR ', $title_clauses) . ") OR (" . implode(' OR ', $slug_clauses) . "))
            ORDER BY ID ASC
        ";
        
        $potential_clones = $wpdb->get_results($query);
        $result['identified'] = count($potential_clones);
        
        if (empty($potential_clones)) {
            return $result;
        }
        
        // Verifica quali pagine non hanno già il meta '_pnrr_is_clone'
        foreach ($potential_clones as $page) {
            $existing_meta = get_post_meta($page->ID, '_pnrr_is_clone', true);
            
            $page_info = array(
                'id' => $page->ID,
                'title' => $page->post_title,
                'slug' => $page->post_name
            );
            
            if (empty($existing_meta)) {
                // Aggiungi il meta per marcare questa come pagina clone
                $update_result = update_post_meta($page->ID, '_pnrr_is_clone', 'yes');
                
                if ($update_result) {
                    $result['updated']++;
                    $page_info['status'] = 'marcata';
                    
                    // Aggiungi anche altri meta utili
                    update_post_meta($page->ID, '_pnrr_clone_batch', 'legacy_' . date('Y-m-d'));
                    update_post_meta($page->ID, '_pnrr_clone_version', PNRR_VERSION);
                    
                    // Log dell'azione
                    $this->log_action('mark_existing', $page->ID, $page->post_title);
                } else {
                    $result['skipped']++;
                    $page_info['status'] = 'errore';
                }
            } else {
                $result['skipped']++;
                $page_info['status'] = 'già marcata';
            }
            
            $result['details'][] = $page_info;
        }
        
        return $result;
    }
}