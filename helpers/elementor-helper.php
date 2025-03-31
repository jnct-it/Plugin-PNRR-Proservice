<?php
/**
 * Funzioni helper per Elementor
 *
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ottiene tutte le pagine che utilizzano Elementor
 *
 * @return array Array associativo di ID => titolo pagina
 */
function pnrr_get_elementor_pages() {
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
 * Verifica se la pagina esiste e usa Elementor
 *
 * @param int $page_id ID della pagina
 * @return bool True se la pagina è valida
 */
function pnrr_is_valid_elementor_page($page_id) {
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
 * Modifica gli elementi specifici di Elementor
 * 
 * @param array $elements Elementi Elementor
 * @param array $clone_data Dati del clone
 * @return array Elementi modificati
 */
function pnrr_modify_elementor_elements($elements, $clone_data) {
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
            $element['elements'] = pnrr_modify_elementor_elements($element['elements'], $clone_data);
        }
    }
    
    return $elements;
}

/**
 * Copia i meta dati di Elementor
 * 
 * @param int $source_id ID della pagina sorgente
 * @param int $target_id ID della pagina di destinazione
 * @param array $clone_data Dati del clone
 */
function pnrr_copy_elementor_data($source_id, $target_id, $clone_data) {
    // Copia il meta _elementor_data
    $elementor_data = get_post_meta($source_id, '_elementor_data', true);
    if (!empty($elementor_data)) {
        // Converte in array per manipolazione
        $elementor_array = json_decode($elementor_data, true);
        
        if (is_array($elementor_array)) {
            // Modifica gli elementi specifici
            $elementor_array = pnrr_modify_elementor_elements($elementor_array, $clone_data);
            
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
