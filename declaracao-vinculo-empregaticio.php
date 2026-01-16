<?php
/*
Plugin Name: Declaração de Vínculo Empregatício Digital
Description: Emissão digital de declarações de vínculo empregatício com fluxo de aprovação.
Author: AutoGPT
Version: 1.0.0
*/

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

function dve_enqueue_assets() {
    if (!is_user_logged_in()) {
        return;
    }
    if (!is_singular() && !is_page()) {
        return;
    }
    wp_enqueue_style(
        'dve-plugin-style',
        plugin_dir_url(__FILE__) . 'declaracao-vinculo-empregaticio.css',
        array(),
        '1.0.0'
    );
}
add_action('wp_enqueue_scripts', 'dve_enqueue_assets');

function dve_plugin_init() {
    dve_register_post_type();
    dve_register_statuses();
    add_shortcode('dve_solicitar_declaracao', 'dve_shortcode_solicitar_declaracao');
    add_shortcode('dve_minhas_declaracoes', 'dve_shortcode_minhas_declaracoes');
}
add_action('init', 'dve_plugin_init');

function dve_register_post_type() {
    $labels = array(
        'name' => 'Declarações',
        'singular_name' => 'Declaração',
        'add_new' => 'Adicionar nova',
        'add_new_item' => 'Nova declaração',
        'edit_item' => 'Editar declaração',
        'new_item' => 'Nova declaração',
        'view_item' => 'Ver declaração',
        'search_items' => 'Pesquisar declarações',
        'not_found' => 'Nenhuma declaração encontrada',
        'not_found_in_trash' => 'Nenhuma declaração encontrada na lixeira',
        'menu_name' => 'Declarações'
    );

    $args = array(
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'supports' => array('title', 'author'),
        'capability_type' => 'post'
    );

    register_post_type('dve_declaracao', $args);
}

function dve_register_statuses() {
    register_post_status('dve_pendente', array(
        'label' => 'Pendente',
        'public' => false,
        'internal' => true,
        'exclude_from_search' => true,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Pendente <span class="count">(%s)</span>', 'Pendentes <span class="count">(%s)</span>')
    ));

    register_post_status('dve_aprovado', array(
        'label' => 'Aprovado',
        'public' => false,
        'internal' => true,
        'exclude_from_search' => true,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Aprovado <span class="count">(%s)</span>', 'Aprovados <span class="count">(%s)</span>')
    ));

    register_post_status('dve_reprovado', array(
        'label' => 'Reprovado',
        'public' => false,
        'internal' => true,
        'exclude_from_search' => true,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Reprovado <span class="count">(%s)</span>', 'Reprovados <span class="count">(%s)</span>')
    ));
}

function dve_admin_menu() {
    add_menu_page(
        'Declarações',
        'Declarações',
        'manage_options',
        'dve_declaracoes',
        'dve_admin_page_declaracoes',
        'dashicons-media-document',
        26
    );

    add_submenu_page(
        'dve_declaracoes',
        'Configurações',
        'Configurações',
        'manage_options',
        'dve_configuracoes',
        'dve_admin_page_configuracoes'
    );
}
add_action('admin_menu', 'dve_admin_menu');

function dve_get_settings() {
    $defaults = array(
        'cnpj_empresa' => '',
        'cidade' => '',
        'responsavel_nome' => '',
        'responsavel_cargo' => '',
        'prefeitura_nome' => '',
        'texto_padrao' => "DECLARAÇÃO DE VÍNCULO EMPREGATÍCIO\n\nDeclaramos, para os devidos fins, a quem possa interessar, em especial à Prefeitura Municipal de Três Corações – MG, que o(a) Sr.(a) <NOME_COMPLETO>, Brasileiro(a), <estado_civil>, inscrito(a) no CPF sob o nº <CPF> e no RG nº <RG>, matrícula nº <MATRICULA>, mantém vínculo empregatício com a Prefeitura Municipal de Três Corações, inscrita no CNPJ sob o nº <CNPJ_DA_EMPRESA>.\n\nO(a) referido(a) empregado(a) exerce a função de <CARGO>, com jornada de trabalho de <JORNADA_SEMANAL> horas semanais.\n\nDeclaramos que as informações acima são verdadeiras, firmando a presente declaração para que produza os efeitos legais necessários.\n\n<CIDADE>, <DATA>\n\n<RESPONSÁVEL_PELA_DECLARAÇÃO>\n<CARGO_DO_RESPONSÁVEL>",
        'background_image_id' => 0,
        'responsaveis_ids' => array(),
        'assinatura_modo' => 'abaixo',
        'assinatura_x' => '',
        'assinatura_y' => ''
    );

    $settings = get_option('dve_settings', array());
    if (!is_array($settings)) {
        $settings = array();
    }

    $settings = wp_parse_args($settings, $defaults);
    if (!is_array($settings['responsaveis_ids'])) {
        $settings['responsaveis_ids'] = array();
    }

    return $settings;
}

function dve_admin_page_configuracoes() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Garante que a biblioteca de mídia do WordPress esteja disponível
    if (function_exists('wp_enqueue_media')) {
        wp_enqueue_media();
    }

    if (isset($_POST['dve_settings_nonce']) && wp_verify_nonce($_POST['dve_settings_nonce'], 'dve_save_settings')) {
        $responsaveis = array();
        if (!empty($_POST['dve_responsaveis']) && is_array($_POST['dve_responsaveis'])) {
            foreach ($_POST['dve_responsaveis'] as $id) {
                $id = intval($id);
                if ($id > 0) {
                    $responsaveis[] = $id;
                }
            }
        }

        $background_id = 0;
        if (!empty($_POST['dve_background_image_id'])) {
            $background_id = intval($_POST['dve_background_image_id']);
        }

        $texto_padrao = '';
        if (isset($_POST['dve_texto_padrao'])) {
            $texto_padrao = wp_unslash($_POST['dve_texto_padrao']);
        }

        $assinatura_modo = isset($_POST['dve_assinatura_modo']) ? sanitize_text_field($_POST['dve_assinatura_modo']) : 'abaixo';
        $assinatura_x = isset($_POST['dve_assinatura_x']) ? sanitize_text_field($_POST['dve_assinatura_x']) : '';
        $assinatura_y = isset($_POST['dve_assinatura_y']) ? sanitize_text_field($_POST['dve_assinatura_y']) : '';

        $settings = array(
            'cnpj_empresa' => sanitize_text_field($_POST['dve_cnpj_empresa'] ?? ''),
            'cidade' => sanitize_text_field($_POST['dve_cidade'] ?? ''),
            'responsavel_nome' => sanitize_text_field($_POST['dve_responsavel_nome'] ?? ''),
            'responsavel_cargo' => sanitize_text_field($_POST['dve_responsavel_cargo'] ?? ''),
            'prefeitura_nome' => sanitize_text_field($_POST['dve_prefeitura_nome'] ?? ''),
            'texto_padrao' => $texto_padrao,
            'background_image_id' => $background_id,
            'responsaveis_ids' => $responsaveis,
            'assinatura_modo' => $assinatura_modo,
            'assinatura_x' => $assinatura_x,
            'assinatura_y' => $assinatura_y
        );

        update_option('dve_settings', $settings);
        echo '<div class="updated"><p>Configurações salvas.</p></div>';
    }

    $settings = dve_get_settings();
    $users = get_users(array('fields' => array('ID', 'display_name')));
    $background_url = '';
    if ($settings['background_image_id']) {
        $background_url = wp_get_attachment_url($settings['background_image_id']);
    }

    echo '<div class="wrap">';
    echo '<h1>Configurações da Declaração</h1>';
    echo '<form method="post">';
    wp_nonce_field('dve_save_settings', 'dve_settings_nonce');

    echo '<table class="form-table">';
    echo '<tr><th><label for="dve_cnpj_empresa">CNPJ da empresa</label></th><td><input name="dve_cnpj_empresa" id="dve_cnpj_empresa" type="text" class="regular-text" value="' . esc_attr($settings['cnpj_empresa']) . '"></td></tr>';
    echo '<tr><th><label for="dve_cidade">Cidade</label></th><td><input name="dve_cidade" id="dve_cidade" type="text" class="regular-text" value="' . esc_attr($settings['cidade']) . '"></td></tr>';
    echo '<tr><th><label for="dve_prefeitura_nome">Nome da Prefeitura</label></th><td><input name="dve_prefeitura_nome" id="dve_prefeitura_nome" type="text" class="regular-text" value="' . esc_attr($settings['prefeitura_nome']) . '"></td></tr>';
    echo '<tr><th><label for="dve_responsavel_nome">Responsável pela declaração</label></th><td><input name="dve_responsavel_nome" id="dve_responsavel_nome" type="text" class="regular-text" value="' . esc_attr($settings['responsavel_nome']) . '"></td></tr>';
    echo '<tr><th><label for="dve_responsavel_cargo">Cargo do responsável</label></th><td><input name="dve_responsavel_cargo" id="dve_responsavel_cargo" type="text" class="regular-text" value="' . esc_attr($settings['responsavel_cargo']) . '"></td></tr>';

    echo '<tr><th>Responsáveis (aprovadores)</th><td>';
    echo '<select multiple name="dve_responsaveis[]" style="min-width:250px;height:120px">';
    foreach ($users as $user) {
        $selected = in_array($user->ID, $settings['responsaveis_ids'], true) ? 'selected' : '';
        echo '<option value="' . esc_attr($user->ID) . '" ' . $selected . '>' . esc_html($user->display_name) . ' (ID ' . intval($user->ID) . ')</option>';
    }
    echo '</select>';
    echo '<p class="description">Selecione os usuários responsáveis pela aprovação das declarações.</p>';
    echo '</td></tr>';

    echo '<tr><th><label for="dve_texto_padrao">Texto padrão da declaração</label></th><td>';
    echo '<textarea name="dve_texto_padrao" id="dve_texto_padrao" rows="12" class="large-text">' . esc_textarea($settings['texto_padrao']) . '</textarea>';
    echo '<p class="description">Use as variáveis: &lt;MATRICULA&gt;, &lt;NOME_COMPLETO&gt;, &lt;DATA&gt;, &lt;estado_civil&gt;, &lt;CPF&gt;, &lt;RG&gt;, &lt;CARGO&gt;, &lt;JORNADA_SEMANAL&gt;, &lt;CNPJ_DA_EMPRESA&gt;, &lt;CIDADE&gt;, &lt;RESPONSÁVEL_PELA_DECLARAÇÃO&gt;, &lt;CARGO_DO_RESPONSÁVEL&gt;.</p>';
    echo '</td></tr>';

    echo '<tr><th>Imagem de fundo (papel timbrado)</th><td>';
    echo '<input type="hidden" id="dve_background_image_id" name="dve_background_image_id" value="' . intval($settings['background_image_id']) . '">';
    echo '<button type="button" class="button" id="dve_background_image_button">Selecionar imagem</button>';
    if ($background_url) {
        echo '<div><img src="' . esc_url($background_url) . '" style="max-width:300px;margin-top:10px"></div>';
    }
    echo '<p class="description">Imagem PNG recomendada.</p>';
    echo '</td></tr>';

    $assinatura_modo = isset($settings['assinatura_modo']) ? $settings['assinatura_modo'] : 'abaixo';
    echo '<tr><th>Assinatura do solicitante</th><td>';
    echo '<label for="dve_assinatura_modo">Posição da assinatura: </label>';
    echo '<select name="dve_assinatura_modo" id="dve_assinatura_modo">';
    echo '<option value="abaixo"' . selected($assinatura_modo, 'abaixo', false) . '>Abaixo do texto</option>';
    echo '<option value="acima"' . selected($assinatura_modo, 'acima', false) . '>Acima do texto</option>';
    echo '<option value="coordenadas"' . selected($assinatura_modo, 'coordenadas', false) . '>Posição manual (mm)</option>';
    echo '</select>';
    echo '<p style="margin-top:8px;">Coordenadas manuais (usadas apenas se selecionar "Posição manual"):</p>';
    echo '<label for="dve_assinatura_x">X (mm): </label>';
    echo '<input type="text" id="dve_assinatura_x" name="dve_assinatura_x" value="' . esc_attr($settings['assinatura_x']) . '" style="width:80px;margin-right:10px;">';
    echo '<label for="dve_assinatura_y">Y (mm): </label>';
    echo '<input type="text" id="dve_assinatura_y" name="dve_assinatura_y" value="' . esc_attr($settings['assinatura_y']) . '" style="width:80px;">';
    echo '</td></tr>';

    echo '</table>';

    submit_button('Salvar configurações');
    echo '</form>';

    echo '<script type="text/javascript">';
    echo 'jQuery(function($){';
    echo '$("#dve_background_image_button").on("click",function(e){e.preventDefault();var frame=wp.media({title:"Selecionar imagem de fundo",multiple:false});frame.on("select",function(){var attachment=frame.state().get("selection").first().toJSON();$("#dve_background_image_id").val(attachment.id);});frame.open();});';
    echo '});';
    echo '</script>';

    echo '</div>';
}

function dve_is_responsavel($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    if (!$user_id) {
        return false;
    }
    $settings = dve_get_settings();
    return in_array($user_id, $settings['responsaveis_ids'], true);
}

function dve_admin_page_declaracoes() {
    if (!current_user_can('read')) {
        return;
    }

    if (isset($_POST['dve_admin_action']) && check_admin_referer('dve_admin_action', 'dve_admin_nonce')) {
        $post_id = intval($_POST['dve_post_id'] ?? 0);
        if ($post_id > 0 && get_post_type($post_id) === 'dve_declaracao') {
            if ($_POST['dve_admin_action'] === 'aprovar' && dve_is_responsavel()) {
                dve_processar_aprovacao($post_id);
            } elseif ($_POST['dve_admin_action'] === 'reprovar' && dve_is_responsavel()) {
                $motivo = sanitize_text_field($_POST['dve_motivo'] ?? '');
                update_post_meta($post_id, 'dve_status', 'reprovado');
                update_post_meta($post_id, 'dve_motivo_reprovacao', $motivo);
                wp_update_post(array('ID' => $post_id, 'post_status' => 'dve_reprovado'));
            }
        }
    }

    $args = array(
        'post_type' => 'dve_declaracao',
        'post_status' => array('dve_pendente', 'dve_aprovado', 'dve_reprovado'),
        'posts_per_page' => 50
    );

    $query = new WP_Query($args);

    echo '<div class="wrap">';
    echo '<h1>Declarações</h1>';

    if (!$query->have_posts()) {
        echo '<p>Nenhuma declaração encontrada.</p>';
        echo '</div>';
        return;
    }

    echo '<style>
        .dve-table { table-layout: auto !important; }
        .dve-col-id { width: 50px; }
        .dve-col-solicitante { width: 15%; }
        .dve-col-data { width: 120px; }
        .dve-col-status { width: 100px; }
        .dve-col-pdf { width: 80px; }
        .dve-col-acoes { width: auto; }
        
        .dve-action-box {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .dve-action-row {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f0f0f1;
            padding: 8px;
            border-radius: 4px;
        }
        .dve-action-row input[type="file"] {
            max-width: 200px;
        }
        .dve-action-row input[type="text"] {
            width: 100%;
            max-width: 200px;
        }
    </style>';

    echo '<table class="widefat striped dve-table">';
    echo '<thead><tr>';
    echo '<th class="dve-col-id">ID</th>';
    echo '<th class="dve-col-solicitante">Solicitante</th>';
    echo '<th class="dve-col-data">Data</th>';
    echo '<th class="dve-col-status">Status</th>';
    echo '<th class="dve-col-pdf">PDF</th>';
    if (dve_is_responsavel()) {
        echo '<th class="dve-col-acoes">Ações</th>';
    }
    echo '</tr></thead>';
    echo '<tbody>';

    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        $author = get_the_author();
        $date = get_the_date('d/m/Y H:i');
        $status = get_post_meta($post_id, 'dve_status', true);
        if (!$status) {
            $status = 'pendente';
        }
        $pdf_url = get_post_meta($post_id, 'dve_pdf_url', true);

        echo '<tr>';
        echo '<td>' . intval($post_id) . '</td>';
        echo '<td>' . esc_html($author) . '</td>';
        echo '<td>' . esc_html($date) . '</td>';
        echo '<td>' . esc_html(ucfirst($status)) . '</td>';
        echo '<td>';
        if ($pdf_url) {
            echo '<a href="' . esc_url($pdf_url) . '" target="_blank" class="button button-small">Abrir</a>';
        } else {
            echo '-';
        }
        echo '</td>';

        if (dve_is_responsavel()) {
            echo '<td>';
            if ($status === 'pendente') {
                echo '<div class="dve-action-box">';
                
                // Form Aprovar
                echo '<form method="post" enctype="multipart/form-data" class="dve-action-row">';
                wp_nonce_field('dve_admin_action', 'dve_admin_nonce');
                echo '<input type="hidden" name="dve_post_id" value="' . intval($post_id) . '">';
                echo '<input type="hidden" name="dve_admin_action" value="aprovar">';
                echo '<input type="file" name="dve_pdf_assinado" accept="application/pdf" required title="Selecione o PDF assinado">';
                echo '<button type="submit" class="button button-primary button-small">Aprovar</button>';
                echo '</form>';

                // Form Reprovar
                echo '<form method="post" class="dve-action-row">';
                wp_nonce_field('dve_admin_action', 'dve_admin_nonce');
                echo '<input type="hidden" name="dve_post_id" value="' . intval($post_id) . '">';
                echo '<input type="hidden" name="dve_admin_action" value="reprovar">';
                echo '<input type="text" name="dve_motivo" placeholder="Motivo da reprovação" required>';
                echo '<button type="submit" class="button button-small">Reprovar</button>';
                echo '</form>';
                
                echo '</div>';
            } else {
                echo '-';
            }
            echo '</td>';
        }

        echo '</tr>';
    }
    wp_reset_postdata();

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

function dve_processar_aprovacao($post_id) {
    if (empty($_FILES['dve_pdf_assinado']['name'])) {
        return;
    }

    $file = $_FILES['dve_pdf_assinado'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return;
    }

    $filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
    if ($filetype['ext'] !== 'pdf') {
        return;
    }

    $overrides = array('test_form' => false);
    $uploaded = wp_handle_upload($file, $overrides);
    if (!empty($uploaded['error'])) {
        return;
    }

    $url = $uploaded['url'];
    update_post_meta($post_id, 'dve_pdf_url', esc_url_raw($url));
    update_post_meta($post_id, 'dve_status', 'aprovado');
    update_post_meta($post_id, 'dve_responsavel_id', get_current_user_id());
    wp_update_post(array('ID' => $post_id, 'post_status' => 'dve_aprovado'));
}

function dve_handle_frontend_post() {
    if (empty($_POST['dve_action'])) {
        return;
    }

    if (!is_user_logged_in()) {
        return;
    }

    if ($_POST['dve_action'] === 'solicitar_declaracao') {
        if (!isset($_POST['dve_nonce']) || !wp_verify_nonce($_POST['dve_nonce'], 'dve_solicitar_declaracao')) {
            return;
        }

        $user = wp_get_current_user();
        $username = $user->user_login;
        $display_name = $user->display_name;

        $estado_civil = sanitize_text_field($_POST['dve_estado_civil'] ?? '');
        $cpf = sanitize_text_field($_POST['dve_cpf'] ?? '');
        $rg = sanitize_text_field($_POST['dve_rg'] ?? '');
        $cargo = sanitize_text_field($_POST['dve_cargo'] ?? '');
        $jornada = sanitize_text_field($_POST['dve_jornada'] ?? '');
        $assinatura = sanitize_textarea_field($_POST['dve_assinatura'] ?? '');

        $title = 'Declaração de ' . $display_name . ' em ' . current_time('d/m/Y H:i');
        $post_id = wp_insert_post(array(
            'post_type' => 'dve_declaracao',
            'post_status' => 'dve_pendente',
            'post_title' => $title,
            'post_author' => $user->ID
        ));

        if ($post_id) {
            update_post_meta($post_id, 'dve_matricula', $username);
            update_post_meta($post_id, 'dve_nome_completo', $display_name);
            update_post_meta($post_id, 'dve_data', current_time('Y-m-d'));
            update_post_meta($post_id, 'dve_estado_civil', $estado_civil);
            update_post_meta($post_id, 'dve_cpf', $cpf);
            update_post_meta($post_id, 'dve_rg', $rg);
            update_post_meta($post_id, 'dve_cargo', $cargo);
            update_post_meta($post_id, 'dve_jornada', $jornada);
            update_post_meta($post_id, 'dve_status', 'pendente');

            // Gera número sequencial anual
            $ano_atual = date('Y');
            $option_name = 'dve_seq_' . $ano_atual;
            $seq = get_option($option_name, 0);
            $seq++;
            update_option($option_name, $seq);
            update_post_meta($post_id, 'dve_numero_sequencial', $seq);
            update_post_meta($post_id, 'dve_ano_sequencial', $ano_atual);

            if ($assinatura) {
                update_post_meta($post_id, 'dve_assinatura_solicitante', $assinatura);
            }

            $pdf_url = dve_gerar_pdf_para_declaracao($post_id);
            if ($pdf_url) {
                update_post_meta($post_id, 'dve_pdf_url', esc_url_raw($pdf_url));
            }

            wp_redirect(add_query_arg('dve_sucesso', '1', wp_get_referer()));
            exit;
        }
    }
}
add_action('template_redirect', 'dve_handle_frontend_post');

function dve_build_texto_declaracao($post_id) {
    $settings = dve_get_settings();
    $user_id = get_post_field('post_author', $post_id);
    $user = get_user_by('id', $user_id);

    $matricula = get_post_meta($post_id, 'dve_matricula', true);
    $nome = get_post_meta($post_id, 'dve_nome_completo', true);
    $data = get_post_meta($post_id, 'dve_data', true);
    if (!$data) {
        $data = current_time('Y-m-d');
    }

    $estado_civil = get_post_meta($post_id, 'dve_estado_civil', true);
    $cpf = get_post_meta($post_id, 'dve_cpf', true);
    $rg = get_post_meta($post_id, 'dve_rg', true);
    $cargo = get_post_meta($post_id, 'dve_cargo', true);
    $jornada = get_post_meta($post_id, 'dve_jornada', true);

    if ($user && !$nome) {
        $nome = $user->display_name;
    }
    if ($user && !$matricula) {
        $matricula = $user->user_login;
    }

    $texto = $settings['texto_padrao'];

    $texto = str_replace('<MATRICULA>', $matricula, $texto);
    $texto = str_replace('<NOME_COMPLETO>', $nome, $texto);
    $ts = strtotime($data);
    $meses = array(
        1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
        5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
        9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'
    );
    $data_extenso = date('d', $ts) . ' de ' . $meses[(int)date('n', $ts)] . ' de ' . date('Y', $ts);
    $texto = str_replace('<DATA>', $data_extenso, $texto);
    $texto = str_replace('<estado_civil>', $estado_civil, $texto);
    $texto = str_replace('<CPF>', $cpf, $texto);
    $texto = str_replace('<RG>', $rg, $texto);
    $texto = str_replace('<CARGO>', $cargo, $texto);
    $texto = str_replace('<JORNADA_SEMANAL>', $jornada, $texto);
    $texto = str_replace('<CNPJ_DA_EMPRESA>', $settings['cnpj_empresa'], $texto);
    $texto = str_replace('<CIDADE>', $settings['cidade'], $texto);
    $texto = str_replace('<RESPONSÁVEL_PELA_DECLARAÇÃO>', $settings['responsavel_nome'], $texto);
    $texto = str_replace('<CARGO_DO_RESPONSÁVEL>', $settings['responsavel_cargo'], $texto);

    return $texto;
}

function dve_gerar_pdf_para_declaracao($post_id) {
    $upload = wp_upload_dir();
    $base_dir = trailingslashit($upload['basedir']) . 'declaracoes';
    $base_url = trailingslashit($upload['baseurl']) . 'declaracoes';

    $ano = date_i18n('Y');
    $mes = date_i18n('m');

    $dir = $base_dir . '/' . $ano . '/' . $mes;
    wp_mkdir_p($dir);

    $file = 'declaracao-' . $post_id . '.pdf';
    $filepath = $dir . '/' . $file;

    $texto = dve_build_texto_declaracao($post_id);
    $assinatura = get_post_meta($post_id, 'dve_assinatura_solicitante', true);
    $settings = dve_get_settings();
    $background_path = '';
    if (!empty($settings['background_image_id'])) {
        $background_path = get_attached_file($settings['background_image_id']);
    }

    // Se TCPDF não estiver disponível, tenta fallback (ou retorna erro)
    if (!class_exists('TCPDF')) {
        return '';
    }

    // Verifica se background é PDF para usar FPDI
    $use_fpdi = false;
    if ($background_path && strtolower(substr($background_path, -4)) === '.pdf') {
        $use_fpdi = true;
    }

    // Inicia PDF (FPDI ou TCPDF)
    if ($use_fpdi && class_exists('\setasign\Fpdi\Tcpdf\Fpdi')) {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    } else {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    }

    $pdf->SetCreator('WordPress Plugin');
    $pdf->SetAuthor('Sistema');
    $pdf->SetTitle('Declaração');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(25, 30, 25); // Margens um pouco maiores
    $pdf->SetAutoPageBreak(TRUE, 25);
    
    // Adiciona página
    $pdf->AddPage();

    // Fundo (Imagem ou PDF)
    if ($background_path && file_exists($background_path)) {
        // Se for PDF (FPDI)
        if ($use_fpdi && method_exists($pdf, 'setSourceFile')) {
            try {
                $pageCount = $pdf->setSourceFile($background_path);
                $tplIdx = $pdf->importPage(1);
                $pdf->useTemplate($tplIdx, 0, 0, 210, 297); // A4 Full Page
            } catch (Exception $e) {
                // Falha silenciosa ou log
            }
        } 
        // Se for Imagem (TCPDF padrão)
        else {
            // Desativa quebra automática temporariamente para imagem de fundo full page
            $bMargin = $pdf->getBreakMargin();
            $auto_page_break = $pdf->getAutoPageBreak();
            $pdf->SetAutoPageBreak(false, 0);
            
            // Imagem cobrindo A4 (210x297mm)
            $pdf->Image($background_path, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
            
            // Restaura configurações de página
            $pdf->SetAutoPageBreak($auto_page_break, $bMargin);
            $pdf->setPageMark();
        }
    }

    // Define fonte
    $pdf->SetFont('helvetica', '', 12);
    $assinatura_modo = isset($settings['assinatura_modo']) ? $settings['assinatura_modo'] : 'abaixo';
    $assinatura_x_conf = isset($settings['assinatura_x']) ? floatval(str_replace(',', '.', $settings['assinatura_x'])) : 0;
    $assinatura_y_conf = isset($settings['assinatura_y']) ? floatval(str_replace(',', '.', $settings['assinatura_y'])) : 0;

    $assinatura_data = null;
    if ($assinatura && preg_match('/^data:image\/(\w+);base64,/', $assinatura, $type)) {
        $data = substr($assinatura, strpos($assinatura, ',') + 1);
        $data = base64_decode($data);
        if ($data !== false) {
            $assinatura_data = $data;
        }
    }

    $html = '<div style="text-align:justify;line-height:1.5;">' . nl2br($texto) . '</div>';

    if ($assinatura_data && $assinatura_modo === 'acima') {
        $sig_width = 60;
        $page_width = $pdf->getPageWidth();
        $x = ($page_width - $sig_width) / 2;
        $pdf->SetY(40);
        $pdf->Image('@' . $assinatura_data, $x, $pdf->GetY(), $sig_width, 0, '', '', '', false, 300, '', false, false, 0);
        $pdf->SetY(90);
        $pdf->writeHTML($html, true, false, true, false, '');
    } else {
        $pdf->writeHTML($html, true, false, true, false, '');
        if ($assinatura_data) {
            if ($assinatura_modo === 'coordenadas' && $assinatura_x_conf > 0 && $assinatura_y_conf > 0) {
                $pdf->Image('@' . $assinatura_data, $assinatura_x_conf, $assinatura_y_conf, 60, 0, '', '', '', false, 300, '', false, false, 0);
            } else {
                $sig_width = 60;
                $page_width = $pdf->getPageWidth();
                $x = ($page_width - $sig_width) / 2;
                $y = $pdf->GetY() + 20;
                $pdf->Image('@' . $assinatura_data, $x, $y, $sig_width, 0, '', '', '', false, 300, '', false, false, 0);
            }
        }
    }

    // Rodapé com contador e informações
    $seq = get_post_meta($post_id, 'dve_numero_sequencial', true);
    $ano_seq = get_post_meta($post_id, 'dve_ano_sequencial', true);

    // Fallback se não tiver número gerado (ex: declarações antigas)
    if (!$seq) {
        $ano_atual = date('Y');
        $option_name = 'dve_seq_' . $ano_atual;
        $seq = get_option($option_name, 0);
        $seq++;
        update_option($option_name, $seq);
        $ano_seq = $ano_atual;
        update_post_meta($post_id, 'dve_numero_sequencial', $seq);
        update_post_meta($post_id, 'dve_ano_sequencial', $ano_seq);
    }

    $matricula = get_post_meta($post_id, 'dve_matricula', true);
    $data_db = get_post_meta($post_id, 'dve_data', true);
    $data_fmt = $data_db ? date('d/m/Y', strtotime($data_db)) : date('d/m/Y');
    $rodape_texto = sprintf('%s - Matr. %s - Declaracao n%s de %s', $data_fmt, $matricula, $seq, $ano_seq);

    $pdf->SetAutoPageBreak(false); // Desativa quebra automática para não pular página
    $pdf->SetY(-20); // Sobe para 20mm da borda inferior
    $pdf->SetFont('helvetica', '', 8); // Fonte tamanho 8 (pequeno)
    $pdf->SetTextColor(150, 150, 150); // Define cor cinza claro (RGB)
    $pdf->Cell(0, 10, $rodape_texto, 0, 0, 'R'); // Alinhado à direita

    // Salva arquivo
    $pdf->Output($filepath, 'F');

    return $base_url . '/' . $ano . '/' . $mes . '/' . $file;
}

function dve_shortcode_solicitar_declaracao() {
    if (!is_user_logged_in()) {
        return '<p>Você precisa estar autenticado para solicitar a declaração.</p>';
    }

    $user = wp_get_current_user();
    $username = $user->user_login;
    $display_name = $user->display_name;

    $out = '<div class="dve-form-wrapper">';
    $out .= '<h2>Solicitar Declaração de Vínculo Empregatício</h2>';
    if (isset($_GET['dve_sucesso']) && $_GET['dve_sucesso'] === '1') {
        $out .= '<div class="notice notice-success" style="border:1px solid #46b450;padding:10px;margin-bottom:15px;">';
        $out .= 'Sua solicitação foi registrada e enviada para análise.';
        $out .= '</div>';
    }

    $out .= '<form method="post" id="dve-form-solicitar">';
    $out .= '<p><strong>Matrícula (login)</strong><br><input type="text" value="' . esc_attr($username) . '" disabled></p>';
    $out .= '<p><strong>Nome completo</strong><br><input type="text" value="' . esc_attr($display_name) . '" disabled></p>';

    $out .= '<p><label>Estado civil<br><input type="text" name="dve_estado_civil" required></label></p>';
    $out .= '<p><label>CPF<br><input type="text" name="dve_cpf" required></label></p>';
    $out .= '<p><label>RG<br><input type="text" name="dve_rg" required></label></p>';
    $out .= '<p><label>Cargo<br><input type="text" name="dve_cargo" required></label></p>';
    $out .= '<p><label>Jornada semanal (horas)<br><input type="text" name="dve_jornada" required></label></p>';

    $out .= '<div class="dve-canvas-wrapper">';
    $out .= '<p>Assinatura do solicitante (opcional):</p>';
    $out .= '<canvas id="dve_canvas" width="400" height="150"></canvas><br>';
    $out .= '<button type="button" id="dve_clear_canvas">Limpar</button>';
    $out .= '</div>';
    $out .= '<input type="hidden" name="dve_assinatura" id="dve_assinatura">';

    $out .= '<p style="margin-top:10px;"><button type="submit">Solicitar declaração</button></p>';

    $out .= '<input type="hidden" name="dve_action" value="solicitar_declaracao">';
    $out .= wp_nonce_field('dve_solicitar_declaracao', 'dve_nonce', true, false);
    $out .= '</form>';
    $out .= '</div>';

    $out .= '<script type="text/javascript">';
    $out .= 'document.addEventListener("DOMContentLoaded",function(){';
    $out .= 'var canvas=document.getElementById("dve_canvas");var ctx=canvas.getContext("2d");var drawing=false;var last={x:0,y:0};';
    $out .= 'function getPos(e){var rect=canvas.getBoundingClientRect();if(e.touches&&e.touches.length){e=e.touches[0];}return{x:e.clientX-rect.left,y:e.clientY-rect.top};}';
    $out .= 'canvas.addEventListener("mousedown",function(e){drawing=true;last=getPos(e);});';
    $out .= 'canvas.addEventListener("mouseup",function(){drawing=false;});';
    $out .= 'canvas.addEventListener("mouseleave",function(){drawing=false;});';
    $out .= 'canvas.addEventListener("mousemove",function(e){if(!drawing)return;var pos=getPos(e);ctx.beginPath();ctx.moveTo(last.x,last.y);ctx.lineTo(pos.x,pos.y);ctx.stroke();last=pos;});';
    $out .= 'canvas.addEventListener("touchstart",function(e){e.preventDefault();drawing=true;last=getPos(e);});';
    $out .= 'canvas.addEventListener("touchend",function(e){e.preventDefault();drawing=false;});';
    $out .= 'canvas.addEventListener("touchmove",function(e){e.preventDefault();if(!drawing)return;var pos=getPos(e);ctx.beginPath();ctx.moveTo(last.x,last.y);ctx.lineTo(pos.x,pos.y);ctx.stroke();last=pos;});';
    $out .= 'document.getElementById("dve_clear_canvas").addEventListener("click",function(){ctx.clearRect(0,0,canvas.width,canvas.height);});';
    $out .= 'document.getElementById("dve-form-solicitar").addEventListener("submit",function(){document.getElementById("dve_assinatura").value=canvas.toDataURL("image/png");});';
    $out .= '});';
    $out .= '</script>';

    return $out;
}

function dve_shortcode_minhas_declaracoes() {
    if (!is_user_logged_in()) {
        return '<p>Você precisa estar autenticado para ver suas declarações.</p>';
    }

    $user_id = get_current_user_id();

    $args = array(
        'post_type' => 'dve_declaracao',
        'post_status' => array('dve_pendente', 'dve_aprovado', 'dve_reprovado'),
        'posts_per_page' => 20,
        'author' => $user_id
    );

    $query = new WP_Query($args);
    if (!$query->have_posts()) {
        return '<p>Você ainda não possui declarações.</p>';
    }

    $out = '<table class="dve-minhas-declaracoes"><thead><tr><th>Data</th><th>Status</th><th>Documento</th><th>Motivo (se reprovado)</th></tr></thead><tbody>';
    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        $date = get_the_date('d/m/Y H:i');
        $status = get_post_meta($post_id, 'dve_status', true);
        if (!$status) {
            $status = 'pendente';
        }
        $pdf_url = get_post_meta($post_id, 'dve_pdf_url', true);
        $motivo = get_post_meta($post_id, 'dve_motivo_reprovacao', true);

        $out .= '<tr>';
        $out .= '<td>' . esc_html($date) . '</td>';
        $status_class = 'dve-status-' . esc_attr($status);
        $out .= '<td class="' . $status_class . '">' . esc_html(ucfirst($status)) . '</td>';
        $out .= '<td>';
        if ($pdf_url) {
            $out .= '<a href="' . esc_url($pdf_url) . '" target="_blank">Abrir</a>';
        } else {
            $out .= '-';
        }
        $out .= '</td>';
        $out .= '<td>' . ($motivo ? esc_html($motivo) : '-') . '</td>';
        $out .= '</tr>';
    }
    wp_reset_postdata();
    $out .= '</tbody></table>';

    return $out;
}
