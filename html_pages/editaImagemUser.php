<?php
session_start();

// Segurança: Garante que o usuário está logado e que um arquivo foi enviado
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html");
    exit;
}

if (isset($_FILES['imagemUser']) && $_FILES['imagemUser']['error'] === 0) {
    
    require_once 'conexao.php';
    $idUsuarioLogado = $_SESSION['user_id'];
    $diretorioUploads = '../uploads/fotos_perfil/';

    $arquivo = $_FILES['imagemUser'];

    // --- 1. Validação do Arquivo ---
    $tamanhoMaximo = 5 * 1024 * 1024; // 5 MB
    if ($arquivo['size'] > $tamanhoMaximo) {
        $_SESSION['mensagem_erro'] = "O arquivo é muito grande! (Máximo 5MB)";
        header("Location: perfil.php");
        exit;
    }

    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($arquivo['type'], $tiposPermitidos)) {
        $_SESSION['mensagem_erro'] = "Tipo de arquivo não permitido! (Apenas JPG, PNG, GIF)";
        header("Location: perfil.php");
        exit;
    }

    // --- 2. Gerar um Nome Único para o Arquivo ---
    // Isso evita que um arquivo substitua outro e adiciona segurança
    $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
    $nomeUnico = uniqid('user_' . $idUsuarioLogado . '_', true) . '.' . $extensao;
    $caminhoCompleto = $diretorioUploads . $nomeUnico;

    // --- 3. Mover o Arquivo para a Pasta de Uploads ---
    if (move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
        
        // --- 4. Apagar a Imagem Antiga (se não for a padrão) ---
        // Primeiro, buscamos o caminho da foto antiga
        $sqlBusca = "SELECT FotoPerfil FROM usuarios WHERE ID_Usuario = ?";
        $stmtBusca = $conexao->prepare($sqlBusca);
        $stmtBusca->bind_param("i", $idUsuarioLogado);
        $stmtBusca->execute();
        $result = $stmtBusca->get_result();
        if ($row = $result->fetch_assoc()) {
            $fotoAntiga = $row['FotoPerfil'];
            // Se a foto antiga não for a padrão, apague o arquivo
            if ($fotoAntiga != '../images/user-img.png' && file_exists($fotoAntiga)) {
                unlink($fotoAntiga);
            }
        }
        $stmtBusca->close();

        // --- 5. Atualizar o Banco de Dados com o Novo Caminho ---
        $sql = "UPDATE usuarios SET FotoPerfil = ? WHERE ID_Usuario = ?";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("si", $caminhoCompleto, $idUsuarioLogado);

        if ($stmt->execute()) {
            $_SESSION['mensagem_sucesso'] = "Imagem de perfil alterada com sucesso!";
        } else {
            $_SESSION['mensagem_erro'] = "Erro ao salvar a imagem no banco de dados.";
        }
        $stmt->close();

    } else {
        $_SESSION['mensagem_erro'] = "Erro ao fazer o upload da imagem.";
    }

    $conexao->close();
} else {
    $_SESSION['mensagem_erro'] = "Nenhum arquivo enviado ou erro no upload.";
}

// Redireciona de volta para a página de perfil
header("Location: perfil.php");
exit;
?>