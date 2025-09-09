<?php
// Arquivo: login.php

// 1. INICIAR A SESSÃO
// A sessão é essencial para manter o usuário logado enquanto ele navega pelo site.
// session_start() deve ser a primeira coisa no seu script.
session_start();

// 2. INCLUIR O ARQUIVO DE CONEXÃO
require_once 'conexao.php';

// 3. VERIFICAR SE O FORMULÁRIO FOI ENVIADO
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 4. RECUPERAR E VALIDAR OS DADOS DO FORMULÁRIO
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];

    if (empty($email) || empty($senha)) {
        die("Erro: E-mail e senha são obrigatórios.");
    }

    // 5. BUSCAR O USUÁRIO NO BANCO DE DADOS PELO E-MAIL
    try {
        $sql = "SELECT ID_Usuario, Nome, Sobrenome, Senha FROM usuarios WHERE Email = ?";
        
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        
        // Obter o resultado da consulta
        $result = $stmt->get_result();

        // 6. VERIFICAR SE O USUÁRIO FOI ENCONTRADO
        if ($result->num_rows === 1) {
            // Usuário encontrado, agora vamos verificar a senha
            $usuario = $result->fetch_assoc();

            // 7. COMPARAR A SENHA FORNECIDA COM O HASH DO BANCO
            // password_verify() é a função correta para checar senhas criadas com password_hash()
            if (password_verify($senha, $usuario['Senha'])) {
                // Senha correta! Login bem-sucedido.
                
                // 8. ARMAZENAR DADOS DO USUÁRIO NA SESSÃO
                // Armazenamos informações úteis para não ter que buscar no banco a todo momento.
                // NUNCA armazene a senha na sessão.
                $_SESSION['user_id'] = $usuario['ID_Usuario'];
                $_SESSION['user_nome'] = $usuario['Nome'];
                $_SESSION['logged_in'] = true;

                // 9. REDIRECIONAR PARA A PÁGINA PRINCIPAL/DASHBOARD
                // Após o login, o usuário é enviado para a área restrita do site.
                header("Location: index.php"); // Vamos criar esta página em breve
                exit();

            } else {
                // Senha incorreta
                die("E-mail ou senha inválidos.");
            }
        } else {
            // Usuário (e-mail) não encontrado
            die("E-mail ou senha inválidos.");
        }

        $stmt->close();

    } catch (mysqli_sql_exception $e) {
        die("Erro no banco de dados: " . $e->getMessage());
    }

    $conexao->close();
} else {
    // Acesso direto ao arquivo não é permitido
    echo "Acesso inválido.";
}
?>