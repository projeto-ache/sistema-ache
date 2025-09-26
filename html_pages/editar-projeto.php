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
    $id_projeto = isset($_POST['id_projeto']) ? (int)$_POST['id_projeto'] : 0;
    $nomeProjeto = trim($_POST['nome_projeto'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $dataConclusao = $_POST['data_conclusao'] ?? null;
    $membrosStr = $_POST['membros'] ?? '';
    
    // Converte a string de IDs de membros em um array
    $participantes = !empty($membrosStr) ? explode(',', $membrosStr) : [];

    if ($id_projeto <= 0 || empty($nomeProjeto)) {
        $mensagem_erro = "Dados do projeto inválidos.";
    } else {
        $conexao->begin_transaction();
        try {
            // 1. ATUALIZA OS DADOS PRINCIPAIS DO PROJETO
            $stmt_update = $conexao->prepare("UPDATE projetos SET NomeProjeto = ?, Descricao = ?, DataConclusao = ? WHERE ID_Projeto = ?");
            $stmt_update->bind_param("sssi", $nomeProjeto, $descricao, $dataConclusao, $id_projeto);
            $stmt_update->execute();
            $stmt_update->close();

            // 2. ATUALIZA OS PARTICIPANTES (Remove todos e insere os novos)
            $stmt_delete = $conexao->prepare("DELETE FROM projetos_usuarios WHERE ID_Projeto = ?");
            $stmt_delete->bind_param("i", $id_projeto);
            $stmt_delete->execute();
            $stmt_delete->close();
            
            if (!empty($participantes)) {
                $sql_insert = "INSERT INTO projetos_usuarios (ID_Projeto, ID_Usuario) VALUES (?, ?)";
                $stmt_insert = $conexao->prepare($sql_insert);
                foreach ($participantes as $id_usuario) {
                    $id_participante = (int)$id_usuario; 
                    $stmt_insert->bind_param("ii", $id_projeto, $id_participante);
                    $stmt_insert->execute();
                }
                $stmt_insert->close();
            }

            // 3. CARIMBA A MODIFICAÇÃO NO PROJETO
            $sql_log = "UPDATE projetos SET ID_UltimoUsuarioModificador = ?, DataHoraUltimaModificacao = NOW() WHERE ID_Projeto = ?";
            $stmt_log = $conexao->prepare($sql_log);
            $stmt_log->bind_param("ii", $idUsuarioLogado, $id_projeto);
            $stmt_log->execute();
            $stmt_log->close();

            $conexao->commit();
            $_SESSION['mensagem_sucesso'] = "Projeto atualizado com sucesso!";
            header("Location: index.php"); // Redireciona para a página inicial após salvar
            exit;

        } catch (Exception $e) {
            $conexao->rollback();
            $mensagem_erro = "Erro ao salvar as alterações: " . $e->getMessage();
        }
    }
}

// --- LÓGICA PARA CARREGAR DADOS PARA EXIBIR O FORMULÁRIO (GET) ---
$id_projeto = isset($_GET['id']) ? (int)$_GET['id'] : (isset($id_projeto) ? $id_projeto : 0);
if ($id_projeto <= 0) {
    die("ID do projeto inválido.");
}

$projeto = null;
$participantes_atuais = [];

try {
    // Busca detalhes do projeto (incluindo a data)
    $stmt_proj = $conexao->prepare("SELECT NomeProjeto, Descricao, DataConclusao FROM projetos WHERE ID_Projeto = ?");
    $stmt_proj->bind_param("i", $id_projeto);
    $stmt_proj->execute();
    $result_proj = $stmt_proj->get_result();
    if (!($projeto = $result_proj->fetch_assoc())) {
        die("Projeto não encontrado.");
    }
    $stmt_proj->close();

    // Busca participantes atuais com seus nomes
    $stmt_part = $conexao->prepare("
        SELECT u.ID_Usuario, u.Nome, u.Sobrenome
        FROM projetos_usuarios pu
        JOIN usuarios u ON pu.ID_Usuario = u.ID_Usuario
        WHERE pu.ID_Projeto = ?
    ");
    $stmt_part->bind_param("i", $id_projeto);
    $stmt_part->execute();
    $result_part = $stmt_part->get_result();
    while ($row = $result_part->fetch_assoc()) {
        $participantes_atuais[] = $row;
    }
    $stmt_part->close();

} catch (Exception $e) {
    $mensagem_erro = "Erro ao buscar dados do projeto: " . $e->getMessage();
}
$conexao->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Editar Projeto - ACHE</title>
    <link rel="stylesheet" href="../style/style-cadastro-projeto.css" />
</head>
<body>
    <div class="projeto-box">
        <h1>Editar Projeto</h1>
        <p class="subtitulo">Altere os dados do projeto e salve as modificações.</p>

        <?php if ($mensagem_erro): ?>
            <p class="error-message" style="color: red; background-color: #ffdddd; padding: 10px; border-radius: 5px;"><?php echo $mensagem_erro; ?></p>
        <?php endif; ?>

        <form action="editar-projeto.php?id=<?php echo $id_projeto; ?>" method="POST">
            <input type="hidden" name="id_projeto" value="<?php echo $id_projeto; ?>">

            <label for="nome_projeto">Nome do Projeto</label>
            <input type="text" id="nome_projeto" name="nome_projeto" required value="<?php echo htmlspecialchars($projeto['NomeProjeto']); ?>" />

            <label for="descricao">Descrição</label>
            <textarea id="descricao" name="descricao" rows="4"><?php echo htmlspecialchars($projeto['Descricao']); ?></textarea>

            <label for="data_conclusao">Data de Conclusão Prevista</label>
            <input type="date" id="data_conclusao" name="data_conclusao" value="<?php echo htmlspecialchars($projeto['DataConclusao']); ?>" />

            <label for="busca_usuario">Adicionar Membros</label>
            <input type="text" id="busca_usuario" placeholder="Digite para buscar usuários..." autocomplete="off" />
            <div id="resultados_busca"></div> 
            <div id="membros_selecionados">
                <p>Membros:</p>
            </div>
            
            <input type="hidden" name="membros" id="membros_ids" />

            <button type="submit">Salvar Alterações</button>
            <div class="cancelar-link"><a href="index.php">Cancelar</a></div>
        </form>
    </div>

    <script>
        // Bloco PHP para "injetar" os membros atuais em uma variável JavaScript
        const membrosIniciais = new Map();
        <?php foreach ($participantes_atuais as $participante): ?>
            membrosIniciais.set(
                '<?php echo $participante['ID_Usuario']; ?>', 
                '<?php echo addslashes($participante['Nome'] . ' ' . $participante['Sobrenome']); ?>'
            );
        <?php endforeach; ?>
    </script>
    
    <script>
        // ----- SCRIPT IDÊNTICO AO DA PÁGINA DE CADASTRO -----
        const buscaInput = document.getElementById('busca_usuario');
        const resultadosDiv = document.getElementById('resultados_busca');
        const selecionadosDiv = document.getElementById('membros_selecionados');
        const membrosHiddenInput = document.getElementById('membros_ids');
        
        // MODIFICAÇÃO: Inicializa o mapa de membros com os dados que vieram do PHP
        let membrosSelecionados = membrosIniciais;

        // MODIFICAÇÃO: Garante que os membros iniciais sejam exibidos ao carregar a página
        document.addEventListener('DOMContentLoaded', atualizarMembrosVisuais);

        // 1. Ouve o que o usuário digita
        buscaInput.addEventListener('keyup', () => {
            const termo = buscaInput.value.trim();
            if (termo.length < 2) {
                resultadosDiv.innerHTML = '';
                return;
            }

            // 2. Envia o termo para o back-end
            fetch(`buscar-usuarios.php?termo=${termo}`)
                .then(response => response.json())
                .then(data => {
                    // 3. Exibe os resultados
                    resultadosDiv.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(usuario => {
                            const userDiv = document.createElement('div');
                            userDiv.classList.add('resultado-item');
                            userDiv.textContent = `${usuario.Nome} ${usuario.Sobrenome} (${usuario.Email})`;
                            userDiv.dataset.id = usuario.ID_Usuario;
                            userDiv.dataset.nome = `${usuario.Nome} ${usuario.Sobrenome}`;
                            resultadosDiv.appendChild(userDiv);
                        });
                    } else {
                        resultadosDiv.innerHTML = '<div class="resultado-item">Nenhum usuário encontrado.</div>';
                    }
                });
        });

        // 4. Adiciona um membro ao clicar no resultado
        resultadosDiv.addEventListener('click', (e) => {
            if (e.target.classList.contains('resultado-item') && e.target.dataset.id) {
                const id = e.target.dataset.id;
                const nome = e.target.dataset.nome;
                
                if (!membrosSelecionados.has(id)) {
                    membrosSelecionados.set(id, nome);
                    atualizarMembrosVisuais();
                }
                
                buscaInput.value = '';
                resultadosDiv.innerHTML = '';
            }
        });

        // 5. Remove um membro
        selecionadosDiv.addEventListener('click', (e) => {
            if (e.target.classList.contains('remover-membro')) {
                const id = e.target.parentElement.dataset.id;
                membrosSelecionados.delete(id);
                atualizarMembrosVisuais();
            }
        });

        function atualizarMembrosVisuais() {
            selecionadosDiv.innerHTML = '<p>Membros:</p>'; // Limpa e adiciona o título
            membrosSelecionados.forEach((nome, id) => {
                const pill = document.createElement('div');
                pill.classList.add('membro-pill');
                pill.dataset.id = id;
                pill.innerHTML = `<span>${nome}</span><span class="remover-membro">&times;</span>`;
                selecionadosDiv.appendChild(pill);
            });
            // Atualiza o input oculto com os IDs
            membrosHiddenInput.value = Array.from(membrosSelecionados.keys()).join(',');
        }
    </script>
</body>
</html>