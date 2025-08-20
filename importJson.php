<?php

/**
 * Get the custom fields of the post type
 * @param string $postType The post type
 * @return array The custom fields
 */
function getCustomFields($postType)
{
    $custom_fields = array();
    if (!post_type_exists($postType)) {
        echo '<div class="notice notice-error is-dismissible">
    <p>El tipus de contingut "cataleg" no existeix.</p>
</div>';
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
 * Download the image from the url and return the attachment id
 * @param string $url The url of the image
 * @return int The attachment id
 */
function download_img_from_url($url)
{
    try{
        if (empty($url)) {
            return null;
        }

        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'] . '/cataleg'; // Ruta completa al subdirectori

        if (!wp_mkdir_p($upload_path)) {
            writeTolog("ERROR: Error al crear el directori: " . $upload_path);
            return null;
        }

        // Genera un nom de fitxer a partir de la URL
        $base_filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $url);
        $file_extension = pathinfo($url, PATHINFO_EXTENSION);
        $filename = $base_filename . '.' . $file_extension;
        $local_file_path = $upload_path . '/' . $filename;

        // Comprova si la imatge ja existeix localment amb aquest nom
        if (file_exists($local_file_path)) {
            //writeTolog("INFO: La imatge per a '$url' ja existeix localment a '$local_file_path'. No es tornarà a pujar.");
            return null; // Retornem null directament si la imatge ja existeix
        }

        //writeTolog("INFO: La imatge per a '$url' no existia '$local_file_path'. Anem a pujar.");
        // Si la imatge no existeix localment, descarrega-la i guarda-la
        $image_data = file_get_contents($url);
        file_put_contents($local_file_path, $image_data);

        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment($attachment, $local_file_path);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $local_file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        writeTolog("Imatge nova! He pujat la imatge a: " . $local_file_path);

        return $attach_id;
    } catch (Exception $e) {
        writeTolog("ERROR: S'ha produït una excepció a download_img_from_url: " . $e->getMessage(), 0);
        return null;
    }
}


/**
 * Execute the field by type
 * @param mixed $key
 * @param mixed $value
 * @param mixed $post_id
 * @return void
 */
function execute_by_type($key, $value, $post_id)
{
    try{
        $field = get_field_object($key, $post_id);
        $type = $field['type'];

        if ($type != 'image'){
            update_field($key, $value, $post_id); // this also covers the null case where the type is not yet set

            if ($type == '') { //if type was new i re-calculate the type so that I upload the image if that's the case
                $field = get_field_object($key, $post_id);
                $type = $field['type'];
            }
        }

        if ($type == 'image') {
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                $attach_id = download_img_from_url($value);
                if ($attach_id !== null) {
                    update_field($key, $attach_id, $post_id);
                    //writeTolog("INFO: s´ha guardat la imatge '$value' i actualitzat el camp '$key' del post $post_id.");

                } else {
                    //writeTolog("INFO: La imatge per a la URL '$value' ja existia localment. No s'ha actualitzat el camp '$key' del post $post_id.");
                }
            } else {
                writeTolog("WARNING: S'ha rebut un valor no vàlid per a la URL de la imatge ('$key'): " . $value);
                update_field($key, '', $post_id); // Guardem "" a la imatge
            }
        }
    } catch (Exception $e) {
        writeTolog("S'ha produït una excepció a execute_by_type per al camp '$key' del post $post_id: " . $e->getMessage(), 0);
    }
}
/**
 * get the json file and import the data to the custom post type
 * @param mixed $path
 * @return void
 */
function importar_json_automaticament_acf_cataleg($path, $postType, $primary_keys, $titleKey, $limit = null)
{
    try{
        writeTolog('INFO: Important contingut del fitxer ' . $path);

        $data = carregar_dades_json($path);
        if (empty($data)) {
            mostrar_missatge_error('El fitxer JSON no conté dades vàlides.');
            return;
        }

        $custom_fields = getCustomFields($postType);
        $counter = 0;

        foreach ($data as $d) {
            if ($limit !== null && $counter >= $limit) { // Comprova si hi ha un límit i si s'ha assolit
                break;
            }

            $post_id = obtenir_o_crear_post($d, $postType, $primary_keys, $titleKey);
            if ($post_id && !is_wp_error($post_id)) {
                actualitzar_camps_personalitzats($post_id, $d, $custom_fields);
            }

            $counter++;
        }

        writeTolog('INFO: Importació del fitxer automàtica completada');
    } catch (Exception $e) {
        writeTolog("ERROR: S'ha produït una excepció a importar_json_automaticament_acf_cataleg per al fitxer '$path': " . $e->getMessage(), 0);
    }
}

function carregar_dades_json($path)
{
    $json_data = file_get_contents($path);
    return json_decode($json_data, true);
}

function mostrar_missatge_error($missatge)
{
    echo '<div class="notice notice-error is-dismissible">
    <p>' . $missatge . '</p>
</div>';
}

function mostrar_missatge_exit($missatge)
{
    echo '<div class="notice notice-success is-dismissible">
    <p>' . $missatge . '</p>
</div>';
}

function obtenir_o_crear_post($d, $postType, $primary_keys, $titleKey)
{
    $meta_query = array_map(function ($key) use ($d) {
        $key = trim($key); // Elimina els espais en blanc al principi i al final de la clau
        return array(
            'key' => $key,
            'value' => $d[$key],
            'compare' => '='
        );
    }, $primary_keys);


    $existing_post = new WP_Query(array(
        'post_type' => $postType,
        'meta_query' => $meta_query
    ));

    if ($existing_post->have_posts()) {
        $existing_post->the_post();
        $post_id = get_the_ID();
        wp_update_post(array(
            'ID' => $post_id,
            'post_title' => $d[$titleKey],
        ));
        writeTolog('INFO: Actualitzant post amb ID ' . $post_id . ' (' . $d[$titleKey] . ')');
    } else {
        $post_data = array(
            'post_title' => $d[$titleKey],
            'post_type' => $postType,
            'post_status' => 'publish',
        );
        $post_id = wp_insert_post($post_data);
        writeTolog('Creant un nou post (' . $d[$titleKey] . ') el post_id es ' . $post_id);
    }

    wp_reset_postdata();
    return $post_id;
}

function actualitzar_camps_personalitzats($post_id, $d, $custom_fields)
{
    foreach ($d as $key => $value) {
        if (in_array($key, $custom_fields)) {
            execute_by_type($key, $value, $post_id);
        }
    }
}

