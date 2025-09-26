<?php
// --- PARTE 1: LÓGICA DE PROCESSAMENTO (BACK-END) ---
session_start();

// Segurança: Garante que o usuário está logado
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html");
    exit;
}

// Pega o ID e o Nome do usuário da sessão
$idUsuarioLogado = $_SESSION['user_id'];
$primeiroNome = $_SESSION['user_nome']; // <<< NOVO: A variável que o menu precisa
$mensagem_erro = '';

// Verifica se o formulário foi enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once 'conexao.php';

    $senhaAtual = $_POST['senha_atual'];
    $novaSenha = $_POST['nova_senha'];
    $confirmarNovaSenha = $_POST['confirmar_nova_senha'];

    // --- Validações Iniciais ---
    if (empty($senhaAtual) || empty($novaSenha) || empty($confirmarNovaSenha)) {
        $mensagem_erro = "Todos os campos são obrigatórios.";
    } elseif ($novaSenha !== $confirmarNovaSenha) {
        $mensagem_erro = "A nova senha e a confirmação não correspondem.";
    } elseif (strlen($novaSenha) < 8) {
        $mensagem_erro = "A nova senha deve ter no mínimo 8 caracteres.";
    } else {
        // --- Verificação da Senha Atual no Banco de Dados ---
        $sql = "SELECT Senha FROM usuarios WHERE ID_Usuario = ?";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("i", $idUsuarioLogado);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $hashSenhaDB = $row['Senha'];

            // Compara a senha digitada com o hash do banco. ESSA É A FORMA SEGURA!
            if (password_verify($senhaAtual, $hashSenhaDB)) {

                // --- Sucesso! A senha atual está correta. Agora, atualizamos para a nova. ---

                // CRIA O HASH da nova senha antes de salvar
                $novaSenhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);

                $updateSql = "UPDATE usuarios SET Senha = ? WHERE ID_Usuario = ?";
                $updateStmt = $conexao->prepare($updateSql);
                $updateStmt->bind_param("si", $novaSenhaHash, $idUsuarioLogado);

                if ($updateStmt->execute()) {
                    // SUCESSO FINAL!
                    $_SESSION['mensagem_sucesso'] = "Senha alterada com sucesso!";
                    header("Location: perfil.php");
                    exit;
                } else {
                    $mensagem_erro = "Erro ao atualizar a senha. Tente novamente.";
                }
                $updateStmt->close();

            } else {
                $mensagem_erro = "A senha atual está incorreta.";
            }
        }
        $stmt->close();
    }
    $conexao->close();
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="../style/style.css">
    <title>Alterar Senha - ACHE</title>
    <style>
<<<<<<< Updated upstream
=======
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


>>>>>>> Stashed changes
        /* O estilo é idêntico ao da página de alterar nome para manter a consistência */
        .editar-senha-page-content {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 0;
        }

        .form-container {
<<<<<<< Updated upstream
            background-color: #fff;
=======
            background-color: var(--light-background-color);
>>>>>>> Stashed changes
            padding: 30px 40px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }

        .form-container h2 {
            margin: 0 0 25px 0;
<<<<<<< Updated upstream
            color: #d50057;
=======
            color: var(--primary-color);
>>>>>>> Stashed changes
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
<<<<<<< Updated upstream
            color: #333;
=======
            color: var(--dark-text-color);
>>>>>>> Stashed changes
        }

        .input-group input {
            width: 100%;
            padding: 12px;
<<<<<<< Updated upstream
            border: 1px solid #ccc;
=======
            border: 1px solid var(--gray-background-color);
>>>>>>> Stashed changes
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .form-button {
            width: 100%;
            padding: 12px 20px;
            font-size: 16px;
            font-weight: bold;
<<<<<<< Updated upstream
            color: #fff;
            background-color: #d50057;
=======
            color: var(--text-color);
            background-color: var(--primary-color);
>>>>>>> Stashed changes
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .form-button:hover {
<<<<<<< Updated upstream
            background-color: #ac1d58;
=======
            background-color: var(--secundary-color);
>>>>>>> Stashed changes
        }

        .back-link {
            display: block;
            margin-top: 20px;
<<<<<<< Updated upstream
            color: #555;
=======
            color: var(--dark-text-color);
>>>>>>> Stashed changes
            text-decoration: none;
        }

        .back-link:hover{
<<<<<<< Updated upstream
            color: #d50057;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border: 1px solid #f5c6cb;
=======
            color: var(--primary-color);
        }

        .error-message {
            background-color: var(--light-background-color);
            color: red;
            padding: 10px;
            border: 1px solid var(--gray-background-color);
>>>>>>> Stashed changes
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
<<<<<<< Updated upstream
                <p>ACHE</p>
=======
                <img src="../images/Logo_Ache - Branco.png" alt="Logo ACHE" height="20px">
>>>>>>> Stashed changes
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






  

    <div class="editar-senha-page-content">
        <div class="form-container">
            <h2>Alterar Senha</h2>

            <?php if (!empty($mensagem_erro)): ?>
                <p class="error-message"><?php echo $mensagem_erro; ?></p>
            <?php endif; ?>

            <form action="editar-senha.php" method="POST">
                <div class="input-group">
                    <label for="senha_atual">Senha Atual</label>
                    <input type="password" id="senha_atual" name="senha_atual" required>
                </div>
                <div class="input-group">
                    <label for="nova_senha">Nova Senha</label>
                    <input type="password" id="nova_senha" name="nova_senha" required>
                </div>
                <div class="input-group">
                    <label for="confirmar_nova_senha">Confirmar Nova Senha</label>
                    <input type="password" id="confirmar_nova_senha" name="confirmar_nova_senha" required>
                </div>
                <button type="submit" class="form-button">Salvar Nova Senha</button>
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