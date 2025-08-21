<?php
function execucioCron() {
    writeTolog('Cron job started');
    
    // Defineix la mida del lot que vols processar en cada execució
    $batch_size = 15; 
    
    $json_files = obtenir_valors_json_files();
    if (empty($json_files)) {
        writeTolog('INFO: No hi ha fitxers JSON configurats per a la importació automàtica.');
        return;
    }
    
    $now = time();

    foreach ($json_files as $file) {
        // Comprova si el fitxer està marcat per a NO ser actualitzat mai
        if ($file->next_import === '0000-00-00 00:00:00') {
            writeTolog('INFO: El fitxer "' . $file->file_path . '" està marcat per a NO ser actualitzat automàticament. Saltant.');
            continue; 
        }

        $next_import_timestamp = strtotime($file->next_import);

        // Si toca importar
        if ($now > $next_import_timestamp) {
            writeTolog('Importació automàtica de ' . $file->file_path);
            
            // Genera una clau única per al marcador de l'offset per a cada fitxer
            $option_name = 'import_offset_' . md5($file->file_path);
            // Llegeix l'índex per on ha de començar l'importació o zero si es nou
            $offset = get_option($option_name, 0);

            // Aquesta és la funció que processarà només un lot
            $more_data_to_process = importar_json_lot(
                $file->file_path, 
                $file->postType, 
                json_decode($file->primary_keys, true), 
                $file->titleKey, 
                $offset, 
                $batch_size
            );

            if ($more_data_to_process) {
                // Si encara queden dades, actualitzem l'offset per a la pròxima execució
                $new_offset = $offset + $batch_size;
                update_option($option_name, $new_offset);
                writeTolog("INFO: Lot completat. S'ha programat el següent lot per a continuar des de l'índex $new_offset.");
                
                // NOTA: Si el teu cron ja s'executa cada hora, i una importació pot trigar molt,
                // seria convenient programar un esdeveniment individual aquí per continuar abans.
                
            } else {
                // Si hem acabat de processar tot el fitxer, ho indiquem
                writeTolog("INFO: Importació del fitxer '" . $file->file_path . "' completada.");
                
                // Neteja l'offset de la base de dades
                delete_option($option_name);
                
                // I programa la propera importació completa, amb la teva lògica de periodicitat
                $next_import = calcularProximaImportacio($file->last_import, $file->importPeriod, $file->hora_exacta);
                updateImportPeriod($file->id, $next_import); // Això hauria de rebre el $next_import
                writeTolog('Propera importació completa: ' . $next_import);
            }
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
