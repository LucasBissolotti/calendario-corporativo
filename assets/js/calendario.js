// Filtro de tarefas

function getInitials(name) {
    const parts = (name || '').trim().split(' ').filter(Boolean);
    if (parts.length === 0) return '';
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function updateTaskCreatorDisplay(nome, avatarUrl) {
    const container = document.getElementById('taskCreatorInfo');
    const avatarEl = document.getElementById('taskCreatorAvatar');
    const nameEl = document.getElementById('taskCreatorName');
    if (!container || !avatarEl || !nameEl) return;

    const safeName = (nome || '').trim();
    if (!safeName) {
        container.classList.remove('is-visible');
        avatarEl.style.backgroundImage = '';
        avatarEl.textContent = '';
        nameEl.textContent = '';
        return;
    }

    nameEl.textContent = safeName;
    if (avatarUrl) {
        const cacheBuster = `?v=${Date.now()}`;
        avatarEl.style.backgroundImage = `url('${avatarUrl}${cacheBuster}')`;
        avatarEl.textContent = '';
        avatarEl.classList.remove('task-creator-placeholder');
    } else {
        avatarEl.style.backgroundImage = '';
        avatarEl.textContent = getInitials(safeName);
        avatarEl.classList.add('task-creator-placeholder');
    }
    container.classList.add('is-visible');
}

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar Select2 para filtro de participantes
    if (window.jQuery && $('#filtroParticipantes').length) {
        $('#filtroParticipantes').select2({
            theme: 'bootstrap-5',
            placeholder: 'Selecione participantes',
            width: '100%',
            allowClear: true,
            dropdownParent: $('#modalFiltro'),
            closeOnSelect: false,
            dropdownCssClass: 'select2-dropdown-filtro-participantes'
        });
    }

    // Submissão do filtro
    const formFiltro = document.getElementById('formFiltro');
    if (formFiltro) {
        formFiltro.addEventListener('submit', function(e) {
            e.preventDefault();
            const participantes = $('#filtroParticipantes').val() || [];
            const categoria = document.getElementById('filtroCategoria').value;
            const usuarioCriador = document.getElementById('filtroUsuarioCriador') ? document.getElementById('filtroUsuarioCriador').value : '';
            const statusSelecionado = document.getElementById('filtroStatus') ? document.getElementById('filtroStatus').value : '';
            const tipoServicoTermo = document.getElementById('filtroTipoServico') ? document.getElementById('filtroTipoServico').value.trim() : '';
            aplicarFiltroTarefas(participantes, categoria, usuarioCriador, statusSelecionado, tipoServicoTermo);
            bootstrap.Modal.getInstance(document.getElementById('modalFiltro')).hide();
        });

        const btnReset = document.getElementById('btnResetFiltros');
        if (btnReset) {
            btnReset.addEventListener('click', function() {
                // Resetar formulário visual
                formFiltro.reset();
                // Limpar Select2 de participantes
                if (window.jQuery && $('#filtroParticipantes').length) {
                    $('#filtroParticipantes').val(null).trigger('change');
                }
                // Garantir resets manuais
                const catEl = document.getElementById('filtroCategoria');
                if (catEl) catEl.value = '';
                const userEl = document.getElementById('filtroUsuarioCriador');
                if (userEl) userEl.value = '';
                const statusEl = document.getElementById('filtroStatus');
                if (statusEl) statusEl.value = '';
                const tipoServicoEl = document.getElementById('filtroTipoServico');
                if (tipoServicoEl) tipoServicoEl.value = '';

                // Aplicar filtros vazios
                aplicarFiltroTarefas([], '', '', '', '');
            });
        }
    }

    // Configurar categorias e participantes iniciais
    (function setupModalInit() {
    })();
});

// Função para aplicar filtro no calendário
function aplicarFiltroTarefas(participantes, categoria, usuarioCriador, statusSelecionado, tipoServicoTermo) {
    // Salvar filtro globalmente
    const termoServico = (tipoServicoTermo || '').trim();
    window.filtroTarefas = { participantes, categoria, usuarioCriador, statusSelecionado, tipoServicoTermo: termoServico };
    window.calendar.refetchEvents();
}
// Configuração do calendário e manipulação de eventos

// Função para obter o ID do usuário atual
function getUserId() {
    // Esta informação deve ser obtida do PHP
    return window.currentUserId || null;
}

// Função para verificar se o usuário é administrador
function isUserAdmin() {
    // Esta informação deve ser obtida do PHP
    return window.isUserAdmin || false;
}

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar o calendário
    const calendarEl = document.getElementById('calendario');
    
    if (calendarEl) {
        inicializarCalendario(calendarEl);
    }
    
    // Inicializar os modais
    configurarModal();
});

// Configuração inicial do calendário
function inicializarCalendario(calendarEl) {
    // Detectar se é mobile para ajustar configurações
    const isMobile = window.innerWidth <= 768;
    
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        locale: 'pt-br',
        buttonText: {
            today: 'Hoje',
            month: 'Mês',
            week: 'Semana',
            day: 'Dia',
            list: 'Lista'
        },
        selectable: true,
        selectMirror: true,
        dayMaxEvents: isMobile ? 3 : true, // Limitar mais eventos em mobile
        weekNumbers: !isMobile, // Remover números de semana em mobile
        navLinks: true,
        editable: true,
        nowIndicator: true,
        themeSystem: 'bootstrap5',
        height: isMobile ? 'auto' : 'auto', // Altura automática
        contentHeight: isMobile ? 'auto' : 'auto',
        // Configurações específicas por view
        views: {
            timeGridWeek: {
                // Na aba "Semana", mostrar todos os eventos do dia (sem botão "+X")
                dayMaxEvents: false
            }
        },
        datesSet: function() {
            // Garantir que o botão do relatório permaneça visível em qualquer view
            const btn = document.getElementById('btnBaixarRelatorio');
            if (btn) {
                btn.style.display = '';
            }
        },
        
        // Carregar eventos da API
        events: function(info, successCallback, failureCallback) {
            // Adicionar filtros se existirem
            let url = `api.php?acao=listar&inicio=${info.startStr}&fim=${info.endStr}`;
            if (window.filtroTarefas) {
                if (window.filtroTarefas.participantes && window.filtroTarefas.participantes.length > 0) {
                    url += `&participantes=${encodeURIComponent(window.filtroTarefas.participantes.join(','))}`;
                }
                if (window.filtroTarefas.categoria && window.filtroTarefas.categoria !== '') {
                    url += `&categoria=${encodeURIComponent(window.filtroTarefas.categoria)}`;
                }
                if (window.filtroTarefas.usuarioCriador && window.filtroTarefas.usuarioCriador !== '') {
                    url += `&usuario_id=${encodeURIComponent(window.filtroTarefas.usuarioCriador)}`;
                }
                if (window.filtroTarefas.statusSelecionado && window.filtroTarefas.statusSelecionado !== '') {
                    url += `&status=${encodeURIComponent(window.filtroTarefas.statusSelecionado)}`;
                }
                if (window.filtroTarefas.tipoServicoTermo && window.filtroTarefas.tipoServicoTermo !== '') {
                    url += `&tipo_servico=${encodeURIComponent(window.filtroTarefas.tipoServicoTermo)}`;
                }
            }
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    // Filtro extra no cliente para garantir que o termo de tipo_servico seja respeitado
                    if (window.filtroTarefas && window.filtroTarefas.tipoServicoTermo) {
                        const termo = window.filtroTarefas.tipoServicoTermo.toLowerCase();
                        data = Array.isArray(data)
                            ? data.filter(ev => {
                                const val = (ev.extendedProps && ev.extendedProps.tipo_servico) ? String(ev.extendedProps.tipo_servico).toLowerCase() : '';
                                return val.includes(termo);
                              })
                            : data;
                    }
                    successCallback(data);
                })
                .catch(error => {
                    console.error('Erro ao carregar eventos:', error);
                    if (window.showAlert) showAlert('Erro ao carregar eventos. Tente novamente mais tarde.', 'error');
                    failureCallback(error);
                });
        },
        
        // Selecionar datas para criar evento
        select: function(info) {
            abrirModalNovaTarefa(info.startStr, info.endStr, info.allDay);
        },
        
        // Clicar em um evento existente
        eventClick: function(info) {
            // Para eventos "dias úteis" divididos em blocos, o ID exibido inclui sufixo (-bX).
            // Usar sempre o ID original da tarefa quando disponível.
            const eventoId = (info.event.extendedProps && info.event.extendedProps.tarefa_id)
                ? info.event.extendedProps.tarefa_id
                : info.event.id;
            carregarTarefa(eventoId);
        },
        
        // Arrastar e soltar eventos (atualizar datas)
        eventDrop: function(info) {
            atualizarDataTarefa(info.event);
        },
        
        // Redimensionar eventos (atualizar datas)
        eventResize: function(info) {
            atualizarDataTarefa(info.event);
        }
            ,
            eventDidMount: function(info) {
                // Adiciona o nome do autor no tooltip do evento
                const usuario = info.event.extendedProps.usuario;
                if (usuario) {
                    info.el.setAttribute('title', `${info.event.title}\nAutor: ${usuario}`);
                }
            }
    });
    
    calendar.render();
    
    // Adicionar ícones aos botões de navegação após renderização
    const prevButton = document.querySelector('.fc-prev-button');
    const nextButton = document.querySelector('.fc-next-button');
    
    if (prevButton) {
        prevButton.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prevButton.setAttribute('aria-label', 'Anterior');
    }
    
    if (nextButton) {
        nextButton.innerHTML = '<i class="fas fa-chevron-right"></i>';
        nextButton.setAttribute('aria-label', 'Próximo');
    }
    
    // Guardar referência do calendário para uso global
    window.calendar = calendar;
    // Ajustar visibilidade do botão externo após primeiro render
    const btn = document.getElementById('btnBaixarRelatorio');
    if (btn) {
        btn.style.display = '';
        if (!btn.dataset.bound) {
            btn.addEventListener('click', baixarRelatorio);
            btn.dataset.bound = '1';
        }
    }
    
    // Ajustar calendário quando a orientação ou tamanho da tela mudar
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            if (window.calendar) {
                window.calendar.updateSize();
            }
        }, 250);
    });
    
    // Listener para mudança de orientação em mobile
    window.addEventListener('orientationchange', function() {
        setTimeout(function() {
            if (window.calendar) {
                window.calendar.updateSize();
            }
        }, 300);
    });
}

// Configurar os modais de tarefa
function configurarModal() {
    // Modal para nova tarefa
    const modalNovaTarefa = document.getElementById('modalTarefa');
    
    if (modalNovaTarefa) {
        // Resetar formulário ao fechar o modal
        modalNovaTarefa.addEventListener('hidden.bs.modal', function() {
            document.getElementById('formTarefa').reset();
            document.getElementById('tarefaId').value = '';
            document.getElementById('listaAnexos').innerHTML = '';
            
            // Remover classe de categoria do cabeçalho
            const modalHeader = modalNovaTarefa.querySelector('.modal-header');
            modalHeader.className = 'modal-header';
            modalHeader.querySelector('.modal-title').textContent = 'Nova Tarefa';
            updateTaskCreatorDisplay(window.currentUserName || 'Você', window.currentUserAvatar || null);
        });
        
        // Inicializar o formulário
        configurarFormularioTarefa();
    }
}

// Configurar o formulário de tarefas
function configurarFormularioTarefa() {
    const formTarefa = document.getElementById('formTarefa');
    
    if (formTarefa) {
        // Configurar o select de categoria para atualizar a cor
        const selectCategoria = document.getElementById('categoria');
        selectCategoria.addEventListener('change', function() {
            atualizarCorCategoria();
        });
        
        // Configurar o checkbox de dia inteiro
        const campoHoraInicio = document.getElementById('horaInicio');
        const campoHoraFim = document.getElementById('horaFim');
        const checkboxDiaInteiro = document.getElementById('diaInteiro');

        // Por padrão, desbloquear campos de hora ao carregar o formulário
        campoHoraInicio.disabled = false;
        campoHoraFim.disabled = false;

        checkboxDiaInteiro.addEventListener('change', function() {
            if (this.checked) {
                campoHoraInicio.disabled = true;
                campoHoraFim.disabled = true;
                campoHoraInicio.value = '';
                campoHoraFim.value = '';
            } else {
                campoHoraInicio.disabled = false;
                campoHoraFim.disabled = false;
            }
        });
        
        // Auto título
        const statusEl = document.getElementById('status');
        const tipoEl = document.getElementById('tipo_servico');
        const participantesEl = document.getElementById('participantes');
        const localizacaoEl = document.getElementById('localizacao');
        const categoriaEl = document.getElementById('categoria');
        function atualizarTituloAutomatico() {
            const tituloInput = document.getElementById('titulo');
            if (!tituloInput) return;
            const statusV = statusEl ? statusEl.value.trim() : '';
            const tipoV = tipoEl ? tipoEl.value.trim() : '';
            const localizacaoV = localizacaoEl ? localizacaoEl.value.trim() : '';
            const categoriaV = categoriaEl ? categoriaEl.value.trim() : '';
            let participantesV = '';
            if (participantesEl) {
                // Preferir valores via jQuery/Select2 quando disponível, com fallback para selectedOptions
                if (window.jQuery && typeof jQuery !== 'undefined') {
                    const vals = jQuery(participantesEl).val();
                    if (Array.isArray(vals)) {
                        participantesV = vals.map(v => String(v).trim()).filter(Boolean).join(', ');
                    }
                }
                if (!participantesV) {
                    participantesV = Array.from(participantesEl.selectedOptions || [])
                        .map(o => o.value.trim())
                        .filter(Boolean)
                        .join(', ');
                }
            }
            const comps = [statusV, categoriaV, localizacaoV, tipoV, participantesV].filter(v => v);
            tituloInput.value = comps.join(' - ');
        }
        if (statusEl) statusEl.addEventListener('change', atualizarTituloAutomatico);
        if (tipoEl) tipoEl.addEventListener('input', atualizarTituloAutomatico);
        if (categoriaEl) categoriaEl.addEventListener('change', atualizarTituloAutomatico);
        if (participantesEl) {
            participantesEl.addEventListener('change', atualizarTituloAutomatico);
            // Integrar com eventos do Select2 para atualização em tempo real
            if (window.jQuery && typeof jQuery !== 'undefined') {
                jQuery(participantesEl).on('select2:select select2:unselect', atualizarTituloAutomatico);
            }
        }
        if (localizacaoEl) localizacaoEl.addEventListener('input', atualizarTituloAutomatico);
        atualizarTituloAutomatico();

        // Envio do formulário
        formTarefa.addEventListener('submit', async function(event) {
            event.preventDefault();
            const submitter = event.submitter || null;
            await salvarTarefa(submitter);
        });
        
        // Configurar botão de exclusão
        const btnExcluir = document.getElementById('btnExcluirTarefa');
        const tarefaIdInput = document.getElementById('tarefaId');
        const atualizarBotaoExclusao = () => {
            if (!btnExcluir) return;
            const temId = tarefaIdInput && tarefaIdInput.value.trim() !== '';
            btnExcluir.style.display = temId ? 'inline-block' : 'none';
        };
        if (btnExcluir) {
            btnExcluir.addEventListener('click', function() {
                confirmarExclusaoTarefa();
            });
        }
        // Reforçar visibilidade do botão conforme id presente/ausente
        atualizarBotaoExclusao();
        if (formTarefa) {
            formTarefa.addEventListener('input', atualizarBotaoExclusao);
            formTarefa.addEventListener('change', atualizarBotaoExclusao);
        }
    }
}

// Abrir modal para nova tarefa
function abrirModalNovaTarefa(dataInicio, dataFim, diaInteiro) {
    const modalEl = document.getElementById('modalTarefa');
    const modal = new bootstrap.Modal(modalEl);
    
    // Limpar formulário
    document.getElementById('formTarefa').reset();
    document.getElementById('tarefaId').value = '';
    document.getElementById('listaAnexos').innerHTML = '';
    // Limpar participantes do Select2
    const participantesField = document.getElementById('participantes');
    if (participantesField) {
        $(participantesField).val([]).trigger('change');
    }
    
    // Atualizar cabeçalho do modal
    const modalHeader = document.querySelector('#modalTarefa .modal-header');
    modalHeader.className = 'modal-header';
    document.querySelector('#modalTarefa .modal-title').textContent = 'Nova Tarefa';
    updateTaskCreatorDisplay(window.currentUserName || 'Você', window.currentUserAvatar || null);
    // Inicializar título automático com Status inicial
    const tituloInputNew = document.getElementById('titulo');
    const statusInicial = document.getElementById('status');
    if (tituloInputNew) {
        tituloInputNew.value = statusInicial ? statusInicial.value.trim() : 'Provisório';
    }
    
    // Preencher datas
    document.getElementById('dataInicio').value = dataInicio.slice(0, 10);
    document.getElementById('dataFim').value = dataFim ? dataFim.slice(0, 10) : dataInicio.slice(0, 10);
    
    // Configurar opção de dia inteiro
    document.getElementById('diaInteiro').checked = diaInteiro;
    const campoHoraInicio = document.getElementById('horaInicio');
    const campoHoraFim = document.getElementById('horaFim');
    
    if (diaInteiro) {
        campoHoraInicio.disabled = true;
        campoHoraFim.disabled = true;
        campoHoraInicio.value = '';
        campoHoraFim.value = '';
    } else {
        campoHoraInicio.disabled = false;
        campoHoraFim.disabled = false;
        campoHoraInicio.value = '08:00';
        campoHoraFim.value = '09:00';
    }
    
    // Configurar botões do modal
    const btnExcluir = document.getElementById('btnExcluirTarefa');
    if (btnExcluir) btnExcluir.style.display = 'none';
    
    // Mostrar modal
    modal.show();
}

// Carregar dados de uma tarefa existente
function carregarTarefa(tarefaId) {
    fetch(`api.php?acao=obter&id=${tarefaId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const tarefa = data.tarefa;
                
                // Abrir o modal
                const modal = new bootstrap.Modal(document.getElementById('modalTarefa'));
                
                // Preencher formulário com dados da tarefa
                document.getElementById('tarefaId').value = tarefa.id;
                document.getElementById('titulo').value = tarefa.titulo;
                const selectCategoriaEl = document.getElementById('categoria');
                // Tentar selecionar a categoria existente no select
                let option = Array.from(selectCategoriaEl.options).find(o => o.value === tarefa.categoria);
                if (!option) {
                    // Categoria não existe mais nas opções: criar uma opção temporária com a cor salva
                    option = new Option(tarefa.categoria, tarefa.categoria);
                    option.dataset.cor = tarefa.cor || '#007bff';
                    selectCategoriaEl.add(option);
                }
                selectCategoriaEl.value = tarefa.categoria;
                document.getElementById('dataInicio').value = tarefa.data_inicio;
                document.getElementById('dataFim').value = tarefa.data_fim;
                document.getElementById('diaInteiro').checked = tarefa.dia_inteiro == 1;
                const diasUteisEl = document.getElementById('diasUteis');
                if (diasUteisEl) diasUteisEl.checked = (tarefa.dias_uteis == 1);
                document.getElementById('horaInicio').value = tarefa.hora_inicio || '';
                document.getElementById('horaFim').value = tarefa.hora_fim || '';
                const participantesField = document.getElementById('participantes');
                if (participantesField) {
                    // transforma string "João,Maria" em array ["João", "Maria"]
                    const arr = (tarefa.participantes || '').split(',').map(s => s.trim()).filter(Boolean);
                    $(participantesField).val(arr).trigger('change');
                }
                const nomeCriador = tarefa.criador_exibicao_nome || tarefa.criador_nome || tarefa.nome_usuario;
                const avatarCriador = tarefa.criador_avatar || null;
                updateTaskCreatorDisplay(nomeCriador, avatarCriador);
                document.getElementById('localizacao').value = tarefa.localizacao || '';
                // Campo 'lembrete' removido
                document.getElementById('descricao').value = tarefa.descricao || '';
                // Novos campos: status e tipo_servico
                const statusEl = document.getElementById('status');
                if (statusEl) {
                    let st = tarefa.status || 'Provisório';
                    if (st === 'Concluído') st = 'Confirmado';
                    if (st === 'Pendente') st = 'Provisório';
                    statusEl.value = st;
                }
                const tipoServicoEl = document.getElementById('tipo_servico');
                if (tipoServicoEl) tipoServicoEl.value = tarefa.tipo_servico || '';
                // Recalcular título automático inicial
                const tituloInputEdit = document.getElementById('titulo');
                if (tituloInputEdit) {
                    const participantesEl2 = document.getElementById('participantes');
                    const localizacaoEl2 = document.getElementById('localizacao');
                    let participantesV = '';
                    if (participantesEl2) {
                        participantesV = Array.from(participantesEl2.selectedOptions || [])
                            .map(o => o.value.trim())
                            .filter(Boolean)
                            .join(', ');
                    }
                    const comps = [
                        statusEl ? statusEl.value.trim() : '',
                        localizacaoEl2 ? localizacaoEl2.value.trim() : '',
                        tipoServicoEl ? tipoServicoEl.value.trim() : '',
                        participantesV
                    ].filter(v => v);
                    tituloInputEdit.value = comps.join(' - ');
                }

                // Preencher checklist dinamicamente
                const checklistValues = (tarefa.checklist || '').split('|').map(s => s.trim()).filter(Boolean);
                // Selecionar todos os checkboxes de checklist na página
                const checklistCheckboxes = document.querySelectorAll("input[type=checkbox][name='checklist[]']");
                checklistCheckboxes.forEach(checkbox => {
                    checkbox.checked = checklistValues.includes(checkbox.value);
                });
                
                // Atualizar estado dos campos de hora
                const campoHoraInicio = document.getElementById('horaInicio');
                const campoHoraFim = document.getElementById('horaFim');
                
                if (tarefa.dia_inteiro == 1) {
                    campoHoraInicio.disabled = true;
                    campoHoraFim.disabled = true;
                } else {
                    campoHoraInicio.disabled = false;
                    campoHoraFim.disabled = false;
                }
                
                // Atualizar cor: se a opção tiver data-cor usamos ela; senão manter a cor da tarefa
                const selectedOption = selectCategoriaEl.options[selectCategoriaEl.selectedIndex];
                if (selectedOption && selectedOption.dataset && selectedOption.dataset.cor) {
                    atualizarCorCategoria();
                } else if (tarefa.cor) {
                    document.getElementById('cor').value = tarefa.cor;
                    const modalHeader = document.querySelector('#modalTarefa .modal-header');
                    if (modalHeader) {
                        modalHeader.style.borderLeft = `4px solid ${tarefa.cor}`;
                    }
                }
                
                // Atualizar lista de anexos
                atualizarListaAnexos(tarefa.anexos);
                
                // Verificar se o usuário atual é o criador da tarefa ou um administrador
                const isCreator = tarefa.usuario_id == getUserId(); // Função que precisamos implementar
                const isAdmin = isUserAdmin(); // Função que precisamos implementar
                
                // Mostrar botão de exclusão apenas para o criador ou para administradores
                const btnExcluir = document.getElementById('btnExcluirTarefa');
                if (btnExcluir) {
                    if (isCreator || isAdmin) {
                        btnExcluir.style.display = 'inline-block';
                    } else {
                        btnExcluir.style.display = 'none';
                    }
                }
                
                document.querySelector('#modalTarefa .modal-title').textContent = 'Editar Tarefa';
                
                // Mostrar modal
                modal.show();
            } else {
                alert('Erro ao carregar tarefa: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar tarefa:', error);
            if (window.showAlert) showAlert('Erro ao carregar tarefa. Por favor, tente novamente.', 'error'); else alert('Erro ao carregar tarefa. Por favor, tente novamente.');
        });
}

// Atualizar a cor do cabeçalho do modal com base na categoria selecionada
function atualizarCorCategoria() {
    const categoria = document.getElementById('categoria').value;
    const modalHeader = document.querySelector('#modalTarefa .modal-header');
    
    // Remover estilos/classes anteriores
    modalHeader.className = 'modal-header';
    modalHeader.style.borderLeft = '';

    // Definir a cor com base no atributo data-cor da opção selecionada
    const select = document.getElementById('categoria');
    const selectedOption = select ? select.options[select.selectedIndex] : null;
    const cor = (selectedOption && selectedOption.dataset && selectedOption.dataset.cor) ? selectedOption.dataset.cor : '#007bff';
    document.getElementById('cor').value = cor;

    // Aplicar um destaque visual opcional no cabeçalho
    modalHeader.style.borderLeft = `4px solid ${cor}`;
}

// Atualizar a lista de anexos no modal
function atualizarListaAnexos(anexos) {
    const listaAnexos = document.getElementById('listaAnexos');
    listaAnexos.innerHTML = '';
    
    if (anexos && anexos.length > 0) {
        anexos.forEach(anexo => {
            const itemAnexo = document.createElement('div');
            itemAnexo.className = 'anexo-item';
            itemAnexo.innerHTML = `
                <span>${anexo.nome_arquivo}</span>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-secondary" title="Downloads desabilitados">Bloqueado</span>
                    <button type="button" class="btn btn-sm btn-danger" onclick="excluirAnexo(${anexo.id}, ${anexo.tarefa_id})">Excluir</button>
                </div>
            `;
            listaAnexos.appendChild(itemAnexo);
        });
    } else {
        listaAnexos.innerHTML = '<p class="text-muted">Nenhum anexo disponível.</p>';
    }
}

// Salvar tarefa (criar nova ou atualizar existente)
async function salvarTarefa(submitter = null) {
    const form = document.getElementById('formTarefa');
    const tarefaId = document.getElementById('tarefaId').value;
    // Detectar se devemos enviar aviso após salvar
    const shouldNotify = !!(submitter && submitter.name === 'enviar_aviso');
    // Construir FormData sem incluir o botão submit (para evitar envio de aviso nesta primeira chamada)
    const formData = new FormData(form);
    // Garantir que o backend não enviará aviso nesta primeira chamada
    formData.delete('enviar_aviso');

    // Validação: se deve notificar, garantir que há participantes
    if (shouldNotify) {
        const participantesField = document.getElementById('participantes');
        if (participantesField) {
            const selecionados = Array.from(participantesField.selectedOptions || []).map(o => o.value).filter(Boolean);
            if (!selecionados || selecionados.length === 0) {
                if (window.showAlert) showAlert('Por favor, selecione pelo menos um participante para enviar avisos.', 'warning'); else alert('Por favor, selecione pelo menos um participante para enviar avisos.');
                if (submitter && submitter.id === 'btnSalvarEnviar') {
                    submitter.disabled = false;
                    submitter.innerHTML = '<i class=\"fas fa-envelope\"></i> Salvar e Enviar Aviso';
                }
                return;
            }
        }
    }
    
    // Determinar se é criação ou atualização
    const acao = tarefaId ? 'atualizar' : 'criar';
    
    // Adicionar o ID se for atualização
    if (tarefaId) {
        formData.append('id', tarefaId);
    }
    // Ajustar título final antes do envio (será refeito no servidor)
    const tituloInput = document.getElementById('titulo');
    if (tituloInput) formData.set('titulo', tituloInput.value);
    
    // Enviar requisição para a API (somente criar/atualizar, sem enviar avisos nesta etapa)
    fetch(`api.php?acao=${acao}`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Se foi criação, atualizar o campo tarefaId com o ID retornado
            if (!tarefaId && data.id) {
                document.getElementById('tarefaId').value = data.id;
            }
            // Fechar o modal
            bootstrap.Modal.getInstance(document.getElementById('modalTarefa')).hide();

            // Atualizar o calendário
            window.calendar.refetchEvents();

            // Exibir mensagem de sucesso
            if (window.showAlert) showAlert(tarefaId ? 'Tarefa atualizada com sucesso!' : 'Tarefa criada com sucesso!', 'success'); else alert(tarefaId ? 'Tarefa atualizada com sucesso!' : 'Tarefa criada com sucesso!');

            // Se for para enviar aviso, fazer uma segunda requisição assíncrona apenas para envio de e-mails
            if (shouldNotify) {
                const btn = document.getElementById('btnSalvarEnviar');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
                }

                const csrfToken = document.getElementById('csrf_token').value;
                const idParaAviso = tarefaId || data.id; // usar o id retornado na criação
                if (idParaAviso) {
                    const avisoData = new FormData();
                    avisoData.append('id', idParaAviso);
                    avisoData.append('csrf_token', csrfToken);

                    // Chamar endpoint específico para enviar avisos sem bloquear o salvamento
                    fetch('api.php?acao=enviar_aviso', {
                        method: 'POST',
                        body: avisoData
                    })
                    .then(r => r.json())
                    .then(avisoResp => {
                        if (avisoResp.status === 'success') {
                            if (window.showAlert) showAlert('Avisos enviados aos participantes.', 'success'); else alert('Avisos enviados aos participantes.');
                        } else {
                            if (window.showAlert) showAlert('Tarefa salva, mas houve um problema ao enviar os avisos: ' + (avisoResp.message || 'Erro desconhecido'), 'warning'); else alert('Tarefa salva, mas houve um problema ao enviar os avisos: ' + (avisoResp.message || 'Erro desconhecido'));
                        }
                    })
                    .catch(err => {
                        console.error('Erro ao enviar avisos:', err);
                        if (window.showAlert) showAlert('Tarefa salva, mas ocorreu um erro ao enviar os avisos. Verifique se o PHPMailer está configurado corretamente. Caso o problema persista, entre em contato com o administrador do sistema para verificar as configurações de e-mail (SMTP).', 'error'); else alert('Tarefa salva, mas ocorreu um erro ao enviar os avisos. Verifique se o PHPMailer está configurado corretamente. Caso o problema persista, entre em contato com o administrador do sistema para verificar as configurações de e-mail (SMTP).');
                    })
                    .finally(() => {
                        // Restaurar botão
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-envelope"></i> Salvar e Enviar Aviso';
                        }
                    });
                }
            }
            else {
                // Se não for para enviar aviso, e o submit veio do botão de aviso (raro), restaurar estado
                if (submitter && submitter.id === 'btnSalvarEnviar') {
                    submitter.disabled = false;
                    submitter.innerHTML = '<i class="fas fa-envelope"></i> Salvar e Enviar Aviso';
                }
            }
        } else {
            if (window.showAlert) showAlert('Erro: ' + data.message, 'error'); else alert('Erro: ' + data.message);
            if (submitter && submitter.id === 'btnSalvarEnviar') {
                submitter.disabled = false;
                submitter.innerHTML = '<i class="fas fa-envelope"></i> Salvar e Enviar Aviso';
            }
        }
    })
    .catch(error => {
        console.error('Erro ao salvar tarefa:', error);
        if (window.showAlert) showAlert('Erro ao salvar tarefa. Por favor, tente novamente.', 'error'); else alert('Erro ao salvar tarefa. Por favor, tente novamente.');
        if (submitter && submitter.id === 'btnSalvarEnviar') {
            submitter.disabled = false;
            submitter.innerHTML = '<i class="fas fa-envelope"></i> Salvar e Enviar Aviso';
        }
    });
}

// Confirmar exclusão de tarefa
function confirmarExclusaoTarefa() {
    if (confirm('Tem certeza que deseja excluir esta tarefa?')) {
        const tarefaId = document.getElementById('tarefaId').value;
        excluirTarefa(tarefaId);
    }
}

// Excluir tarefa
function excluirTarefa(tarefaId) {
    const formData = new FormData();
    formData.append('id', tarefaId);
    formData.append('csrf_token', document.getElementById('csrf_token').value);
    fetch('api.php?acao=excluir', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Fechar o modal
            bootstrap.Modal.getInstance(document.getElementById('modalTarefa')).hide();
            
            // Atualizar o calendário
            window.calendar.refetchEvents();
            
            // Exibir mensagem de sucesso
            if (window.showAlert) showAlert('Tarefa excluída com sucesso!', 'success'); else alert('Tarefa excluída com sucesso!');
        } else {
            if (window.showAlert) showAlert('Erro: ' + data.message, 'error'); else alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro ao excluir tarefa:', error);
        if (window.showAlert) showAlert('Erro ao excluir tarefa. Por favor, tente novamente.', 'error'); else alert('Erro ao excluir tarefa. Por favor, tente novamente.');
    });
}

// Excluir anexo
function excluirAnexo(anexoId, tarefaId) {
    if (confirm('Tem certeza que deseja excluir este anexo?')) {
        const formData = new FormData();
        formData.append('anexo_id', anexoId);
        formData.append('tarefa_id', tarefaId);
        formData.append('csrf_token', document.getElementById('csrf_token').value);
        
        fetch('api.php?acao=excluir_anexo', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Recarregar a tarefa para atualizar os anexos
                carregarTarefa(tarefaId);
                
                // Exibir mensagem de sucesso
                if (window.showAlert) showAlert('Anexo excluído com sucesso!', 'success'); else alert('Anexo excluído com sucesso!');
            } else {
                if (window.showAlert) showAlert('Erro: ' + data.message, 'error'); else alert('Erro: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro ao excluir anexo:', error);
            if (window.showAlert) showAlert('Erro ao excluir anexo. Por favor, tente novamente.', 'error'); else alert('Erro ao excluir anexo. Por favor, tente novamente.');
        });
    }
}

// Atualizar data de tarefa (após arrastar ou redimensionar)
function atualizarDataTarefa(evento) {
    // Para blocos de dias úteis usamos tarefa_id original; senão, usar id do evento
    const tarefaId = (evento.extendedProps && evento.extendedProps.tarefa_id)
        ? evento.extendedProps.tarefa_id
        : evento.id;
    const formData = new FormData();
    
    formData.append('id', tarefaId);
    // Usar o título original salvo no banco
    const tituloOriginal = (evento.extendedProps && evento.extendedProps.titulo_original) ? evento.extendedProps.titulo_original : evento.title;
    formData.append('titulo', tituloOriginal);
    formData.append('categoria', evento.extendedProps.categoria);
    formData.append('cor', evento.backgroundColor);
    // Helpers para formatar datas/horas no fuso local, evitando deslocamentos por UTC
    const toLocalDateStr = (d) => {
        const yr = d.getFullYear();
        const mo = String(d.getMonth() + 1).padStart(2, '0');
        const da = String(d.getDate()).padStart(2, '0');
        return `${yr}-${mo}-${da}`;
    };
    const toLocalTimeStr = (d) => {
        const hh = String(d.getHours()).padStart(2, '0');
        const mm = String(d.getMinutes()).padStart(2, '0');
        return `${hh}:${mm}`;
    };

    // Data início
    let dataInicio = '';
    if (evento.start) {
        if (evento.allDay && evento.startStr) {
            dataInicio = evento.startStr.slice(0, 10);
        } else {
            dataInicio = toLocalDateStr(new Date(evento.start));
        }
    }
    formData.append('data_inicio', dataInicio);

    // Data fim
    let dataFim = '';
    if (evento.allDay) {
        // endStr é exclusivo; subtrair um dia de forma baseada em string
        if (evento.endStr) {
            const endDate = new Date(evento.endStr.slice(0, 10) + 'T00:00:00');
            endDate.setDate(endDate.getDate() - 1);
            dataFim = toLocalDateStr(endDate);
        } else if (evento.startStr) {
            dataFim = evento.startStr.slice(0, 10);
        }
    } else {
        if (evento.end) {
            dataFim = toLocalDateStr(new Date(evento.end));
        } else if (evento.start) {
            dataFim = toLocalDateStr(new Date(evento.start));
        }
    }
    
    formData.append('data_fim', dataFim);
    formData.append('hora_inicio', evento.start && !evento.allDay ? toLocalTimeStr(new Date(evento.start)) : '');
    formData.append('hora_fim', evento.end && !evento.allDay ? toLocalTimeStr(new Date(evento.end)) : '');
    formData.append('dia_inteiro', evento.allDay ? '1' : '0');
    // Preservar dias_uteis ao arrastar/redimensionar
    const diasUteisFlag = (evento.extendedProps && (evento.extendedProps.dias_uteis === true || evento.extendedProps.dias_uteis === 1 || evento.extendedProps.dias_uteis === '1')) ? '1' : '0';
    formData.append('dias_uteis', diasUteisFlag);
    formData.append('participantes', evento.extendedProps.participantes || '');
    formData.append('localizacao', evento.extendedProps.localizacao || '');
    // Campo 'lembrete' removido
    formData.append('descricao', '');
    // Preservar novos campos em updates de arrastar/redimensionar
    formData.append('status', (evento.extendedProps && evento.extendedProps.status) ? evento.extendedProps.status : 'Provisório');
    formData.append('tipo_servico', (evento.extendedProps && evento.extendedProps.tipo_servico) ? evento.extendedProps.tipo_servico : '');
    formData.append('csrf_token', document.getElementById('csrf_token').value);
    
    fetch('api.php?acao=atualizar', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status !== 'success') {
            if (window.showAlert) showAlert('Erro ao atualizar data: ' + data.message, 'error'); else alert('Erro ao atualizar data: ' + data.message);
            window.calendar.refetchEvents();
        }
    })
    .catch(error => {
        console.error('Erro ao atualizar data da tarefa:', error);
        if (window.showAlert) showAlert('Erro ao atualizar data. Por favor, tente novamente.', 'error'); else alert('Erro ao atualizar data. Por favor, tente novamente.');
        window.calendar.refetchEvents();
    });
}

// ---- Exportação de Relatório (Lista) ----
function formatYMD(d){
    const yr = d.getFullYear();
    const mo = String(d.getMonth()+1).padStart(2,'0');
    const da = String(d.getDate()).padStart(2,'0');
    return `${yr}-${mo}-${da}`;
}
function baixarRelatorio(){
    if (!window.calendar) return;
    const view = window.calendar.view;
    const inicio = formatYMD(view.currentStart);
    // FullCalendar usa end exclusivo; subtrair 1 dia para incluir fim
    const endExclusive = new Date(view.currentEnd.getTime());
    endExclusive.setDate(endExclusive.getDate()-1);
    const fim = formatYMD(endExclusive);
    let url = `api.php?acao=exportar_relatorio&inicio=${encodeURIComponent(inicio)}&fim=${encodeURIComponent(fim)}`;
    if (window.filtroTarefas) {
        if (window.filtroTarefas.participantes && window.filtroTarefas.participantes.length > 0) {
            url += `&participantes=${encodeURIComponent(window.filtroTarefas.participantes.join(','))}`;
        }
        if (window.filtroTarefas.categoria && window.filtroTarefas.categoria !== '') {
            url += `&categoria=${encodeURIComponent(window.filtroTarefas.categoria)}`;
        }
        if (window.filtroTarefas.tipoServicoTermo && window.filtroTarefas.tipoServicoTermo !== '') {
            url += `&tipo_servico=${encodeURIComponent(window.filtroTarefas.tipoServicoTermo)}`;
        }
    }
    // Disparar download
    const a = document.createElement('a');
    a.href = url; a.style.display = 'none';
    document.body.appendChild(a); a.click(); a.remove();
}