<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.html");
    exit;
}

require_once 'conexao.php';
$idUsuarioLogado = $_SESSION['user_id'];
$mensagem_sucesso = '';
$mensagem_erro = '';

// --- LÓGICA DE PROCESSAMENTO DO FORMULÁRIO (QUANDO ENVIADO VIA POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Coleta dos dados do formulário
    $id_atribuicao = isset($_POST['id_atribuicao']) ? (int)$_POST['id_atribuicao'] : 0;
    $id_tarefa_existente = isset($_POST['id_tarefa_existente']) ? (int)$_POST['id_tarefa_existente'] : 0;
    $nome_tarefa_nova = trim($_POST['nome_tarefa_nova'] ?? '');
    $descricao_nova = trim($_POST['descricao_nova'] ?? '');
    $status = $_POST['status'] ?? 'A Fazer'; // Adicionado campo status
    $data_prazo = $_POST['data_prazo'] ?? '';
    $usuarios_atribuidos = $_POST['usuario_atribuido'] ?? []; // Agora vem do select multiple

    if ($id_atribuicao <= 0 || empty($data_prazo)) {
        $mensagem_erro = "Erro: Dados inválidos.";
    } else {
        $conexao->begin_transaction();
        try {
            // Se o nome da tarefa personalizada estiver vazio, busca o nome da tarefa padrão
            if (empty($nome_tarefa_nova) && $id_tarefa_existente > 0) {
                 $stmt_nome = $conexao->prepare("SELECT NomeTarefa FROM tarefas WHERE ID_Tarefa = ?");
                 $stmt_nome->bind_param("i", $id_tarefa_existente);
                 $stmt_nome->execute();
                 $nome_tarefa_nova = $stmt_nome->get_result()->fetch_assoc()['NomeTarefa'] ?? 'Tarefa sem nome';
                 $stmt_nome->close();
            }

            // ATUALIZA OS DADOS PRINCIPAIS DA TAREFA
            $sql_update = "UPDATE projetos_tarefas_atribuidas SET ID_Tarefa = ?, NomeTarefaPersonalizado = ?, DescricaoPersonalizada = ?, Status = ?, DataPrazo = ? WHERE ID_Atribuicao = ?";
            $stmt_update = $conexao->prepare($sql_update);
            $stmt_update->bind_param("issssi", $id_tarefa_existente, $nome_tarefa_nova, $descricao_nova, $status, $data_prazo, $id_atribuicao);
            $stmt_update->execute();
            $stmt_update->close();

            // ATUALIZA OS USUÁRIOS ATRIBUÍDOS
            $stmt_delete = $conexao->prepare("DELETE FROM tarefa_atribuida_usuarios WHERE ID_Atribuicao = ?");
            $stmt_delete->bind_param("i", $id_atribuicao);
            $stmt_delete->execute();
            $stmt_delete->close();

            if (!empty($usuarios_atribuidos)) {
                $sql_insert_users = "INSERT INTO tarefa_atribuida_usuarios (ID_Atribuicao, ID_Usuario) VALUES (?, ?)";
                $stmt_insert_users = $conexao->prepare($sql_insert_users);
                foreach ($usuarios_atribuidos as $id_usuario) {
                    $id_participante = (int)$id_usuario;
                    $stmt_insert_users->bind_param("ii", $id_atribuicao, $id_participante);
                    $stmt_insert_users->execute();
                }
                $stmt_insert_users->close();
            }

            // CARIMBO DE MODIFICAÇÃO
            $stmt_get_id = $conexao->prepare("SELECT ID_Projeto FROM projetos_tarefas_atribuidas WHERE ID_Atribuicao = ?");
            $stmt_get_id->bind_param("i", $id_atribuicao);
            $stmt_get_id->execute();
            $idProjetoDaTarefa = $stmt_get_id->get_result()->fetch_assoc()['ID_Projeto'];
            $stmt_get_id->close();
            
            if($idProjetoDaTarefa) {
                $sql_log = "UPDATE projetos SET ID_UltimoUsuarioModificador = ?, DataHoraUltimaModificacao = NOW() WHERE ID_Projeto = ?";
                $stmt_log = $conexao->prepare($sql_log);
                $stmt_log->bind_param("ii", $idUsuarioLogado, $idProjetoDaTarefa);
                $stmt_log->execute();
                $stmt_log->close();
            }

            $conexao->commit();
            $_SESSION['mensagem_sucesso'] = "Tarefa atualizada com sucesso!";
            header("Location: index.php");
            exit;

        } catch (Exception $e) {
            $conexao->rollback();
            $mensagem_erro = "Erro ao salvar as alterações: " . $e->getMessage();
        }
    }
}

// --- LÓGICA PARA CARREGAR DADOS PARA EXIBIR O FORMULÁRIO (GET) ---
$id_atribuicao = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_atribuicao <= 0) {
    die("Erro: ID da tarefa inválido.");
}

try {
    // Busca os detalhes da tarefa que será editada
    $stmt_tarefa = $conexao->prepare(
        "SELECT pta.*, p.NomeProjeto FROM projetos_tarefas_atribuidas pta
         JOIN projetos p ON pta.ID_Projeto = p.ID_Projeto
         WHERE pta.ID_Atribuicao = ?"
    );
    $stmt_tarefa->bind_param("i", $id_atribuicao);
    $stmt_tarefa->execute();
    $result = $stmt_tarefa->get_result();
    if (!($tarefa_atual = $result->fetch_assoc())) {
        die("Tarefa não encontrada.");
    }
    $stmt_tarefa->close();
    
    $idProjeto = $tarefa_atual['ID_Projeto'];
    $nomeProjeto = htmlspecialchars($tarefa_atual['NomeProjeto']);

    // Busca todas as tarefas padrão para o dropdown
    $tarefas_predefinidas = [];
    $result_tarefas = $conexao->query("SELECT ID_Tarefa, NomeTarefa FROM tarefas ORDER BY NomeTarefa ASC");
    while ($tarefa = $result_tarefas->fetch_assoc()) {
        $tarefas_predefinidas[] = $tarefa;
    }

    // Busca participantes DO PROJETO
    $participantes = [];
    $sql_participantes = "SELECT u.ID_Usuario, u.Nome FROM usuarios u 
                          JOIN projetos_usuarios pu ON u.ID_Usuario = pu.ID_Usuario WHERE pu.ID_Projeto = ?
                          UNION 
                          SELECT u.ID_Usuario, u.Nome FROM usuarios u
                          JOIN projetos p ON u.ID_Usuario = p.ID_Usuario_Criador WHERE p.ID_Projeto = ?";
    $stmt_participantes = $conexao->prepare($sql_participantes);
    $stmt_participantes->bind_param("ii", $idProjeto, $idProjeto);
    $stmt_participantes->execute();
    $result_participantes = $stmt_participantes->get_result();
    while ($p = $result_participantes->fetch_assoc()) {
        $participantes[] = $p;
    }
    $stmt_participantes->close();

    // Busca usuários ATUALMENTE atribuídos a esta tarefa
    $usuarios_atribuidos_ids = [];
    $stmt_atribuidos = $conexao->prepare("SELECT ID_Usuario FROM tarefa_atribuida_usuarios WHERE ID_Atribuicao = ?");
    $stmt_atribuidos->bind_param("i", $id_atribuicao);
    $stmt_atribuidos->execute();
    $result_atribuidos = $stmt_atribuidos->get_result();
    while ($row = $result_atribuidos->fetch_assoc()) {
        $usuarios_atribuidos_ids[] = $row['ID_Usuario'];
    }
    $stmt_atribuidos->close();

} catch (Exception $e) {
    $mensagem_erro = "Erro ao carregar dados da página: " . $e->getMessage();
    $tarefa_atual = []; $tarefas_predefinidas = []; $participantes = []; $usuarios_atribuidos_ids = [];
    $nomeProjeto = "Erro";
}
$conexao->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Editar Tarefa - <?php echo $nomeProjeto; ?></title>
    <style>
        /* Estilo copiado diretamente de 'nova-tarefa.php' para garantir consistência */
        :root {
            --primary-color: #D60059; /* Cor vermelha do cabeçalho */
            --secundary-color: #B3004A; /* Cor do menu principal */
            --text-color: white;
            --dark-text-color: black; /* Cor para texto em fundos claros */
            --icon-color: white;
            --light-background-color: white;
            --gray-background-color: #d8d8d8;
            --dark-background-color: black;
        }
        
        body { 
            font-family: Arial, sans-serif; 
            background-color: var(--gray-background-color); 
            margin: 0; 
            padding: 0; 
        }

        .form-container { 
            max-width: 600px; 
            margin: 40px auto; 
            padding: 30px; 
            background-color: var(--light-background-color); 
            border-radius: 8px; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
        }

        .form-container h1 { 
            color: var(--primary-color); 
            text-align: center; 
            margin-bottom: 10px; 
            font-size: 1.8em; 
        }

        .form-container .project-name { 
            color: var(--dark-text-color); 
            text-align: center; 
            font-size: 1.1em; 
            margin-bottom: 30px; 
        }

        .form-group { 
            margin-bottom: 20px; 
        }

        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            color: var(--dark-text-color); 
            font-weight: bold; 
        }

        .form-group input[type="text"], .form-group input[type="date"], .form-group select, .form-group textarea { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid var(--gray-background-color); 
            border-radius: 5px; 
            box-sizing: border-box; 
            font-size: 1em; 
            transition: border-color 0.2s; 
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { 
            border-color: var(--dark-background-color); 
            outline: none; 
        }

        .form-group textarea { 
            min-height: 100px; 
            resize: vertical; 
        }

        .btn-submit { 
            width: 100%; 
            padding: 15px; 
            background-color: var(--primary-color); 
            color: var(--text-color); 
            border: none; border-radius: 5px; 
            cursor: pointer; 
            font-size: 1.1em; 
            font-weight: bold; 
            transition: background-color 0.2s; 
        }
        
        .btn-submit:hover { 
            background-color: var(--secundary-color); 
        }

        .message { 
            padding: 15px; 
            margin-bottom: 20px; 
            border-radius: 5px; 
            text-align: center; 
        }

        .success { 
            background-color: var(--light-background-color); 
            color: #155724; 
            border: 1px solid var(--gray-background-color); 
        }

        .error { 
            background-color: var(--light-background-color); 
            color: red; 
            border: 1px solid var(--gray-background-color); 
        }

        .back-link { 
            display: block; 
            text-align: center; 
            margin-top: 20px; 
            color: var(--dark-text-color); 
            text-decoration: none; 
        }

        .divider { 
            text-align: center; 
            font-size: 0.9em; 
            color: var(--gray-background-color); 
            margin: 20px 0; 
            font-weight: bold; 
        }

    </style>
</head>
<body>
    <div class="form-container">
        <h1>Editar Tarefa</h1>
        <p class="project-name">No Projeto: <?php echo $nomeProjeto; ?></p>

        <?php if ($mensagem_erro): ?>
            <div class="message error"><?php echo htmlspecialchars($mensagem_erro); ?></div>
        <?php endif; ?>

        <form action="editar-tarefa.php?id=<?php echo $id_atribuicao; ?>" method="post">
            <input type="hidden" name="id_atribuicao" value="<?php echo $id_atribuicao; ?>">

            <div class="form-group">
                <label for="id_tarefa_existente">Tarefa Padrão:</label>
                <select id="id_tarefa_existente" name="id_tarefa_existente">
                    <option value="0">-- Tarefa 100% personalizada --</option>
                    <?php foreach ($tarefas_predefinidas as $tarefa): ?>
                        <option value="<?php echo $tarefa['ID_Tarefa']; ?>" 
                            <?php echo ($tarefa['ID_Tarefa'] == $tarefa_atual['ID_Tarefa']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tarefa['NomeTarefa']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <p class="divider">- Personalize os Detalhes -</p>

            <div class="form-group">
                <label for="nome_tarefa_nova">Nome da Tarefa:</label>
                <input type="text" id="nome_tarefa_nova" name="nome_tarefa_nova" 
                       value="<?php echo htmlspecialchars($tarefa_atual['NomeTarefaPersonalizado'] ?? ''); ?>"
                       placeholder="Se deixar em branco, usa o nome da tarefa padrão">
            </div>

            <div class="form-group">
                <label for="descricao_nova">Descrição da Tarefa:</label>
                <textarea id="descricao_nova" name="descricao_nova" 
                          placeholder="Adicione detalhes específicos para esta tarefa"><?php echo htmlspecialchars($tarefa_atual['DescricaoPersonalizada'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="status">Status da Tarefa:</label>
                <select id="status" name="status">
                    <option value="A Fazer" <?php echo ($tarefa_atual['Status'] == 'A Fazer') ? 'selected' : ''; ?>>A Fazer</option>
                    <option value="Em Andamento" <?php echo ($tarefa_atual['Status'] == 'Em Andamento') ? 'selected' : ''; ?>>Em Andamento</option>
                    <option value="Concluído" <?php echo ($tarefa_atual['Status'] == 'Concluído') ? 'selected' : ''; ?>>Concluído</option>
                </select>
            </div>

            <div class="form-group">
                <label for="data_prazo">Prazo de Conclusão:</label>
                <input type="date" id="data_prazo" name="data_prazo" 
                       value="<?php echo htmlspecialchars($tarefa_atual['DataPrazo'] ?? ''); ?>" required>
            </div>

            <div id="opcoes_recorrencia" style="display: none; border-left: 3px solid #ccc; padding-left: 15px; margin-bottom: 20px;">
                 <div class="form-group"><label for="frequencia">Repetir a cada:</label><select id="frequencia" name="frequencia" disabled><option>Semana</option></select></div>
                 <div class="form-group"><label for="data_fim_recorrencia">Repetir até:</label><input type="date" id="data_fim_recorrencia" name="data_fim_recorrencia" disabled></div>
            </div>

            <div class="form-group">
                <label for="usuario_atribuido">Atribuir para (segure Ctrl/Cmd para selecionar vários):</label>
                <select id="usuario_atribuido" name="usuario_atribuido[]" multiple style="height: 120px;">
                    <?php foreach ($participantes as $p): ?>
                        <option value="<?php echo $p['ID_Usuario']; ?>"
                            <?php echo in_array($p['ID_Usuario'], $usuarios_atribuidos_ids) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['Nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-submit">Salvar Alterações</button>
        </form>
        <a href="index.php" class="back-link">Cancelar e Voltar</a>
    </div>
</body>
</html>