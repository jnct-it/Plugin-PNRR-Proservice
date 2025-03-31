<?php
/**
 * Gestore delle pagine clone
 * 
 * Si occupa della creazione, aggiornamento ed eliminazione delle pagine clone
 * 
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

class PNRR_Page_Handler {
    /**
     * Costruttore
     */
    public function __construct() {
        // Inizializzazione
    }
    
    /**
     * Crea o aggiorna una pagina clone
     *
     * @param WP_Post $source_page Pagina sorgente
     * @param array $clone_data Dati del clone
     * @return int|WP_Error ID della pagina o oggetto errore
     */
    public function create_or_update_page($source_page, $clone_data) {
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
            
            $result = wp_update_post($update_args);
            if (is_wp_error($result)) {
                return $result;
            }
            
            return $page_id;
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
            
            return $page_id;
        }
    }
    
    /**
     * Configura i meta della pagina clone
     *
     * @param int $page_id ID della pagina clone
     * @param int $source_id ID della pagina sorgente
     * @param array $clone_data Dati del clone
     */
    public function setup_page_meta($page_id, $source_id, $clone_data) {
        update_post_meta($page_id, '_pnrr_is_clone', 'yes');
        update_post_meta($page_id, '_pnrr_clone_data', $clone_data);
        update_post_meta($page_id, '_pnrr_source_page_id', $source_id);
        update_post_meta($page_id, '_pnrr_clone_batch', date('Y-m-d H:i:s'));
        update_post_meta($page_id, '_pnrr_clone_version', PNRR_VERSION);
        update_post_meta($page_id, '_pnrr_clone_uuid', $clone_data['clone_uuid']);
    }
    
    /**
     * Ottiene tutte le pagine clone
     * 
     * @return array Array di oggetti WP_Post
     */
    public function get_clone_pages() {
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
        
        return get_posts($args);
    }
    
    /**
     * Elimina una pagina clone
     *
     * @param int $page_id ID della pagina da eliminare
     * @param bool $force_delete Se eliminare definitivamente
     * @return bool|WP_Error True in caso di successo, WP_Error in caso di errore
     */
    public function delete_clone_page($page_id, $force_delete = true) {
        // Verifica che la pagina sia effettivamente un clone
        $is_clone = get_post_meta($page_id, '_pnrr_is_clone', true);
        if ($is_clone !== 'yes') {
            return new WP_Error('not_clone', 'La pagina specificata non è un clone');
        }
        
        // Esegui l'eliminazione
        $result = wp_delete_post($page_id, $force_delete);
        
        if ($result === false) {
            return new WP_Error('delete_failed', 'Impossibile eliminare la pagina');
        }
        
        return true;
    }
}
