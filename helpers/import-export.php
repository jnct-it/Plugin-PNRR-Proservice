<?php
/**
 * Funzioni per l'importazione ed esportazione dei dati
 *
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Analizza un file CSV e restituisce un array di dati
 *
 * @param string $file_path Percorso completo del file CSV
 * @return array|WP_Error Array di dati o WP_Error in caso di problemi
 */
function pnrr_parse_csv_file($file_path) {
    // Verifica che il file esista
    if (!file_exists($file_path)) {
        return new WP_Error('file_not_found', 'Il file CSV non è stato trovato');
    }
    
    // Verifica che il file sia leggibile
    if (!is_readable($file_path)) {
        return new WP_Error('file_not_readable', 'Il file CSV non può essere letto');
    }
    
    // Apri il file in modalità lettura
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return new WP_Error('file_open_error', 'Impossibile aprire il file CSV');
    }
    
    // Leggi la prima riga (intestazioni)
    $headers = fgetcsv($handle, 0, ',');
    
    // Converti le intestazioni in minuscolo per facilitare il confronto
    $headers = array_map('strtolower', $headers);
    
    // Verifica la presenza delle colonne obbligatorie
    $required_columns = array('slug', 'title');
    $optional_columns = array('logo_url', 'footer_text', 'home_url');
    
    foreach ($required_columns as $column) {
        if (!in_array($column, $headers)) {
            fclose($handle);
            return new WP_Error(
                'missing_column',
                sprintf('Colonna obbligatoria mancante: %s', $column)
            );
        }
    }
    
    // Prepara l'array dei risultati
    $data = array();
    
    // Leggi tutte le righe e costruisci l'array dei dati
    $row_number = 1; // La prima riga (intestazione) è già stata letta
    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        $row_number++;
        
        // Verifica che la riga abbia lo stesso numero di colonne delle intestazioni
        if (count($row) !== count($headers)) {
            fclose($handle);
            return new WP_Error(
                'invalid_row',
                sprintf('La riga %d ha un numero errato di colonne', $row_number)
            );
        }
        
        // Combina intestazioni e valori
        $row_data = array_combine($headers, $row);
        
        // Verifica che slug e title non siano vuoti
        if (empty($row_data['slug']) || empty($row_data['title'])) {
            fclose($handle);
            return new WP_Error(
                'missing_required_data',
                sprintf('Dati obbligatori mancanti alla riga %d', $row_number)
            );
        }
        
        // Assicurati che tutte le colonne opzionali esistano
        foreach ($optional_columns as $column) {
            if (!isset($row_data[$column])) {
                $row_data[$column] = '';
            }
        }
        
        // Aggiungi valori predefiniti
        $row_data['enabled'] = true;
        $row_data['last_updated'] = current_time('mysql');
        
        // Aggiungi la riga all'array dei dati
        $data[] = $row_data;
    }
    
    fclose($handle);
    
    // Verifica che ci siano dati
    if (empty($data)) {
        return new WP_Error('empty_data', 'Il file CSV non contiene dati validi');
    }
    
    return $data;
}

/**
 * Converti un percorso file caricato in un percorso assoluto e verifica il tipo di file
 * 
 * @param array $file Array $_FILES dell'upload
 * @return string|WP_Error Percorso assoluto al file o errore
 */
function pnrr_validate_uploaded_csv($file) {
    // Verifica errori di caricamento
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = array(
            UPLOAD_ERR_INI_SIZE => 'Il file caricato supera la direttiva upload_max_filesize in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'Il file caricato supera la direttiva MAX_FILE_SIZE specificata nel form HTML',
            UPLOAD_ERR_PARTIAL => 'Il file è stato caricato solo parzialmente',
            UPLOAD_ERR_NO_FILE => 'Nessun file è stato caricato',
            UPLOAD_ERR_NO_TMP_DIR => 'Manca una cartella temporanea',
            UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere il file su disco',
            UPLOAD_ERR_EXTENSION => 'Un\'estensione PHP ha interrotto il caricamento del file'
        );
        
        $message = isset($error_messages[$file['error']]) 
            ? $error_messages[$file['error']] 
            : 'Errore sconosciuto durante il caricamento del file';
        
        return new WP_Error('upload_error', $message);
    }
    
    // Verifica dimensione
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB max
        return new WP_Error('file_too_large', 'Il file è troppo grande (massimo 5MB)');
    }
    
    // Verifica estensione e tipo MIME
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    
    $allowed_types = array(
        'text/csv',
        'text/plain',
        'application/csv',
        'application/excel',
        'application/vnd.ms-excel',
        'application/vnd.msexcel'
    );
    
    // Verifica MIME type
    if (!in_array($mime_type, $allowed_types)) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        // Accetta comunque file con estensione .csv anche se il MIME type non corrisponde
        if (strtolower($ext) !== 'csv') {
            return new WP_Error(
                'invalid_file_type', 
                sprintf('Tipo di file non consentito. Tipo rilevato: %s', $mime_type)
            );
        }
    }
    
    return $file['tmp_name'];
}

/**
 * Processa un file CSV e importa i dati dei cloni
 * 
 * @param string $file_path Percorso del file CSV
 * @param bool $create_pages Se true, crea anche le pagine dopo l'importazione
 * @return array|WP_Error Array con risultati o oggetto errore
 */
function pnrr_process_csv_import($file_path, $create_pages = false) {
    global $pnrr_plugin;
    
    // Verifica che l'istanza del clone manager sia disponibile
    if (!isset($pnrr_plugin['clone_manager']) || !is_object($pnrr_plugin['clone_manager'])) {
        return new WP_Error('clone_manager_not_found', 'Errore: Gestore dei cloni non trovato');
    }
    
    $clone_manager = $pnrr_plugin['clone_manager'];
    
    // Apri il file CSV con codifica UTF-8
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return new WP_Error('file_open_error', 'Impossibile aprire il file CSV');
    }
    
    // Rileva l'encoding del file e convertilo in UTF-8 se necessario
    $first_bytes = fread($handle, 3);
    rewind($handle);
    $bom = "\xEF\xBB\xBF"; // UTF-8 BOM
    $is_utf8_bom = (substr($first_bytes, 0, 3) === $bom);
    
    // Se c'è BOM, salta i primi 3 byte
    if ($is_utf8_bom) {
        fseek($handle, 3);
    }
    
    // Prova a rilevare automaticamente il delimitatore
    $first_line = fgets($handle);
    rewind($handle);
    if ($is_utf8_bom) {
        fseek($handle, 3);
    }
    
    $delimiter = ',';  // Default a virgola
    
    // Controlla se il primo rigo contiene il punto e virgola
    if (strpos($first_line, ';') !== false) {
        $delimiter = ';';
    }
    
    // Log per debug
    if (function_exists('pnrr_debug_log')) {
        pnrr_debug_log("Importazione CSV - Delimitatore rilevato: " . $delimiter . ", UTF-8 BOM: " . ($is_utf8_bom ? "Sì" : "No"));
    }
    
    // Leggi intestazioni con il delimitatore corretto
    $header = fgetcsv($handle, 0, $delimiter, '"', '"'); // Gestisce correttamente le virgolette
    if (!$header) {
        fclose($handle);
        return new WP_Error('invalid_format', 'Formato CSV non valido o intestazioni mancanti');
    }
    
    // Normalizza le intestazioni (tutto minuscolo e trim)
    $header = array_map('trim', $header);
    $header = array_map('strtolower', $header);
    
    // Log delle intestazioni trovate
    if (function_exists('pnrr_debug_log')) {
        pnrr_debug_log("Intestazioni trovate: " . print_r($header, true));
    }
    
    // Mapping aggiornato per le nuove colonne del CSV - aggiungi più varianti per essere sicuri
    $field_mapping = [
        'nome'               => 'title',
        'logo'               => 'logo_url',
        'logo (url)'         => 'logo_url',
        'url sito'           => 'home_url',
        'url'                => 'home_url',
        'indirizzo'          => 'address',
        'indirizzo (testo)'  => 'address',
        'contatti'           => 'contacts',
        'contatti (testo)'   => 'contacts',
        'altro'              => 'other_info',
        'altre'              => 'other_info',
        'altre informazioni' => 'other_info',
        'altro (testo)'      => 'other_info'
    ];
    
    // Verifica che l'intestazione obbligatoria "nome" sia presente
    $required_found = false;
    foreach ($header as $column) {
        if ($column === 'nome') {
            $required_found = true;
            break;
        }
    }
    
    if (!$required_found) {
        fclose($handle);
        return new WP_Error(
            'missing_required', 
            'Intestazione obbligatoria "Nome" non trovata. Intestazioni trovate: ' . implode(', ', $header)
        );
    }
    
    // Debug per verificare quali intestazioni sono state trovate
    if (function_exists('pnrr_debug_log')) {
        pnrr_debug_log("Importazione CSV - Intestazioni rilevate: " . print_r($header, true));
        pnrr_debug_log("Importazione CSV - Intestazioni mappate: " . print_r($field_mapping, true));
    }
    
    // Avvia l'importazione
    $clones_data = [];
    $row_number = 1; // La prima riga è l'intestazione
    
    while (($row = fgetcsv($handle, 0, $delimiter, '"', '"')) !== false) { // Gestisce correttamente le virgolette
        $row_number++;
        
        if (count($row) < count($header)) {
            // Riga incompleta, aggiungi celle vuote
            $row = array_pad($row, count($header), '');
        }
        
        // Debug per visualizzare la riga corrente
        if (function_exists('pnrr_debug_log')) {
            pnrr_debug_log("Importazione CSV - Riga $row_number: " . print_r($row, true));
        }
        
        // Costruisci l'array dei dati del clone
        $clone_data = [];
        
        // Processa ogni colonna e gestisci la codifica dei caratteri
        foreach ($header as $index => $column) {
            $column = strtolower(trim($column));
            
            if (isset($field_mapping[$column])) {
                $mapped_field = $field_mapping[$column];
                $value = isset($row[$index]) ? $row[$index] : '';
                
                // Debug più dettagliato
                if (function_exists('pnrr_debug_log')) {
                    pnrr_debug_log("Importazione CSV - Colonna '$column' => Campo '$mapped_field': " . substr($value, 0, 50) . (strlen($value) > 50 ? '...' : ''));
                }
                
                // Assicura che i dati siano in UTF-8 valido
                if (!empty($value) && !mb_check_encoding($value, 'UTF-8')) {
                    $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                }
                
                // Preserva i ritorni a capo e i caratteri speciali
                $clone_data[$mapped_field] = $value;
            }
        }
        
        // Assicura che ci sia almeno un titolo
        if (empty($clone_data['title'])) {
            continue; // Salta righe senza titolo
        }
        
        // Gestione prefisso PNRR
        $title = isset($clone_data['title']) ? sanitize_text_field($clone_data['title']) : '';
        if (!empty($title)) {
            // Se il titolo non inizia con "PNRR - ", aggiungi il prefisso
            if (substr($title, 0, 7) !== 'PNRR - ') {
                $title = 'PNRR - ' . $title;
            }
            
            // Salva anche la versione senza prefisso
            $clean_title = pnrr_remove_title_prefix($title);
            
            // Aggiorna i dati con il titolo elaborato
            $clone_data['title'] = $title;
            $clone_data['clean_title'] = $clean_title;
        }
        
        // Genera automaticamente lo slug se non presente o vuoto
        if (empty($clone_data['slug'])) {
            $clone_data['slug'] = sanitize_title($clone_data['title']);
        }
        
        // Aggiungi campi obbligatori per il salvataggio
        $clone_data['enabled'] = true;
        $clone_data['clone_uuid'] = 'pnrr_' . uniqid();
        $clone_data['last_updated'] = current_time('mysql');
        
        // Sanitizza i campi in modo sicuro preservando l'HTML valido
        if (isset($clone_data['address'])) {
            $clone_data['address'] = wp_kses_post($clone_data['address']);
        }
        if (isset($clone_data['contacts'])) {
            $clone_data['contacts'] = wp_kses_post($clone_data['contacts']);
        }
        if (isset($clone_data['other_info'])) {
            $clone_data['other_info'] = wp_kses_post($clone_data['other_info']);
        }
        
        // Rimuoviamo il campo footer_text se presente poiché non è più utilizzato
        if (isset($clone_data['footer_text'])) {
            unset($clone_data['footer_text']);
        }
        
        $clones_data[] = $clone_data;
    }
    
    fclose($handle);
    
    // Se non ci sono dati, restituisci un errore
    if (empty($clones_data)) {
        return new WP_Error('no_data', 'Nessun dato valido trovato nel CSV');
    }
    
    // Prepara i dati nel formato corretto per il plugin
    $option_name = $clone_manager->get_option_name();
    $result = update_option($option_name, $clones_data);
    
    if (!$result) {
        return new WP_Error('update_failed', 'Impossibile aggiornare i dati dei cloni');
    }
    
    // Aggiorna anche la proprietà del clone manager (ricarica i dati)
    $clone_manager->reload_data();
    
    $response = [
        'imported' => count($clones_data),
        'success' => true
    ];
    
    // Se richiesto, crea le pagine clone - passa subito i dati ai shortcode
    if ($create_pages) {
        $source_page = $clone_manager->get_master_page();
        if (is_wp_error($source_page)) {
            $response['pages_created'] = 0;
            $response['page_creation_error'] = $source_page->get_error_message();
        } else {
            $pages_created = 0;
            $errors = [];
            
            foreach ($clones_data as $index => $clone_data) {
                $result = $clone_manager->clone_single_page($source_page, $clone_data);
                
                // Salva immediatamente i meta per ogni nuovo clone
                if (!is_wp_error($result) && is_numeric($result)) {
                    update_post_meta($result, '_pnrr_title', $clone_data['title'] ?? '');
                    update_post_meta($result, '_pnrr_clean_title', $clone_data['clean_title'] ?? ''); // Salva il titolo pulito
                    update_post_meta($result, '_pnrr_logo_url', $clone_data['logo_url'] ?? '');
                    update_post_meta($result, '_pnrr_home_url', $clone_data['home_url'] ?? '');
                    update_post_meta($result, '_pnrr_address', $clone_data['address'] ?? '');
                    update_post_meta($result, '_pnrr_contacts', $clone_data['contacts'] ?? '');
                    update_post_meta($result, '_pnrr_other_info', $clone_data['other_info'] ?? '');
                }
                
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $pages_created++;
                }
            }
            
            $response['pages_created'] = $pages_created;
            if (!empty($errors)) {
                $response['page_creation_errors'] = $errors;
            }
        }
    }
    
    return $response;
}

/**
 * Esporta i dati dei cloni in formato CSV
 *
 * @return string URL del file CSV generato
 */
function pnrr_export_clones_csv() {
    global $pnrr_plugin;

    // Verifica che l'istanza del clone manager sia disponibile
    if (!isset($pnrr_plugin['clone_manager']) || !is_object($pnrr_plugin['clone_manager'])) {
        return new WP_Error('clone_manager_not_found', 'Errore: Gestore dei cloni non trovato');
    }

    $clone_manager = $pnrr_plugin['clone_manager'];
    $clones = $clone_manager->get_clone_data();

    if (empty($clones)) {
        return new WP_Error('no_clones', 'Nessun clone disponibile per l\'esportazione');
    }

    // Crea directory di esportazione
    $upload_dir = wp_upload_dir();
    $export_dir = $upload_dir['basedir'] . '/pnrr_exports';

    if (!is_dir($export_dir)) {
        wp_mkdir_p($export_dir);
    }

    // Crea file CSV
    $filename = 'pnrr-clones-export-' . date('Y-m-d-His') . '.csv';
    $filepath = $export_dir . '/' . $filename;

    // Aggiungi BOM UTF-8 all'inizio del file per supporto corretto dei caratteri
    file_put_contents($filepath, "\xEF\xBB\xBF");
    
    $csv_handle = fopen($filepath, 'a');

    // Usa lo stesso delimitatore dell'import per coerenza
    $delimiter = ';';
    
    // Scrivi intestazioni con le nuove colonne
    fputcsv($csv_handle, ['Nome', 'Logo (url)', 'Url sito', 'Indirizzo (testo)', 'Contatti (testo)', 'Altre informazioni'], $delimiter, '"');

    // Scrivi dati
    foreach ($clones as $clone) {
        // Per l'esportazione, rimuovi il prefisso "PNRR - " se presente nel titolo
        $title = isset($clone['title']) ? $clone['title'] : '';
        if (substr($title, 0, 7) === 'PNRR - ') {
            $title = substr($title, 7);
        }
        
        $row = [
            $title,
            isset($clone['logo_url']) ? $clone['logo_url'] : '',
            isset($clone['home_url']) ? $clone['home_url'] : '',
            isset($clone['address']) ? $clone['address'] : '',
            isset($clone['contacts']) ? $clone['contacts'] : '',
            isset($clone['other_info']) ? $clone['other_info'] : ''
        ];

        fputcsv($csv_handle, $row, $delimiter, '"');
    }

    fclose($csv_handle);

    return $upload_dir['baseurl'] . '/pnrr_exports/' . $filename;
}

/**
 * Prepara i dati per l'esportazione CSV
 * 
 * @param array $clone_data Array con i dati dei cloni
 * @return string Contenuto CSV pronto per il download
 */
function pnrr_prepare_csv_export($clone_data) {
    if (empty($clone_data)) {
        return '';
    }
    
    // Prepara l'output CSV
    $output = fopen('php://temp', 'r+');
    
    // Aggiungi intestazioni
    fputcsv($output, ['Nome', 'Logo (url)', 'Url sito', 'Indirizzo (testo)', 'Contatti (testo)', 'Altro (testo)']);
    
    // Aggiungi righe
    foreach ($clone_data as $clone) {
        $row = [
            isset($clone['title']) ? $clone['title'] : '',
            isset($clone['logo_url']) ? $clone['logo_url'] : '',
            isset($clone['home_url']) ? $clone['home_url'] : '',
            isset($clone['address']) ? $clone['address'] : '',
            isset($clone['contacts']) ? $clone['contacts'] : '',
            isset($clone['other_info']) ? $clone['other_info'] : ''
        ];
        fputcsv($output, $row);
    }
    
    // Ottieni il contenuto
    rewind($output);
    $csv_content = stream_get_contents($output);
    fclose($output);
    
    return $csv_content;
}
