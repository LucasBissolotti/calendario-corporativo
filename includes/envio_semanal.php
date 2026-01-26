<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/tarefa.php';
require_once __DIR__ . '/usuario.php';

function garantir_tabela_envio_semanal(): void {
    try {
        $db = Database::getInstance()->getConnection();
        $db->exec("CREATE TABLE IF NOT EXISTS envio_semanal_destinatarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER UNIQUE NOT NULL,
            nome TEXT NOT NULL DEFAULT '',
            email TEXT NOT NULL DEFAULT '',
            created_at INTEGER DEFAULT (strftime('%s','now')),
            FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        );");

        // Garantir colunas nome/email caso a tabela já exista sem elas
        $cols = $db->query("PRAGMA table_info(envio_semanal_destinatarios)")->fetchAll(PDO::FETCH_ASSOC);
        $temNome = false; $temEmail = false;
        foreach ($cols as $c) {
            if ($c['name'] === 'nome') $temNome = true;
            if ($c['name'] === 'email') $temEmail = true;
        }
        if (!$temNome) {
            $db->exec("ALTER TABLE envio_semanal_destinatarios ADD COLUMN nome TEXT NOT NULL DEFAULT ''");
        }
        if (!$temEmail) {
            $db->exec("ALTER TABLE envio_semanal_destinatarios ADD COLUMN email TEXT NOT NULL DEFAULT ''");
        }
    } catch (Throwable $e) {
        error_log('Falha ao garantir tabela de envio semanal: ' . $e->getMessage());
    }
}

// Funções auxiliares para agendamento e envio semanal de tarefas

function periodo_semana_seguinte(): array {
    $hoje = new DateTime('today');
    $inicio = clone $hoje;
    $inicio->modify('next monday');
    $fim = clone $inicio;
    $fim->modify('+6 days');
    return [
        'inicio' => $inicio->format('Y-m-d'),
        'fim' => $fim->format('Y-m-d'),
        'inicio_br' => $inicio->format('d/m/Y'),
        'fim_br' => $fim->format('d/m/Y'),
    ];
}

function listar_destinatarios_envio_semanal(): array {
    garantir_tabela_envio_semanal();
    $db = Database::getInstance()->getConnection();
    $sql = "SELECT d.usuario_id, u.nome, u.email FROM envio_semanal_destinatarios d JOIN usuarios u ON u.id = d.usuario_id ORDER BY u.nome";
    $stmt = $db->query($sql);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function adicionar_destinatario_envio_semanal(int $usuario_id): array {
    garantir_tabela_envio_semanal();
    $db = Database::getInstance()->getConnection();

    // Verificar se já existe
    $check = $db->prepare("SELECT 1 FROM envio_semanal_destinatarios WHERE usuario_id = :usuario_id LIMIT 1");
    $check->execute([':usuario_id' => $usuario_id]);
    if ($check->fetchColumn()) {
        return ['status' => 'exists'];
    }

    // Buscar nome/email do usuário para evitar violação de NOT NULL
    $uStmt = $db->prepare("SELECT nome, email FROM usuarios WHERE id = :id LIMIT 1");
    $uStmt->execute([':id' => $usuario_id]);
    $user = $uStmt->fetch(PDO::FETCH_ASSOC);
    $nome = $user['nome'] ?? '';
    $email = $user['email'] ?? '';

    // Verificar colunas existentes para montar o insert correto
    $cols = $db->query("PRAGMA table_info(envio_semanal_destinatarios)")->fetchAll(PDO::FETCH_ASSOC);
    $temNome = false; $temEmail = false;
    foreach ($cols as $c) {
        if ($c['name'] === 'nome') $temNome = true;
        if ($c['name'] === 'email') $temEmail = true;
    }

    if ($temNome && $temEmail) {
        $stmt = $db->prepare("INSERT INTO envio_semanal_destinatarios (usuario_id, nome, email) VALUES (:usuario_id, :nome, :email)");
        $ok = $stmt->execute([':usuario_id' => $usuario_id, ':nome' => $nome, ':email' => $email]);
    } else {
        $stmt = $db->prepare("INSERT INTO envio_semanal_destinatarios (usuario_id) VALUES (:usuario_id)");
        $ok = $stmt->execute([':usuario_id' => $usuario_id]);
    }
    if ($ok && $stmt->rowCount() > 0) {
        return ['status' => 'ok'];
    }
    return ['status' => 'erro'];
}

function remover_destinatario_envio_semanal(int $usuario_id): bool {
    garantir_tabela_envio_semanal();
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("DELETE FROM envio_semanal_destinatarios WHERE usuario_id = :usuario_id");
    return $stmt->execute([':usuario_id' => $usuario_id]);
}

function marcar_envio_semanal(string $data): void {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO meta (chave, valor) VALUES ('envio_semanal_ultimo_envio', :v) ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor");
        $stmt->execute([':v' => $data]);
    } catch (Throwable $e) {
        error_log('Falha ao marcar envio semanal: ' . $e->getMessage());
    }
}

function ultimo_envio_semanal(): string {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT valor FROM meta WHERE chave = 'envio_semanal_ultimo_envio'");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val ? (string)$val : '0';
    } catch (Throwable $e) {
        error_log('Falha ao ler ultimo envio semanal: ' . $e->getMessage());
        return '0';
    }
}

function obter_tarefas_semana_seguinte(): array {
    $periodo = periodo_semana_seguinte();
    $tarefaObj = new Tarefa();
    return $tarefaObj->listar_por_periodo($periodo['inicio'], $periodo['fim']);
}

function montar_corpo_resumo_semanal(array $tarefas, array $periodo): string {
    $tituloPeriodo = $periodo['inicio_br'] . ' até ' . $periodo['fim_br'];
    $listaTarefas = '';

    if (empty($tarefas)) {
        $listaTarefas = '<p>Não há atividades programadas para a próxima semana.</p>';
    } else {
        foreach ($tarefas as $t) {
            $hora = '';
            if (!empty($t['dia_inteiro']) && $t['dia_inteiro'] == 1) {
                $hora = 'Dia inteiro';
            } elseif (!empty($t['hora_inicio'])) {
                $hora = 'às ' . $t['hora_inicio'];
                if (!empty($t['hora_fim'])) {
                    $hora .= ' até ' . $t['hora_fim'];
                }
            }
            $listaTarefas .= '<div style="border:1px solid #e9ecef;border-left:4px solid ' . htmlspecialchars($t['cor']) . ';padding:12px;margin-bottom:12px;border-radius:6px;">'
                . '<div><strong>' . htmlspecialchars($t['titulo']) . '</strong></div>'
                . '<div style="color:#555;">Data: ' . date('d/m/Y', strtotime($t['data_inicio']))
                . ($t['data_inicio'] !== $t['data_fim'] ? ' até ' . date('d/m/Y', strtotime($t['data_fim'])) : '')
                . (!empty($hora) ? ' ' . $hora : '') . '</div>'
                . (!empty($t['localizacao']) ? '<div style="color:#555;">Local: ' . htmlspecialchars($t['localizacao']) . '</div>' : '')
                . (!empty($t['status']) ? '<div style="color:#555;">Status: ' . htmlspecialchars($t['status']) . '</div>' : '')
                . (!empty($t['participantes']) ? '<div style="color:#555;">Participantes: ' . htmlspecialchars($t['participantes']) . '</div>' : '')
                . (!empty($t['descricao']) ? '<div style="color:#555;">Descrição: ' . nl2br(htmlspecialchars($t['descricao'])) . '</div>' : '')
                . '</div>';
        }
    }

    $corpo = "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'><title>Agenda semanal</title></head><body style='font-family:Arial,sans-serif;background:#f8f9fa;padding:20px;'>";
    $corpo .= "<div style='max-width:720px;margin:0 auto;background:#fff;border:1px solid #e9ecef;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.05);'>";
    $corpo .= "<div style='padding:24px 24px 8px 24px;border-bottom:1px solid #e9ecef;'><h2 style='margin:0;color:#2c3e50;'>Agenda da próxima semana</h2><p style='margin:6px 0 0 0;color:#555;'>Período: {$tituloPeriodo}</p></div>";
    $corpo .= "<div style='padding:20px;'>{$listaTarefas}</div>";
    $siteTitle = defined('SITE_TITLE') ? SITE_TITLE : 'Calendário Corporativo';
    $corpo .= "<div style='padding:16px 24px;border-top:1px solid #e9ecef;color:#6c757d;font-size:12px;'>Este é um envio automático do {$siteTitle}. Caso não deseje mais receber, peça a um administrador para removê-lo da lista de destinatários semanais.</div>";
    $corpo .= "</div></body></html>";
    return $corpo;
}

function enviar_resumo_semanal(bool $forcar = false, bool $modoTeste = false): array {
    $hoje = date('Y-m-d');
    if (!$forcar) {
        $ultimo = ultimo_envio_semanal();
        if ($ultimo === $hoje) {
            return ['status' => 'skipped', 'mensagem' => 'Envio já realizado hoje.'];
        }
    }

    $destinatarios = listar_destinatarios_envio_semanal();
    if (empty($destinatarios)) {
        return ['status' => 'erro', 'mensagem' => 'Nenhum destinatário configurado.'];
    }

    $periodo = periodo_semana_seguinte();
    $tarefas = obter_tarefas_semana_seguinte();
    $corpo = montar_corpo_resumo_semanal($tarefas, $periodo);
    $assunto = 'Agenda da próxima semana: ' . $periodo['inicio_br'] . ' a ' . $periodo['fim_br'];

    $usar_phpmailer = file_exists(__DIR__ . '/phpmailer_config.php');
    if ($usar_phpmailer) {
        require_once __DIR__ . '/phpmailer_config.php';
    }

    $enviados = 0;
    foreach ($destinatarios as $dest) {
        $ok = false;
        if ($usar_phpmailer) {
            $ok = enviar_email_phpmailer($dest['email'], $dest['nome'], $assunto, $corpo);
        } else {
            $ok = enviar_email_nativo($dest['email'], $assunto, strip_tags($corpo));
        }
        if ($ok) $enviados++;
    }

    if (!$modoTeste && $enviados > 0) {
        marcar_envio_semanal($hoje);
    }

    return ['status' => 'ok', 'enviados' => $enviados, 'total' => count($destinatarios)];
}

function obter_usuarios_para_envio(): array {
    $usuarioObj = new Usuario();
    return $usuarioObj->listar_todos();
}
