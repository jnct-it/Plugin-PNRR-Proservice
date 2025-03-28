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
        // wp_enqueue_style('pnrr-public-css', PNRR_PLUGIN_URL . 'css/public.css', array(), PNRR_VERSION);
        // wp_enqueue_script('pnrr-public-js', PNRR_PLUGIN_URL . 'js/public.js', array('jquery'), PNRR_VERSION, true);
    }

    /**
     * Ottiene tutte le pagine che utilizzano Elementor
     *
     * @return array Array associativo di ID => titolo pagina
     */
    public function get_elementor_pages() {
        $pages = array();
        
        $args = array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_elementor_data',
                    'compare' => 'EXISTS'
                )
            )
        );
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                
                // Verifica che i dati Elementor non siano vuoti
                $elementor_data = get_post_meta(get_the_ID(), '_elementor_data', true);
                if (!empty($elementor_data)) {
                    $pages[get_the_ID()] = get_the_title();
                }
            }
            wp_reset_postdata();
        }
        
        return $pages;
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
     *
     * @param int $page_id ID della pagina
     * @return bool True se la pagina è valida
     */
    public function is_valid_elementor_page($page_id) {
        if (empty($page_id)) {
            return false;
        }
        
        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page' || $page->post_status !== 'publish') {
            return false;
        }
        
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        return !empty($elementor_data);
    }

    /**
     * Elimina tutte le pagine clone
     * 
     * @param bool $force_delete Se true, elimina permanentemente le pagine
     * @return array Risultato dell'operazione con contatore e errori
     */
    public function delete_all_clones($force_delete = true) {
        $result = array(
            'deleted' => 0,
            'errors' => array(),
            'skipped' => 0,
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
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $post_title = get_the_title();
                
                // Esegui l'eliminazione definitiva se richiesto, altrimenti sposta nel cestino
                $deleted = wp_delete_post($post_id, $force_delete);
                
                if ($deleted) {
                    $result['deleted']++;
                    
                    // Log dell'operazione
                    $this->log_action('delete', $post_id, $post_title);
                } else {
                    $result['errors'][] = sprintf('Impossibile eliminare la pagina "%s" (ID: %d)', $post_title, $post_id);
                    $result['skipped']++;
                }
            }
            wp_reset_postdata();
        }
        
        return $result;
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
