<?php
// Arquivo: cadastrar.php

// 1. INCLUIR O ARQUIVO DE CONEXÃO
require_once 'conexao.php'; // Lembre-se de ter o arquivo 'conexao.php' na mesma pasta

// 2. VERIFICAR SE O FORMULÁRIO FOI ENVIADO
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 3. RECUPERAR DADOS DO FORMULÁRIO
    $nomeCompleto = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    $confirmarSenha = $_POST['confirmar'];

    // 4. VALIDAR DADOS (SERVER-SIDE)
    $partesNome = explode(" ", $nomeCompleto, 2);
    $nome = $partesNome[0];
    $sobrenome = isset($partesNome[1]) ? $partesNome[1] : '';

    if (empty($nome) || empty($email) || empty($senha)) {
        die("Erro: Todos os campos obrigatórios devem ser preenchidos.");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Erro: Formato de e-mail inválido.");
    }
    if ($senha !== $confirmarSenha) {
        die("Erro: As senhas não coincidem.");
    }
    if (strlen($senha) < 6 || !preg_match('/[A-Z]/', $senha) || !preg_match('/[a-z]/', $senha) || !preg_match('/\d/', $senha) || !preg_match('/[^A-Za-z0-9]/', $senha)) {
        die("Erro: A senha não atende aos requisitos de segurança.");
    }

    // 5. CRIPTOGRAFAR A SENHA
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    // 6. PREPARAR E EXECUTAR A INSERÇÃO NO BANCO
    try {
        // Verificar se o e-mail já existe
        $sql_check = "SELECT ID_Usuario FROM usuarios WHERE Email = ?";
        $stmt_check = $conexao->prepare($sql_check);
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            die("Erro: Este e-mail já está cadastrado.");
        }
        $stmt_check->close();

        // Inserir o novo usuário
        $sql_insert = "INSERT INTO usuarios (Nome, Sobrenome, Email, Senha, DataCriacao) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conexao->prepare($sql_insert);
        $stmt->bind_param("ssss", $nome, $sobrenome, $email, $senhaHash);

        if ($stmt->execute()) {
            // Sucesso! Redireciona para o login.
            header("Location: login.html"); // Altere para o caminho correto da sua página de login
            exit();
        } else {
            echo "Erro ao realizar o cadastro. Por favor, tente novamente.";
        }
        $stmt->close();

    } catch (mysqli_sql_exception $e) {
        die("Erro no banco de dados: " . $e->getMessage());
    }

    // Fecha a conexão
    $conexao->close();

} else {
    // Acesso direto ao arquivo não é permitido
    echo "Acesso inválido.";
}
?>