<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html");
    exit;
}

require_once 'conexao.php';

// 1. Validar e obter o ID do projeto da URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID do projeto inválido.");
}
$id_projeto = (int)$_GET['id'];

// 2. Buscar os dados do projeto, participantes atuais e todos os usuários
$projeto = null;
$participantes_atuais = [];
$todos_usuarios = [];

try {
    // Busca detalhes do projeto
    $stmt = $conexao->prepare("SELECT NomeProjeto, Descricao FROM projetos WHERE ID_Projeto = ?");
    $stmt->bind_param("i", $id_projeto);
    $stmt->execute();
    $result = $stmt->get_result();
    $projeto = $result->fetch_assoc();
    $stmt->close();

    if (!$projeto) {
        die("Projeto não encontrado.");
    }

    // Busca participantes atuais do projeto
    $stmt = $conexao->prepare("SELECT ID_Usuario FROM projetos_usuarios WHERE ID_Projeto = ?");
    $stmt->bind_param("i", $id_projeto);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $participantes_atuais[] = $row['ID_Usuario'];
    }
    $stmt->close();

    // Busca todos os usuários para a lista de seleção
    $result = $conexao->query("SELECT ID_Usuario, Nome, Email FROM usuarios");
    while ($row = $result->fetch_assoc()) {
        $todos_usuarios[] = $row;
    }

} catch (Exception $e) {
    die("Erro ao buscar dados do projeto: " . $e->getMessage());
}

$conexao->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Projeto</title>
    <link rel="stylesheet" href="../style/style-editar-projeto.css"> 
</head>
<body>
    
    <div class="form-container">
        <h1>Editar Projeto</h1>
        
        <form action="processar_edicao_projeto.php" method="POST">
            <input type="hidden" name="id_projeto" value="<?php echo $id_projeto; ?>">

            <div class="form-group">
                <label for="nomeProjeto">Nome do Projeto:</label>
                <input type="text" id="nomeProjeto" name="nomeProjeto" value="<?php echo htmlspecialchars($projeto['NomeProjeto']); ?>" required>
            </div>

            <div class="form-group">
                <label for="descricao">Descrição:</label>
                <textarea id="descricao" name="descricao" rows="5"><?php echo htmlspecialchars($projeto['Descricao']); ?></textarea>
            </div>

            <div class="form-group">
                <label>Participantes:</label>
                <div class="checkbox-list">
                    <?php foreach ($todos_usuarios as $usuario): ?>
                        <label>
                            <input type="checkbox" name="participantes[]" value="<?php echo $usuario['ID_Usuario']; ?>" 
                                <?php echo in_array($usuario['ID_Usuario'], $participantes_atuais) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($usuario['Nome'] . ' (' . $usuario['Email'] . ')'); ?>
                        </label>
                    <?php endforeach; ?>
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