<?php
include 'db.php';
include 'cron.php';
include 'log.php';
include 'importJson.php';
/**
 * Plugin Name: JsonToCustomFields
 * Description: plugin to easily import JSON files to custom fields
 * Version: 0.1
 * Author: Cesc97
 */
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
        $titleKey = sanitize_text_field($_POST['titleKey']);
        $json_file_path = plugin_dir_path(__FILE__) . $json_file['name'];
        saveJsonFile($json_file_path, $period, $hora_exacta, $postType, $primary_keys, $titleKey);
        echo '<div class="notice notice-success is-dismissible"><p>Fitxer JSON guardat correctament.</p></div>';
    }

    if (isset($_POST['import'])) {
        $file = getJsonFile($_POST['file_id']);
        $json_file_path = $file[0]->file_path;
        $postType = $file[0]->postType;
        $primary_keys = json_decode($file[0]->primary_keys, true);
        $titleKey = sanitize_text_field($file[0]->titleKey);


        importar_json_automaticament_acf_cataleg($json_file_path, $postType, $primary_keys, $titleKey);
    }
}

// Mostra el formulari per introduir el nom del fitxer JSON
function mostrar_formulari_importacio_json()
{
    $json_files = obtenir_valors_json_files();

    echo '<div class="wrap">';
    echo '<h1>Importar JSON a ACF</h1>';
    echo '<p>Fitxers JSON actualment seleccionats:</p>';

    if (!empty($json_files)) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Fitxer JSON</th><th>Última importació</th><th>Pròxima importació</th><th>Periodicitat</th><th>Tipus de post</th><th>Claus Primàries</th><th>Títol</th><th>Accions</th></tr></thead>';
        echo '<tbody>';
        foreach ($json_files as $file) {
            echo '<tr>';
            echo '<td>' . esc_html($file->file_path) . '</td>';
            echo '<td>' . esc_html((new DateTime($file->last_import))->format('d/m/Y H:i:s')) . '</td>';
            echo '<td>' . esc_html((new DateTime($file->next_import))->format('d/m/Y H:i:s')) . '</td>';
            echo '<td>' . esc_html(getImportPeriod($file->importPeriod)) . '</td>';
            echo '<td>' . esc_html($file->postType) . '</td>';
            echo '<td>' . esc_html($file->primary_keys ? implode(', ', json_decode($file->primary_keys)) : 'Sense claus primàries') . '</td>';
            echo '<td>' . esc_html($file->titleKey ?: 'Sense clau de títol') . '</td>';
            echo '<td>';
            echo '<form method="post">';
            echo '<input type="hidden" name="file_id" value="' . esc_attr($file->id) . '">';
            echo '<button type="submit" name="delete" class="button">Esborrar</button>';
            echo '<button type="submit" name="import" class="button button-primary">Importar</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No s\'ha seleccionat cap fitxer JSON.</p>';
    }

    echo '<form method="post" action="" enctype="multipart/form-data">';
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="json_file">Fitxer JSON</label></th>';
    echo '<td><input name="json_file" type="file" id="json_file" class="regular-text"></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="importPeriod">Periodicitat</label></th>';
    echo '<td>';
    echo '<select name="importPeriod" id="importPeriod">';
    echo '<option value="0">No</option>';
    echo '<option value="1">Diari</option>';
    echo '<option value="2">Setmanal</option>';
    echo '<option value="3">Quinzenal</option>';
    echo '<option value="4">Mensual</option>';
    echo '<option value="5">Anual</option>';
    echo '<option value="6">Ara</option>';
    echo '</select>';
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="hora_exacta">Hora Exacta</label></th>';
    echo '<td><input type="time" name="hora_exacta" id="hora_exacta"></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="postType">Tipus de Post</label></th>';
    echo '<td><input type="text" name="postType" id="postType" placeholder="postType" class="regular-text"></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="primary_keys">Claus Primàries</label></th>';
    echo '<td><input type="text" name="primary_keys" id="primary_keys" placeholder="primary_keys" class="regular-text"><p>Claus primàries separades per comes</p></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="titleKey">Clau de Títol</label></th>';
    echo '<td><input type="text" name="titleKey" id="titleKey" placeholder="titleKey" class="regular-text"></td>';
    echo '</tr>';
    echo '</table>';
    echo '<p>Ara són les ' . esc_html(date('H:i:s')) . '</p>';
    echo '<p>Data actual ' . esc_html(date('Y-m-d')) . '</p>';
    echo '<input type="submit" name="saveFile" value="Guardar JSON" class="button button-primary">';
    echo '</form>';
    echo '<a href="' . esc_url(plugin_dir_url(__FILE__) . 'log.txt') . '">Veure fitxer de log</a>';
    echo '</div>';
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
        $second = $second ? $second : 0;
        $data->setTime($hour, $minute, $second);
    }

    return $data->format('Y-m-d H:i:s');
}
