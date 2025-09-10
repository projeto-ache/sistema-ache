<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header("Location: login.html");
  exit;
}

$idUsuarioLogado = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$primeiroNome = htmlspecialchars((string) ($_SESSION['user_nome'] ?? ''), ENT_QUOTES, 'UTF-8');

require_once 'conexao.php';

// =================================================================
// ETAPA 1: CARREGAR TODOS OS DADOS (VERSÃO COMPLETA E OTIMIZADA)
// =================================================================
$projetos = []; // Array principal que vai guardar tudo

try {
  // --- 1. Obter a lista de IDs de todos os projetos do usuário ---
  $sql_ids = "SELECT DISTINCT p.ID_Projeto
                FROM projetos p
                LEFT JOIN projetos_usuarios pu ON p.ID_Projeto = pu.ID_Projeto
                WHERE p.ID_Usuario_Criador = ? OR pu.ID_Usuario = ?";
  $stmt_ids = $conexao->prepare($sql_ids);
  $stmt_ids->bind_param("ii", $idUsuarioLogado, $idUsuarioLogado);
  $stmt_ids->execute();
  $result_ids = $stmt_ids->get_result();
  $project_ids = [];
  while ($row = $result_ids->fetch_assoc()) {
    $project_ids[] = $row['ID_Projeto'];
  }
  $stmt_ids->close();

  // Se o usuário tiver projetos, busca todos os detalhes de uma vez.
  if (!empty($project_ids)) {

    $placeholders = implode(',', array_fill(0, count($project_ids), '?'));
    $types = str_repeat('i', count($project_ids));

    // --- 2. Buscar os detalhes de TODOS os projetos ---
    $sql_projetos = "SELECT p.ID_Projeto, p.NomeProjeto, p.Descricao, p.DataCriacao, u.Email AS EmailCriador 
                     FROM projetos p 
                     JOIN usuarios u ON p.ID_Usuario_Criador = u.ID_Usuario 
                     WHERE p.ID_Projeto IN ($placeholders)";
    $stmt_projetos = $conexao->prepare($sql_projetos);
    $stmt_projetos->bind_param($types, ...$project_ids);
    $stmt_projetos->execute();
    $result_projetos = $stmt_projetos->get_result();

    while ($projeto_data = $result_projetos->fetch_assoc()) {
      $id_projeto = $projeto_data['ID_Projeto'];
      $projetos[$id_projeto] = $projeto_data;
      $projetos[$id_projeto]['Participantes'] = [];
      $projetos[$id_projeto]['Tarefas'] = [];
    }
    $stmt_projetos->close();

    // --- 3. Buscar TODOS os participantes ---
    $sql_usuarios = "SELECT pu.ID_Projeto, u.Email 
                         FROM projetos_usuarios pu 
                         JOIN usuarios u ON pu.ID_Usuario = u.ID_Usuario 
                         WHERE pu.ID_Projeto IN ($placeholders)";
    $stmt_usuarios = $conexao->prepare($sql_usuarios);
    $stmt_usuarios->bind_param($types, ...$project_ids);
    $stmt_usuarios->execute();
    $result_usuarios = $stmt_usuarios->get_result();

    while ($usuario = $result_usuarios->fetch_assoc()) {
      $id_projeto = $usuario['ID_Projeto'];
      if (isset($projetos[$id_projeto])) {
        $projetos[$id_projeto]['Participantes'][] = htmlspecialchars($usuario['Email'], ENT_QUOTES, 'UTF-8');
      }
    }
    $stmt_usuarios->close();

    // --- 4. Buscar TODAS as tarefas com todos os detalhes necessários ---
    // Na "ETAPA 1" do seu index.php, atualize a consulta de tarefas
    $sql_tarefas = "SELECT 
                        pta.ID_Projeto,
                        pta.ID_Atribuicao, -- Importante para o GROUP BY
                        pta.Status, 
                        pta.DataCriacao,
                        pta.DataPrazo, 
                        pta.DataConclusao,
                        COALESCE(NULLIF(pta.NomeTarefaPersonalizado, ''), t.NomeTarefa) AS NomeTarefaExibicao,
                        pta.DescricaoPersonalizada,
                        t.NomeTarefa AS NomeTarefaOriginal,
                        -- CAMPOS NOVOS ADICIONADOS AQUI:
                        t.Classificacao_Ache,
                        t.Categoria_Ache,
                        t.Fase_Ache,
                        t.ComoFazer,
                        t.DocumentoReferencia,
                        -- A MÁGICA ACONTECE AQUI:
                        GROUP_CONCAT(u.Nome SEPARATOR ', ') AS NomesUsuariosAtribuidos
                    FROM 
                        projetos_tarefas_atribuidas pta 
                    JOIN 
                        tarefas t ON pta.ID_Tarefa = t.ID_Tarefa
                    LEFT JOIN -- Junta com a nossa nova tabela
                        tarefa_atribuida_usuarios tau ON pta.ID_Atribuicao = tau.ID_Atribuicao
                    LEFT JOIN -- Junta com a tabela de usuários para pegar os nomes
                        usuarios u ON tau.ID_Usuario = u.ID_Usuario
                    WHERE 
                        pta.ID_Projeto IN ($placeholders)
                    GROUP BY -- Agrupa os resultados para cada tarefa atribuída
                        pta.ID_Atribuicao";

    $stmt_tarefas = $conexao->prepare($sql_tarefas);
    $stmt_tarefas->bind_param($types, ...$project_ids);
    $stmt_tarefas->execute();
    $result_tarefas = $stmt_tarefas->get_result();

    while ($tarefa = $result_tarefas->fetch_assoc()) {
      $id_projeto = $tarefa['ID_Projeto'];
      if (isset($projetos[$id_projeto])) {
        $projetos[$id_projeto]['Tarefas'][] = $tarefa;
      }
    }
    $stmt_tarefas->close();
  }

  // Reorganiza o array para que a parte do HTML possa usá-lo com um loop simples.
  $projetos = array_values($projetos);

  // ADICIONE ESTA LINHA AQUI
  $projetos_json = json_encode($projetos);

} catch (Exception $e) {
  $conexao->close();
  die("Erro ao buscar os dados dos projetos: " . $e->getMessage());
}

$conexao->close();

// =================================================================
// ETAPA 2: RENDERIZAR O HTML (AGORA COM OS DADOS JÁ CARREGADOS)
// =================================================================
?>


<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <link rel="stylesheet" href="../style/style-chatbot.css">
  <link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
  <link rel="stylesheet" href="../style/style.css" />
  <title>Index</title>
</head>

<body>

  <div id="calendario-popup" class="calendario-popup" style="display: none;">
    <div class="calendario-popup-content">
      <div class="calendario-popoup-top">
        <div class="calendario-popoup-top-left">
          <div class="calendario-popoup-top-title">
            <p id="popup-title">Título da Tarefa</p>
          </div>
        </div>
        <div class="calendario-popoup-top-right">
          <button class="close-button">&times;</button>
        </div>
      </div>
      <div class="calendario-popoup-midle">
        <a href="#" id="popup-edit-btn"><button>
            <div class="calendario-popoup-midle-edit">
              <p>Editar tarefa</p>
            </div>
          </button></a>
        <button id="popup-delete-btn">
          <div class="calendario-popoup-midle-exclude">
            <p>Excluir tarefa</p>
          </div>
        </button>
      </div>
      <div class="calendario-popup-down">
        <div class="calendario-popup-down-part">
          <div class="calendario-popup-down-title">
            <p>Projeto:</p>
          </div>
          <div class="calendario-popup-down-answer">
            <p id="popup-project">Nome do Projeto</p>
          </div>
        </div>
        <div class="calendario-popup-down-part">
          <div class="calendario-popup-down-title">
            <p>Prazo final:</p>
          </div>
          <div class="calendario-popup-down-answer">
            <p id="popup-duedate">DD/MM/YYYY</p>
          </div>
        </div>
        <div class="calendario-popup-down-part">
          <div class="calendario-popup-down-title">
            <p>Status:</p>
          </div>
          <div class="calendario-popup-down-answer">
            <p id="popup-status">Status da Tarefa</p>
          </div>
        </div>
      </div>
    </div>
  </div>



  <div id="modal-tarefa-detalhes" class="modal-overlay" style="display: none;">
    <div class="modal-content">
      <button id="modal-close-btn" class="modal-close-btn">&times;</button>
      <div id="modal-tarefa-body">
      </div>
    </div>
  </div>



  <div id="menu-contexto-projeto" class="menu-contexto">
    <ul>
      <li><button class="menu-opcao menu-opcao-editar"><i class="fas fa-edit"></i> Editar</button></li>
      <li><button class="menu-opcao menu-opcao-excluir"><i class="fas fa-trash-alt"></i> Excluir</button></li>
    </ul>
  </div>



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
  <div class="index-content">


    <div class="left-main">
      <div class="left-main-new-project-content">
        <div class="new-project-button">
          <a href="novo-projeto.php"><button>
              <p>Novo Projeto</p>
            </button></a>
        </div>
        <div class="new-project-plus">
          <button>
            <span class="material-symbols-outlined">
              keyboard_arrow_down
            </span>
          </button>
        </div>
      </div>
      <div class="left-main-favorites-content">
        <div class="favorites-title-content">
          <button class="btn-cls-favorites-atalhos">
            <div class="favorites-title">
              <p>Favoritos</p>
            </div>
            <div class="favorites-plus">
              <span class="material-symbols-outlined">
                keyboard_arrow_down
              </span>
            </div>
          </button>
        </div>

        <div class="favorites-atalhos-content">
          <div class="favorites-atalhos-ex1">
            <button class="btn-menu-user-projects">
              <div class="title-favorites-atalhos">
                <p>Seus projetos</p>
              </div>
            </button>
            <div class="button-favorites-atalhos-content">
              <button>
                <div class="button-favorites-atalhos">
                  <p>1109</p>
                </div>
                <div class="button-favorites-atalhos-hover">
                  <span class="material-symbols-outlined"> more_horiz </span>
                </div>
              </button>
            </div>
          </div>
          <div class="favorites-atalhos-ex1">
            <button class="btn-menu-user-projects" id="btn-mostrar-calendario-sidebar">
              <div class="title-favorites-atalhos">
                <p>Calendário</p>
              </div>
            </button>
            <div class="button-favorites-atalhos-content">
              <button>
                <div class="button-favorites-atalhos">
                  <p>101</p>
                </div>
                <div class="button-favorites-atalhos-hover">
                  <span class="material-symbols-outlined"> more_horiz </span>
                </div>
              </button>
            </div>
          </div>
          <div class="favorites-atalhos-ex1">
            <button class="btn-menu-user-projects">
              <div class="title-favorites-atalhos">
                <p>Atribuídos</p>
              </div>
            </button>
            <div class="button-favorites-atalhos-content">
              <button>
                <div class="button-favorites-atalhos">
                  <p>31</p>
                </div>
                <div class="button-favorites-atalhos-hover">
                  <span class="material-symbols-outlined"> more_horiz </span>
                </div>
              </button>
            </div>
          </div>
        </div>
      </div>



      <div class="user-projects-atalhos-content">
        <div class="left-user-space-content">
          <div class="favorites-title-content">
            <button class="btn-cls-user-space-atalhos">
              <div class="favorites-title">
                <p>Seu espaço</p>
              </div>
              <div class="favorites-plus">
                <span class="material-symbols-outlined">
                  keyboard_arrow_down
                </span>
              </div>
            </button>
          </div>



          <div class="user-atalhos-content">
            <?php foreach ($projetos as $projeto): ?>
              <div class="favorites-atalhos-ex1">
                <button class="title-favorites-atalhos btn-mostrar-projeto"
                  data-id-projeto="<?php echo $projeto['ID_Projeto']; ?>">
                  <p><?php echo htmlspecialchars($projeto['NomeProjeto']); ?></p>
                </button>
                <div class="button-favorites-atalhos-content">
                  <button class="btn-opcoes-projeto" data-id-projeto="<?php echo $projeto['ID_Projeto']; ?>">
                    <div class="button-favorites-atalhos-hover">
                      <span class="material-symbols-outlined">more_horiz</span>
                    </div>
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>



        </div>
      </div>
    </div>


    <div class="right-main">



      <div class="right-main-calendar-view" style="display: none;">
        <div class="calendar-page">
          <div class="calendar-header">
            <button id="prev-month-btn" class="calendar-nav-button">&lt;</button>
            <h2 id="month-year-header">Carregando...</h2>
            <button id="next-month-btn" class="calendar-nav-button">&gt;</button>
          </div>

          <div class="calendar-filters">
            <div class="filter-group">
              <label for="project-filter"><i class="fas fa-briefcase"></i> Projeto:</label>
              <select id="project-filter">
                <option value="all">Todos os Projetos</option>
                <?php foreach ($projetos as $projeto): ?>
                  <option value="<?php echo htmlspecialchars($projeto['NomeProjeto']); ?>">
                    <?php echo htmlspecialchars($projeto['NomeProjeto']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="filter-group status-filters">
              <label><i class="fas fa-check-circle"></i> Status:</label>
              <div class="status-checkboxes">
                <label>
                  <input type="checkbox" name="status" value="A Fazer" checked>
                  <span class="status-indicator status-a-fazer"></span> A Fazer
                </label>
                <label>
                  <input type="checkbox" name="status" value="Em Andamento" checked>
                  <span class="status-indicator status-em-andamento"></span> Em Andamento
                </label>
                <label>
                  <input type="checkbox" name="status" value="Concluído" checked>
                  <span class="status-indicator status-concluido"></span> Concluído
                </label>
              </div>
            </div>
          </div>

          <div class="calendar-weekdays">
            <div>Dom</div>
            <div>Seg</div>
            <div>Ter</div>
            <div>Qua</div>
            <div>Qui</div>
            <div>Sex</div>
            <div>Sáb</div>
          </div>
          <div class="calendar-grid" id="calendar-days-grid">
          </div>
        </div>
      </div>




      <div class="exibicao-selection-project-space-direct">

        <?php if (!empty($projetos)): ?>
          <?php foreach ($projetos as $projeto): ?>
            <div class="exibicao-selection-project-space-content" id="project-info-<?php echo $projeto['ID_Projeto']; ?>">
              <div class="exibicao-selection-project-space-top">
                <div class="exibicao-selection-project-space-top-project-name">
                  <p><?php echo htmlspecialchars($projeto['NomeProjeto']); ?></p>
                </div>
                <button class="btn-kanbam">
                  <div class="exibicao-selection-project-space-top-project-kanbam">
                    <div class="exibicao-selection-project-space-top-project-kanbam-icon">
                      <img src="..\images\label-purple.png" alt="" width="35px" height="35px" />
                      <img src="..\images\label-green.png" alt="" width="35px" height="35px" />
                      <img src="..\images\label-yellow.png" alt="" width="35px" height="35px" />
                    </div>
                    <div class="exibicao-selection-project-space-top-project-kanbam-p">
                      <p>Kanbam</p>
                    </div>
                  </div>
                </button>
              </div>
              <div class="exibicao-selection-project-space-creator-space">
                <p>Criador: <?php echo htmlspecialchars($projeto['EmailCriador']); ?></p>
              </div>
              <div class="exibicao-selection-project-space-workers-space">
                <p>Para: <?php echo !empty($projeto['Participantes']) ? implode('; ', $projeto['Participantes']) : '—'; ?>
                </p>
              </div>
              <div class="exibicao-selection-project-space-description-content">
                <div class="exibicao-selection-project-space-description-title">
                  <p>Descrição:</p>
                </div>
                <div class="exibicao-selection-project-space-description-text">
                  <p><?php echo nl2br(htmlspecialchars($projeto['Descricao'])); ?></p>
                </div>
              </div>
              <div class="exibicao-selection-project-space-tarefas-contents">
                <div class="exibicao-selection-project-space-tarefas-title">
                  <p>Tarefas:</p>
                </div>
                <div class="exibicao-selection-project-space-tarefas-addnew-button">
                  <button>
                    <div class="exibicao-selection-project-space-tarefas-addnew-button-title">
                      <a href="nova-tarefa.php?id_projeto=<?php echo $projeto['ID_Projeto']; ?>">
                        <p>Criar Nova Tarefa</p>
                      </a>
                    </div>
                    <div class="exibicao-selection-project-space-tarefas-addnew-button-plus">
                      <p>+</p>
                    </div>
                  </button>
                </div>
                <div class="exibicao-selection-project-space-tarefas-exibir-tarefas">
                  <?php if (empty($projeto['Tarefas'])): ?>
                    <p style="padding: 15px; color: #888;">Nenhuma tarefa foi adicionada a este projeto ainda.</p>
                  <?php else: ?>
                    <?php foreach ($projeto['Tarefas'] as $tarefa): ?>
                      <div class="exibicao-selection-project-tarefa tarefa-clicavel">
                        <div class="exibicao-selection-project-tarefa-top">
                          <div class="exibicao-selection-project-tarefa-top-img-status">
                            <?php
                            $status_imagem = 'like-purple.png';
                            if ($tarefa['Status'] == 'Em Andamento') {
                              $status_imagem = 'like-yellow.png';
                            } elseif ($tarefa['Status'] == 'Concluído') {
                              $status_imagem = 'like-green.png';
                            }
                            ?>
                            <img src="..\images\<?php echo $status_imagem; ?>"
                              alt="Status: <?php echo htmlspecialchars($tarefa['Status']); ?>" width="25px" height="25px" />
                          </div>
                          <div class="exibicao-selection-project-tarefa-top-text">
                            <p><?php echo htmlspecialchars($tarefa['NomeTarefaExibicao']); ?></p>
                          </div>
                        </div>
                        <div class="exibicao-selection-project-tarefa-bottom">
                          <div class="exibicao-selection-project-tarefa-bottom-left">
                            <div class="exibicao-selection-project-tarefa-bottom-left-atribuido">
                              <p>Atribuído em: <?php echo date('d/m/Y', strtotime($tarefa['DataCriacao'])); ?></p>
                            </div>
                            <div class="exibicao-selection-project-tarefa-bottom-left-prazo">
                              <p>Prazo: <?php echo date('d/m/Y', strtotime($tarefa['DataPrazo'])); ?></p>
                            </div>
                            <div class="exibicao-selection-project-tarefa-bottom-left-conclusao">
                              <p>Concluído:
                                <?php echo $tarefa['DataConclusao'] ? date('d/m/Y', strtotime($tarefa['DataConclusao'])) : '--'; ?>
                              </p>
                            </div>
                          </div>
                          <div class="exibicao-selection-project-tarefa-bottom-right">
                            <div class="exibicao-selection-project-tarefa-bottom-right-img">
                              <img src="..\images\user-img.png" alt="Usuário Atribuído" width="50px" height="50px" />
                            </div>
                          </div>
                        </div>


                        <div class="tarefa-detalhes-expansivel">
                          <hr class="tarefa-divisor">


                          <div class="detalhe-item">
                            <span class="detalhe-label">Atribuído para:</span>
                            <span
                              class="detalhe-valor"><?php echo htmlspecialchars($tarefa['NomesUsuariosAtribuidos'] ?? 'Não atribuído'); ?></span>
                          </div>
                          <?php if (!empty($tarefa['DescricaoPersonalizada'])): ?>
                            <div class="detalhe-item">
                              <span class="detalhe-label">Tarefa Padrão Original:</span>
                              <p class="detalhe-descricao"><?php echo nl2br(htmlspecialchars($tarefa['NomeTarefaOriginal'])); ?>
                              </p>
                            </div>
                            <div class="detalhe-item">
                              <span class="detalhe-label">Descrição Personalizada:</span>
                              <span
                                class="detalhe-valor"><?php echo htmlspecialchars($tarefa['DescricaoPersonalizada']); ?></span>
                            </div>
                          <?php endif; ?>

                          <?php if (!empty($tarefa['Classificacao_Ache'])): ?>
                            <div class="detalhe-item">
                              <span class="detalhe-label">Classificação:</span>
                              <span class="detalhe-valor"><?php echo htmlspecialchars($tarefa['Classificacao_Ache']); ?></span>
                            </div>
                          <?php endif; ?>

                          <?php if (!empty($tarefa['Categoria_Ache'])): ?>
                            <div class="detalhe-item">
                              <span class="detalhe-label">Categoria:</span>
                              <span class="detalhe-valor"><?php echo htmlspecialchars($tarefa['Categoria_Ache']); ?></span>
                            </div>
                          <?php endif; ?>

                          <?php if (!empty($tarefa['Fase_Ache'])): ?>
                            <div class="detalhe-item">
                              <span class="detalhe-label">Fase:</span>
                              <span class="detalhe-valor"><?php echo htmlspecialchars($tarefa['Fase_Ache']); ?></span>
                            </div>
                          <?php endif; ?>

                          <?php if (!empty($tarefa['ComoFazer'])): ?>
                            <div class="detalhe-item">
                              <span class="detalhe-label">Como Fazer:</span>
                              <p class="detalhe-descricao"><?php echo nl2br(htmlspecialchars($tarefa['ComoFazer'])); ?></p>
                            </div>
                          <?php endif; ?>

                          <?php if (!empty($tarefa['DocumentoReferencia'])): ?>
                            <div class="detalhe-item">
                              <span class="detalhe-label">Documento de Referência:</span>
                              <span class="detalhe-valor"><?php echo htmlspecialchars($tarefa['DocumentoReferencia']); ?></span>
                            </div>
                          <?php endif; ?>

                          <div class="tarefa-actions-container">
                            <a href="editar-tarefa.php?id=<?php echo $tarefa['ID_Atribuicao']; ?>" class="btn-editar-tarefa">
                              <i class="fas fa-edit"></i> Editar Tarefa
                            </a>
                            <button type="button" class="btn-excluir-tarefa"
                              data-id-atribuicao="<?php echo $tarefa['ID_Atribuicao']; ?>">
                              <i class="fas fa-trash-alt"></i> Excluir
                            </button>
                          </div>


                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($projetos)): ?>
          <?php foreach ($projetos as $projeto): ?>
            <div class="exibicao-selection-project-space-kanbam-content"
              id="project-kanban-<?php echo $projeto['ID_Projeto']; ?>" style="display: none;">
              <div class="exibicao-selection-project-space-kanbam-top">
                <div class="exibicao-selection-project-space-kanbam-top-project-name">
                  <p><?php echo htmlspecialchars($projeto['NomeProjeto']); ?></p>
                </div>

                <button class="btn-gerenciamento">
                  <div class="exibicao-selection-project-space-kanbam-top-return-button">
                    <div class="exibicao-selection-project-space-kanbam-top-return-button-img">
                      <img src="..\images\return-icon.png" alt="" width="35px" height="35px" />
                    </div>
                    <div class="exibicao-selection-project-space-kanbam-top-return-button-text">
                      <p>Gerênciamento</p>
                    </div>
                  </div>
                </button>
              </div>

              <div class="exibicao-selection-project-space-creator-space">
                <p>Criador: <?php echo htmlspecialchars($projeto['EmailCriador']); ?></p>
              </div>

              <div class="exibicao-selection-project-space-workers-space">
                <p>Para: <?php echo !empty($projeto['Participantes']) ? implode('; ', $projeto['Participantes']) : '—'; ?>
                </p>
              </div>

              <div class="exibicao-selection-project-space-description-content">
                <div class="exibicao-selection-project-space-description-title">
                  <p>Descricao:</p>
                </div>
                <div class="exibicao-selection-project-space-description-text">
                  <p><?php echo nl2br(htmlspecialchars($projeto['Descricao'])); ?></p>
                </div>
              </div>

              <div class="exibicao-selection-project-space-tarefas-contents">
                <div class="exibicao-selection-project-space-tarefas-title">
                  <p>Tarefas:</p>
                </div>






                <div class="kanbam-board-content">

                  <div class="kanbam-collum">
                    <div class="kanbam-collum-top">
                      <div class="kanbam-collum-top-icon"><img src="..\images\like-purple.png" alt="" width="35px"
                          height="35px"></div>
                      <div class="kanbam-collum-top-text">
                        <p>A fazer:</p>
                      </div>
                    </div>
                    <div class="kanbam-collum-new-tarefa-button-content">
                      <a href="nova-tarefa.php?id_projeto=<?php echo $projeto['ID_Projeto']; ?>"
                        class="kanbam-add-task-link">
                        <button>
                          <div class="kanbam-collum-new-tarefa-button">
                            <div class="kanbam-collum-new-tarefa-button-text">
                              <p>Criar Nova Tarefa</p>
                            </div>
                            <div class="kanbam-collum-new-tarefa-button-plus">
                              <p>+</p>
                            </div>
                          </div>
                        </button>
                      </a>
                    </div>
                    <div class="kanbam-collum-new-tarefa-content" data-status="A Fazer">
                      <?php foreach ($projeto['Tarefas'] as $tarefa): ?>
                        <?php if ($tarefa['Status'] == 'A Fazer'): ?>
                          <div class="kanbam-collum-todo-tarefa" draggable="true"
                            data-id-atribuicao="<?php echo $tarefa['ID_Atribuicao']; ?>">
                            <p><?php echo htmlspecialchars($tarefa['NomeTarefaExibicao']); ?></p>
                          </div>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <div class="kanbam-collum">
                    <div class="kanbam-collum-top">
                      <div class="kanbam-collum-top-icon"><img src="..\images\like-yellow.png" alt="" width="35px"
                          height="35px"></div>
                      <div class="kanbam-collum-top-text">
                        <p>Em Andamento:</p>
                      </div>
                    </div>
                    <div class="kanbam-collum-new-tarefa-button-content">
                      <a href="nova-tarefa.php?id_projeto=<?php echo $projeto['ID_Projeto']; ?>"
                        class="kanbam-add-task-link">
                        <button>
                          <div class="kanbam-collum-new-tarefa-button">
                            <div class="kanbam-collum-new-tarefa-button-text">
                              <p>Criar Nova Tarefa</p>
                            </div>
                            <div class="kanbam-collum-new-tarefa-button-plus">
                              <p>+</p>
                            </div>
                          </div>
                        </button>
                      </a>
                    </div>
                    <div class="kanbam-collum-new-tarefa-content" data-status="Em Andamento">
                      <?php foreach ($projeto['Tarefas'] as $tarefa): ?>
                        <?php if ($tarefa['Status'] == 'Em Andamento'): ?>
                          <div class="kanbam-collum-doing-tarefa" draggable="true"
                            data-id-atribuicao="<?php echo $tarefa['ID_Atribuicao']; ?>">
                            <p><?php echo htmlspecialchars($tarefa['NomeTarefaExibicao']); ?></p>
                          </div>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <div class="kanbam-collum">
                    <div class="kanbam-collum-top">
                      <div class="kanbam-collum-top-icon"><img src="..\images\like-green.png" alt="" width="35px"
                          height="35px"></div>
                      <div class="kanbam-collum-top-text">
                        <p>Concluído:</p>
                      </div>
                    </div>
                    <div class="kanbam-collum-new-tarefa-button-content">
                      <a href="nova-tarefa.php?id_projeto=<?php echo $projeto['ID_Projeto']; ?>"
                        class="kanbam-add-task-link">
                        <button>
                          <div class="kanbam-collum-new-tarefa-button">
                            <div class="kanbam-collum-new-tarefa-button-text">
                              <p>Criar Nova Tarefa</p>
                            </div>
                            <div class="kanbam-collum-new-tarefa-button-plus">
                              <p>+</p>
                            </div>
                          </div>
                        </button>
                      </a>
                    </div>
                    <div class="kanbam-collum-new-tarefa-content" data-status="Concluído">
                      <?php foreach ($projeto['Tarefas'] as $tarefa): ?>
                        <?php if ($tarefa['Status'] == 'Concluído'): ?>
                          <div class="kanbam-collum-done-tarefa" draggable="true"
                            data-id-atribuicao="<?php echo $tarefa['ID_Atribuicao']; ?>">
                            <p><?php echo htmlspecialchars($tarefa['NomeTarefaExibicao']); ?></p>
                          </div>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>






              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

      </div>








      <div class="right-main-user-projects">

        <div class="left-main-flexible-menu">
          <div class="left-main-new-project-content-flexible-menu">
            <div class="new-project-button-flexible-menu">
              <button id="btn-open-filter">
                <p>Seus projetos</p>
              </button>
            </div>
            <div class="new-project-plus-flexible-menu">
              <button id="btn-open-filter">
                <span class="material-symbols-outlined"> filter_alt </span>
              </button>
            </div>
          </div>
          <div class="left-main-favorites-content-flexible-menu" id="project-list-container">
            <?php if (empty($projetos)): ?>
              <p style="padding: 15px; color: #888; font-size: 0.9em;">Você ainda não tem projetos. Crie um novo!</p>
            <?php else: ?>
              <?php foreach ($projetos as $projeto): ?>
                <div class="favorites-atalhos-content-flexible-menu">
                  <div class="favorites-atalhos-ex1-flexible-menu">
                    <div class="main-favorites-atalhos-ex1-flexible-menu">
                      <button class="btn-exb-projeto" data-id-projeto="<?php echo $projeto['ID_Projeto']; ?>">
                        <div class="title-favorites-atalhos-flexible-menu">
                          <p><?php echo htmlspecialchars($projeto['NomeProjeto']); ?></p>
                        </div>
                      </button>
                      <div class="button-favorites-atalhos-content-flexible-menu">
                        <button>
                          <span class="material-symbols-outlined"> flag_2 </span>
                          <span class="material-symbols-outlined"> keep </span>
                        </button>
                      </div>
                    </div>
                    <div class="button-favorites-atalhos-content-flexible-menu-att">
                      <p>Ultima att. Mateus atualizou uma tarefa</p>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>





        <div class="exibicao-selection-project-space">
          <?php if (!empty($projetos)): ?>
            <?php foreach ($projetos as $projeto): ?>
              <div class="exibicao-selection-project-space-content" id="project-info-<?php echo $projeto['ID_Projeto']; ?>"
                style="display: none;">
                <div class="exibicao-selection-project-space-top">
                  <div class="exibicao-selection-project-space-top-project-name">
                    <p><?php echo htmlspecialchars($projeto['NomeProjeto']); ?></p>
                  </div>

                  <button class="btn-kanbam">
                    <div class="exibicao-selection-project-space-top-project-kanbam">
                      <div class="exibicao-selection-project-space-top-project-kanbam-icon">
                        <img src="..\images\label-purple.png" alt="" width="35px" height="35px" />
                        <img src="..\images\label-green.png" alt="" width="35px" height="35px" />
                        <img src="..\images\label-yellow.png" alt="" width="35px" height="35px" />
                      </div>

                      <div class="exibicao-selection-project-space-top-project-kanbam-p">
                        <p>Kanbam</p>
                      </div>
                    </div>
                  </button>

                </div>

                <div class="exibicao-selection-project-space-creator-space">
                  <p>Criador: <?php echo htmlspecialchars($projeto['EmailCriador']); ?></p>
                </div>

                <div class="exibicao-selection-project-space-workers-space">
                  <p>Para: <?php echo !empty($projeto['Participantes']) ? implode('; ', $projeto['Participantes']) : '—'; ?>
                  </p>
                </div>

                <div class="exibicao-selection-project-space-description-content">
                  <div class="exibicao-selection-project-space-description-title">
                    <p>Descrição:</p>
                  </div>
                  <div class="exibicao-selection-project-space-description-text">
                    <p><?php echo nl2br(htmlspecialchars($projeto['Descricao'])); ?></p>
                  </div>
                </div>

                <div class="exibicao-selection-project-space-tarefas-contents">
                  <div class="exibicao-selection-project-space-tarefas-title">
                    <p>Tarefas:</p>
                  </div>


                  <div class="exibicao-selection-project-space-tarefas-addnew-button">
                    <button>
                      <div class="exibicao-selection-project-space-tarefas-addnew-button-title">
                        <a href="nova-tarefa.php?id_projeto=<?php echo $projeto['ID_Projeto']; ?>">
                          <p>Criar Nova Tarefa</p>
                        </a>
                      </div>
                      <div class="exibicao-selection-project-space-tarefas-addnew-button-plus">
                        <p>+</p>
                      </div>
                    </button>
                  </div>


                  <div class="exibicao-selection-project-space-tarefas-exibir-tarefas">


                    <!-- Padrão para adicionar tarefa -->
                    <?php if (empty($projeto['Tarefas'])): ?>
                      <p style="padding: 15px; color: #888;">Nenhuma tarefa foi adicionada a este projeto ainda.</p>
                    <?php else: ?>
                      <?php foreach ($projeto['Tarefas'] as $tarefa): ?>

                        <div class="exibicao-selection-project-tarefa tarefa-clicavel">

                          <div class="exibicao-selection-project-tarefa-top">
                            <div class="exibicao-selection-project-tarefa-top-img-status">
                              <?php
                              // Lógica para mudar a imagem de status
                              $status_imagem = 'like-purple.png'; // Padrão 'A Fazer'
                              if ($tarefa['Status'] == 'Em Andamento') {
                                $status_imagem = 'like-yellow.png';
                              } elseif ($tarefa['Status'] == 'Concluído') {
                                $status_imagem = 'like-green.png';
                              }
                              ?>
                              <img src="..\images\<?php echo $status_imagem; ?>"
                                alt="Status: <?php echo htmlspecialchars($tarefa['Status']); ?>" width="25px" height="25px" />
                            </div>
                            <div class="exibicao-selection-project-tarefa-top-text">
                              <p><?php echo htmlspecialchars($tarefa['NomeTarefaExibicao']); ?></p>
                            </div>
                          </div>
                          <div class="exibicao-selection-project-tarefa-bottom">
                            <div class="exibicao-selection-project-tarefa-bottom-left">
                              <div class="exibicao-selection-project-tarefa-bottom-left-atribuido">
                                <p>Atribuído em: <?php echo date('d/m/Y', strtotime($tarefa['DataCriacao'])); ?></p>
                              </div>
                              <div class="exibicao-selection-project-tarefa-bottom-left-prazo">
                                <p>Prazo: <?php echo date('d/m/Y', strtotime($tarefa['DataPrazo'])); ?></p>
                              </div>
                              <div class="exibicao-selection-project-tarefa-bottom-left-conclusao">
                                <p>Concluído:
                                  <?php echo $tarefa['DataConclusao'] ? date('d/m/Y', strtotime($tarefa['DataConclusao'])) : '--'; ?>
                                </p>
                              </div>
                            </div>
                            <div class="exibicao-selection-project-tarefa-bottom-right">
                              <div class="exibicao-selection-project-tarefa-bottom-right-img">
                                <img src="..\images\user-img.png" alt="Usuário Atribuído" width="50px" height="50px" />
                              </div>
                            </div>
                          </div>
                          <div class="tarefa-detalhes-expansivel">
                            <hr class="tarefa-divisor">


                            <div class="detalhe-item">
                              <span class="detalhe-label">Atribuído para:</span>
                              <span
                                class="detalhe-valor"><?php echo htmlspecialchars($tarefa['NomesUsuariosAtribuidos'] ?? 'Não atribuído'); ?></span>
                            </div>
                            <?php if (!empty($tarefa['DescricaoPersonalizada'])): ?>
                              <div class="detalhe-item">
                                <span class="detalhe-label">Tarefa Padrão Original:</span>
                                <p class="detalhe-descricao"><?php echo nl2br(htmlspecialchars($tarefa['NomeTarefaOriginal'])); ?>
                                </p>
                              </div>
                              <div class="detalhe-item">
                                <span class="detalhe-label">Descrição Personalizada:</span>
                                <span
                                  class="detalhe-valor"><?php echo htmlspecialchars($tarefa['DescricaoPersonalizada']); ?></span>
                              </div>
                            <?php endif; ?>

                            <?php if (!empty($tarefa['Classificacao_Ache'])): ?>
                              <div class="detalhe-item">
                                <span class="detalhe-label">Classificação:</span>
                                <span class="detalhe-valor"><?php echo htmlspecialchars($tarefa['Classificacao_Ache']); ?></span>
                              </div>
                            <?php endif; ?>

                            <?php if (!empty($tarefa['Categoria_Ache'])): ?>
                              <div class="detalhe-item">
                                <span class="detalhe-label">Categoria:</span>
                                <span class="detalhe-valor"><?php echo htmlspecialchars($tarefa['Categoria_Ache']); ?></span>
                              </div>
                            <?php endif; ?>

                            <?php if (!empty($tarefa['Fase_Ache'])): ?>
                              <div class="detalhe-item">
                                <span class="detalhe-label">Fase:</span>
                                <span class="detalhe-valor"><?php echo htmlspecialchars($tarefa['Fase_Ache']); ?></span>
                              </div>
                            <?php endif; ?>

                            <?php if (!empty($tarefa['ComoFazer'])): ?>
                              <div class="detalhe-item">
                                <span class="detalhe-label">Como Fazer:</span>
                                <p class="detalhe-descricao"><?php echo nl2br(htmlspecialchars($tarefa['ComoFazer'])); ?></p>
                              </div>
                            <?php endif; ?>

                            <?php if (!empty($tarefa['DocumentoReferencia'])): ?>
                              <div class="detalhe-item">
                                <span class="detalhe-label">Documento de Referência:</span>
                                <span class="detalhe-valor"><?php echo htmlspecialchars($tarefa['DocumentoReferencia']); ?></span>
                              </div>
                            <?php endif; ?>

                            <div class="tarefa-actions-container">
                              <a href="editar-tarefa.php?id=<?php echo $tarefa['ID_Atribuicao']; ?>" class="btn-editar-tarefa">
                                <i class="fas fa-edit"></i> Editar Tarefa
                              </a>
                              <button type="button" class="btn-excluir-tarefa"
                                data-id-atribuicao="<?php echo $tarefa['ID_Atribuicao']; ?>">
                                <i class="fas fa-trash-alt"></i> Excluir
                              </button>
                            </div>


                          </div>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                    <!-- FIM - Padrão para adicionar tarefa -->







                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>


          <?php if (!empty($projetos)): ?>
            <?php foreach ($projetos as $projeto): ?>
              <div class="exibicao-selection-project-space-kanbam-content"
                id="project-kanban-<?php echo $projeto['ID_Projeto']; ?>" style="display: none;">
                <div class="exibicao-selection-project-space-kanbam-top">
                  <div class="exibicao-selection-project-space-kanbam-top-project-name">
                    <p><?php echo htmlspecialchars($projeto['NomeProjeto']); ?></p>
                  </div>

                  <button class="btn-gerenciamento">
                    <div class="exibicao-selection-project-space-kanbam-top-return-button">
                      <div class="exibicao-selection-project-space-kanbam-top-return-button-img">
                        <img src="..\images\return-icon.png" alt="" width="35px" height="35px" />
                      </div>
                      <div class="exibicao-selection-project-space-kanbam-top-return-button-text">
                        <p>Gerênciamento</p>
                      </div>
                    </div>
                  </button>
                </div>

                <div class="exibicao-selection-project-space-creator-space">
                  <p>Criador: <?php echo htmlspecialchars($projeto['EmailCriador']); ?></p>
                </div>

                <div class="exibicao-selection-project-space-workers-space">
                  <p>Para: <?php echo !empty($projeto['Participantes']) ? implode('; ', $projeto['Participantes']) : '—'; ?>
                  </p>
                </div>

                <div class="exibicao-selection-project-space-description-content">
                  <div class="exibicao-selection-project-space-description-title">
                    <p>Descricao:</p>
                  </div>
                  <div class="exibicao-selection-project-space-description-text">
                    <p><?php echo nl2br(htmlspecialchars($projeto['Descricao'])); ?></p>
                  </div>
                </div>

                <div class="exibicao-selection-project-space-tarefas-contents">
                  <div class="exibicao-selection-project-space-tarefas-title">
                    <p>Tarefas:</p>
                  </div>






                  <div class="kanbam-board-content">

                    <div class="kanbam-collum">
                      <div class="kanbam-collum-top">
                        <div class="kanbam-collum-top-icon"><img src="..\images\like-purple.png" alt="" width="35px"
                            height="35px"></div>
                        <div class="kanbam-collum-top-text">
                          <p>A fazer:</p>
                        </div>
                      </div>
                      <div class="kanbam-collum-new-tarefa-button-content">
                        <a href="nova-tarefa.php?id_projeto=<?php echo $projeto['ID_Projeto']; ?>"
                          class="kanbam-add-task-link">
                          <button>
                            <div class="kanbam-collum-new-tarefa-button">
                              <div class="kanbam-collum-new-tarefa-button-text">
                                <p>Criar Nova Tarefa</p>
                              </div>
                              <div class="kanbam-collum-new-tarefa-button-plus">
                                <p>+</p>
                              </div>
                            </div>
                          </button>
                        </a>
                      </div>
                      <div class="kanbam-collum-new-tarefa-content" data-status="A Fazer">
                        <?php foreach ($projeto['Tarefas'] as $tarefa): ?>
                          <?php if ($tarefa['Status'] == 'A Fazer'): ?>
                            <div class="kanbam-collum-todo-tarefa" draggable="true"
                              data-id-atribuicao="<?php echo $tarefa['ID_Atribuicao']; ?>">
                              <p><?php echo htmlspecialchars($tarefa['NomeTarefaExibicao']); ?></p>
                            </div>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </div>
                    </div>

                    <div class="kanbam-collum">
                      <div class="kanbam-collum-top">
                        <div class="kanbam-collum-top-icon"><img src="..\images\like-yellow.png" alt="" width="35px"
                            height="35px"></div>
                        <div class="kanbam-collum-top-text">
                          <p>Em Andamento:</p>
                        </div>
                      </div>
                      <div class="kanbam-collum-new-tarefa-button-content">
                        <a href="nova-tarefa.php?id_projeto=<?php echo $projeto['ID_Projeto']; ?>"
                          class="kanbam-add-task-link">
                          <button>
                            <div class="kanbam-collum-new-tarefa-button">
                              <div class="kanbam-collum-new-tarefa-button-text">
                                <p>Criar Nova Tarefa</p>
                              </div>
                              <div class="kanbam-collum-new-tarefa-button-plus">
                                <p>+</p>
                              </div>
                            </div>
                          </button>
                        </a>
                      </div>
                      <div class="kanbam-collum-new-tarefa-content" data-status="Em Andamento">
                        <?php foreach ($projeto['Tarefas'] as $tarefa): ?>
                          <?php if ($tarefa['Status'] == 'Em Andamento'): ?>
                            <div class="kanbam-collum-doing-tarefa" draggable="true"
                              data-id-atribuicao="<?php echo $tarefa['ID_Atribuicao']; ?>">
                              <p><?php echo htmlspecialchars($tarefa['NomeTarefaExibicao']); ?></p>
                            </div>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </div>
                    </div>

                    <div class="kanbam-collum">
                      <div class="kanbam-collum-top">
                        <div class="kanbam-collum-top-icon"><img src="..\images\like-green.png" alt="" width="35px"
                            height="35px"></div>
                        <div class="kanbam-collum-top-text">
                          <p>Concluído:</p>
                        </div>
                      </div>
                      <div class="kanbam-collum-new-tarefa-button-content">
                        <a href="nova-tarefa.php?id_projeto=<?php echo $projeto['ID_Projeto']; ?>"
                          class="kanbam-add-task-link">
                          <button>
                            <div class="kanbam-collum-new-tarefa-button">
                              <div class="kanbam-collum-new-tarefa-button-text">
                                <p>Criar Nova Tarefa</p>
                              </div>
                              <div class="kanbam-collum-new-tarefa-button-plus">
                                <p>+</p>
                              </div>
                            </div>
                          </button>
                        </a>
                      </div>
                      <div class="kanbam-collum-new-tarefa-content" data-status="Concluído">
                        <?php foreach ($projeto['Tarefas'] as $tarefa): ?>
                          <?php if ($tarefa['Status'] == 'Concluído'): ?>
                            <div class="kanbam-collum-done-tarefa" draggable="true"
                              data-id-atribuicao="<?php echo $tarefa['ID_Atribuicao']; ?>">
                              <p><?php echo htmlspecialchars($tarefa['NomeTarefaExibicao']); ?></p>
                            </div>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>






                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

        </div>








        <div class="right-main-user-projects">

          <div class="left-main-flexible-menu">
            <div class="left-main-new-project-content-flexible-menu">
              <div class="new-project-button-flexible-menu">
                <button>
                  <p>Seus projetos</p>
                </button>
              </div>
              <div class="new-project-plus-flexible-menu">
                <button>
                  <span class="material-symbols-outlined"> filter_alt </span>
                </button>
              </div>
            </div>
            <div class="left-main-favorites-content-flexible-menu" id="project-list-container">
              <?php if (empty($projetos)): ?>
                <p style="padding: 15px; color: #888; font-size: 0.9em;">Você ainda não tem projetos. Crie um novo!</p>
              <?php else: ?>
                <?php foreach ($projetos as $projeto): ?>
                  <div class="favorites-atalhos-content-flexible-menu">
                    <div class="favorites-atalhos-ex1-flexible-menu">
                      <div class="main-favorites-atalhos-ex1-flexible-menu">
                        <button class="btn-exb-projeto" data-id-projeto="<?php echo $projeto['ID_Projeto']; ?>">
                          <div class="title-favorites-atalhos-flexible-menu">
                            <p><?php echo htmlspecialchars($projeto['NomeProjeto']); ?></p>
                          </div>
                        </button>
                        <div class="button-favorites-atalhos-content-flexible-menu">
                          <button>
                            <span class="material-symbols-outlined"> flag_2 </span>
                            <span class="material-symbols-outlined"> keep </span>
                          </button>
                        </div>
                      </div>
                      <div class="button-favorites-atalhos-content-flexible-menu-att">
                        <p>Ultima att. Mateus atualizou uma tarefa</p>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

        </div>




      </div>
    </div>


    <div id="filter-overlay" class="filter-overlay"></div>
    <div id="filter-popup" class="filter-popup">
      <div class="popup-header">
        <h3>Ordenar Projetos</h3>
        <button id="btn-close-filter" class="close-button">&times;</button>
      </div>
      <div class="popup-body">
        <input type="text" id="search-input" placeholder="Pesquisar por nome...">
        <button class="filter-option" data-sort="asc">Ordem Alfabética (A-Z)</button>
        <button class="filter-option" data-sort="desc">Ordem Alfabética (Z-A)</button>
        <button class="filter-option" data-sort="date_desc">Mais Recentes</button>
        <button class="filter-option" data-sort="date_asc">Mais Antigos</button>
        <button class="filter-option" data-sort="default">Ordem Padrão</button>
      </div>
    </div>

    <script>
      // ... seu javascript existente ...
    </script>
</body>


<script>
  // =================================================================================
  // FLUXOS DE EXIBIÇÃO DE PROJETO (VERSÃO FINAL CORRIGIDA)
  // =================================================================================

  // Função para esconder todas as visualizações de projeto e limpar a tela
  function esconderTodasAsVisualizacoes() {
    // Esconde os contêineres principais dos dois fluxos
    document.querySelectorAll('.exibicao-selection-project-space-direct, .right-main-user-projects, .right-main-calendar-view').forEach(function (view) {
      view.style.display = 'none';
    });

    // Garante que todos os painéis de conteúdo individuais sejam escondidos como reset
    document.querySelectorAll('.exibicao-selection-project-space-content, .exibicao-selection-project-space-kanbam-content').forEach(function (content) {
      content.style.display = 'none';
    });

    // Garante que o menu flexível do fluxo 1 também seja escondido
    const flexibleMenu = document.querySelector(".left-main-flexible-menu");
    if (flexibleMenu) {
      flexibleMenu.style.display = "none";
    }
  }


  // --- LÓGICA PARA O FLUXO 1 (Visão Geral: Clicar em "Seus Projetos") ---

  // 1. Botão "Seus projetos" na barra lateral principal
  document.querySelectorAll(".btn-menu-user-projects").forEach(function (btn) {
    btn.addEventListener("click", function () {
      esconderTodasAsVisualizacoes(); // Reseta a tela antes de mostrar a nova

      // Mostra o contêiner geral do fluxo 1
      const userProjectsContainer = document.querySelector(".right-main-user-projects");
      if (userProjectsContainer) {
        userProjectsContainer.style.display = "flex";
      }

      // Mostra o menu flexível com a lista de projetos
      const flexibleMenu = document.querySelector(".left-main-flexible-menu");
      if (flexibleMenu) {
        flexibleMenu.style.display = "block";
      }
    });
  });

  // 2. Botão de um projeto específico na lista do Fluxo 1 (menu flexível)
  document.querySelectorAll(".btn-exb-projeto").forEach(function (btn) {
    btn.addEventListener("click", function () {
      const idProjeto = this.getAttribute("data-id-projeto");
      const containerFlow1 = document.querySelector(".exibicao-selection-project-space");

      if (!containerFlow1) return;

      // Mostra o contêiner de detalhes do Fluxo 1
      containerFlow1.style.display = "block";

      // Esconde todos os outros conteúdos DENTRO DELE para garantir um estado limpo
      containerFlow1.querySelectorAll('.exibicao-selection-project-space-content, .exibicao-selection-project-space-kanbam-content').forEach(function (content) {
        content.style.display = "none";
      });

      // Encontra e mostra o conteúdo correto APENAS DENTRO DESTE CONTÊINER
      const painelParaMostrar = containerFlow1.querySelector('#project-info-' + idProjeto);
      if (painelParaMostrar) {
        painelParaMostrar.style.display = "block";
      }
    });
  });


  // --- LÓGICA PARA O FLUXO 2 (Acesso Direto: Clicar em um projeto em "Seu espaço") ---
  document.querySelectorAll('.btn-mostrar-projeto').forEach(function (botao) {
    botao.addEventListener('click', function () {
      esconderTodasAsVisualizacoes(); // Reseta a tela antes de mostrar a nova
      const idProjetoSelecionado = this.getAttribute('data-id-projeto');
      const containerFlow2 = document.querySelector('.exibicao-selection-project-space-direct');

      if (!containerFlow2) return;

      // Mostra o contêiner de detalhes do Fluxo 2
      containerFlow2.style.display = 'block';

      // Esconde todos os outros conteúdos DENTRO DELE para um estado limpo
      containerFlow2.querySelectorAll('.exibicao-selection-project-space-content, .exibicao-selection-project-space-kanbam-content').forEach(function (content) {
        content.style.display = "none";
      });

      // Encontra e mostra o conteúdo correto APENAS DENTRO DESTE CONTÊINER
      const painelParaMostrar = containerFlow2.querySelector('#project-info-' + idProjetoSelecionado);
      if (painelParaMostrar) {
        painelParaMostrar.style.display = 'block';
      }
    });
  });


  // =================================================================================
  // LÓGICA DE ALTERNÂNCIA GERENCIAMENTO / KANBAN (VERSÃO FINAL CORRIGIDA)
  // =================================================================================
  document.querySelectorAll(".btn-kanbam").forEach(function (btn) {
    btn.addEventListener("click", function () {
      const parentContent = btn.closest(".exibicao-selection-project-space-content");
      if (!parentContent) return;

      const mainContainer = parentContent.parentElement;
      if (!mainContainer) return;

      const idProjeto = parentContent.id.replace("project-info-", "");
      const kanbam = mainContainer.querySelector("#project-kanban-" + idProjeto);

      if (kanbam) {
        parentContent.style.display = "none";
        kanbam.style.display = "block";
      }
    });
  });

  document.querySelectorAll(".btn-gerenciamento").forEach(function (btn) {
    btn.addEventListener("click", function () {
      const parentKanbam = btn.closest(".exibicao-selection-project-space-kanbam-content");
      if (!parentKanbam) return;

      const mainContainer = parentKanbam.parentElement;
      if (!mainContainer) return;

      const idProjeto = parentKanbam.id.replace("project-kanban-", "");
      const content = mainContainer.querySelector("#project-info-" + idProjeto);

      if (content) {
        parentKanbam.style.display = "none";
        content.style.display = "block";
      }
    });
  });


  // =================================================================================
  // DEMAIS FUNCIONALIDADES (DRAG & DROP, MENUS, TAREFAS, ETC.)
  // =================================================================================

  // =================================================================================
  // LÓGICA DO MODAL DE DETALHES DA TAREFA (NOVO)
  // =================================================================================

  // Pega todos os dados dos projetos que o PHP nos deu
  const todosProjetos = <?php echo $projetos_json; ?>;
  const modal = document.getElementById('modal-tarefa-detalhes');
  const modalBody = document.getElementById('modal-tarefa-body');
  const closeModalBtn = document.getElementById('modal-close-btn');

  // Função para encontrar uma tarefa específica em todos os projetos
  function encontrarTarefaPorId(idAtribuicao) {
    for (const projeto of todosProjetos) {
      for (const tarefa of projeto.Tarefas) {
        // Compara como strings para evitar problemas de tipo
        if (String(tarefa.ID_Atribuicao) === String(idAtribuicao)) {
          return tarefa;
        }
      }
    }
    return null; // Retorna nulo se não encontrar
  }

  // Adiciona um "espião" de cliques no corpo do documento
  document.body.addEventListener('click', function (event) {
    const kanbanCard = event.target.closest('.kanbam-collum-todo-tarefa, .kanbam-collum-doing-tarefa, .kanbam-collum-done-tarefa');

    // Se o clique foi em um cartão do Kanban
    if (kanbanCard) {
      event.preventDefault(); // Previne o início do drag se for só um clique

      const idAtribuicao = kanbanCard.dataset.idAtribuicao;
      const tarefa = encontrarTarefaPorId(idAtribuicao);

      if (tarefa) {
        // Constrói o HTML com os detalhes da tarefa
        let detalhesHtml = `
          <h2>${tarefa.NomeTarefaExibicao}</h2>
          <div class="detalhe-item">
            <span class="detalhe-label">Status:</span>
            <span class="detalhe-valor">${tarefa.Status}</span>
          </div>
          <div class="detalhe-item">
            <span class="detalhe-label">Atribuído para:</span>
            <span class="detalhe-valor">${tarefa.NomesUsuariosAtribuidos || 'Não atribuído'}</span>
          </div>
          <div class="detalhe-item">
            <span class="detalhe-label">Prazo:</span>
            <span class="detalhe-valor">${new Date(tarefa.DataPrazo).toLocaleDateString('pt-BR')}</span>
          </div>
        `;

        if (tarefa.DescricaoPersonalizada) {
          detalhesHtml += `
            <div class="detalhe-item">
              <span class="detalhe-label">Descrição Personalizada:</span>
              <p class="detalhe-descricao">${tarefa.DescricaoPersonalizada}</p>
            </div>
            <div class="detalhe-item">
              <span class="detalhe-label">Tarefa Padrão Original:</span>
              <span class="detalhe-valor">${tarefa.NomeTarefaOriginal}</span>
            </div>
          `;
        }

        if (tarefa.ComoFazer) {
          detalhesHtml += `<div class="detalhe-item"><span class="detalhe-label">Como Fazer:</span><p class="detalhe-descricao">${tarefa.ComoFazer}</p></div>`;
        }
        if (tarefa.DocumentoReferencia) {
          detalhesHtml += `<div class="detalhe-item"><span class="detalhe-label">Documento de Referência:</span><span class="detalhe-valor">${tarefa.DocumentoReferencia}</span></div>`;
        }

        // Adiciona os botões de ação no final
        detalhesHtml += `
            <div class="tarefa-actions-container">
                <a href="editar-tarefa.php?id=${tarefa.ID_Atribuicao}" class="btn-editar-tarefa"><i class="fas fa-edit"></i> Editar Tarefa</a>
                <button type="button" class="btn-excluir-tarefa" data-id-atribuicao="${tarefa.ID_Atribuicao}"><i class="fas fa-trash-alt"></i> Excluir</button>
            </div>
        `;

        modalBody.innerHTML = detalhesHtml;
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('visivel'), 10); // Adiciona a classe para o efeito de transição
      }
    }
  });

  // Função para fechar o modal
  function fecharModal() {
    modal.classList.remove('visivel');
    setTimeout(() => modal.style.display = 'none', 300); // Espera a transição terminar para esconder
  }

  // Eventos para fechar o modal
  closeModalBtn.addEventListener('click', fecharModal);
  modal.addEventListener('click', function (event) {
    // Fecha o modal apenas se o clique for no fundo (overlay) e não no conteúdo
    if (event.target === modal) {
      fecharModal();
    }
  });

  // --- LÓGICA KANBAN DRAG & DROP (VERSÃO COMPLETA E FUNCIONAL) ---
  const taskCards = document.querySelectorAll(
    ".kanbam-collum-todo-tarefa, .kanbam-collum-doing-tarefa, .kanbam-collum-done-tarefa"
  );
  const taskContainers = document.querySelectorAll(
    ".kanbam-collum-new-tarefa-content"
  );
  let draggedCard = null;

  // Adiciona os eventos de "pegar" e "largar" o cartão
  taskCards.forEach((card) => {
    card.addEventListener("dragstart", () => {
      draggedCard = card;
      setTimeout(() => {
        card.classList.add("dragging");
      }, 0);
    });
    card.addEventListener("dragend", () => {
      if (draggedCard) {
        draggedCard.classList.remove("dragging");
      }
      draggedCard = null;
    });
  });

  // Adiciona os eventos às colunas para saber onde o cartão está sendo arrastado e onde foi solto
  taskContainers.forEach((container) => {
    container.addEventListener("dragover", (e) => {
      e.preventDefault();
      const afterElement = getDragAfterElement(container, e.clientY);
      if (draggedCard) {
        if (afterElement == null) {
          container.appendChild(draggedCard);
        } else {
          container.insertBefore(draggedCard, afterElement);
        }
      }
    });

    // EVENTO DE DROP (SOLTAR) ATUALIZADO PARA SALVAR NO BANCO
    container.addEventListener("drop", (e) => {
      e.preventDefault();
      if (!draggedCard) return;

      // 1. Pega os dados necessários do cartão e da coluna
      const idAtribuicao = draggedCard.dataset.idAtribuicao;
      const novoStatus = container.dataset.status;

      // 2. Mapeia o status visual para a classe CSS correta
      const statusClassMap = {
        'A Fazer': 'kanbam-collum-todo-tarefa',
        'Em Andamento': 'kanbam-collum-doing-tarefa',
        'Concluído': 'kanbam-collum-done-tarefa'
      };

      // 3. Atualiza a aparência do cartão imediatamente
      draggedCard.classList.remove('kanbam-collum-todo-tarefa', 'kanbam-collum-doing-tarefa', 'kanbam-collum-done-tarefa');
      draggedCard.classList.add(statusClassMap[novoStatus]);

      // 4. Envia a atualização para o servidor para tornar a mudança permanente
      fetch('atualizar_status_tarefa.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id_atribuicao=${idAtribuicao}&novo_status=${novoStatus}`
      })
        .then(response => response.json())
        .then(data => {
          if (!data.success) {
            alert('Erro ao salvar o novo status: ' + data.message);
            // Se a atualização falhar, recarregamos a página para manter a consistência com o banco.
            location.reload();
          }
          // Se tiver sucesso, não fazemos nada, pois a mudança visual já foi feita.
        })
        .catch(error => {
          console.error('Erro de comunicação:', error);
          alert('Erro de comunicação ao salvar o status da tarefa.');
          location.reload();
        });
    });
  });

  // Função auxiliar para encontrar a posição correta ao arrastar
  function getDragAfterElement(container, y) {
    const draggableElements = [
      ...container.querySelectorAll(
        ".kanbam-collum-todo-tarefa:not(.dragging), .kanbam-collum-doing-tarefa:not(.dragging), .kanbam-collum-done-tarefa:not(.dragging)"
      )
    ];
    return draggableElements.reduce(
      (closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
          return {
            offset: offset,
            element: child
          };
        } else {
          return closest;
        }
      }, {
      offset: Number.NEGATIVE_INFINITY
    }
    ).element;
  }

  // --- LÓGICA PARA MENUS LATERAIS E DROPDOWNS ---
  document
    .querySelector(".btn-exb-left-main")
    .addEventListener("click", function () {
      const leftMain = document.querySelector(".left-main");
      const rightMain = document.querySelector(".right-main");
      if (leftMain.style.display === "block" || leftMain.style.display === "") {
        leftMain.style.display = "none";
        rightMain.style.width = "100%";
      } else {
        leftMain.style.display = "block";
        rightMain.style.width = "calc(100% - 250px)";
      }
    });

  document
    .querySelector(".btn-cls-favorites-atalhos")
    .addEventListener("click", function () {
      const fav = document.querySelector(".favorites-atalhos-content");
      fav.style.display =
        fav.style.display === "none" || fav.style.display === "" ?
          "block" :
          "none";
    });

  document
    .querySelector(".btn-cls-user-space-atalhos")
    .addEventListener("click", function () {
      const user = document.querySelector(".user-atalhos-content");
      user.style.display =
        user.style.display === "none" || user.style.display === "" ?
          "block" :
          "none";
    });


  // --- CÓDIGO PARA EXPANDIR DETALHES DAS TAREFAS ---
  document.querySelectorAll('.tarefa-clicavel').forEach(tarefa => {
    tarefa.addEventListener('click', function (event) {
      // Impede que o clique na tarefa propague para outros elementos
      if (event.target.closest('button, a')) {
        return;
      }
      const detalhes = this.querySelector('.tarefa-detalhes-expansivel');
      if (detalhes) {
        detalhes.classList.toggle('visivel');
      }
    });
  });

  // =================================================================================
  // LÓGICA DO MENU DE CONTEXTO E SUAS OPÇÕES (VERSÃO CORRIGIDA E MELHORADA)
  // =================================================================================
  const menuContexto = document.getElementById('menu-contexto-projeto');

  // Adiciona o listener para ABRIR o menu de contexto no botão de opções '...'
  document.querySelectorAll('.btn-opcoes-projeto').forEach(item => {
    item.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation(); // Impede que o clique no botão feche o menu imediatamente
      menuContexto.style.top = `${event.pageY}px`;
      menuContexto.style.left = `${event.pageX}px`;
      menuContexto.style.display = 'block';
      // Armazena o ID do projeto no próprio menu para que os botões possam usá-lo
      menuContexto.dataset.idProjeto = this.dataset.idProjeto;
    });
  });

  // --- LÓGICA PARA EXCLUIR TAREFA VIA BOTÃO ---
  document.body.addEventListener('click', function (event) {
    // Usamos 'event delegation' para capturar cliques nos botões de excluir,
    // mesmo que as tarefas sejam carregadas dinamicamente.
    if (event.target.classList.contains('btn-excluir-tarefa') || event.target.closest('.btn-excluir-tarefa')) {

      const botao = event.target.closest('.btn-excluir-tarefa');
      const idAtribuicao = botao.dataset.idAtribuicao;

      if (confirm('Tem certeza que deseja excluir esta tarefa? Esta ação não pode ser desfeita.')) {
        fetch('excluir_tarefa.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `id_atribuicao=${idAtribuicao}`
        })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Remove a tarefa da tela sem recarregar a página
              const tarefaParaRemover = botao.closest('.tarefa-clicavel');
              if (tarefaParaRemover) {
                tarefaParaRemover.style.transition = 'opacity 0.5s ease';
                tarefaParaRemover.style.opacity = '0';
                setTimeout(() => tarefaParaRemover.remove(), 500);
              }
            } else {
              alert('Erro: ' + data.message);
            }
          })
          .catch(error => {
            console.error('Erro na comunicação com o servidor:', error);
            alert('Ocorreu um erro de comunicação ao tentar excluir a tarefa.');
          });
      }
    }
  });

  // Adiciona um listener global para FECHAR o menu quando se clica em qualquer outro lugar
  window.addEventListener('click', function () {
    if (menuContexto.style.display === 'block') {
      menuContexto.style.display = 'none';
    }
  });

  // --- AÇÕES DOS BOTÕES 'EDITAR' E 'EXCLUIR' ---

  // MELHORIA: Seleciona os botões de forma mais segura e específica
  const botaoEditar = menuContexto.querySelector('button.menu-opcao:not(.menu-opcao-excluir)');
  const botaoExcluir = menuContexto.querySelector('button.menu-opcao-excluir');

  // Listener para o botão Editar
  botaoEditar.addEventListener('click', function (event) {
    event.stopPropagation(); // Impede que o clique feche o menu antes da ação

    // CORREÇÃO: Usando 'menuContexto' com 'o' minúsculo
    const idProjeto = menuContexto.dataset.idProjeto;

    if (idProjeto) {
      // Redireciona para a página de edição
      window.location.href = `editar-projeto.php?id=${idProjeto}`;
    }
  });

  // Listener para o botão Excluir
  botaoExcluir.addEventListener('click', function (event) {
    event.stopPropagation(); // Impede que o clique feche o menu antes da ação
    const idProjeto = menuContexto.dataset.idProjeto; // Pega o ID armazenado no menu
    if (idProjeto) {
      if (confirm('Você tem certeza que deseja excluir este projeto? Esta ação não pode ser desfeita.')) {
        fetch('excluir_projeto.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `id_projeto=${idProjeto}`
        })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              alert(data.message);
              // Remove o projeto de TODAS as listas na tela para consistência
              document.querySelectorAll(`[data-id-projeto='${idProjeto}']`).forEach(el => {
                const itemParaRemover = el.closest('.favorites-atalhos-ex1, .favorites-atalhos-ex1-flexible-menu');
                if (itemParaRemover) {
                  itemParaRemover.remove();
                }
              });
              // Esconde a view de detalhes se o projeto excluído estiver visível
              esconderTodasAsVisualizacoes();
            } else {
              alert('Erro: ' + data.message);
            }
          })
          .catch(error => {
            console.error('Erro na comunicação com o servidor:', error);
            alert('Ocorreu um erro de comunicação. Tente novamente.');
          });
      }
    }
  });

  // =================================================================================
  // LÓGICA DO CALENDÁRIO GLOBAL INTEGRADO (VERSÃO COM FILTROS)
  // =================================================================================
  const btnMostrarCalendario = document.getElementById('btn-mostrar-calendario-global');
  const calendarView = document.querySelector('.right-main-calendar-view');

  btnMostrarCalendario.addEventListener('click', function () {
    esconderTodasAsVisualizacoes();
    calendarView.style.display = 'block';
    // Inicializa o calendário APENAS quando o usuário clica
    inicializarCalendario();
  });

  // --- NOVO CÓDIGO PARA O BOTÃO DO CALENDÁRIO NA SIDEBAR ---

  // 1. Selecionamos o novo botão pelo seu novo ID
  const btnMostrarCalendarioSidebar = document.getElementById('btn-mostrar-calendario-sidebar');

  // 2. Verificamos se o botão existe antes de adicionar o evento
  if (btnMostrarCalendarioSidebar) {
    // 3. Adicionamos a MESMA função de clique a ele
    btnMostrarCalendarioSidebar.addEventListener('click', function () {
      esconderTodasAsVisualizacoes();
      calendarView.style.display = 'block';
      inicializarCalendario();
    });
  }

  // Elementos do Calendário
  const monthYearHeader = document.getElementById('month-year-header');
  const calendarGrid = document.getElementById('calendar-days-grid');
  const prevMonthBtn = document.getElementById('prev-month-btn');
  const nextMonthBtn = document.getElementById('next-month-btn');

  // Elementos do Modal
  const modalCalendario = document.getElementById('calendario-popup');
  const closeModalCalendarioBtn = modalCalendario.querySelector('.close-button');
  const popupDeleteBtn = document.getElementById('popup-delete-btn');

  // Elementos dos FILTROS (NOVOS)
  const projectFilter = document.getElementById('project-filter');
  const statusFilters = document.querySelectorAll('input[name="status"]');

  let currentDate = new Date();
  let tasksData = {}; // Armazena os dados originais do servidor

  // Função que busca os dados do servidor
  async function inicializarCalendario() {
    calendarGrid.innerHTML = '<p>Carregando tarefas...</p>';
    try {
      const response = await fetch('buscar_tarefas_calendario.php');
      if (!response.ok) throw new Error('Falha ao buscar tarefas.');
      tasksData = await response.json();
      // A primeira renderização usa os filtros padrão
      applyFiltersAndRender();
    } catch (error) {
      console.error(error);
      calendarGrid.innerHTML = `<p style="color: red; text-align: center;">Não foi possível carregar as tarefas.</p>`;
    }
  }

  // NOVA FUNÇÃO CENTRAL: Aplica os filtros e chama a renderização
  function applyFiltersAndRender() {
    const selectedProject = projectFilter.value;
    const selectedStatuses = Array.from(statusFilters)
      .filter(checkbox => checkbox.checked)
      .map(checkbox => checkbox.value);

    let filteredTasks = {};
    // Itera sobre cada data nos dados originais
    for (const date in tasksData) {
      // Filtra as tarefas para aquela data
      const tasksForDate = tasksData[date].filter(task => {
        const projectMatch = selectedProject === 'all' || task.project === selectedProject;
        const statusMatch = selectedStatuses.includes(task.status);
        return projectMatch && statusMatch;
      });

      // Se houver tarefas após o filtro, adiciona ao objeto filtrado
      if (tasksForDate.length > 0) {
        filteredTasks[date] = tasksForDate;
      }
    }
    // Chama a função de renderização com os dados JÁ FILTRADOS
    renderCalendar(filteredTasks);
  }

  // FUNÇÃO DE RENDERIZAÇÃO MODIFICADA para aceitar dados
  function renderCalendar(tasksToRender) {
    currentDate.setDate(1);
    const month = currentDate.getMonth();
    const year = currentDate.getFullYear();
    monthYearHeader.textContent = new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' }).format(currentDate);

    const firstDayIndex = currentDate.getDay();
    const lastDayOfMonth = new Date(year, month + 1, 0).getDate();
    const prevLastDay = new Date(year, month, 0).getDate();

    let daysHtml = '';
    for (let i = firstDayIndex; i > 0; i--) {
      daysHtml += `<div class="calendar-day empty">${prevLastDay - i + 1}</div>`;
    }

    for (let day = 1; day <= lastDayOfMonth; day++) {
      const today = new Date();
      const isToday = day === today.getDate() && month === today.getMonth() && year === today.getFullYear();
      const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
      const dayTasks = tasksToRender[dateStr] || []; // Usa os dados filtrados

      let tasksHtml = '<div class="tasks-container">';
      dayTasks.forEach(task => {
        const statusClassMap = { 'A Fazer': 'status-a-fazer', 'Em Andamento': 'status-em-andamento', 'Concluído': 'status-concluido' };
        const statusClass = statusClassMap[task.status] || '';
        tasksHtml += `<button class="task-item-button ${statusClass}" data-id="${task.id}">${task.title}</button>`;
      });
      tasksHtml += '</div>';
      daysHtml += `<div class="calendar-day ${isToday ? 'today' : ''}"><div class="day-number">${day}</div>${tasksHtml}</div>`;
    }

    const totalCells = firstDayIndex + lastDayOfMonth;
    const remainingDays = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
    for (let i = 1; i <= remainingDays; i++) {
      daysHtml += `<div class="calendar-day empty">${i}</div>`;
    }

    calendarGrid.innerHTML = daysHtml;
  }

  // Lógica do Modal e Navegação (sem alterações, mas mantida aqui)
  function openModalCalendario(task) {
    document.getElementById('popup-title').textContent = task.title;
    document.getElementById('popup-project').textContent = task.project;
    document.getElementById('popup-duedate').textContent = new Date(task.dueDate).toLocaleDateString('pt-BR', { timeZone: 'UTC' });
    document.getElementById('popup-status').textContent = task.status;

    document.getElementById('popup-edit-btn').href = `editar-tarefa.php?id=${task.id}`;
    popupDeleteBtn.dataset.idAtribuicao = task.id;

    modalCalendario.style.display = 'flex';
  }

  function closeModalCalendario() {
    modalCalendario.style.display = 'none';
  }

  prevMonthBtn.addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() - 1);
    applyFiltersAndRender();
  });

  nextMonthBtn.addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() + 1);
    applyFiltersAndRender();
  });

  closeModalCalendarioBtn.addEventListener('click', closeModalCalendario);
  window.addEventListener('click', (event) => {
    if (event.target === modalCalendario) closeModalCalendario();
  });

  calendarGrid.addEventListener('click', (event) => {
    const taskButton = event.target.closest('.task-item-button');
    if (taskButton) {
      const taskId = taskButton.dataset.id;
      for (const date in tasksData) {
        const foundTask = tasksData[date].find(t => String(t.id) === taskId);
        if (foundTask) {
          foundTask.dueDate = date;
          openModalCalendario(foundTask);
          break;
        }
      }
    }
  });

  popupDeleteBtn.addEventListener('click', function () {
    const idAtribuicao = this.dataset.idAtribuicao;
    if (confirm('Tem certeza que deseja excluir esta tarefa?')) {
      fetch('excluir_tarefa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id_atribuicao=${idAtribuicao}`
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            closeModalCalendario();
            inicializarCalendario(); // Recarrega os dados e o calendário
          } else {
            alert('Erro: ' + data.message);
          }
        })
        .catch(error => console.error('Erro:', error));
    }
  });






  // --- LÓGICA DO POPUP DE FILTRO E ORDENAÇÃO ---

// --- LÓGICA INTEGRADA DE FILTRO, BUSCA E ORDENAÇÃO ---

// Elementos do DOM
const btnOpenFilter = document.getElementById('btn-open-filter');
const btnCloseFilter = document.getElementById('btn-close-filter');
const filterPopup = document.getElementById('filter-popup');
const filterOverlay = document.getElementById('filter-overlay');
const projectListContainer = document.getElementById('project-list-container');
const filterOptions = document.querySelectorAll('.filter-option');
const searchInput = document.getElementById('search-input'); // Novo elemento

// Variáveis de Estado
const originalProjects = [...todosProjetos];
let currentSortType = 'default'; // Guarda a ordenação atual

// Funções para abrir e fechar o popup (sem alterações)
btnOpenFilter.addEventListener('click', () => {
    filterOverlay.style.display = 'block';
    filterPopup.style.display = 'block';
});

function closeFilterPopup() {
    filterOverlay.style.display = 'none';
    filterPopup.style.display = 'none';
}
btnCloseFilter.addEventListener('click', closeFilterPopup);
filterOverlay.addEventListener('click', closeFilterPopup);


// Função para redesenhar a lista (sem alterações)
function renderProjectList(projectsToRender) {
    projectListContainer.innerHTML = '';
    if (projectsToRender.length === 0) {
        projectListContainer.innerHTML = '<p style="padding: 15px; color: #888; font-size: 0.9em;">Nenhum projeto encontrado.</p>';
        return;
    }
    projectsToRender.forEach(projeto => {
        const projectHTML = `
            <div class="favorites-atalhos-content-flexible-menu">
              <div class="favorites-atalhos-ex1-flexible-menu">
                <div class="main-favorites-atalhos-ex1-flexible-menu">
                  <button class="btn-exb-projeto" data-id-projeto="${projeto.ID_Projeto}">
                    <div class="title-favorites-atalhos-flexible-menu"><p>${projeto.NomeProjeto}</p></div>
                  </button>
                  <div class="button-favorites-atalhos-content-flexible-menu">
                    <button><span class="material-symbols-outlined">flag_2</span><span class="material-symbols-outlined">keep</span></button>
                  </div>
                </div>
                <div class="button-favorites-atalhos-content-flexible-menu-att"><p>Ultima att. Mateus atualizou uma tarefa</p></div>
              </div>
            </div>`;
        projectListContainer.innerHTML += projectHTML;
    });
}

// ==========================================================
// A NOVA FUNÇÃO MESTRE QUE CONTROLA TUDO
// ==========================================================
function applyFiltersAndSort() {
    let processedProjects = [...originalProjects];

    // 1. APLICA A ORDENAÇÃO ATUAL
    switch (currentSortType) {
        case 'asc':
            processedProjects.sort((a, b) => a.NomeProjeto.localeCompare(b.NomeProjeto));
            break;
        case 'desc':
            processedProjects.sort((a, b) => b.NomeProjeto.localeCompare(a.NomeProjeto));
            break;
        case 'date_desc':
            processedProjects.sort((a, b) => new Date(b.DataCriacao) - new Date(a.DataCriacao));
            break;
        case 'date_asc':
            processedProjects.sort((a, b) => new Date(a.DataCriacao) - new Date(b.DataCriacao));
            break;
        // O caso 'default' não faz nada, mantendo a ordem original.
    }

    // 2. APLICA O FILTRO DE BUSCA (DEPOIS DE ORDENAR)
    const searchTerm = searchInput.value.toLowerCase();
    if (searchTerm.length > 0) {
        processedProjects = processedProjects.filter(projeto => 
            projeto.NomeProjeto.toLowerCase().includes(searchTerm)
        );
    }

    // 3. RENDERIZA O RESULTADO FINAL
    renderProjectList(processedProjects);
}


// --- ATUALIZAÇÃO DOS EVENTOS ---

// Os botões de ordenação agora apenas atualizam o estado e chamam a função mestre
filterOptions.forEach(button => {
    button.addEventListener('click', () => {
        currentSortType = button.getAttribute('data-sort'); // Atualiza a ordenação
        applyFiltersAndSort(); // Aplica
        closeFilterPopup();
    });
});

// O campo de busca chama a função mestre a cada tecla digitada
searchInput.addEventListener('input', () => {
    applyFiltersAndSort();
});

  // NOVOS LISTENERS para os filtros
  projectFilter.addEventListener('change', applyFiltersAndRender);
  statusFilters.forEach(checkbox => checkbox.addEventListener('change', applyFiltersAndRender));
</script>
<script src="../Js/chatbot.js"></script>




</body>

</html>