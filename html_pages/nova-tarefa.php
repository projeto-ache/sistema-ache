<?php
session_start();
// Verifica se o usuário está logado, senão redireciona para o login
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.html");
    exit;
}

// Pega o ID do usuário logado no início para usarmos no "carimbo"
$idUsuarioLogado = $_SESSION['user_id'];

require_once 'conexao.php'; // Abre a conexão UMA VEZ no início

$idProjeto = isset($_GET['id_projeto']) ? (int) $_GET['id_projeto'] : 0;
if ($idProjeto <= 0) {
    die("Erro: ID do projeto não fornecido ou inválido.");
}

$mensagem_sucesso = '';
$mensagem_erro = '';

// --- LÓGICA DE PROCESSAMENTO DO FORMULÁRIO ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Coleta de todos os dados do formulário
    $idTarefaExistente = isset($_POST['id_tarefa_existente']) ? (int) $_POST['id_tarefa_existente'] : 0;
    $nomeTarefaNova = trim($_POST['nome_tarefa_nova'] ?? '');
    $descricaoNova = trim($_POST['descricao_nova'] ?? '');
    $dataPrazo = $_POST['data_prazo'] ?? '';
    $idsUsuariosAtribuidos = $_POST['usuario_atribuido'] ?? []; // <-- Os IDs chegam aqui

    $habilitarRecorrencia = isset($_POST['habilitar_recorrencia']);
    $frequencia = $_POST['frequencia'] ?? 'semanal';
    $dataFimRecorrencia = $_POST['data_fim_recorrencia'] ?? '';

    // 2. Validações
    if (empty($dataPrazo)) {
        $mensagem_erro = "A data de prazo é obrigatória.";
    } elseif ($idTarefaExistente === 0 && empty($nomeTarefaNova)) {
        $mensagem_erro = "Você deve selecionar uma tarefa padrão ou criar uma tarefa personalizada com um nome.";
    } elseif ($habilitarRecorrencia && empty($dataFimRecorrencia)) {
        $mensagem_erro = "A data final da recorrência é obrigatória.";
    } else {

        $conexao->begin_transaction();
        try {
            if ($idTarefaExistente > 0 && empty($nomeTarefaNova)) {
                $stmt_nome_tarefa = $conexao->prepare("SELECT NomeTarefa FROM tarefas WHERE ID_Tarefa = ?");
                $stmt_nome_tarefa->bind_param("i", $idTarefaExistente);
                $stmt_nome_tarefa->execute();
                $result_nome = $stmt_nome_tarefa->get_result();
                if ($row_nome = $result_nome->fetch_assoc()) {
                    $nomeTarefaNova = $row_nome['NomeTarefa'];
                }
                $stmt_nome_tarefa->close();
            }

            if (!$habilitarRecorrencia) {
                // --- LÓGICA PARA TAREFA ÚNICA ---
                $sql_atribuicao = "INSERT INTO projetos_tarefas_atribuidas (ID_Projeto, ID_Tarefa, Status, DataCriacao, DataPrazo, NomeTarefaPersonalizado, DescricaoPersonalizada) VALUES (?, ?, 'A Fazer', NOW(), ?, ?, ?)";
                $stmt_atribuicao = $conexao->prepare($sql_atribuicao);
                $stmt_atribuicao->bind_param("iisss", $idProjeto, $idTarefaExistente, $dataPrazo, $nomeTarefaNova, $descricaoNova);
                $stmt_atribuicao->execute();
                $idNovaAtribuicao = $conexao->insert_id;
                $stmt_atribuicao->close();

                // =======================================================
                //      INÍCIO DA CORREÇÃO (BLOCO QUE FALTAVA)
                // =======================================================
                if (!empty($idsUsuariosAtribuidos)) {
                    $sql_usuarios_tarefa = "INSERT INTO tarefa_atribuida_usuarios (ID_Atribuicao, ID_Usuario) VALUES (?, ?)";
                    $stmt_usuarios_tarefa = $conexao->prepare($sql_usuarios_tarefa);
                    foreach ($idsUsuariosAtribuidos as $id_usuario) {
                        $id_usuario_int = (int) $id_usuario;
                        $stmt_usuarios_tarefa->bind_param("ii", $idNovaAtribuicao, $id_usuario_int);
                        $stmt_usuarios_tarefa->execute();
                    }
                    $stmt_usuarios_tarefa->close();
                }
                // =======================================================
                //      FIM DA CORREÇÃO
                // =======================================================

                // CARIMBO DE MODIFICAÇÃO (TAREFA ÚNICA)
                $sql_log = "UPDATE projetos SET ID_UltimoUsuarioModificador = ?, DataHoraUltimaModificacao = NOW() WHERE ID_Projeto = ?";
                $stmt_log = $conexao->prepare($sql_log);
                $stmt_log->bind_param("ii", $idUsuarioLogado, $idProjeto);
                $stmt_log->execute();
                $stmt_log->close();

                $mensagem_sucesso = "Tarefa criada com sucesso!";
            } else {
                // --- LÓGICA PARA TAREFAS RECORRENTES ---
                $dataDeOcorrencia = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                $dataFinal = new DateTime($dataFimRecorrencia);
                $dataDeOcorrencia->setTime(0, 0, 0);
                $dataFinal->setTime(0, 0, 0);
                $idRecorrencia = uniqid('rec_', true);
                $contadorTarefas = 0;
                $limiteSeguranca = 120;

                while ($dataDeOcorrencia <= $dataFinal && $contadorTarefas < $limiteSeguranca) {
                    $prazoFormatado = $dataPrazo;
                    $sql_atribuicao = "INSERT INTO projetos_tarefas_atribuidas (ID_Projeto, ID_Tarefa, Status, DataCriacao, DataPrazo, NomeTarefaPersonalizado, DescricaoPersonalizada, ID_Recorrencia, FrequenciaRecorrencia, DataFimRecorrencia) VALUES (?, ?, 'A Fazer', NOW(), ?, ?, ?, ?, ?, ?)";
                    $stmt_atribuicao = $conexao->prepare($sql_atribuicao);
                    $stmt_atribuicao->bind_param("iissssss", $idProjeto, $idTarefaExistente, $prazoFormatado, $nomeTarefaNova, $descricaoNova, $idRecorrencia, $frequencia, $dataFimRecorrencia);
                    $stmt_atribuicao->execute();
                    $idNovaAtribuicao = $conexao->insert_id;
                    $stmt_atribuicao->close();

                    // =======================================================
                    //      INÍCIO DA CORREÇÃO (BLOCO QUE FALTAVA)
                    // =======================================================
                    if (!empty($idsUsuariosAtribuidos)) {
                        $sql_usuarios_tarefa = "INSERT INTO tarefa_atribuida_usuarios (ID_Atribuicao, ID_Usuario) VALUES (?, ?)";
                        $stmt_usuarios_tarefa = $conexao->prepare($sql_usuarios_tarefa);
                        foreach ($idsUsuariosAtribuidos as $id_usuario) {
                            $id_usuario_int = (int) $id_usuario;
                            $stmt_usuarios_tarefa->bind_param("ii", $idNovaAtribuicao, $id_usuario_int);
                            $stmt_usuarios_tarefa->execute();
                        }
                        $stmt_usuarios_tarefa->close();
                    }
                    // =======================================================
                    //      FIM DA CORREÇÃO
                    // =======================================================

                    switch ($frequencia) {
                        case 'diaria':
                            $dataDeOcorrencia->modify('+1 day');
                            break;
                        case 'semanal':
                            $dataDeOcorrencia->modify('+1 week');
                            break;
                        case 'mensal':
                            $dataDeOcorrencia->modify('+1 month');
                            break;
                    }
                    $contadorTarefas++;
                }

                // CARIMBO DE MODIFICAÇÃO (TAREFA RECORRENTE)
                if ($contadorTarefas > 0) {
                    $sql_log = "UPDATE projetos SET ID_UltimoUsuarioModificador = ?, DataHoraUltimaModificacao = NOW() WHERE ID_Projeto = ?";
                    $stmt_log = $conexao->prepare($sql_log);
                    $stmt_log->bind_param("ii", $idUsuarioLogado, $idProjeto);
                    $stmt_log->execute();
                    $stmt_log->close();
                }

                $mensagem_sucesso = "$contadorTarefas tarefas recorrentes foram criadas com sucesso!";
            }
            $conexao->commit();
        } catch (Exception $e) {
            $conexao->rollback();
            $mensagem_erro = "Erro ao criar a tarefa: " . $e->getMessage();
        }
    }
}

// --- LÓGICA PARA CARREGAR DADOS PARA O FORMULÁRIO (SEMPRE EXECUTA) ---
try {
    // Busca o nome do projeto
    $stmt_nome_proj = $conexao->prepare("SELECT NomeProjeto FROM projetos WHERE ID_Projeto = ?");
    $stmt_nome_proj->bind_param("i", $idProjeto);
    $stmt_nome_proj->execute();
    $projeto = $stmt_nome_proj->get_result()->fetch_assoc();
    $nomeProjeto = $projeto ? htmlspecialchars($projeto['NomeProjeto']) : 'Projeto Desconhecido';
    $stmt_nome_proj->close();

    // Busca a lista de TODAS as tarefas pré-definidas
    $tarefas_predefinidas = [];
    $sql_tarefas = "SELECT ID_Tarefa, NomeTarefa FROM tarefas ORDER BY NomeTarefa ASC";
    $result_tarefas = $conexao->query($sql_tarefas);
    while ($tarefa = $result_tarefas->fetch_assoc()) {
        $tarefas_predefinidas[] = $tarefa;
    }

    // Busca os participantes do projeto
    $participantes = [];
    $sql_participantes = "SELECT u.ID_Usuario, u.Nome FROM usuarios u 
                          JOIN projetos_usuarios pu ON u.ID_Usuario = pu.ID_Usuario 
                          WHERE pu.ID_Projeto = ?
                          UNION 
                          SELECT u.ID_Usuario, u.Nome FROM usuarios u
                          JOIN projetos p ON u.ID_Usuario = p.ID_Usuario_Criador
                          WHERE p.ID_Projeto = ?";
    $stmt_participantes = $conexao->prepare($sql_participantes);
    $stmt_participantes->bind_param("ii", $idProjeto, $idProjeto);
    $stmt_participantes->execute();
    $result_participantes = $stmt_participantes->get_result();
    while ($participante = $result_participantes->fetch_assoc()) {
        $participantes[] = $participante;
    }
    $stmt_participantes->close();

} catch (Exception $e) {
    $nomeProjeto = 'Erro ao carregar projeto';
    $tarefas_predefinidas = [];
    $participantes = [];
    $mensagem_erro = "Erro ao carregar dados da página: " . $e->getMessage();
}

$conexao->close(); // Fecha a conexão UMA VEZ, no final de toda a lógica PHP
?>


<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../style/style.css" />
    <title>Adicionar Tarefa ao Projeto</title>
    <style>
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
            overflow-y: scroll;
        }

        .form-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background-color: var(--light-background-color);
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .form-container h1 {
            color: var(--dark-text-color);
            text-align: center;
            margin-bottom: 10px;
        }

        .form-container .project-name {
            color: var(--dark-text-color);
            text-align: center;
            font-size: 1.2em;
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--gray-background-color);
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 1em;
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
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: bold;
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
            color: #721c24;
            border: 1px solid var(--gray-background-color);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
        }

        .divider {
            text-align: center;
            font-size: 0.9em;
            color: var(--gray-background-color);
            margin: 20px 0;
        }
    </style>
</head>

<body>

    <div class="form-container">
        <h1 style="color: var(--primary-color);">Adicionar Tarefa</h1>
        <p class="project-name">Ao Projeto: <?php echo $nomeProjeto; ?></p>

        <?php if ($mensagem_sucesso): ?>
            <div class="message success"><?php echo $mensagem_sucesso; ?></div>
        <?php endif; ?>
        <?php if ($mensagem_erro): ?>
            <div class="message error"><?php echo $mensagem_erro; ?></div>
        <?php endif; ?>

        <form action="nova-tarefa.php?id_projeto=<?php echo $idProjeto; ?>" method="post">

            <div class="form-group">
                <label for="id_tarefa_existente">Selecione uma Tarefa Padrão:</label>
                <select id="id_tarefa_existente" name="id_tarefa_existente">
                    <option value="0">-- Escolha uma tarefa --</option>
                    <?php foreach ($tarefas_predefinidas as $tarefa): ?>
                        <option value="<?php echo $tarefa['ID_Tarefa']; ?>">
                            <?php echo htmlspecialchars($tarefa['NomeTarefa']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <p class="divider" style="color: var(--gray-background-color); font-weight: 600;">- OU -</p>

            <div class="form-group">
                <label for="nome_tarefa_nova">Nome da Tarefa Personalizado (Opcional):</label>
                <input type="text" id="nome_tarefa_nova" name="nome_tarefa_nova">
            </div>

            <div class="form-group">
                <label for="descricao_nova">Descrição Personalizada (Opcional):</label>
                <textarea id="descricao_nova" name="descricao_nova"></textarea>
            </div>

            <div class="form-group">
                <label for="data_prazo">Prazo de Conclusão (para todas as tarefas):</label>
                <input type="date" id="data_prazo" name="data_prazo" required>
            </div>

            <div class="form-group">
                <label for="habilitar_recorrencia">
                    <input type="checkbox" id="habilitar_recorrencia" name="habilitar_recorrencia" value="1">
                    Habilitar Repetição (começando hoje)
                </label>
            </div>

            <div id="opcoes_recorrencia"
                style="display: none; border-left: 3px solid var(--primary-color); padding-left: 15px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="frequencia">Repetir a cada:</label>
                    <select id="frequencia" name="frequencia">
                        <option value="diaria">Dia</option>
                        <option value="semanal" selected>Semana</option>
                        <option value="mensal">Mês</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="data_fim_recorrencia">Repetir até a data de:</label>
                    <input type="date" id="data_fim_recorrencia" name="data_fim_recorrencia">
                </div>
            </div>










            <div class="form-group">
                <label for="usuario_atribuido">Atribuir para (segure Ctrl para selecionar vários):</label>
                <select id="usuario_atribuido" name="usuario_atribuido[]" multiple style="height: 120px;">
                    <?php foreach ($participantes as $p): ?>
                        <option value="<?php echo $p['ID_Usuario']; ?>">
                            <?php echo htmlspecialchars($p['Nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-submit">Adicionar Tarefa ao Projeto</button>
        </form>
        <a href="index.php" class="back-link" style="text-decoration: none; color: var(--dark-text-color);">Voltar para a Página Inicial</a>
    </div>


    <script>
        // Script para mostrar/esconder as opções de recorrência
        const checkboxRecorrencia = document.getElementById('habilitar_recorrencia');
        const divOpcoesRecorrencia = document.getElementById('opcoes_recorrencia');
        const inputDataFim = document.getElementById('data_fim_recorrencia');

        checkboxRecorrencia.addEventListener('change', function () {
            if (this.checked) {
                // Se marcou recorrência
                divOpcoesRecorrencia.style.display = 'block';
                inputDataFim.required = true; // Data final se torna obrigatória
            } else {
                // Se desmarcou recorrência
                divOpcoesRecorrencia.style.display = 'none';
                inputDataFim.required = false; // Data final deixa de ser obrigatória
            }
        });
    </script>


</body>

</html>