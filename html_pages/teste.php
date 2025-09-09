<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exemplo de Menu de Contexto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"/>
    
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            padding: 20px;
        }

        h1 {
            color: #333;
        }

        p {
            color: #666;
        }

        /* Estilo da nossa lista de projetos de exemplo */
        .lista-projetos {
            list-style: none;
            padding: 0;
            max-width: 300px;
        }

        .item-projeto {
            background-color: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 5px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .item-projeto:hover {
            background-color: #e9ecef;
        }

        /* =================================
           ESTILOS DO MENU DE CONTEXTO
           ================================= */

        .menu-contexto {
            /* Começa escondido */
            display: none; 

            /* Posicionamento absoluto para flutuar na tela */
            position: absolute; 
            z-index: 1000; /* Garante que fique sobre outros elementos */

            /* Aparência */
            background-color: #ffffff;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.15);
            padding: 5px 0;
            min-width: 180px;
        }

        .menu-contexto ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .menu-contexto .menu-opcao {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            background: none;
            border: none;
            padding: 10px 15px;
            text-align: left;
            font-size: 0.9em;
            cursor: pointer;
            color: #333;
        }

        .menu-contexto .menu-opcao:hover {
            background-color: #f0f0f0;
        }

        .menu-contexto .menu-opcao-excluir:hover {
            background-color: #ffebee;
            color: #c62828;
        }

        .menu-contexto .menu-opcao i {
            width: 15px;
            text-align: center;
        }

    </style>
</head>
<body>

    <h1>Página de Exemplo</h1>
    <p>Clique com o botão direito nos projetos abaixo para ver o menu de contexto em ação.</p>

    <ul class="lista-projetos">
        <li class="item-projeto" data-id="101">Projeto ALPHA</li>
        <li class="item-projeto" data-id="102">Projeto BETA</li>
        <li class="item-projeto" data-id="103">Projeto CHARLIE</li>
    </ul>

    <div id="menu-contexto" class="menu-contexto">
        <ul>
            <li><button class="menu-opcao"><i class="fas fa-edit"></i> Editar Projeto</button></li>
            <li><button class="menu-opcao menu-opcao-excluir"><i class="fas fa-trash-alt"></i> Excluir Projeto</button></li>
        </ul>
    </div>


    <script>
        // 1. Pega os elementos do HTML que vamos usar
        const menuContexto = document.getElementById('menu-contexto');
        const itensProjeto = document.querySelectorAll('.item-projeto');

        // 2. Lógica para MOSTRAR o menu
        itensProjeto.forEach(item => {
            // Adiciona um "espião" de clique direito em cada item da lista
            item.addEventListener('contextmenu', function(event) {
                // Impede o menu padrão do navegador de aparecer (muito importante!)
                event.preventDefault();

                // Pega o ID do projeto que foi clicado, guardado no atributo 'data-id'
                const idDoItemClicado = event.target.dataset.id;
                console.log("ID do item com clique direito:", idDoItemClicado);
                
                // Guarda esse ID no próprio menu para uso futuro
                menuContexto.dataset.itemId = idDoItemClicado;

                // Posiciona o nosso menu personalizado nas coordenadas do clique
                menuContexto.style.top = `${event.pageY}px`;
                menuContexto.style.left = `${event.pageX}px`;
                
                // Finalmente, mostra o menu
                menuContexto.style.display = 'block';
            });
        });

        // 3. Lógica para ESCONDER o menu
        // Adiciona um "espião" de clique normal na página inteira
        window.addEventListener('click', function() {
            // Se o menu estiver visível, esconde-o
            if (menuContexto.style.display === 'block') {
                menuContexto.style.display = 'none';
            }
        });

    </script>
</body>
</html>