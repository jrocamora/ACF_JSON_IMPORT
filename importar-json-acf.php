<?php

/**
 * Plugin Name: Importar JSON a ACF
 * Description: Un plugin per importar dades d'un fitxer JSON a camps personalitzats ACF del tipus de contingut "cataleg".
 * Version: 1.0
 * Author: El teu Nom
 */

/**
 * function to write to log file
 * @param mixed $message The message to write to the log file
 */
function writeTolog($message)
{
    $log_file = plugin_dir_path(__FILE__) . 'log.txt';
    if (!file_exists($log_file)) {
        $file = fopen($log_file, 'w');
        fclose($file);
    }
    $current_time = date('Y-m-d H:i:s');
    $formatted_message = "[$current_time] $message" . PHP_EOL;
    file_put_contents($log_file, $formatted_message, FILE_APPEND);
}

// check if ACF is installed
if (! function_exists('acf_add_local_field_group')) {
    add_action('admin_notices', 'acf_no_installed_notice');
    function acf_no_installed_notice()
    {
?>
        <div class="notice notice-error">
            <p><?php _e('Advanced Custom Fields no està activat. Aquest plugin necessita ACF per funcionar.', 'text-domain'); ?></p>
        </div>
<?php
    }
    return;
}

// Register activion hook
register_activation_hook(__FILE__, 'crear_taula_json_files');

/**
 * Create the table json_files
 * @return void
 */
function crear_taula_json_files()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'json_files';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        file_path text NOT NULL,
        last_import datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        next_import datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        importPeriod int DEFAULT 0 NOT NULL,
        hora_exacta time ,
        postType varchar(255) NOT NULL,
        primary_keys text NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";


    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Afegir una entrada al menú d'administració
function afegir_menu_importacio_json()
{
    add_menu_page(
        'Importar JSON a ACF',   // Títol de la pàgina
        'Importar JSON',         // Títol del menú
        'manage_options',        // Capacitat necessària
        'importar-json-acf',     // Slug de la pàgina
        'mostrar_pagina_importacio_json', // Funció que renderitza el contingut de la pàgina
        'dashicons-upload',      // Icona del menú
        100                      // Posició del menú
    );
}
add_action('admin_menu', 'afegir_menu_importacio_json');

// Funció principal que mostra la pàgina d'importació
function mostrar_pagina_importacio_json()
{
    processar_formulari_importacio_json();
    mostrar_formulari_importacio_json();
}

// Processa el formulari si s'ha enviat
function processar_formulari_importacio_json()
{


    if (isset($_POST['delete'])) {
        deleteJsonFiles($_POST['file_id']);
        echo '<div class="notice notice-success is-dismissible"><p>Tots els fitxers JSON s\'han eliminat correctament.</p></div>';
    }
    if (isset($_POST['saveFile'])) {
        $json_file = $_FILES['json_file'];
        $period = $_POST['importPeriod'];
        $hora_exacta = $_POST['hora_exacta'];
        $postType = $_POST['postType'];
        $primary_keys = $_POST['primary_keys'];
        $json_file_path = plugin_dir_path(__FILE__) . $json_file['name'];
        saveJsonFile($json_file_path, $period, $hora_exacta, $postType, $primary_keys);
        echo '<div class="notice notice-success is-dismissible"><p>Fitxer JSON guardat correctament.</p></div>';
    }

    if (isset($_POST['import'])) {
        $file = getJsonFile($_POST['file_id']);
        $json_file_path = $file[0]->file_path;
        $postType = $file[0]->postType;
        $primary_keys = json_decode($file[0]->primary_keys, true);


        desar_json_automaticament_acf_cataleg($json_file_path, $postType, $primary_keys);
    }
}

// Mostra el formulari per introduir el nom del fitxer JSON
function mostrar_formulari_importacio_json()
{
    $json_files = obtenir_valors_json_files();

    echo '<div class="wrap">';
    echo '<h1>Importar JSON a ACF</h1>';
    echo '<p>Fitxers JSON actualment seleccionats:</p>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Fitxer JSON</th><th>ultima importació</th><th>proxima importació</th><th>periodicitat</th><th>Tipus de post</th><th>Claus Primaries</th><th>Accions</th></tr></thead>';
    echo '<tbody>';
    if (!empty($json_files)) {

        foreach ($json_files as $file) {
            echo '<tr>';
            echo '<td>' . esc_html($file->file_path) . '</td>';
            echo '<td>';
            echo (new DateTime($file->last_import))->format('d/m/Y H:i:s');
            echo '</td>';
            echo '<td>';
            echo (new DateTime($file->next_import))->format('d/m/Y H:i:s');
            echo '</td>';
            echo '<td>';
            echo getImportPeriod($file->importPeriod);
            echo '</td>';
            echo '<td>';
            echo $file->postType;
            echo '</td>';
            echo '<td>';
            if ($file->primary_keys == '') {
                echo 'Sense claus primàries';
            } else {
                foreach (json_decode($file->primary_keys) as $key) {
                    echo $key . ', ';
                }
            }
            echo '</td>';
            echo '<td>';
            echo '<form method="post">
            <input type="hidden" name="file_id" value="' . $file->id . '">
            <button type="submit" name="delete">Esborrar</button>
            <button type="submit" name="import">Importar</button>
        </form>';
            echo '</td>';
            echo '<tr>';
        }
    } else {
        echo '<p>No s\'ha seleccionat cap fitxer JSON.</p>';
    }
    echo '</tbody></table>';

    echo '<form method="post" action="" enctype="multipart/form-data">';

    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="json_file">Fitxer JSON</label></th>';
    echo '<td><input name="json_file" type="file" id="json_file" class="regular-text">';
    echo '<select name="importPeriod" id="importPeriod">';
    echo '<option value="0">No</option>';
    echo '<option value="1">Diari</option>';
    echo '<option value="2">Setmanal</option>';
    echo '<option value="3">Quinzenal</option>';
    echo '<option value="4">Mensual</option>';
    echo '<option value="5">Anual</option>';
    echo '<option value="6">ara</option>';
    echo '</select>';
    echo '<input type="time" name="hora_exacta" id="hora_exacta">';
    echo '<input type="text" name="postType" id="postType" placeholder="postType">';
    echo '<p> claus primàries separades per comes</p>';
    echo '<input type="text" name="primary_keys" id="primary_keys" placeholder="primary_keys">';

    echo '<p> ara son les ' . date('H:i:s') . '</p>';
    echo '<p> data actual ' . date('Y-m-d') . '</p>';
    echo '<input type="submit" name="saveFile" value="guardar json" class="button button-primary">';
    echo '</td></tr>';
    echo '</table>';

    echo '</form>';
    echo '<a href="' . plugin_dir_url(__FILE__) . 'log.txt">veure fitxer de log</a>';
    echo '</div>';
}

/**
 * Get the json files from the database
 * @return array The json files
 */
function obtenir_valors_json_files()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'json_files';
    return $wpdb->get_results("SELECT * FROM $table_name");
}
function getJsonFile($id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'json_files';
    return $wpdb->get_results("SELECT * FROM $table_name WHERE id = $id");
}

/**
 * Get the json file path
 * @param int $id The id of the json file
 * @return object The json file path
 */
function getJsonFilePath($id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'json_files';
    return $wpdb->get_results("SELECT file_path FROM $table_name WHERE id = $id");
}

/**
 * delete the json file from the database
 * @param mixed $id
 * @return void
 */
function deleteJsonFiles($id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'json_files';
    $wpdb->query("DELETE FROM $table_name WHERE id = $id");
    wp(admin_url('admin.php?page=importar-json-acf'));
    wp_redirect(admin_url('admin.php?page=importar-json-acf'));
}

/**
 * Save the json file to the database
 * @param string $json_file_path The path of the json file
 * @param int $period (0: no, 1: diari, 2: setmanal, 3: quinzenal, 4: mensual, 5: anual, 6: ara) The period of the import
 * @param string $hora_exacta (optional) The exact time to import the file
 * @return void
 */
function saveJsonFile($json_file_path, $period, $hora_exacta, $postType, $primary_keys)
{
    writeTolog('Fitxer JSON guardat: ' . $json_file_path);
    global $wpdb;
    $table_name = $wpdb->prefix . 'json_files';
    $next_import = calcularProximaImportacio(date('Y-m-d H:i:s'), $period, $hora_exacta);
    $primary_keys = explode(',', $primary_keys);
    $primary_keys_json = json_encode($primary_keys);
    $wpdb->insert(
        $table_name,
        array(
            'file_path' => $json_file_path,
            'last_import' => current_time('mysql'),
            'next_import' => $next_import,
            'importPeriod' => $period,
            'hora_exacta' => $hora_exacta,
            'postType' => $postType,
            'primary_keys' => $primary_keys_json  // Guardar les claus primàries com a JSON
        )
    );
    wp(admin_url('admin.php?page=importar-json-acf'));
    wp_redirect(admin_url('admin.php?page=importar-json-acf'));
}
/**
 * function that will be executed by the cron job and check if the json files need to be imported
 * @return void
 */

function updateImportPeriod($id, $period, $hora_exacta)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'json_files';
    $next_import = calcularProximaImportacio(date('Y-m-d H:i:s'), $period, $hora_exacta);
    $wpdb->query("UPDATE $table_name SET last_import = NOW(), next_import = '$next_import' WHERE id = $id");
}
function execucioCron()
{
    writeTolog('Cron job started');
    $now = time();

    $json_files = obtenir_valors_json_files();
    foreach ($json_files as $file) {
        if ($now > strtotime($file->next_import)) {
            writeTolog('Importació automàtica de ' . $file->file_path);
            $json_file_path = $file->file_path;
            $postType = $file->postType;
            $primary_keys = json_decode($file->primary_keys, true);
            desar_json_automaticament_acf_cataleg($json_file_path, $postType, $primary_keys);
            $next_import = calcularProximaImportacio($file->last_import, $file->importPeriod, $file->hora_exacta);
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

/**
 * Get the import period
 * @param int $period (0: no, 1: diari, 2: setmanal, 3: quinzenal, 4: mensual, 5: anual, 6: ara) The period of the import
 * @return string The import period
 */
function getImportPeriod($period)
{
    switch ($period) {
        case 1:
            return 'diària';
        case 2:
            return 'setmanal';
        case 3:
            return 'quinzenal';
        case 4:
            return 'mensual';
        case 5:
            return 'anual';
        case 6:
            return 'ara';
        case 0:
            return 'no';
        default:
            throw new Exception('Periode no reconegut');
    }
}

/**
 * Calculate the next import date
 * @param string $ultimaImportacio The last import date
 * @param int $periode (0: no, 1: diari, 2: setmanal, 3: quinzenal, 4: mensual, 5: anual, 6: ara) The period of the import
 * @param string $horaExacta (optional) The exact time to import the file
 * @return string The next import date
 */
function calcularProximaImportacio($ultimaImportacio, $periode, $horaExacta = null)
{
    $data = new DateTime($ultimaImportacio);

    switch ($periode) {
        case 1: //diaria
            $data->modify('+1 day');
            break;
        case 2: //setmanal
            $data->modify('+1 week');
            break;
        case 3: //quinzenal
            $data->modify('+15 days');
            break;
        case 4: //mensual
            $data->modify('+1 month');
            break;
        case 5: //anual
            $data->modify('+1 year');
            break;
        case 6: //ara
            if ($horaExacta) {
                $timeParts = explode(':', $horaExacta);
                if (count($timeParts) === 3) {
                    list($hour, $minute, $second) = $timeParts;
                    $data->setTime($hour, $minute, $second);
                } else {
                    // Handle the case where $horaExacta does not have exactly 3 parts
                    $data->setTime(date('H'), date('i'), date('s'));
                }
            } else {
                $data->setTime(date('H'), date('i'), date('s'));
            }
            return $data->format('Y-m-d H:i:s');
        case 0: //no
            return date('Y-m-d H:i:s', PHP_INT_MAX);
        default:
            throw new Exception('Periode no reconegut');
    }

    if ($horaExacta) {
        list($hour, $minute, $second) = explode(':', $horaExacta);
        $data->setTime($hour, $minute, $second);
    }

    return $data->format('Y-m-d H:i:s');
}



/**
 * Get the custom fields of the post type
 * @param string $postType The post type
 * @return array The custom fields
 */
function getCustomFields($postType)
{
    $custom_fields = array();
    if (!post_type_exists($postType)) {
        echo '<div class="notice notice-error is-dismissible"><p>El tipus de contingut "cataleg" no existeix.</p></div>';
        return;
    } else {
        $fieldgroups = acf_get_field_groups(array('post_type' => $postType));
        if ($fieldgroups) {
            foreach ($fieldgroups as $fieldgroup) {
                $fields = acf_get_fields($fieldgroup['key']);
                foreach ($fields as $field) {
                    $custom_fields[] = $field['name'];
                }
            }
        }
    }
    return $custom_fields;
}



/**
 * get the json file and import the data to the custom post type
 * @param mixed $path
 * @return void
 */
function desar_json_automaticament_acf_cataleg($path, $postType, $primary_keys)
{



    writeTolog('Important contingut del fitxer ' . $path);
    // Carregar el contingut del fitxer JSON
    $json_data = file_get_contents($path);
    $data = json_decode($json_data, true);

    // Comprovar si el JSON s'ha desat correctament
    if (empty($data)) {
        echo '<div class="notice notice-error is-dismissible"><p>El fitxer JSON no conté dades vàlides.</p></div>';
        return;
    }
    $custom_fields = getCustomFields($postType);

    // Recórrer les pel·lícules del JSON i crear o actualitzar el contingut
    foreach ($data as $d) {
        // Cerca un post existent amb l'any corresponent i el títol original
        $meta_query = array();

        foreach ($primary_keys as $key) {
            $meta_query[] = array(
                'key' => $key,
                'value' => $d[$key],
                'compare' => '='
            );
        }

        $existing_post = new WP_Query(array(
            'post_type' => $postType,
            'meta_query' => $meta_query
        ));

        if ($existing_post->have_posts()) {
            // Si el post ja existeix, agafem el primer (assumint que l'any i el titol original són únics)
            $existing_post->the_post();
            $post_id = get_the_ID();

            // Actualitzem el títol sempre amb title_ca
            wp_update_post(array(
                'ID'         => $post_id,
                'post_title' => $d['title_ca'],

            ));
            writeTolog('Actualitzant post amb ID ' . $post_id . ' (' . $d['title_ca'] . ')');
        } else {
            // Si no existeix un post amb aquest any, el creem
            $post_data = array(
                'post_title'    => $d['title_ca'],
                'post_type'     => $postType,
                'post_status'   => 'publish',
            );
            writeTolog('Creant un nou post (' . $d['title_ca'] . ')');
            // Inserim el post i obtenim l'ID
            $post_id = wp_insert_post($post_data);
        }

        // Si el post es crea o ja existeix, desa els camps personalitzats
        if ($post_id && !is_wp_error($post_id)) {

            foreach ($d as $key => $value) {
                if (in_array($key, $custom_fields)) {
                    update_field($key, $value, $post_id);
                }
            }
        }

        // Reset post data per evitar conflictes amb altres queries
        wp_reset_postdata();
    }
    writeTolog('Importació del fitxer automàtica completada');
    echo '<div class="notice notice-success is-dismissible"><p>Totes les dades s\'han importat o actualitzat correctament!</p></div>';
}
