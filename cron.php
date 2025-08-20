<?php
function execucioCron()
{
    writeTolog('Cron job started');
    $now = time();

    $json_files = obtenir_valors_json_files();
    foreach ($json_files as $file) {

        if ($file->next_import === '0000-00-00 00:00:00') {
            writeTolog('INFO: El fitxer "' . $file->file_path . '" està marcat per a NO ser actualitzat automàticament. Saltant.');
            continue; // Passa a la següent iteració del bucle
        }


        writeTolog('DEBUG: $file->next_import = ' . $file->next_import);
        $next_import_timestamp = strtotime($file->next_import);
        writeTolog('DEBUG: strtotime($file->next_import) = ' . var_export($next_import_timestamp, true));
        writeTolog('DEBUG: $now = ' . $now);
        writeTolog('DEBUG: Comparació (' . $now . ' > ' . var_export($next_import_timestamp, true) . ') = ' . var_export($now > $next_import_timestamp, true));

        writeTolog('INFO: El fitxer "' . $file->file_path . '" està marcat per a ser actualitzat automàticament a les ' . var_export($next_import_timestamp, true) . ', ara son les '. $now . '.');

        if ($now > strtotime($file->next_import)) {
            writeTolog('Importació automàtica de ' . $file->file_path);
            $json_file_path = $file->file_path;
            $postType = $file->postType;
            $primary_keys = json_decode($file->primary_keys, true);
            $titleKey = $file->titleKey;
            importar_json_automaticament_acf_cataleg($json_file_path, $postType, $primary_keys, $titleKey);
            writeTolog('Torno del cataleg');
            $next_import = calcularProximaImportacio($file->last_import, $file->importPeriod, $file->hora_exacta);
            writeTolog('Next Import calculat ' . $next_import);
            updateImportPeriod($file->id, $file->importPeriod, $file->hora_exacta);
            writeTolog('Propera importació: ' . $next_import);
        }
    }
}

// Registrar la tasca cron que s'executarà cada hora
if (! wp_next_scheduled('execucio_cron_hora')) {
    wp_schedule_event(time(), 'hourly', 'execucio_cron_hora');
}

// Afegir la funció que s'executarà
add_action('execucio_cron_hora', 'execucioCron');
//desectivar cron al desactivar el plugin
register_deactivation_hook(__FILE__, 'desactivar_cron');

function desactivar_cron()
{
    wp_clear_scheduled_hook('execucio_cron_hora');
}
