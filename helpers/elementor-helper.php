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
 * Wrapper per PNRR_Elementor_Handler::modify_elementor_elements()
 * 
 * @param array $elements Elementi Elementor
 * @param array $clone_data Dati del clone
 * @return array Elementi modificati
 */
function pnrr_modify_elementor_elements($elements, $clone_data) {
    global $pnrr_plugin;
    
    if (isset($pnrr_plugin['elementor_handler']) && method_exists($pnrr_plugin['elementor_handler'], 'modify_elementor_elements')) {
        return $pnrr_plugin['elementor_handler']->modify_elementor_elements($elements, $clone_data);
    }
    
    // Fallback alla vecchia implementazione
    // Implementazione rimossa per evitare duplicati
    return $elements;
}

/**
 * Copia i meta dati di Elementor
 * Wrapper per PNRR_Elementor_Handler::copy_elementor_data()
 * 
 * @param int $source_id ID della pagina sorgente
 * @param int $target_id ID della pagina di destinazione
 * @param array $clone_data Dati del clone
 */
function pnrr_copy_elementor_data($source_id, $target_id, $clone_data) {
    global $pnrr_plugin;
    
    if (isset($pnrr_plugin['elementor_handler']) && method_exists($pnrr_plugin['elementor_handler'], 'copy_elementor_data')) {
        return $pnrr_plugin['elementor_handler']->copy_elementor_data($source_id, $target_id, $clone_data);
    }
    
    // Fallback rimosso per evitare duplicati
}
