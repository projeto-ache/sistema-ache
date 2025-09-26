<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html");
    exit;
}

// Pega os dados do usuário da sessão para o cabeçalho
$primeiroNome = htmlspecialchars($_SESSION['user_nome'] ?? '', ENT_QUOTES, 'UTF-8');
// Precisaremos de todos os projetos para o menu lateral esquerdo
require_once 'conexao.php';
$projetos = [];
$idUsuarioLogado = $_SESSION['user_id'];
try {
    $sql_projetos = "SELECT DISTINCT p.ID_Projeto, p.NomeProjeto
                     FROM projetos p
                     LEFT JOIN projetos_usuarios pu ON p.ID_Projeto = pu.ID_Projeto
                     WHERE p.ID_Usuario_Criador = ? OR pu.ID_Usuario = ?
                     ORDER BY p.NomeProjeto ASC";
    $stmt = $conexao->prepare($sql_projetos);
    $stmt->bind_param("ii", $idUsuarioLogado, $idUsuarioLogado);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $projetos[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Lida com o erro, mas não interrompe a página
    error_log("Erro ao buscar projetos para o menu: " . $e->getMessage());
}
$conexao->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendário Global - InovaFarma</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="../style/style.css" />
</head>
<body>

    <div id="calendario-popup" class="calendario-popup" style="display: none;">
        <div class="calendario-popup-content">
            <div class="calendario-popoup-top">
                <div class="calendario-popoup-top-left">
                    <div class="calendario-popoup-top-title"><p id="popup-title">Título da Tarefa</p></div>
                </div>
                <div class="calendario-popoup-top-right">
                    <button class="close-button">&times;</button>
                </div>
            </div>
            <div class="calendario-popoup-midle">
                <a href="#" id="popup-edit-btn"><button><div class="calendario-popoup-midle-edit"><p>Editar tarefa</p></div></button></a>
                <button id="popup-delete-btn"><div class="calendario-popoup-midle-exclude"><p>Excluir tarefa</p></div></button>
            </div>
            <div class="calendario-popup-down">
                <div class="calendario-popup-down-part">
                    <div class="calendario-popup-down-title"><p>Projeto:</p></div>
                    <div class="calendario-popup-down-answer"><p id="popup-project">Nome do Projeto</p></div>
                </div>
                <div class="calendario-popup-down-part">
                    <div class="calendario-popup-down-title"><p>Prazo final:</p></div>
                    <div class="calendario-popup-down-answer"><p id="popup-duedate">DD/MM/YYYY</p></div>
                </div>
                <div class="calendario-popup-down-part">
                    <div class="calendario-popup-down-title"><p>Status:</p></div>
                    <div class="calendario-popup-down-answer"><p id="popup-status">Status da Tarefa</p></div>
                </div>
            </div>
        </div>
    </div>

    <header>
        <div class="menu-logo"><a href="#"><div class="menu-logo-texto"><p>ACHE</p></div></a></div>
        <div class="menu-content">
            <div class="menu-content-mensagem"><p>Bem-vindo, <?php echo $primeiroNome; ?>!</p></div>
            <div class="menu-content-data" id="current-date"></div>
            <div class="menu-content-time" id="current-time"></div>
            <div class="menu-content-logout"><a href="logout.php" class="logoutBtn"><button type="button">Sair</button></a></div>
        </div>
    </header>
    <div class="second-menu">
        <div class="menu-burger-icon"><button class="btn-exb-left-main"><i class="fas fa-bars"></i></button></div>
        <div class="menu-atalhos-iniciais">
            <div class="menu-atalhos-iniciais-pagina-inicial"><a href="index.php">Página Inicial</a></div>
            <div class="menu-atalhos-iniciais-ajuda"><a href="#">Ajuda</a></div>
        </div>
        <div class="menu-atalhos-pessoais">
            <ul class="nav-icons">
                <li><a href="calendario-global.php"><i class="fas fa-calendar-alt"></i></a></li>
                <li><a href="#"><i class="fas fa-bell"></i></a></li>
                <li><a href="#"><i class="fas fa-cog"></i></a></li>
                <li><a href="#"><i class="fas fa-user-circle"></i></a></li>
            </ul>
        </div>
    </div>

    <div class="index-content">
        <div class="left-main">
            <div class="left-main-new-project-content">
                 <div class="new-project-button"><a href="novo-projeto.php"><button><p>Novo Projeto</p></button></a></div>
            </div>
            <div class="left-main-favorites-content">
                <div class="favorites-title-content"><button class="btn-cls-favorites-atalhos"><div class="favorites-title"><p>Favoritos</p></div><div class="favorites-plus"><span class="material-symbols-outlined">keyboard_arrow_down</span></div></button></div>
                <div class="favorites-atalhos-content">
                    <div class="favorites-atalhos-ex1"><button class="btn-menu-user-projects"><div class="title-favorites-atalhos"><p>Seus projetos</p></div></button></div>
                </div>
            </div>
            <div class="user-projects-atalhos-content">
                <div class="left-user-space-content">
                    <div class="favorites-title-content"><button class="btn-cls-user-space-atalhos"><div class="favorites-title"><p>Seu espaço</p></div><div class="favorites-plus"><span class="material-symbols-outlined">keyboard_arrow_down</span></div></button></div>
                    <div class="user-atalhos-content">
                        <?php foreach ($projetos as $projeto): ?>
                            <div class="favorites-atalhos-ex1"><a href="index.php" class="title-favorites-atalhos btn-mostrar-projeto" data-id-projeto="<?php echo $projeto['ID_Projeto']; ?>"><p><?php echo htmlspecialchars($projeto['NomeProjeto']); ?></p></a></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="right-main">
            <div class="calendar-page">
                <div class="calendar-header">
                    <button id="prev-month-btn" class="calendar-nav-button">&lt;</button>
                    <h2 id="month-year-header">Carregando...</h2>
                    <button id="next-month-btn" class="calendar-nav-button">&gt;</button>
                </div>
                <div class="calendar-weekdays">
                    <div>Dom</div><div>Seg</div><div>Ter</div><div>Qua</div><div>Qui</div><div>Sex</div><div>Sáb</div>
                </div>
                <div class="calendar-grid" id="calendar-days-grid">
                    </div>
            </div>
        </div>
    </div>

    <script>
    // Script para Data/Hora do cabeçalho
    function atualizarDataHora() {
        const agora = new Date();
        document.getElementById("current-date").textContent = agora.toLocaleDateString("pt-BR");
        document.getElementById("current-time").textContent = agora.toLocaleTimeString("pt-BR", { hour: '2-digit', minute: '2-digit' });
    }
    atualizarDataHora();
    setInterval(atualizarDataHora, 1000);

    // Script do Calendário
    document.addEventListener('DOMContentLoaded', () => {
        const monthYearHeader = document.getElementById('month-year-header');
        const calendarGrid = document.getElementById('calendar-days-grid');
        const prevMonthBtn = document.getElementById('prev-month-btn');
        const nextMonthBtn = document.getElementById('next-month-btn');
        const modal = document.getElementById('calendario-popup');
        const closeModalBtn = modal.querySelector('.close-button');
        const popupDeleteBtn = document.getElementById('popup-delete-btn');

        let currentDate = new Date();
        let tasksData = {};

        async function inicializarCalendario() {
            try {
                const response = await fetch('buscar_tarefas_calendario.php');
                if (!response.ok) throw new Error('Falha ao buscar tarefas.');
                tasksData = await response.json();
                renderCalendar();
            } catch (error) {
                console.error(error);
                calendarGrid.innerHTML = `<p style="color: red; text-align: center;">Não foi possível carregar as tarefas.</p>`;
            }
        }

        function renderCalendar() {
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
                const dayTasks = tasksData[dateStr] || [];

                let tasksHtml = '<div class="tasks-container">';
                dayTasks.forEach(task => {
                    tasksHtml += `<button class="task-item-button" data-id="${task.id}">${task.title}</button>`;
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
        
        function openModal(task) {
            // Preenche o popup com os dados da tarefa
            document.getElementById('popup-title').textContent = task.title;
            document.getElementById('popup-project').textContent = task.project;
            document.getElementById('popup-duedate').textContent = new Date(task.dueDate).toLocaleDateString('pt-BR', {timeZone: 'UTC'});
            document.getElementById('popup-status').textContent = task.status;
            
            // Configura os botões de ação
            document.getElementById('popup-edit-btn').href = `editar-tarefa.php?id=${task.id}`;
            popupDeleteBtn.dataset.idAtribuicao = task.id; // Armazena o ID no botão de exclusão
            
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        prevMonthBtn.addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        });

        nextMonthBtn.addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        });

        closeModalBtn.addEventListener('click', closeModal);
        window.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });

        calendarGrid.addEventListener('click', (event) => {
            const taskButton = event.target.closest('.task-item-button');
            if (taskButton) {
                const taskId = taskButton.dataset.id;
                // Encontra a tarefa correta nos dados buscados
                for (const date in tasksData) {
                    const foundTask = tasksData[date].find(t => String(t.id) === taskId);
                    if (foundTask) {
                        // Precisamos da data original para formatar corretamente
                        foundTask.dueDate = date;
                        openModal(foundTask);
                        break;
                    }
                }
            }
        });

        // Lógica para o botão de exclusão DENTRO do popup
        popupDeleteBtn.addEventListener('click', function() {
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
                        closeModal();
                        inicializarCalendario(); // Recarrega os dados e o calendário
                    } else {
                        alert('Erro: ' + data.message);
                    }
                })
                .catch(error => console.error('Erro:', error));
            }
        });

        inicializarCalendario();
    });
    </script>
</body>
</html>