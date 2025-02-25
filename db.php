<?php

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
        titleKey varchar(255) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";


    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
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
function saveJsonFile($json_file_path, $period, $hora_exacta, $postType, $primary_keys, $titleKey)
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
            'primary_keys' => $primary_keys_json, // Guardar les claus primàries com a JSON
            'titleKey' => $titleKey
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
