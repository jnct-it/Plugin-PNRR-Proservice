<?php
/**
 * Configurazione principale del plugin
 *
 * Questo file contiene tutte le costanti e le configurazioni del plugin
 *
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

// Definizione delle costanti del plugin
define('PNRR_VERSION', '1.1');
define('PNRR_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)) . '/');
define('PNRR_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)) . '/');
define('PNRR_OPTION_NAME', 'pnrr_plugin_options');

// Dichiarazione della variabile globale per contenere tutte le istanze principali
global $pnrr_plugin;
$pnrr_plugin = array(
    'core' => null,
    'admin' => null,
    'clone_manager' => null
);
