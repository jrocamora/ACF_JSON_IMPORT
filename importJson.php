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
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($url);
    $filename = basename($url);
    if (wp_mkdir_p($upload_dir['path'])) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }
    file_put_contents($file, $image_data);
    $wp_filetype = wp_check_filetype($filename, null);
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit'
    );
    $attach_id = wp_insert_attachment($attachment, $file);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    wp_update_attachment_metadata($attach_id, $attach_data);
    return $attach_id;
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
    $field = get_field_object($key, $post_id);
    $type = $field['type'];
    switch ($type) {
        case 'image':
            $attach_id = download_img_from_url($value);
            update_field($key, $attach_id, $post_id);
            break;
        default:
            update_field($key, $value, $post_id);
            break;
    }
}
/**
 * get the json file and import the data to the custom post type
 * @param mixed $path
 * @return void
 */
function importar_json_automaticament_acf_cataleg($path, $postType, $primary_keys, $titleKey, $limit = 10)
{
    writeTolog('Important contingut del fitxer ' . $path);

    $data = carregar_dades_json($path);
    if (empty($data)) {
        mostrar_missatge_error('El fitxer JSON no conté dades vàlides.');
        return;
    }

    $custom_fields = getCustomFields($postType);
    $counter = 0;

    foreach ($data as $d) {
        if ($counter >= $limit) {
            break;
        }

        $post_id = obtenir_o_crear_post($d, $postType, $primary_keys, $titleKey);
        if ($post_id && !is_wp_error($post_id)) {
            actualitzar_camps_personalitzats($post_id, $d, $custom_fields);
        }

        $counter++;
    }

    writeTolog('Importació del fitxer automàtica completada');
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
        writeTolog('Actualitzant post amb ID ' . $post_id . ' (' . $d[$titleKey] . ')');
    } else {
        $post_data = array(
            'post_title' => $d[$titleKey],
            'post_type' => $postType,
            'post_status' => 'publish',
        );
        $post_id = wp_insert_post($post_data);
        writeTolog('Creant un nou post (' . $d[$titleKey] . ')');
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
