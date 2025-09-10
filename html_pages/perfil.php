<?php
// Inicia a sessão para acessar os dados do usuário
session_start();

// Verifica se o usuário está logado. Se não estiver, redireciona para a página de login.
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html");
    exit;
}

// Pega dados básicos da sessão
$idUsuarioLogado = $_SESSION['user_id'];
$primeiroNome = htmlspecialchars((string) ($_SESSION['user_nome'] ?? 'Usuário'), ENT_QUOTES, 'UTF-8');
$emailUsuario = 'email@exemplo.com';
$caminhoFoto = '../images/user-img.png'; // Valor Padrão

// Busca o email E A FOTO do usuário no banco de dados
require_once 'conexao.php';
try {
    $sql = "SELECT Email, FotoPerfil FROM usuarios WHERE ID_Usuario = ?"; // Adicionado FotoPerfil
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $idUsuarioLogado);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $emailUsuario = htmlspecialchars($row['Email'], ENT_QUOTES, 'UTF-8');
        $caminhoFoto = htmlspecialchars($row['FotoPerfil'], ENT_QUOTES, 'UTF-8'); // Nova variável
    }
    $stmt->close();
    $conexao->close();
} catch (Exception $e) {
    // Em caso de erro, o email padrão será usado.
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <title>Meu Perfil - ACHE</title>

    <style>
        .perfil-page-content {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px 0;
        }

        .profile-card {
            display: flex;
            align-items: center;
            gap: 30px;
            padding: 30px;
            border-radius: 10px;
            max-width: 800px;
            width: 90%;
            margin-bottom: 30px;
            /* Espaço entre o card principal e os detalhes */
        }

        .image-container {
            position: relative;
            width: 200px;
            height: 200px;
            flex-shrink: 0;
        }

        .profile-picture {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .edit-icon-form {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background-color: #d50057;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            border: 2px solid white;
        }

        .edit-icon-form .material-symbols-outlined {
            color: white;
            font-size: 24px;
        }

        .file-upload-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .text-content {
            flex-grow: 1;
        }

        .text-content h1 {
            font-size: 32px;
            font-weight: 600;
            margin: 0 0 10px 0;
        }

        .text-content .tagline {
            font-size: 18px;
            color: #555;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        .text-content .logout-link {
            font-size: 18px;
            color: #d50057;
            text-decoration: none;
            font-weight: bold;
        }

        .text-content .logout-link:hover {
            text-decoration: underline;
        }

        /* Estilos para a área de detalhes (agora sempre visível) */
        .profile-details {
            padding: 25px;
            border-radius: 10px;
            max-width: 800px;
            width: 90%;
        }

        .profile-details h2 {
            margin: 0 0 20px 0;
            color: #d50057;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .info-item {
            font-size: 18px;
            color: black;
            margin-bottom: 15px;
        }

        .actions-container {
            margin-top: 25px;
            display: flex;
            gap: 15px;
        }

        .action-button {
            padding: 10px 20px;
            text-decoration: none;
            color: #fff;
            background-color: #d50057;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .action-button:hover {
            background-color: #ac1d58;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            margin-bottom: 20px;
            width: 90%;
            max-width: 800px;
            text-align: center;
        }
    </style>
</head>













    <script>
        // --- O SCRIPT QUE PEGAVA NOME DO USUÁRIO DO LOCALSTORAGE FOI REMOVIDO ---

        // --- Função para atualizar data e hora (continua igual) ---
        function atualizarDataHora() {
            const agora = new Date();
            const data = agora.toLocaleDateString("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric" });
            const hora = agora.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });
            document.getElementById("current-date").textContent = data;
            document.getElementById("current-time").textContent = hora;
        }
        atualizarDataHora();
        setInterval(atualizarDataHora, 1000);

        // --- O SCRIPT DE LOGOUT COM LOCALSTORAGE FOI REMOVIDO ---
    </script>


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

<div class="perfil-page-content">
    <?php
    // Verifica se existe uma mensagem de sucesso na sessão
    if (isset($_SESSION['mensagem_sucesso'])) {
        // Exibe a mensagem
        echo '<div class="success-message">' . $_SESSION['mensagem_sucesso'] . '</div>';
        // Remove a mensagem da sessão para não exibi-la novamente
        unset($_SESSION['mensagem_sucesso']);
    }
    ?>

    <div class="profile-card">
        <div class="image-container">
            <img src="<?php echo $caminhoFoto; ?>" alt="imagem de usuário" class="profile-picture">
            <form id="uploadForm" class="edit-icon-form" action="editaImagemUser.php" method="POST"
                enctype="multipart/form-data">
                <input type="file" name="imagemUser" class="file-upload-input" onchange="submitForm()">
                <span class="material-symbols-outlined">edit</span>
            </form>
        </div>
        <div class="text-content">
            <h1>Olá, <?php echo $primeiroNome; ?></h1>
            <p class="tagline">"ACHE gerenciamento de tarefas. Seja o melhor que você pode ser. <br>Utilize o
                gerenciamento de tarefas para facilitar a sua vida!"</p>
            <a href="logout.php" class="logout-link">Sair</a>
        </div>
    </div>

    <div class="profile-details">
        <h2>Detalhes da Conta</h2>

        <div class="info-item">
            <strong>Nome:</strong> <span><?php echo $primeiroNome; ?></span>
        </div>
        <div class="info-item">
            <strong>Email:</strong> <span><?php echo $emailUsuario; ?></span>
        </div>

        <div class="actions-container">
            <a href="editar-nome.php" class="action-button">Alterar Nome</a>
            <a href="editar-senha.php" class="action-button">Alterar Senha</a>
        </div>
    </div>

    <script>
        // Função para o upload da imagem (continua aqui)
        function submitForm() {
            document.getElementById('uploadForm').submit();
        }

        // A lógica do botão de toggle foi removida!
    </script>

</div>
</body>

</html>