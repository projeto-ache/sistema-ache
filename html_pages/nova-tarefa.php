<?php
session_start();
// Verifica se o usuário está logado, senão redireciona para o login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html");
    exit;
}

require_once 'conexao.php';

// Pega o ID do projeto da URL. Se não existir ou for inválido, interrompe.
$idProjeto = isset($_GET['id_projeto']) ? (int)$_GET['id_projeto'] : 0;
if ($idProjeto <= 0) {
    die("Erro: ID do projeto não fornecido ou inválido.");
}

$mensagem_sucesso = '';
$mensagem_erro = '';

// --- LÓGICA DE PROCESSAMENTO DO FORMULÁRIO (QUANDO ENVIADO) ---
// --- LÓGICA DE PROCESSAMENTO DO FORMULÁRIO (QUANDO ENVIADO) - VERSÃO CORRIGIDA ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Coleta e valida os dados do formulário
    $idTarefaExistente = isset($_POST['id_tarefa_existente']) ? (int)$_POST['id_tarefa_existente'] : 0;
    $nomeTarefaNova = trim($_POST['nome_tarefa_nova'] ?? '');
    $descricaoNova = trim($_POST['descricao_nova'] ?? '');
    $dataPrazo = $_POST['data_prazo'] ?? '';
    // AGORA RECEBEMOS UM ARRAY DE USUÁRIOS
    $idsUsuariosAtribuidos = $_POST['usuario_atribuido'] ?? [];

    if (empty($dataPrazo)) {
        $mensagem_erro = "A data de prazo é obrigatória.";
    } elseif ($idTarefaExistente === 0 && empty($nomeTarefaNova)) {
        $mensagem_erro = "Você deve selecionar uma tarefa existente ou criar uma nova.";
    } else {
        $conexao->begin_transaction(); // Inicia a transação
        try {
            $idTarefaFinal = 0;
            
            // 2. Decide se usa uma tarefa existente ou cria uma nova
            if ($idTarefaExistente > 0) {
                $idTarefaFinal = $idTarefaExistente;
            } else {
                $sql_nova_tarefa = "INSERT INTO tarefas (NomeTarefa, Descricao) VALUES (?, ?)";
                $stmt_nova = $conexao->prepare($sql_nova_tarefa);
                $stmt_nova->bind_param("ss", $nomeTarefaNova, $descricaoNova);
                $stmt_nova->execute();
                $idTarefaFinal = $conexao->insert_id;
                $stmt_nova->close();
            }

            // 3. Insere a tarefa na tabela de atribuição (SEM O ID DO USUÁRIO)
            $statusInicial = 'A Fazer';
            $sql_atribuicao = "INSERT INTO projetos_tarefas_atribuidas 
                               (ID_Projeto, ID_Tarefa, Status, DataCriacao, DataPrazo, NomeTarefaPersonalizado, DescricaoPersonalizada) 
                               VALUES (?, ?, ?, NOW(), ?, ?, ?)";
            $stmt_atribuicao = $conexao->prepare($sql_atribuicao);
            $stmt_atribuicao->bind_param("iissss", $idProjeto, $idTarefaFinal, $statusInicial, $dataPrazo, $nomeTarefaNova, $descricaoNova);
            $stmt_atribuicao->execute();

            // Pega o ID da atribuição que acabamos de criar
            $idNovaAtribuicao = $conexao->insert_id;
            $stmt_atribuicao->close();

            // 4. Se usuários foram selecionados, insere-os na nova tabela de ligação
            if (!empty($idsUsuariosAtribuidos)) {
                $sql_usuarios_tarefa = "INSERT INTO tarefa_atribuida_usuarios (ID_Atribuicao, ID_Usuario) VALUES (?, ?)";
                $stmt_usuarios_tarefa = $conexao->prepare($sql_usuarios_tarefa);

                // Para cada usuário selecionado, executa um INSERT
                foreach ($idsUsuariosAtribuidos as $id_usuario) {
                    // LINHA CORRIGIDA:
                    $stmt_usuarios_tarefa->bind_param("ii", $idNovaAtribuicao, $id_usuario);
                    $stmt_usuarios_tarefa->execute();
                }
                $stmt_usuarios_tarefa->close();
            }
            
            // Se tudo deu certo até aqui, confirma as operações no banco
            $conexao->commit();
            $mensagem_sucesso = "Tarefa atribuída com sucesso!";

        } catch (Exception $e) {
            // Se qualquer passo falhar, desfaz tudo
            $conexao->rollback();
            $mensagem_erro = "Erro ao atribuir a tarefa: " . $e->getMessage();
        }
    }
}

// --- LÓGICA PARA CARREGAR DADOS PARA O FORMULÁRIO (continua igual) ---
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
    die("Erro ao carregar dados da página: " . $e->getMessage());
}

$conexao->close();
?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../style/style.css" />
    <title>Adicionar Tarefa ao Projeto</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .form-container { max-width: 600px; margin: 50px auto; padding: 30px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-container h1 { color: #333; text-align: center; margin-bottom: 10px; }
        .form-container .project-name { color: #555; text-align: center; font-size: 1.2em; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #666; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 1em; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .btn-submit { width: 100%; padding: 15px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1.1em; font-weight: bold; }
        .btn-submit:hover { background-color: #0056b3; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 5px; text-align: center; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
        .back-link { display: block; text-align: center; margin-top: 20px; }
        .divider { text-align: center; font-size: 0.9em; color: #aaa; margin: 20px 0; }
    </style>
</head>
<body>

    <div class="form-container">
        <h1>Adicionar Tarefa</h1>
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
            
            <p class="divider">- OU -</p>

            <div class="form-group">
                <label for="nome_tarefa_nova">Nome da Tarefa Personalizado (Opcional):</label>
                <input type="text" id="nome_tarefa_nova" name="nome_tarefa_nova">
            </div>

            <div class="form-group">
                <label for="descricao_nova">Descrição Personalizada (Opcional):</label>
                <textarea id="descricao_nova" name="descricao_nova"></textarea>
            </div>

            <div class="form-group">
                <label for="data_prazo">Prazo de Conclusão:</label>
                <input type="date" id="data_prazo" name="data_prazo" required>
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
        <a href="index.php" class="back-link">Voltar para a Página Inicial</a>
    </div>

</body>
</html>