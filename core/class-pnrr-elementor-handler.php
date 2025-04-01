<?php
/**
 * Gestore dei componenti Elementor
 * 
 * Si occupa della manipolazione dei dati di Elementor nelle pagine clone
 * 
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

class PNRR_Elementor_Handler {
    /**
     * Costruttore
     */
    public function __construct() {
        // Inizializzazione
    }
    
    /**
     * Copia i meta dati di Elementor
     * 
     * @param int $source_id ID della pagina sorgente
     * @param int $target_id ID della pagina di destinazione
     * @param array $clone_data Dati del clone
     * @return bool Esito dell'operazione
     */
    public function copy_elementor_data($source_id, $target_id, $clone_data) {
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
            return true;
        }
        
        return false;
    }
    
    /**
     * Modifica gli elementi specifici di Elementor
     * 
     * @param array $elements Elementi Elementor
     * @param array $clone_data Dati del clone
     * @return array Elementi modificati
     */
    public function modify_elementor_elements($elements, $clone_data) {
        foreach ($elements as &$element) {
            // Modifica logo se è un widget immagine
            if (isset($element['widgetType']) && $element['widgetType'] === 'image') {
                if (isset($element['settings']['_element_id']) && $element['settings']['_element_id'] === 'logo') {
                    if (!empty($clone_data['logo_url'])) {
                        $element['settings']['image']['url'] = $clone_data['logo_url'];
                    }
                }
            }
            
            // Modifica link home se è un widget pulsante
            if (isset($element['widgetType']) && $element['widgetType'] === 'button') {
                if (isset($element['settings']['_element_id']) && $element['settings']['_element_id'] === 'home-button') {
                    if (!empty($clone_data['home_url'])) {
                        $element['settings']['link']['url'] = $clone_data['home_url'];
                    }
                }
            }
            
            // Modifica testo footer se è un widget di testo
            if (isset($element['widgetType']) && $element['widgetType'] === 'text-editor') {
                if (isset($element['settings']['_element_id']) && $element['settings']['_element_id'] === 'footer-text') {
                    if (!empty($clone_data['footer_text'])) {
                        $element['settings']['editor'] = $clone_data['footer_text'];
                    }
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
     * Verifica se una pagina usa Elementor
     *
     * @param int $page_id ID della pagina
     * @return bool True se la pagina usa Elementor
     */
    public function is_elementor_page($page_id) {
        if (empty($page_id)) {
            return false;
        }
        
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        return !empty($elementor_data);
    }
    
    /**
     * Ottiene tutte le pagine che usano Elementor
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
     * Duplica i dati di Elementor da una pagina all'altra
     * 
     * @param int $source_id ID della pagina sorgente (master)
     * @param int $target_id ID della pagina di destinazione (clone)
     * @param array $clone_data Dati del clone
     * @return bool Esito dell'operazione
     */
    public function duplicate_elementor_data($source_id, $target_id, $clone_data = []) {
        // Recupera i dati Elementor della pagina master
        $source_data = $this->get_elementor_data($source_id);
        
        // Se non ci sono dati, restituisci false
        if (!$source_data) {
            return false;
        }
        
        // Elabora gli shortcode nel contenuto Elementor se ci sono dati del clone
        if (!empty($clone_data)) {
            $source_data = $this->process_elementor_shortcodes($source_data, $target_id, $clone_data);
        }
        
        // Aggiorna i dati Elementor nella pagina clone
        return $this->update_elementor_data($target_id, $source_data);
    }
    
    /**
     * Elabora gli shortcode nei dati Elementor
     * 
     * @param array $elementor_data Dati Elementor
     * @param int $target_id ID della pagina clone
     * @param array $clone_data Dati del clone
     * @return array Dati Elementor elaborati
     */
    private function process_elementor_shortcodes($elementor_data, $target_id, $clone_data) {
        // Debug - Log dei dati Elementor e del clone prima dell'elaborazione
        if (function_exists('pnrr_debug_log')) {
            pnrr_debug_log("Elaborazione shortcode Elementor - ID pagina: " . $target_id);
            pnrr_debug_log("Dati clone: " . print_r($clone_data, true));
        }
        
        // Converti l'array in stringa JSON
        $json_data = json_encode($elementor_data);
        
        // Elabora gli shortcode nella stringa JSON
        if (function_exists('pnrr_process_elementor_content_shortcodes')) {
            // Assicuriamo che l'elaborazione avvenga senza escape dell'HTML
            $json_data = stripslashes($json_data); // Rimuove eventuali backslash esistenti
            $json_data = pnrr_process_elementor_content_shortcodes($json_data, $target_id, $clone_data);
        }
        
        // Converti di nuovo in array e restituisci
        $processed_data = json_decode($json_data, true);
        
        // Verifica se c'è stato un errore di parsing JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Log dell'errore
            if (function_exists('pnrr_debug_log')) {
                pnrr_debug_log('Errore parsing JSON dopo elaborazione shortcode: ' . json_last_error_msg(), 'error');
            }
            // Fallback ai dati originali se c'è un errore
            return $elementor_data;
        }
        
        return $processed_data;
    }
}
