<?php
// Inicia a sessão para poder acessá-la
session_start();

// Remove todas as variáveis da sessão
$_SESSION = array();

// Destrói a sessão
session_destroy();

// Redireciona para a página de login
header("Location: index-N-logado.html");
exit;
?>