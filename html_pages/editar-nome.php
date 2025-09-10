<?php
// --- PARTE 1: LÓGICA DE PROCESSAMENTO (BACK-END) ---
session_start();

// Segurança: Garante que o usuário está logado
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html");
    exit;
}

// Pega os dados do usuário da sessão
$idUsuarioLogado = $_SESSION['user_id'];
$nomeAtual = $_SESSION['user_nome'];
$primeiroNome = $_SESSION['user_nome']; // <<< NOVO: A variável que o menu precisa
$mensagem_erro = '';

// Verifica se o formulário foi enviado (se a requisição é do tipo POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Pega o novo nome do formulário e limpa espaços em branco
    $novoNome = trim($_POST['novo_nome']);

    // Validação: Verifica se o nome não está vazio
    if (!empty($novoNome)) {
        require_once 'conexao.php';

        // Prepara a query para atualizar o nome no banco de dados
        $sql = "UPDATE usuarios SET Nome = ? WHERE ID_Usuario = ?";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("si", $novoNome, $idUsuarioLogado);

        // Executa a query e verifica se foi bem-sucedida
        if ($stmt->execute()) {
            // SUCESSO!
            // 1. Atualiza o nome na sessão para que a mudança apareça imediatamente
            $_SESSION['user_nome'] = $novoNome;

            // 2. Cria uma mensagem de sucesso para exibir na página de perfil
            $_SESSION['mensagem_sucesso'] = "Nome alterado com sucesso!";

            // 3. Redireciona o usuário de volta para a página de perfil
            header("Location: perfil.php");
            exit;
        } else {
            // ERRO no banco de dados
            $mensagem_erro = "Erro ao atualizar o nome. Tente novamente.";
        }
        $stmt->close();
        $conexao->close();
    } else {
        // ERRO de validação
        $mensagem_erro = "O campo nome não pode estar em branco.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="../style/style.css">
    <title>Alterar Nome - ACHE</title>
    <style>
        /* Reutilizando o seu novo estilo */
        .editar-nome-page-content {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 0;
        }

        .form-container {
            background-color: #fff;
            padding: 30px 40px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }

        .form-container h2 {
            margin: 0 0 25px 0;
            color: #d50057;
            /* Sua cor de destaque */
            font-size: 28px;
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }

        .input-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
            /* Garante que padding não afete a largura total */
        }

        .form-button {
            width: 100%;
            padding: 12px 20px;
            font-size: 16px;
            font-weight: bold;
            color: #fff;
            background-color: #d50057;
            /* Sua cor de destaque */
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .form-button:hover {
            background-color: #ac1d58;
            /* Sua cor de hover */
        }

        .back-link {
            display: block;
            margin-top: 20px;
            color: #555;
            text-decoration: none;
        }

        .back-link:hover{
            color: #d50057;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>



<body>

<header>
    <div class="menu-logo">
        <a href="index.php">
            <div class="menu-logo-texto">
                <p>ACHE</p>
            </div>
        </a>
    </div>

    <div class="menu-content">
        <div class="menu-content-mensagem">
            <p id="welcome-msg">Bem-vindo, <?php echo $primeiroNome; ?>!</p>
        </div>
        <div class="menu-content-data" id="current-date"></div>
        <div class="menu-content-time" id="current-time"></div>

        <div class="menu-content-logout">
            <a href="logout.php" class="logoutBtn">
                <button type="button">Sair</button>
            </a>
        </div>
    </div>
</header>

<div class="second-menu">
    <div class="menu-burger-icon">
      <button class="btn-exb-left-main">
        <i class="fas fa-bars" alt="Fechar display de navegação"></i>
      </button>
    </div>
    <div class="menu-atalhos-iniciais">
      <div class="menu-atalhos-iniciais-pagina-inicial">
        <a href="index.php">Página Inicial</a>
      </div>
      <div class="menu-atalhos-iniciais-ajuda">
        <a href="#">Ajuda</a>
      </div>
    </div>
    <div class="menu-atalhos-pessoais">
      <ul class="nav-icons">
        <li>
          <button id="btn-mostrar-calendario-global" class="nav-icon-btn"><i class="fas fa-calendar-alt"></i></button>
        </li>
        <li>
          <a href="#"><i class="fas fa-bell"></i></a>
        </li>
        <li>
          <a href="#"><i class="fas fa-cog"></i></a>
        </li>
        <li>
          <a href="perfil.php"><i class="fas fa-user-circle"></i></a>
        </li>
      </ul>
    </div>
  </div>







    <div class="editar-nome-page-content">
        <div class="form-container">
            <h2>Alterar Nome</h2>

            <?php if (!empty($mensagem_erro)): ?>
                <p class="error-message"><?php echo $mensagem_erro; ?></p>
            <?php endif; ?>

            <form action="editar-nome.php" method="POST">
                <div class="input-group">
                    <label for="novo_nome">Novo Nome</label>
                    <input type="text" id="novo_nome" name="novo_nome"
                        value="<?php echo htmlspecialchars($nomeAtual, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <button type="submit" class="form-button">Salvar Alterações</button>
            </form>

            <a href="perfil.php" class="back-link">Voltar para o Perfil</a>
        </div>

    </div>

    <script>
        function atualizarDataHora() {
            const agora = new Date();
            const data = agora.toLocaleDateString("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric" });
            const hora = agora.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });

            const dateElement = document.getElementById("current-date");
            const timeElement = document.getElementById("current-time");

            // Verifica se os elementos existem na página antes de tentar alterá-los
            if (dateElement) {
                dateElement.textContent = data;
            }
            if (timeElement) {
                timeElement.textContent = hora;
            }
        }

        // Chama a função uma vez assim que a página carrega
        atualizarDataHora();
        // Define um intervalo para que a função seja chamada a cada segundo, atualizando o relógio
        setInterval(atualizarDataHora, 1000);
    </script>

</body>

</html>