<?php
/**
 * Funzioni per la gestione degli shortcode personalizzati
 *
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra tutti gli shortcode del plugin
 */
function pnrr_register_shortcodes() {
    add_shortcode('pnrr_nome', 'pnrr_shortcode_nome');
    add_shortcode('pnrr_logo', 'pnrr_shortcode_logo');
    add_shortcode('pnrr_url', 'pnrr_shortcode_url');
    add_shortcode('pnrr_indirizzo', 'pnrr_shortcode_indirizzo');
    add_shortcode('pnrr_contatti', 'pnrr_shortcode_contatti');
    add_shortcode('pnrr_altre', 'pnrr_shortcode_altre');
    add_shortcode('pnrr_cup', 'pnrr_shortcode_cup');
}
add_action('init', 'pnrr_register_shortcodes');

/**
 * Shortcode per visualizzare il titolo/nome dell'ente
 *
 * @param array $atts Attributi dello shortcode
 * @return string Output dello shortcode
 */
function pnrr_shortcode_nome($atts) {
    // Ottieni gli attributi
    $a = shortcode_atts(array(
        'class' => '',
        'raw' => 'false',
        'before' => '',
        'after' => '',
    ), $atts);

    // Ottieni il nome dalla pagina corrente
    $title = '';
    $page_id = get_the_ID();
    
    if ($page_id) {
        // Controllo se è una pagina clone
        $is_clone = get_post_meta($page_id, '_pnrr_is_clone', true);
        
        if ($is_clone && $is_clone === 'yes') {
            // È una pagina clone, ottieni il titolo personalizzato
            $title = get_post_meta($page_id, '_pnrr_title', true);
            
            // Se non è stato impostato un titolo personalizzato, usa quello della pagina
            if (empty($title)) {
                $title = get_the_title($page_id);
            }
            
            // Rimuovi il prefisso "PNRR - " usando la funzione centralizzata
            $title = pnrr_remove_title_prefix($title);
        } else {
            // Non è una pagina clone, usa il titolo normale
            $title = get_the_title($page_id);
        }
    }

    // Permetti modifiche tramite filtro
    $title = apply_filters('pnrr_shortcode_nome', $title);
    
    // Restituisci il nome con eventuali elementi HTML se richiesto
    if ($a['raw'] === 'true') {
        return $title;
    } else {
        $class = !empty($a['class']) ? ' class="' . esc_attr($a['class']) . '"' : '';
        return $a['before'] . '<span' . $class . '>' . esc_html($title) . '</span>' . $a['after'];
    }
}

/**
 * Shortcode per il logo
 */
function pnrr_shortcode_logo($atts) {
    $atts = shortcode_atts(array(
        'width' => 'auto',
        'height' => 'auto',
        'class' => 'pnrr-logo',
        'alt' => 'Logo',
        'link' => 'true'  // Attributo per decidere se linkare o no
    ), $atts, 'pnrr_logo');
    
    $post_id = get_the_ID();
    $logo_url = get_post_meta($post_id, '_pnrr_logo_url', true);
    $home_url = get_post_meta($post_id, '_pnrr_home_url', true);
    
    // Se siamo nella pagina master (editor) o non c'è logo, mostriamo un placeholder
    if (isset($_GET['action']) && $_GET['action'] === 'elementor' || empty($logo_url)) {
        // Correggi il percorso per evitare doppie barre
        $placeholder_url = rtrim(PNRR_PLUGIN_URL, '/') . '/assets/images/placeholder-logo.png';
        $img_html = '<img src="' . $placeholder_url . '" alt="Logo Placeholder" class="' . esc_attr($atts['class']) . '" width="' . esc_attr($atts['width']) . '" height="' . esc_attr($atts['height']) . '">';
    } else {
        $img_html = '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($atts['alt']) . '" class="' . esc_attr($atts['class']) . '" width="' . esc_attr($atts['width']) . '" height="' . esc_attr($atts['height']) . '">';
    }
    
    // Link il logo all'URL del sito, se disponibile e se l'attributo link è true
    if (!empty($home_url) && $atts['link'] === 'true') {
        return '<a href="' . esc_url($home_url) . '" target="_blank" rel="noopener">' . $img_html . '</a>';
    }
    
    return $img_html;
}

/**
 * Shortcode per l'URL del sito
 */
function pnrr_shortcode_url($atts) {
    $atts = shortcode_atts(array(
        'text' => 'Visita il sito',
        'class' => 'pnrr-url-link',
        'target' => '_blank',
        'raw' => false  // Nuovo attributo per restituire solo l'URL raw
    ), $atts, 'pnrr_url');
    
    $post_id = get_the_ID();
    $url = get_post_meta($post_id, '_pnrr_home_url', true);
    
    // Se siamo nella pagina master (editor), mostriamo un placeholder
    if (isset($_GET['action']) && $_GET['action'] === 'elementor' || empty($url)) {
        return '<a href="#" class="' . esc_attr($atts['class']) . ' pnrr-placeholder">' . esc_html($atts['text']) . '</a>';
    }
    
    // Se è richiesto l'URL raw, restituiscilo senza formattazione
    if (filter_var($atts['raw'], FILTER_VALIDATE_BOOLEAN)) {
        return esc_url($url);
    }
    
    return '<a href="' . esc_url($url) . '" class="' . esc_attr($atts['class']) . '" target="' . esc_attr($atts['target']) . '" rel="noopener">' . esc_html($atts['text']) . '</a>';
}

/**
 * Shortcode per l'indirizzo
 */
function pnrr_shortcode_indirizzo($atts) {
    $atts = shortcode_atts(array(
        'class' => 'pnrr-indirizzo'
    ), $atts, 'pnrr_indirizzo');
    
    $post_id = get_the_ID();
    $indirizzo = get_post_meta($post_id, '_pnrr_address', true);
    
    // Assicurati che il contenuto del meta non sia vuoto
    if (empty($indirizzo)) {
        $indirizzo = get_post_meta($post_id, '_pnrr_address', true);
    }
    
    // Se siamo nella pagina master (editor) o non c'è indirizzo, mostriamo un placeholder
    if (isset($_GET['action']) && $_GET['action'] === 'elementor' || empty($indirizzo)) {
        return '<div class="' . esc_attr($atts['class']) . ' pnrr-placeholder">[Indirizzo dell\'ente]</div>';
    }
    
    // Importante: utilizzare wpautop per preservare i ritorni a capo
    return '<div class="' . esc_attr($atts['class']) . '">' . wpautop(wp_kses_post($indirizzo)) . '</div>';
}

/**
 * Shortcode per i contatti
 */
function pnrr_shortcode_contatti($atts) {
    $atts = shortcode_atts(array(
        'class' => 'pnrr-contatti'
    ), $atts, 'pnrr_contatti');
    
    $post_id = get_the_ID();
    $contatti = get_post_meta($post_id, '_pnrr_contacts', true);
    
    // Assicurati che il contenuto del meta non sia vuoto
    if (empty($contatti)) {
        $contatti = get_post_meta($post_id, '_pnrr_contacts', true);
    }
    
    // Se siamo nella pagina master (editor), mostriamo un placeholder
    if (isset($_GET['action']) && $_GET['action'] === 'elementor' || empty($contatti)) {
        return '<div class="' . esc_attr($atts['class']) . ' pnrr-placeholder">[Contatti dell\'ente]</div>';
    }
    
    // Formatta automaticamente email e numeri di telefono come link
    $contatti = preg_replace('/(\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b)/i', '<a href="mailto:$1">$1</a>', $contatti);
    $contatti = preg_replace('/(\b(?:\+\d{1,3}[ -]?)?(?:\(\d{1,4}\)|\d{1,4})[ -]?\d{1,9}[ -]?\d{1,9}\b)/', '<a href="tel:$1">$1</a>', $contatti);
    
    // Importante: utilizzare wpautop per preservare i ritorni a capo
    return '<div class="' . esc_attr($atts['class']) . '">' . wpautop(wp_kses_post($contatti)) . '</div>';
}

/**
 * Shortcode per altre informazioni
 */
function pnrr_shortcode_altre($atts) {
    $atts = shortcode_atts(array(
        'class' => 'pnrr-altre-info'
    ), $atts, 'pnrr_altre');
    
    $post_id = get_the_ID();
    $altre = get_post_meta($post_id, '_pnrr_other_info', true);
    
    // Assicurati che il contenuto del meta non sia vuoto
    if (empty($altre)) {
        $altre = get_post_meta($post_id, '_pnrr_other_info', true);
    }
    
    // Se siamo nella pagina master (editor), mostriamo un placeholder
    if (isset($_GET['action']) && $_GET['action'] === 'elementor' || empty($altre)) {
        return '<div class="' . esc_attr($atts['class']) . ' pnrr-placeholder">[Altre informazioni]</div>';
    }
    
    // Importante: utilizzare wpautop per preservare i ritorni a capo
    return '<div class="' . esc_attr($atts['class']) . '">' . wpautop(wp_kses_post($altre)) . '</div>';
}

/**
 * Shortcode per il CUP (Codice Unico di Progetto)
 */
function pnrr_shortcode_cup($atts) {
    $atts = shortcode_atts(array(
        'class' => 'pnrr-cup',
        'before' => '',
        'after' => ''
    ), $atts, 'pnrr_cup');
    
    $post_id = get_the_ID();
    $cup = get_post_meta($post_id, '_pnrr_cup', true);
    
    // Se siamo nella pagina master (editor), mostriamo un placeholder
    if (isset($_GET['action']) && $_GET['action'] === 'elementor' || empty($cup)) {
        return '<span class="' . esc_attr($atts['class']) . ' pnrr-placeholder">[Codice CUP]</span>';
    }
    
    return $atts['before'] . '<span class="' . esc_attr($atts['class']) . '">' . esc_html($cup) . '</span>' . $atts['after'];
}

/**
 * Elabora il contenuto Elementor e sostituisce gli shortcode con i dati specifici
 * 
 * @param string $content Il contenuto Elementor
 * @param int $post_id ID della pagina clone
 * @param array $clone_data Dati del clone
 * @return string Contenuto elaborato
 */
function pnrr_process_elementor_content_shortcodes($content, $post_id, $clone_data) {
    // Estrai il titolo e crea una versione pulita
    $title = isset($clone_data['title']) ? $clone_data['title'] : '';
    $clean_title = $title;
    
    // Rimuovi il prefisso "PNRR - " se presente
    if (substr($title, 0, 7) === 'PNRR - ') {
        $clean_title = substr($title, 7);
    }
    
    // Salva sia il titolo originale che quello pulito
    update_post_meta($post_id, '_pnrr_title', $title);
    update_post_meta($post_id, '_pnrr_clean_title', $clean_title);
    
    // Salva gli altri dati del clone
    update_post_meta($post_id, '_pnrr_logo_url', isset($clone_data['logo_url']) ? $clone_data['logo_url'] : '');
    update_post_meta($post_id, '_pnrr_home_url', isset($clone_data['home_url']) ? $clone_data['home_url'] : '');
    update_post_meta($post_id, '_pnrr_cup', isset($clone_data['cup']) ? $clone_data['cup'] : '');
    update_post_meta($post_id, '_pnrr_address', isset($clone_data['address']) ? $clone_data['address'] : '');
    update_post_meta($post_id, '_pnrr_contacts', isset($clone_data['contacts']) ? $clone_data['contacts'] : '');
    update_post_meta($post_id, '_pnrr_other_info', isset($clone_data['other_info']) ? $clone_data['other_info'] : '');
    
    // Usa il titolo pulito per lo shortcode
    $shortcodes_map = array(
        '[pnrr_nome]' => $clean_title,
        '[pnrr_cup]' => isset($clone_data['cup']) ? $clone_data['cup'] : '',
        '[pnrr_url raw="true"]' => isset($clone_data['home_url']) ? esc_url($clone_data['home_url']) : '',
        '[pnrr_url]' => isset($clone_data['home_url']) ? 
            '<a href="' . esc_url($clone_data['home_url']) . '" target="_blank" rel="noopener">' . esc_html($clone_data['home_url']) . '</a>' : '',
        '[pnrr_indirizzo]' => isset($clone_data['address']) && !empty($clone_data['address']) ? 
            '<div class="pnrr-indirizzo">' . wpautop(wp_kses_post($clone_data['address'])) . '</div>' : '',
        '[pnrr_contatti]' => isset($clone_data['contacts']) && !empty($clone_data['contacts']) ? 
            '<div class="pnrr-contatti">' . wpautop(wp_kses_post($clone_data['contacts'])) . '</div>' : '',
        '[pnrr_altre]' => isset($clone_data['other_info']) && !empty($clone_data['other_info']) ? 
            '<div class="pnrr-altre-info">' . wpautop(wp_kses_post($clone_data['other_info'])) . '</div>' : '',
    );
    
    // Gestione speciale per il logo (con link)
    $logo_pattern = '/\[pnrr_logo([^\]]*)\]/';
    if (preg_match_all($logo_pattern, $content, $matches)) {
        foreach ($matches[0] as $index => $full_match) {
            $atts_string = $matches[1][$index];
            $atts = shortcode_parse_atts($atts_string);
            
            $logo_html = '';
            if (!empty($clone_data['logo_url'])) {
                $width = isset($atts['width']) ? 'width="' . esc_attr($atts['width']) . '"' : '';
                $height = isset($atts['height']) ? 'height="' . esc_attr($atts['height']) . '"' : '';
                $class = isset($atts['class']) ? 'class="' . esc_attr($atts['class']) . '"' : 'class="pnrr-logo"';
                $alt = isset($atts['alt']) ? 'alt="' . esc_attr($atts['alt']) . '"' : 'alt="Logo"';
                
                // Crea il tag HTML per l'immagine
                $img_html = '<img src="' . esc_url($clone_data['logo_url']) . '" ' . $width . ' ' . $height . ' ' . $class . ' ' . $alt . '>';
                
                // Se c'è un URL home e l'attributo link non è esplicitamente impostato su false, aggiungi il link
                $link = isset($atts['link']) ? $atts['link'] : 'true';
                if (!empty($clone_data['home_url']) && $link !== 'false') {
                    $logo_html = '<a href="' . esc_url($clone_data['home_url']) . '" target="_blank" rel="noopener">' . $img_html . '</a>';
                } else {
                    $logo_html = $img_html;
                }
            }
            
            $content = str_replace($full_match, $logo_html, $content);
        }
    }
    
    // Sostituisci gli altri shortcode, assicurandoci che i tag HTML siano preservati
    foreach ($shortcodes_map as $shortcode => $value) {
        $content = str_replace($shortcode, $value, $content);
    }
    
    return $content;
}

/**
 * Registra gli shortcode personalizzati per essere utilizzati nei widget HTML Elementor
 */
function pnrr_register_elementor_shortcodes() {
    if (defined('ELEMENTOR_VERSION')) {
        add_filter('elementor/widget/render_content', 'pnrr_process_shortcodes_in_elementor', 10, 2);
    }
}
add_action('init', 'pnrr_register_elementor_shortcodes');

/**
 * Elabora gli shortcode nei widget HTML di Elementor
 *
 * @param string $content Il contenuto del widget
 * @param \Elementor\Widget_Base $widget L'oggetto widget
 * @return string Contenuto elaborato
 */
function pnrr_process_shortcodes_in_elementor($content, $widget) {
    if ($widget->get_name() === 'html' || $widget->get_name() === 'text-editor') {
        $content = do_shortcode($content);
    }
    return $content;
}

/**
 * Aggiungi stili CSS per i placeholder degli shortcode in Elementor
 */
function pnrr_add_shortcode_styles() {
    // Aggiungi stili solo nell'editor di Elementor
    if (!isset($_GET['action']) || $_GET['action'] !== 'elementor') {
        return;
    }
    
    echo '<style>
        .pnrr-placeholder {
            background-color: #f7f7f7;
            border: 1px dashed #ccc;
            padding: 5px 10px;
            display: inline-block;
            color: #666;
            font-style: italic;
        }
        .elementor-editor-active [class*="pnrr-"] {
            position: relative;
        }
        .elementor-editor-active [class*="pnrr-"]::before {
            content: "PNRR Shortcode";
            position: absolute;
            top: -20px;
            left: 0;
            background: #6d7882;
            color: #fff;
            padding: 2px 6px;
            font-size: 10px;
            border-radius: 3px;
            z-index: 999;
        }
    </style>';
}
add_action('wp_head', 'pnrr_add_shortcode_styles');
add_action('admin_head', 'pnrr_add_shortcode_styles');
