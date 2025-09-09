<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Novo Projeto - ACHE</title>
    <link rel="stylesheet" href="../style/style-cadastro-projeto.css" />
</head>
<body>
    <div class="projeto-box">
        <h1>Novo Projeto</h1>
        <p class="subtitulo">Preencha os dados para iniciar um novo projeto.</p>

        <form action="criar-projeto.php" method="POST">
            <label for="nome_projeto">Nome do Projeto</label>
            <input type="text" id="nome_projeto" name="nome_projeto" required />

            <label for="descricao">Descrição</label>
            <textarea id="descricao" name="descricao" rows="4"></textarea>

            <label for="data_conclusao">Data de Conclusão Prevista</label>
            <input type="date" id="data_conclusao" name="data_conclusao" />

            <label for="busca_usuario">Adicionar Membros</label>
            <input type="text" id="busca_usuario" placeholder="Digite para buscar usuários..." autocomplete="off" />
            <div id="resultados_busca"></div> <div id="membros_selecionados">
                <p>Membros:</p>
                </div>
            
            <input type="hidden" name="membros" id="membros_ids" />

            <button type="submit">Criar Projeto</button>
            <div class="cancelar-link"><a href="index.php">Cancelar</a></div>
        </form>
    </div>

    <script>
        const buscaInput = document.getElementById('busca_usuario');
        const resultadosDiv = document.getElementById('resultados_busca');
        const selecionadosDiv = document.getElementById('membros_selecionados');
        const membrosHiddenInput = document.getElementById('membros_ids');
        let membrosSelecionados = new Map();

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