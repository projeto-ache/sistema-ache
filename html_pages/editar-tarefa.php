<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html");
    exit;
}

require_once 'conexao.php';

$id_atribuicao = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_atribuicao <= 0) {
    die("Erro: ID da tarefa inválido.");
}

// --- LÓGICA PARA BUSCAR OS DADOS DA TAREFA E PREENCHER O FORMULÁRIO ---
try {
    // Busca os detalhes da tarefa que será editada
    $stmt_tarefa = $conexao->prepare(
        "SELECT pta.ID_Projeto, pta.ID_Tarefa, pta.NomeTarefaPersonalizado, pta.DescricaoPersonalizada, pta.DataPrazo, pta.Status, p.NomeProjeto
                FROM projetos_tarefas_atribuidas pta
                JOIN projetos p ON pta.ID_Projeto = p.ID_Projeto
                WHERE pta.ID_Atribuicao = ?"
    );
    $stmt_tarefa->bind_param("i", $id_atribuicao);
    $stmt_tarefa->execute();
    $tarefa_atual = $stmt_tarefa->get_result()->fetch_assoc();
    $stmt_tarefa->close();

    if (!$tarefa_atual) die("Tarefa não encontrada.");
    $idProjeto = $tarefa_atual['ID_Projeto'];
    $nomeProjeto = htmlspecialchars($tarefa_atual['NomeProjeto']);

    // Busca todas as tarefas padrão para o dropdown
    $tarefas_predefinidas = [];
    $result_tarefas = $conexao->query("SELECT ID_Tarefa, NomeTarefa FROM tarefas WHERE Origem = 'Desafio Aché' ORDER BY NomeTarefa ASC");
    while ($tarefa = $result_tarefas->fetch_assoc()) {
        $tarefas_predefinidas[] = $tarefa;
    }

    // Busca os participantes DO PROJETO para a lista de checkboxes
    $participantes = [];
    $sql_participantes = "SELECT u.ID_Usuario, u.Nome, u.Email FROM usuarios u 
                          JOIN projetos_usuarios pu ON u.ID_Usuario = pu.ID_Usuario WHERE pu.ID_Projeto = ?
                          UNION 
                          SELECT u.ID_Usuario, u.Nome, u.Email FROM usuarios u
                          JOIN projetos p ON u.ID_Usuario = p.ID_Usuario_Criador WHERE p.ID_Projeto = ?";
    $stmt_participantes = $conexao->prepare($sql_participantes);
    $stmt_participantes->bind_param("ii", $idProjeto, $idProjeto);
    $stmt_participantes->execute();
    $result_participantes = $stmt_participantes->get_result();
    while ($p = $result_participantes->fetch_assoc()) {
        $participantes[$p['ID_Usuario']] = $p;
    }
    $stmt_participantes->close();

    // Busca quais usuários estão ATUALMENTE atribuídos a esta tarefa
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
    die("Erro ao carregar dados da página: " . $e->getMessage());
}
$conexao->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../style/style-editar-projeto.css" /> 
    <title>Editar Tarefa</title>
</head>
<body>
    <div class="form-container">
        <h1>Editar Tarefa</h1>
        <h2>No Projeto: <?php echo $nomeProjeto; ?></h2>

        <form action="processar_edicao_tarefa.php" method="post">
            <input type="hidden" name="id_atribuicao" value="<?php echo $id_atribuicao; ?>">

            <div class="form-group">
                <label for="id_tarefa_existente">Tarefa Padrão Aché:</label>
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
            
            <p class="divider">- E Personalize Abaixo -</p>

            <div class="form-group">
                <label for="nome_tarefa_nova">Nome da Tarefa:</label>
                <input type="text" id="nome_tarefa_nova" name="nome_tarefa_nova" 
                       value="<?php echo htmlspecialchars($tarefa_atual['NomeTarefaPersonalizado']); ?>"
                       placeholder="Se deixar em branco, usa o nome da tarefa padrão">
            </div>

            <div class="form-group">
                <label for="descricao_nova">Descrição da Tarefa:</label>
                <textarea id="descricao_nova" name="descricao_nova" 
                          placeholder="Adicione detalhes específicos para esta tarefa"><?php echo htmlspecialchars($tarefa_atual['DescricaoPersonalizada']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="status">Status da Tarefa:</label>
                <select id="status" name="status">
                    <option value="A Fazer" <?php echo ($tarefa_atual['Status'] == 'A Fazer') ? 'selected' : ''; ?>>
                        A Fazer
                    </option>
                    <option value="Em Andamento" <?php echo ($tarefa_atual['Status'] == 'Em Andamento') ? 'selected' : ''; ?>>
                        Em Andamento
                    </option>
                    <option value="Concluído" <?php echo ($tarefa_atual['Status'] == 'Concluído') ? 'selected' : ''; ?>>
                        Concluído
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="data_prazo">Prazo de Conclusão:</label>
                <input type="date" id="data_prazo" name="data_prazo" 
                       value="<?php echo htmlspecialchars($tarefa_atual['DataPrazo']); ?>" required>
            </div>

            <div class="form-group">
                <label>Atribuir para:</label>
                <div class="checkbox-list">
                    <?php if (empty($participantes)): ?>
                        <p style="color: #888;">Nenhum participante no projeto para atribuir.</p>
                    <?php else: ?>
                        <?php foreach ($participantes as $p): ?>
                            <label>
                                <input type="checkbox" name="usuario_atribuido[]" value="<?php echo $p['ID_Usuario']; ?>"
                                    <?php echo in_array($p['ID_Usuario'], $usuarios_atribuidos_ids) ? 'checked' : ''; ?>>
                                <?php echo htmlspecialchars($p['Nome'] . ' (' . $p['Email'] . ')'); ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="button-container">
                <button type="submit" class="btn-primary">Salvar Alterações</button>
                <a href="index.php" class="btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>