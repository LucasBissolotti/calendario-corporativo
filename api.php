<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

// Iniciar a sessão e verificar autenticação
iniciar_sessao();
requer_login();

// Verificar CSRF token para requisições POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Erro de validação CSRF']);
        exit;
    }
}

// Incluir as classes necessárias
require_once 'includes/tarefa.php';
require_once 'includes/usuario.php';
require_once 'includes/email_helper.php';
require_once 'includes/categoria.php';

// Instanciar a classe Tarefa
$tarefa = new Tarefa();

// Variável para armazenar a resposta
$response = ['status' => 'error', 'message' => 'Operação não especificada'];

// Processar a ação solicitada
if (isset($_GET['acao'])) {
    switch ($_GET['acao']) {
        case 'exportar_relatorio':
            // Exporta CSV com colunas: Serviço | Participantes | Início | Fim | Categoria | Status
            $inicio = isset($_GET['inicio']) ? $_GET['inicio'] : date('Y-m-01');
            $fim = isset($_GET['fim']) ? $_GET['fim'] : date('Y-m-t');
            $filtro_participantes = isset($_GET['participantes']) ? $_GET['participantes'] : '';
            $filtro_categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';
            $filtro_tipo_servico = isset($_GET['tipo_servico']) ? trim($_GET['tipo_servico']) : '';

            $tarefas = $tarefa->listar_por_periodo($inicio, $fim);

            // Filtros
            if ($filtro_participantes !== '') {
                $participantes_array = array_map('trim', explode(',', $filtro_participantes));
                $tarefas = array_filter($tarefas, function($t) use ($participantes_array) {
                    if (empty($t['participantes'])) return false;
                    $t_participantes = array_map('trim', explode(',', $t['participantes']));
                    return count(array_intersect($participantes_array, $t_participantes)) > 0;
                });
            }
            if ($filtro_categoria !== '') {
                $tarefas = array_filter($tarefas, function($t) use ($filtro_categoria) {
                    return $t['categoria'] === $filtro_categoria;
                });
            }
            if ($filtro_tipo_servico !== '') {
                $termo = $filtro_tipo_servico;
                $tarefas = array_filter($tarefas, function($t) use ($termo) {
                    $valor = isset($t['tipo_servico']) ? (string)$t['tipo_servico'] : '';
                    if ($valor === '') return false;
                    if (function_exists('mb_stripos')) {
                        return mb_stripos($valor, $termo) !== false;
                    }
                    return stripos($valor, $termo) !== false;
                });
            }

            // Ordenar por início
            usort($tarefas, function($a, $b){
                $a_dt = $a['data_inicio'] . ' ' . ($a['hora_inicio'] ?? '');
                $b_dt = $b['data_inicio'] . ' ' . ($b['hora_inicio'] ?? '');
                return strcmp($a_dt, $b_dt);
            });

            $filename = 'relatorio_tarefas_powerbi_' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            // BOM para compatibilidade com Excel
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            // Cabeçalho (formato flat, 1 linha por participante)
            fputcsv($out, ['TarefaID','Serviço','Participante','Inicio','Fim','Categoria','Status'], ';');
            foreach ($tarefas as $t) {
                $servico = isset($t['tipo_servico']) ? (string)$t['tipo_servico'] : '';
                $categoria = isset($t['categoria']) ? (string)$t['categoria'] : '';
                $status = isset($t['status']) ? (string)$t['status'] : '';
                $participantesRaw = isset($t['participantes']) ? (string)$t['participantes'] : '';
                // Início / Fim em ISO 8601 para Power BI
                $ini = $t['data_inicio'];
                if (!empty($t['hora_inicio'])) { $ini .= 'T' . $t['hora_inicio']; }
                $fimData = $t['data_fim'];
                if (!empty($t['hora_fim'])) { $fimData .= 'T' . $t['hora_fim']; }

                $tarefaId = isset($t['id']) ? (int)$t['id'] : null;

                // Explodir participantes em linhas individuais
                $participantesList = array_filter(array_map('trim', explode(',', $participantesRaw)), function($p){ return $p !== ''; });
                if (empty($participantesList)) {
                    // Linha sem participante explícito (mantém compatibilidade)
                    fputcsv($out, [$tarefaId, $servico, '', $ini, $fimData, $categoria, $status], ';');
                } else {
                    foreach ($participantesList as $p) {
                        fputcsv($out, [$tarefaId, $servico, $p, $ini, $fimData, $categoria, $status], ';');
                    }
                }
            }
            fclose($out);
            return; // evitar saída JSON padrão
            break;
        case 'listar':
            // Obter parâmetros
            $inicio = isset($_GET['inicio']) ? $_GET['inicio'] : date('Y-m-01');
            $fim = isset($_GET['fim']) ? $_GET['fim'] : date('Y-m-t');

            // Filtros opcionais
            $filtro_participantes = isset($_GET['participantes']) ? $_GET['participantes'] : '';
            $filtro_categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';
            $filtro_usuario_id = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
            $filtro_status = isset($_GET['status']) ? $_GET['status'] : '';
            $filtro_tipo_servico = isset($_GET['tipo_servico']) ? trim($_GET['tipo_servico']) : '';

            // Listar tarefas do período
            $tarefas = $tarefa->listar_por_periodo($inicio, $fim);

            // Aplicar filtro de participantes
            if ($filtro_participantes !== '') {
                $participantes_array = array_map('trim', explode(',', $filtro_participantes));
                $tarefas = array_filter($tarefas, function($t) use ($participantes_array) {
                    if (empty($t['participantes'])) return false;
                    $t_participantes = array_map('trim', explode(',', $t['participantes']));
                    // Se algum participante do filtro está na tarefa
                    return count(array_intersect($participantes_array, $t_participantes)) > 0;
                });
            }

            // Aplicar filtro de categoria
            if ($filtro_categoria !== '') {
                $tarefas = array_filter($tarefas, function($t) use ($filtro_categoria) {
                    return $t['categoria'] === $filtro_categoria;
                });
            }

            // Aplicar filtro de usuário criador
            if ($filtro_usuario_id > 0) {
                $tarefas = array_filter($tarefas, function($t) use ($filtro_usuario_id) {
                    return isset($t['usuario_id']) && (int)$t['usuario_id'] === $filtro_usuario_id;
                });
            }

            // Aplicar filtro de status (pode vir em lista separada por vírgula)
            if ($filtro_status !== '') {
                $status_list = array_filter(array_map('trim', explode(',', $filtro_status)), function($s){ return $s !== ''; });
                if (!empty($status_list)) {
                    $tarefas = array_filter($tarefas, function($t) use ($status_list) {
                        if (!isset($t['status'])) return false;
                        return in_array($t['status'], $status_list, true);
                    });
                }
            }

            // Aplicar filtro de tipo de serviço (texto parcial, case-insensitive)
            if ($filtro_tipo_servico !== '') {
                $termo = $filtro_tipo_servico;
                $tarefas = array_filter($tarefas, function($t) use ($termo) {
                    $valor = isset($t['tipo_servico']) ? (string)$t['tipo_servico'] : '';
                    if ($valor === '') return false;
                    if (function_exists('mb_stripos')) {
                        return mb_stripos($valor, $termo) !== false;
                    }
                    return stripos($valor, $termo) !== false;
                });
            }

            // Formatar para o FullCalendar
            $eventos = [];
            foreach ($tarefas as $t) {
                // Determinar se deve ser exibido como all-day: quando marcado como dia inteiro
                // ou quando não há horas definidas (evita eventos 00:00→00:00 encurtarem 1 dia visualmente)
                $sem_horas = (empty($t['hora_inicio']) && empty($t['hora_fim']));
                $isAllDayDisplay = ($t['dia_inteiro'] ? true : false) || $sem_horas;
                // Se for tarefa marcada como dias úteis, criar eventos contínuos agrupando dias úteis consecutivos
                if (isset($t['dias_uteis']) && $t['dias_uteis']) {
                    $blocoIndex = 0; // para gerar IDs únicos por bloco e evitar mesclagem pelo FullCalendar
                    $periodo_inicio = new DateTime($t['data_inicio']);
                    $periodo_fim = new DateTime($t['data_fim']);
                    if ($isAllDayDisplay) {
                        $periodo_fim->modify('+1 day');
                    }
                    $interval = new DateInterval('P1D');
                    $periodo = new DatePeriod($periodo_inicio, $interval, $periodo_fim);

                    $bloco_inicio = null;
                    $bloco_fim = null;
                    foreach ($periodo as $data) {
                        $dia_semana = (int)$data->format('N'); // 6=sábado, 7=domingo
                        if ($dia_semana < 6) { // Dias úteis
                            if ($bloco_inicio === null) {
                                $bloco_inicio = clone $data;
                            }
                            $bloco_fim = clone $data;
                        } else {
                            // Se está em um bloco de dias úteis e encontrou fim de semana, fecha o bloco
                            if ($bloco_inicio !== null && $bloco_fim !== null) {
                                // Ajustar end para incluir o último dia (FullCalendar trata end como exclusivo)
                                $endDate = clone $bloco_fim;
                                if ($isAllDayDisplay) {
                                    $endDate->modify('+1 day');
                                }
                                $tituloExibicao = $t['titulo'];
                                $evento = [
                                    'id' => $t['id'] . '-b' . $blocoIndex,
                                    'title' => $tituloExibicao,
                                    'start' => $bloco_inicio->format('Y-m-d') . (!$isAllDayDisplay && $t['hora_inicio'] ? 'T' . $t['hora_inicio'] : ''),
                                    'end' => $endDate->format('Y-m-d') . (!$isAllDayDisplay && $t['hora_fim'] ? 'T' . $t['hora_fim'] : ''),
                                    'allDay' => $isAllDayDisplay,
                                    'color' => $t['cor'],
                                    'extendedProps' => [
                                        'categoria' => $t['categoria'],
                                        'localizacao' => $t['localizacao'],
                                        'participantes' => $t['participantes'],
                                        'usuario' => !empty($t['criador_nome']) ? $t['criador_nome'] : $t['nome_usuario'],
                                        'titulo_original' => $t['titulo'],
                                        'tarefa_id' => $t['id'],
                                        'bloco_index' => $blocoIndex,
                                        'dias_uteis' => true,
                                        'status' => isset($t['status']) ? $t['status'] : null,
                                        'tipo_servico' => isset($t['tipo_servico']) ? $t['tipo_servico'] : null
                                    ]
                                ];
                                $eventos[] = $evento;
                                $blocoIndex++;
                                $bloco_inicio = null;
                                $bloco_fim = null;
                            }
                        }
                    }
                    // Se terminou em um bloco de dias úteis, fecha o bloco
                    if ($bloco_inicio !== null && $bloco_fim !== null) {
                        // Ajustar end para incluir o último dia (exclusividade do FullCalendar)
                        $endDate = clone $bloco_fim;
                        if ($isAllDayDisplay) {
                            $endDate->modify('+1 day');
                        }
                        $tituloExibicao = $t['titulo'];
                        $evento = [
                            'id' => $t['id'] . '-b' . $blocoIndex,
                            'title' => $tituloExibicao,
                            'start' => $bloco_inicio->format('Y-m-d') . (!$isAllDayDisplay && $t['hora_inicio'] ? 'T' . $t['hora_inicio'] : ''),
                            'end' => $endDate->format('Y-m-d') . (!$isAllDayDisplay && $t['hora_fim'] ? 'T' . $t['hora_fim'] : ''),
                            'allDay' => $isAllDayDisplay,
                            'color' => $t['cor'],
                            'extendedProps' => [
                                'categoria' => $t['categoria'],
                                'localizacao' => $t['localizacao'],
                                    'participantes' => $t['participantes'],
                                'usuario' => !empty($t['criador_nome']) ? $t['criador_nome'] : $t['nome_usuario'],
                                'titulo_original' => $t['titulo'],
                                'tarefa_id' => $t['id'],
                                'bloco_index' => $blocoIndex,
                                'dias_uteis' => true,
                                'status' => isset($t['status']) ? $t['status'] : null,
                                'tipo_servico' => isset($t['tipo_servico']) ? $t['tipo_servico'] : null
                            ]
                        ];
                        $eventos[] = $evento;
                    }
                } else {
                    // Para eventos de dia inteiro, precisamos adicionar um dia à data de fim para que o FullCalendar mostre corretamente
                    $data_fim = $t['data_fim'];
                    if ($isAllDayDisplay) {
                        $data_fim_obj = new DateTime($t['data_fim']);
                        $data_fim_obj->modify('+1 day');
                        $data_fim = $data_fim_obj->format('Y-m-d');
                    }
                    $tituloExibicao = $t['titulo'];
                    $evento = [
                        'id' => $t['id'],
                        'title' => $tituloExibicao,
                        'start' => $t['data_inicio'] . (!$isAllDayDisplay && $t['hora_inicio'] ? 'T' . $t['hora_inicio'] : ''),
                        'end' => $data_fim . (!$isAllDayDisplay && $t['hora_fim'] ? 'T' . $t['hora_fim'] : ''),
                        'allDay' => $isAllDayDisplay,
                        'color' => $t['cor'],
                        'extendedProps' => [
                            'categoria' => $t['categoria'],
                            'localizacao' => $t['localizacao'],
                            'participantes' => $t['participantes'],
                            'usuario' => !empty($t['criador_nome']) ? $t['criador_nome'] : $t['nome_usuario'],
                            'titulo_original' => $t['titulo'],
                            'dias_uteis' => (bool)$t['dias_uteis'],
                            'status' => isset($t['status']) ? $t['status'] : null,
                            'tipo_servico' => isset($t['tipo_servico']) ? $t['tipo_servico'] : null
                        ]
                    ];
                    $eventos[] = $evento;
                }
            }

            $response = $eventos;
            break;
            
        case 'obter':
            if (isset($_GET['id'])) {
                $id = (int)$_GET['id'];
                $resultado = $tarefa->obter_por_id($id);
                
                if ($resultado) {
                    // Obter anexos
                    $anexos = $tarefa->listar_anexos($id);
                    $resultado['anexos'] = $anexos;

                    // Dados do criador (nome e avatar) para exibir no modal
                    $criador_nome = !empty($resultado['criador_nome']) ? $resultado['criador_nome'] : $resultado['nome_usuario'];
                    $criador_avatar = null;
                    $usuario_helper = new Usuario();
                    $criador = $usuario_helper->obter_por_id($resultado['usuario_id']);
                    if ($criador && !empty($criador['avatar']) && file_exists($criador['avatar'])) {
                        $criador_avatar = $criador['avatar'];
                    }
                    $resultado['criador_exibicao_nome'] = $criador_nome;
                    $resultado['criador_avatar'] = $criador_avatar;
                    
                    $response = [
                        'status' => 'success',
                        'tarefa' => $resultado
                    ];
                } else {
                    $response = [
                        'status' => 'error',
                        'message' => 'Tarefa não encontrada'
                    ];
                }
            } else {
                $response = [
                    'status' => 'error',
                    'message' => 'ID da tarefa não especificado'
                ];
            }
            break;
            
        case 'criar':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $participantes = null;
                if (isset($_POST['participantes'])) {
                    if (is_array($_POST['participantes'])) {
                        $participantes = implode(',', array_map('sanitizar', $_POST['participantes']));
                    } else {
                        $participantes = sanitizar($_POST['participantes']);
                    }
                }
                // Resolver a cor da categoria no servidor para consistência
                $categoria_nome = sanitizar($_POST['categoria']);
                $cor_post = isset($_POST['cor']) ? sanitizar($_POST['cor']) : null;
                $cat_obj = new Categoria();
                $cat_row = $cat_obj->obter_por_nome($categoria_nome);
                $cor_final = $cat_row && !empty($cat_row['cor']) ? $cat_row['cor'] : $cor_post;

                // Processar lista de participantes
                $participantes_lista = array_filter(array_map('trim', explode(',', $participantes ?? '')));
                $participantes = implode(',', $participantes_lista);
                // Versão legível dos participantes para uso apenas no título: ", " entre nomes
                $participantes_legivel = !empty($participantes_lista) ? implode(', ', $participantes_lista) : '';

                // Componentes título automático
                $status = isset($_POST['status']) ? sanitizar($_POST['status']) : 'Provisório';
                if ($status === 'Concluído') $status = 'Confirmado';
                if ($status === 'Pendente') $status = 'Provisório';
                $tipo_servico_val = isset($_POST['tipo_servico']) ? sanitizar($_POST['tipo_servico']) : '';
                $localizacao_val = isset($_POST['localizacao']) ? sanitizar($_POST['localizacao']) : '';
                $participantes_str = $participantes_legivel ?: '';
                $componentesTitulo = array_filter([$status, $categoria_nome, $localizacao_val, $tipo_servico_val, $participantes_str], function($v){ return trim($v) !== ''; });
                $titulo_auto = implode(' - ', $componentesTitulo);
                $dados = [
                    'usuario_id' => $_SESSION['usuario_id'],
                    'criador_nome' => isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : null,
                    'titulo' => $titulo_auto,
                    'categoria' => $categoria_nome,
                    'cor' => $cor_final,
                    'data_inicio' => sanitizar($_POST['data_inicio']),
                    'data_fim' => sanitizar($_POST['data_fim']),
                    'hora_inicio' => isset($_POST['hora_inicio']) ? sanitizar($_POST['hora_inicio']) : null,
                    'hora_fim' => isset($_POST['hora_fim']) ? sanitizar($_POST['hora_fim']) : null,
                    'dia_inteiro' => isset($_POST['dia_inteiro']) ? 1 : 0,
                    'dias_uteis' => isset($_POST['dias_uteis']) ? 1 : 0,
                    'participantes' => $participantes,
                    'localizacao' => isset($_POST['localizacao']) ? sanitizar($_POST['localizacao']) : null,
                    'descricao' => isset($_POST['descricao']) ? sanitizar($_POST['descricao']) : null,
                    'checklist' => isset($_POST['checklist']) ? implode('|', array_map('sanitizar', (array)$_POST['checklist'])) : '',
                    'status' => $status,
                    'tipo_servico' => $tipo_servico_val
                ];
                
                $tarefa_id = $tarefa->criar($dados);
                
                if ($tarefa_id) {
                    // Se marcou para enviar aviso aos participantes
                    if (isset($_POST['enviar_aviso']) && $_POST['enviar_aviso'] == '1' && !empty($participantes)) {
                        // Buscar todos os usuários do sistema para obter os e-mails
                        $usuario_obj = new Usuario();
                        $usuarios_sistema = $usuario_obj->listar_todos();
                        
                        // Extrair e-mails dos participantes
                        $participantes_array = explode(',', $participantes);
                        $participantes_emails = extrair_emails_participantes($participantes_array, $usuarios_sistema);
                        
                        // Obter os dados completos da tarefa
                        $tarefa_completa = $tarefa->obter_por_id($tarefa_id);
                        
                        // Enviar e-mail para os participantes
                        if (!empty($participantes_emails)) {
                            enviar_aviso_tarefa($tarefa_completa, $participantes_emails);
                        }
                    }
                    
                    // Processar anexos, se houver
                    if (isset($_FILES['anexos']) && !empty($_FILES['anexos']['name'][0])) {
                        $total_arquivos = count($_FILES['anexos']['name']);
                        
                        for ($i = 0; $i < $total_arquivos; $i++) {
                            if ($_FILES['anexos']['error'][$i] === UPLOAD_ERR_OK) {
                                $nome = basename($_FILES['anexos']['name'][$i]);
                                $tipo = $_FILES['anexos']['type'][$i];
                                $tmp_name = $_FILES['anexos']['tmp_name'][$i];
                                
                                // Gerar nome único para evitar sobrescrever arquivos
                                $nome_unico = time() . '_' . $nome;
                                $caminho = UPLOAD_DIR . $nome_unico;
                                
                                if (move_uploaded_file($tmp_name, $caminho)) {
                                    $tarefa->adicionar_anexo($tarefa_id, $nome, $tipo, $nome_unico);
                                }
                            }
                        }
                    }
                    
                    $response = [
                        'status' => 'success',
                        'message' => 'Tarefa criada com sucesso',
                        'id' => $tarefa_id
                    ];
                } else {
                    $response = [
                        'status' => 'error',
                        'message' => 'Erro ao criar tarefa'
                    ];
                }
            }
            break;
            
        case 'atualizar':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                // Detectar se usuário atual é administrador para permitir edição de qualquer tarefa
                $is_admin = (isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin']) ? true : false;
                
                $participantes = null;
                if (isset($_POST['participantes'])) {
                    if (is_array($_POST['participantes'])) {
                        $participantes = implode(',', array_map('sanitizar', $_POST['participantes']));
                    } else {
                        $participantes = sanitizar($_POST['participantes']);
                    }
                }
                // Resolver a cor da categoria no servidor para consistência
                $categoria_nome = sanitizar($_POST['categoria']);
                $cor_post = isset($_POST['cor']) ? sanitizar($_POST['cor']) : null;
                $cat_obj = new Categoria();
                $cat_row = $cat_obj->obter_por_nome($categoria_nome);
                $cor_final = $cat_row && !empty($cat_row['cor']) ? $cat_row['cor'] : $cor_post;

                $status = isset($_POST['status']) ? sanitizar($_POST['status']) : 'Provisório';
                if ($status === 'Concluído') $status = 'Confirmado';
                if ($status === 'Pendente') $status = 'Provisório';
                $tipo_servico_val = isset($_POST['tipo_servico']) ? sanitizar($_POST['tipo_servico']) : '';
                $localizacao_val = isset($_POST['localizacao']) ? sanitizar($_POST['localizacao']) : '';
                // Processar lista de participantes
                $participantes_lista = array_filter(array_map('trim', explode(',', $participantes ?? '')));
                $participantes = implode(',', $participantes_lista);
                // Versão legível dos participantes para uso apenas no título: ", " entre nomes
                $participantes_legivel = !empty($participantes_lista) ? implode(', ', $participantes_lista) : '';
                $participantes_str = $participantes_legivel ?: '';
                $componentesTitulo = array_filter([$status, $categoria_nome, $localizacao_val, $tipo_servico_val, $participantes_str], function($v){ return trim($v) !== ''; });
                $titulo_auto = implode(' - ', $componentesTitulo);
                $dados = [
                    'usuario_id' => $_SESSION['usuario_id'], // permanece para verificação de permissão
                    'criador_nome' => null, // não alterar proprietário nem nome criador em edição
                    'titulo' => $titulo_auto,
                    'categoria' => $categoria_nome,
                    'cor' => $cor_final,
                    'data_inicio' => sanitizar($_POST['data_inicio']),
                    'data_fim' => sanitizar($_POST['data_fim']),
                    'hora_inicio' => isset($_POST['hora_inicio']) ? sanitizar($_POST['hora_inicio']) : null,
                    'hora_fim' => isset($_POST['hora_fim']) ? sanitizar($_POST['hora_fim']) : null,
                    'dia_inteiro' => isset($_POST['dia_inteiro']) ? 1 : 0,
                    'dias_uteis' => isset($_POST['dias_uteis']) ? 1 : 0,
                    'participantes' => $participantes,
                    'localizacao' => isset($_POST['localizacao']) ? sanitizar($_POST['localizacao']) : null,
                    'descricao' => isset($_POST['descricao']) ? sanitizar($_POST['descricao']) : null,
                    'checklist' => isset($_POST['checklist']) ? implode('|', array_map('sanitizar', (array)$_POST['checklist'])) : '',
                    'status' => $status,
                    'tipo_servico' => $tipo_servico_val
                ];
                
                if ($tarefa->atualizar($id, $dados, $is_admin)) {
                    // Se marcou para enviar aviso aos participantes
                    if (isset($_POST['enviar_aviso']) && $_POST['enviar_aviso'] == '1' && !empty($participantes)) {
                        // Buscar todos os usuários do sistema para obter os e-mails
                        $usuario_obj = new Usuario();
                        $usuarios_sistema = $usuario_obj->listar_todos();
                        
                        // Extrair e-mails dos participantes
                        $participantes_array = explode(',', $participantes);
                        $participantes_emails = extrair_emails_participantes($participantes_array, $usuarios_sistema);
                        
                        // Obter os dados completos da tarefa
                        $tarefa_completa = $tarefa->obter_por_id($id);
                        
                        // Enviar e-mail para os participantes
                        if (!empty($participantes_emails)) {
                            enviar_aviso_tarefa($tarefa_completa, $participantes_emails);
                        }
                    }
                    
                    // Processar anexos, se houver
                    if (isset($_FILES['anexos']) && !empty($_FILES['anexos']['name'][0])) {
                        $total_arquivos = count($_FILES['anexos']['name']);
                        
                        for ($i = 0; $i < $total_arquivos; $i++) {
                            if ($_FILES['anexos']['error'][$i] === UPLOAD_ERR_OK) {
                                $nome = basename($_FILES['anexos']['name'][$i]);
                                $tipo = $_FILES['anexos']['type'][$i];
                                $tmp_name = $_FILES['anexos']['tmp_name'][$i];
                                
                                // Gerar nome único para evitar sobrescrever arquivos
                                $nome_unico = time() . '_' . $nome;
                                $caminho = UPLOAD_DIR . $nome_unico;
                                
                                if (move_uploaded_file($tmp_name, $caminho)) {
                                    $tarefa->adicionar_anexo($id, $nome, $tipo, $nome_unico);
                                }
                            }
                        }
                    }
                    
                    $response = [
                        'status' => 'success',
                        'message' => 'Tarefa atualizada com sucesso'
                    ];
                } else {
                    $response = [
                        'status' => 'error',
                        'message' => 'Erro ao atualizar tarefa'
                    ];
                }
            }
            break;
            
        case 'excluir':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                
                // Verificar se o usuário é admin
                $is_admin = isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin'];
                
                // Obter os anexos para excluir os arquivos físicos
                $anexos = $tarefa->listar_anexos($id);
                
                // Verificar se o usuário pode excluir a tarefa (é o criador ou admin)
                $tarefa_atual = $tarefa->obter_por_id($id);
                if ($tarefa_atual && ($is_admin || $tarefa_atual['usuario_id'] == $_SESSION['usuario_id'])) {
                    if ($tarefa->excluir($id, $_SESSION['usuario_id'], $is_admin)) {
                        // Excluir os arquivos físicos dos anexos
                        foreach ($anexos as $anexo) {
                            $arquivo = UPLOAD_DIR . $anexo['caminho'];
                            if (file_exists($arquivo)) {
                                unlink($arquivo);
                            }
                        }
                        
                        $response = [
                            'status' => 'success',
                            'message' => 'Tarefa excluída com sucesso'
                        ];
                    } else {
                        $response = [
                            'status' => 'error',
                            'message' => 'Erro ao excluir tarefa'
                        ];
                    }
                } else {
                    $response = [
                        'status' => 'error',
                        'message' => 'Você não tem permissão para excluir esta tarefa'
                    ];
                }
            } else {
                $response = [
                    'status' => 'error',
                    'message' => 'ID da tarefa não fornecido'
                ];
            }
            break;
            
        case 'excluir_anexo':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['anexo_id']) && isset($_POST['tarefa_id'])) {
                $anexo_id = (int)$_POST['anexo_id'];
                $tarefa_id = (int)$_POST['tarefa_id'];
                
                // Obter informações do anexo para excluir o arquivo físico
                $anexos = $tarefa->listar_anexos($tarefa_id);
                $anexo_encontrado = null;
                
                foreach ($anexos as $anexo) {
                    if ($anexo['id'] == $anexo_id) {
                        $anexo_encontrado = $anexo;
                        break;
                    }
                }
                
                if ($anexo_encontrado && $tarefa->excluir_anexo($anexo_id, $tarefa_id)) {
                    // Excluir o arquivo físico
                    $arquivo = UPLOAD_DIR . $anexo_encontrado['caminho'];
                    if (file_exists($arquivo)) {
                        unlink($arquivo);
                    }
                    
                    $response = [
                        'status' => 'success',
                        'message' => 'Anexo excluído com sucesso'
                    ];
                } else {
                    $response = [
                        'status' => 'error',
                        'message' => 'Erro ao excluir anexo'
                    ];
                }
            }
            break;

        case 'enviar_aviso':
            // Endpoint dedicado para envio de avisos aos participantes sem bloquear o salvamento
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                $tarefa_atual = $tarefa->obter_por_id($id);

                if ($tarefa_atual) {
                    // Preparar lista de e-mails dos participantes
                    $participantes = $tarefa_atual['participantes'];
                    if (!empty($participantes)) {
                        $usuario_obj = new Usuario();
                        $usuarios_sistema = $usuario_obj->listar_todos();
                        $participantes_array = is_array($participantes) ? $participantes : explode(',', $participantes);
                        $participantes_emails = extrair_emails_participantes($participantes_array, $usuarios_sistema);

                        if (!empty($participantes_emails)) {
                            $ok = enviar_aviso_tarefa($tarefa_atual, $participantes_emails);
                            if ($ok) {
                                $response = [
                                    'status' => 'success',
                                    'message' => 'Avisos enviados com sucesso'
                                ];
                            } else {
                                $response = [
                                    'status' => 'error',
                                    'message' => 'Falha ao enviar avisos. Verifique se o PHPMailer está configurado corretamente. Caso o problema persista, entre em contato com o administrador do sistema para verificar as configurações de e-mail (SMTP).'
                                ];
                            }
                        } else {
                            $response = [
                                'status' => 'error',
                                'message' => 'Nenhum e-mail encontrado para os participantes. Certifique-se de que os participantes possuem e-mails cadastrados no sistema.'
                            ];
                        }
                    } else {
                        $response = [
                            'status' => 'error',
                            'message' => 'Tarefa não possui participantes'
                        ];
                    }
                } else {
                    $response = [
                        'status' => 'error',
                        'message' => 'Tarefa não encontrada'
                    ];
                }
            } else {
                $response = [
                    'status' => 'error',
                    'message' => 'ID da tarefa não fornecido'
                ];
            }
            break;

        default:
            break;
    }
}

// Enviar resposta JSON
header('Content-Type: application/json');
echo json_encode($response);