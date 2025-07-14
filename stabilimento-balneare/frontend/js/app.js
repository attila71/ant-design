// Configurazione API
const API_BASE = '../backend/api.php';

// Variabili globali
let currentTab = 'dashboard';
let tendeData = [];
let clientiData = [];
let prenotazioniData = [];
let serviziData = [];
let draggedTenda = null;

// === INIZIALIZZAZIONE ===
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    setupEventListeners();
    setDefaultDates();
});

function initializeApp() {
    loadDashboardData();
    loadClienti();
    loadServizi();
    showLoading(false);
}

function setupEventListeners() {
    // Navigazione tab
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', function() {
            const tab = this.dataset.tab;
            switchTab(tab);
        });
    });

    // Form submissions
    document.getElementById('form-prenotazione').addEventListener('submit', handlePrenotazioneSubmit);
    document.getElementById('form-cliente').addEventListener('submit', handleClienteSubmit);
    document.getElementById('form-conto').addEventListener('submit', handleContoSubmit);

    // Search clienti
    document.getElementById('search-clienti').addEventListener('input', function() {
        searchClienti(this.value);
    });

    // Data visualizzazione mappa
    document.getElementById('data-visualizzazione').addEventListener('change', function() {
        loadMappaTende(this.value);
    });

    // Filtri prenotazioni
    document.getElementById('filtro-data-inizio').addEventListener('change', loadPrenotazioni);
    document.getElementById('filtro-data-fine').addEventListener('change', loadPrenotazioni);

    // Servizio select change
    document.getElementById('servizio-select').addEventListener('change', function() {
        const servizio = serviziData.find(s => s.id == this.value);
        if (servizio) {
            document.getElementById('descrizione-conto').value = servizio.nome;
            document.getElementById('importo-conto').value = servizio.prezzo;
        }
    });

    // Close modals on outside click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id.replace('modal-', ''));
            }
        });
    });
}

function setDefaultDates() {
    const today = new Date().toISOString().split('T')[0];
    const nextMonth = new Date();
    nextMonth.setMonth(nextMonth.getMonth() + 1);
    const nextMonthStr = nextMonth.toISOString().split('T')[0];

    document.getElementById('data-visualizzazione').value = today;
    document.getElementById('filtro-data-inizio').value = today;
    document.getElementById('filtro-data-fine').value = nextMonthStr;
    document.getElementById('data-inizio').value = today;
    document.getElementById('data-fine').value = today;
}

// === UTILITY FUNCTIONS ===
function showLoading(show = true) {
    const overlay = document.getElementById('loading-overlay');
    overlay.classList.toggle('active', show);
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 5000);
}

async function apiCall(path, method = 'GET', data = null) {
    try {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
            }
        };

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }

        const url = method === 'GET' && data 
            ? `${API_BASE}?path=${path}&${new URLSearchParams(data).toString()}`
            : `${API_BASE}?path=${path}`;

        const response = await fetch(url, options);
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Errore API');
        }

        return result;
    } catch (error) {
        showToast(error.message, 'error');
        throw error;
    }
}

// === NAVIGATION ===
function switchTab(tabName) {
    // Update navigation
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

    // Update content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(tabName).classList.add('active');

    currentTab = tabName;

    // Load data for specific tabs
    switch (tabName) {
        case 'mappa':
            loadMappaTende();
            break;
        case 'prenotazioni':
            loadPrenotazioni();
            break;
        case 'clienti':
            loadClienti();
            break;
        case 'conti':
            loadContiAperti();
            break;
    }
}

// === MODAL MANAGEMENT ===
function openModal(modalName) {
    const modal = document.getElementById(`modal-${modalName}`);
    modal.classList.add('active');

    // Populate dropdowns
    switch (modalName) {
        case 'nuova-prenotazione':
            populateClienteSelect();
            populateTendaSelect();
            populatePrenotazioneSelect();
            break;
        case 'nuovo-conto':
            populatePrenotazioneSelect();
            populateServizioSelect();
            break;
    }
}

function closeModal(modalName) {
    const modal = document.getElementById(`modal-${modalName}`);
    modal.classList.remove('active');
    
    // Reset forms
    const form = modal.querySelector('form');
    if (form) form.reset();
}

// === DASHBOARD ===
async function loadDashboardData() {
    try {
        showLoading(true);
        const stats = await apiCall('stats');
        
        // Update header stats
        document.getElementById('stat-occupate').textContent = stats.tende_occupate;
        document.getElementById('stat-oggi').textContent = stats.prenotazioni_oggi;

        // Update dashboard stats
        document.getElementById('dashboard-occupate').textContent = stats.tende_occupate;
        document.getElementById('dashboard-totali').textContent = stats.tende_totali;
        document.getElementById('dashboard-fatturato').textContent = `€${parseFloat(stats.fatturato_mese).toFixed(2)}`;
        document.getElementById('dashboard-conti').textContent = `€${parseFloat(stats.conti_aperti).toFixed(2)}`;

        // Load recent bookings
        await loadRecentBookings();
        await loadUnpaidBills();

    } catch (error) {
        console.error('Error loading dashboard data:', error);
    } finally {
        showLoading(false);
    }
}

async function loadRecentBookings() {
    try {
        const today = new Date().toISOString().split('T')[0];
        const nextWeek = new Date();
        nextWeek.setDate(nextWeek.getDate() + 7);
        const nextWeekStr = nextWeek.toISOString().split('T')[0];

        const prenotazioni = await apiCall('prenotazioni', 'GET', {
            data_inizio: today,
            data_fine: nextWeekStr
        });

        const container = document.getElementById('recent-bookings');
        
        if (prenotazioni.length === 0) {
            container.innerHTML = '<p class="text-center text-secondary">Nessuna prenotazione recente</p>';
            return;
        }

        container.innerHTML = prenotazioni.slice(0, 5).map(p => `
            <div class="booking-item" style="padding: 0.75rem; border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong>${p.cliente_nome}</strong>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">
                            ${p.numero_tenda} • ${formatDate(p.data_inizio)} - ${formatDate(p.data_fine)}
                        </div>
                    </div>
                    <span class="badge ${p.stato}">${p.stato}</span>
                </div>
            </div>
        `).join('');
    } catch (error) {
        console.error('Error loading recent bookings:', error);
    }
}

async function loadUnpaidBills() {
    try {
        const conti = await apiCall('conti');
        const container = document.getElementById('unpaid-bills');
        
        if (conti.length === 0) {
            container.innerHTML = '<p class="text-center text-secondary">Nessun conto aperto</p>';
            return;
        }

        container.innerHTML = conti.slice(0, 5).map(c => `
            <div class="bill-item" style="padding: 0.75rem; border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong>${c.cliente_nome}</strong> - ${c.numero_tenda}
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">
                            ${c.descrizione}
                        </div>
                    </div>
                    <span style="font-weight: 600; color: var(--danger-color);">€${parseFloat(c.importo).toFixed(2)}</span>
                </div>
            </div>
        `).join('');
    } catch (error) {
        console.error('Error loading unpaid bills:', error);
    }
}

// === MAPPA TENDE ===
async function loadMappaTende(data = null) {
    try {
        showLoading(true);
        tendeData = await apiCall('tende');
        renderMappaTende();
    } catch (error) {
        console.error('Error loading tende:', error);
    } finally {
        showLoading(false);
    }
}

function renderMappaTende() {
    const container = document.getElementById('mappa-container');
    container.innerHTML = '';

    tendeData.forEach(tenda => {
        const tendaEl = document.createElement('div');
        tendaEl.className = `tenda ${tenda.stato_attuale}`;
        tendaEl.dataset.tendaId = tenda.id;
        tendaEl.style.left = `${tenda.posizione_x}px`;
        tendaEl.style.top = `${tenda.posizione_y}px`;
        
        const clienteInfo = tenda.cliente_nome ? `<div class="tenda-cliente">${tenda.cliente_nome}</div>` : '';
        
        tendaEl.innerHTML = `
            <div class="tenda-numero">${tenda.numero_tenda}</div>
            ${clienteInfo}
        `;

        // Drag & Drop
        tendaEl.draggable = true;
        tendaEl.addEventListener('dragstart', handleDragStart);
        tendaEl.addEventListener('dragend', handleDragEnd);

        // Tooltip info
        tendaEl.title = `${tenda.numero_tenda} - ${tenda.zona} - ${tenda.tipo} - €${tenda.prezzo_giornaliero}/giorno`;

        container.appendChild(tendaEl);
    });

    // Drop zone
    container.addEventListener('dragover', handleDragOver);
    container.addEventListener('drop', handleDrop);
}

function handleDragStart(e) {
    draggedTenda = this;
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', this.outerHTML);
}

function handleDragEnd(e) {
    this.classList.remove('dragging');
    draggedTenda = null;
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

async function handleDrop(e) {
    e.preventDefault();
    
    if (!draggedTenda) return;

    const rect = e.currentTarget.getBoundingClientRect();
    const x = e.clientX - rect.left - 40; // Centro della tenda
    const y = e.clientY - rect.top - 40;

    // Limiti della mappa
    const maxX = rect.width - 80;
    const maxY = rect.height - 80;
    
    const newX = Math.max(0, Math.min(x, maxX));
    const newY = Math.max(0, Math.min(y, maxY));

    // Aggiorna posizione visuale
    draggedTenda.style.left = `${newX}px`;
    draggedTenda.style.top = `${newY}px`;

    // Salva nel database
    try {
        await apiCall('tende', 'PUT', {
            id: parseInt(draggedTenda.dataset.tendaId),
            posizione_x: newX,
            posizione_y: newY
        });

        showToast('Posizione tenda aggiornata');
    } catch (error) {
        // Ripristina posizione originale in caso di errore
        const originalTenda = tendeData.find(t => t.id == draggedTenda.dataset.tendaId);
        draggedTenda.style.left = `${originalTenda.posizione_x}px`;
        draggedTenda.style.top = `${originalTenda.posizione_y}px`;
    }
}

function resetPosizioni() {
    if (confirm('Ripristinare le posizioni originali di tutte le tende?')) {
        // Implementazione reset posizioni
        showToast('Funzione reset posizioni da implementare');
    }
}

// === PRENOTAZIONI ===
async function loadPrenotazioni() {
    try {
        showLoading(true);
        const dataInizio = document.getElementById('filtro-data-inizio').value;
        const dataFine = document.getElementById('filtro-data-fine').value;
        
        prenotazioniData = await apiCall('prenotazioni', 'GET', {
            data_inizio: dataInizio,
            data_fine: dataFine
        });

        renderPrenotazioni();
    } catch (error) {
        console.error('Error loading prenotazioni:', error);
    } finally {
        showLoading(false);
    }
}

function renderPrenotazioni() {
    const tbody = document.getElementById('prenotazioni-tbody');
    
    if (prenotazioniData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">Nessuna prenotazione trovata</td></tr>';
        return;
    }

    tbody.innerHTML = prenotazioniData.map(p => `
        <tr>
            <td>${p.cliente_nome}</td>
            <td>${p.cliente_telefono}</td>
            <td>${p.numero_tenda}</td>
            <td>${formatDate(p.data_inizio)}</td>
            <td>${formatDate(p.data_fine)}</td>
            <td><span class="badge ${p.stato}">${p.stato}</span></td>
            <td>€${parseFloat(p.prezzo_totale).toFixed(2)}</td>
            <td>
                <button class="btn btn-sm btn-secondary" onclick="editPrenotazione(${p.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deletePrenotazione(${p.id})">
                    <i class="fas fa-trash"></i>
                </button>
                <button class="btn btn-sm btn-primary" onclick="viewConti(${p.id})">
                    <i class="fas fa-receipt"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

async function handlePrenotazioneSubmit(e) {
    e.preventDefault();
    
    const formData = {
        cliente_id: document.getElementById('cliente-select').value,
        tenda_id: document.getElementById('tenda-select').value,
        data_inizio: document.getElementById('data-inizio').value,
        data_fine: document.getElementById('data-fine').value,
        prezzo_totale: parseFloat(document.getElementById('prezzo-totale').value),
        acconto: parseFloat(document.getElementById('acconto').value) || 0,
        note: document.getElementById('note-prenotazione').value
    };

    try {
        showLoading(true);
        await apiCall('prenotazioni', 'POST', formData);
        showToast('Prenotazione creata con successo');
        closeModal('nuova-prenotazione');
        
        if (currentTab === 'prenotazioni') {
            loadPrenotazioni();
        }
        if (currentTab === 'dashboard') {
            loadDashboardData();
        }
    } catch (error) {
        console.error('Error creating prenotazione:', error);
    } finally {
        showLoading(false);
    }
}

async function deletePrenotazione(id) {
    if (confirm('Sei sicuro di voler annullare questa prenotazione?')) {
        try {
            await apiCall('prenotazioni', 'DELETE', { id });
            showToast('Prenotazione annullata');
            loadPrenotazioni();
        } catch (error) {
            console.error('Error deleting prenotazione:', error);
        }
    }
}

function editPrenotazione(id) {
    // Implementazione modifica prenotazione
    showToast('Funzione modifica prenotazione da implementare');
}

function viewConti(prenotazioneId) {
    // Switch to conti tab and filter by prenotazione
    switchTab('conti');
    // Implementare filtro per prenotazione specifica
}

// === CLIENTI ===
async function loadClienti() {
    try {
        showLoading(true);
        clientiData = await apiCall('clienti');
        renderClienti();
    } catch (error) {
        console.error('Error loading clienti:', error);
    } finally {
        showLoading(false);
    }
}

function renderClienti() {
    const tbody = document.getElementById('clienti-tbody');
    
    if (clientiData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Nessun cliente trovato</td></tr>';
        return;
    }

    tbody.innerHTML = clientiData.map(c => `
        <tr>
            <td>${c.nome}</td>
            <td>${c.telefono}</td>
            <td>${c.email || '-'}</td>
            <td>${formatDateTime(c.data_registrazione)}</td>
            <td>
                <button class="btn btn-sm btn-secondary" onclick="editCliente(${c.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-primary" onclick="newPrenotazioneForCliente(${c.id})">
                    <i class="fas fa-calendar-plus"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

async function searchClienti(term) {
    if (term.length < 2) {
        renderClienti();
        return;
    }

    try {
        const clienti = await apiCall('clienti/search', 'GET', { term });
        clientiData = clienti;
        renderClienti();
    } catch (error) {
        console.error('Error searching clienti:', error);
    }
}

async function handleClienteSubmit(e) {
    e.preventDefault();
    
    const formData = {
        nome: document.getElementById('nome-cliente').value,
        telefono: document.getElementById('telefono-cliente').value,
        email: document.getElementById('email-cliente').value,
        note: document.getElementById('note-cliente').value
    };

    try {
        showLoading(true);
        await apiCall('clienti', 'POST', formData);
        showToast('Cliente creato con successo');
        closeModal('nuovo-cliente');
        loadClienti();
    } catch (error) {
        console.error('Error creating cliente:', error);
    } finally {
        showLoading(false);
    }
}

function editCliente(id) {
    showToast('Funzione modifica cliente da implementare');
}

function newPrenotazioneForCliente(clienteId) {
    openModal('nuova-prenotazione');
    setTimeout(() => {
        document.getElementById('cliente-select').value = clienteId;
    }, 100);
}

// === CONTI APERTI ===
async function loadContiAperti() {
    try {
        showLoading(true);
        const conti = await apiCall('conti');
        renderContiAperti(conti);
    } catch (error) {
        console.error('Error loading conti:', error);
    } finally {
        showLoading(false);
    }
}

function renderContiAperti(conti) {
    const tbody = document.getElementById('conti-tbody');
    
    if (conti.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">Nessun conto aperto</td></tr>';
        return;
    }

    tbody.innerHTML = conti.map(c => `
        <tr>
            <td>${c.cliente_nome}</td>
            <td>${c.numero_tenda}</td>
            <td>${c.descrizione}</td>
            <td>€${parseFloat(c.importo).toFixed(2)}</td>
            <td>${formatDateTime(c.data_inserimento)}</td>
            <td>
                <span class="badge ${c.pagato ? 'confermata' : 'provvisoria'}">
                    ${c.pagato ? 'Pagato' : 'Da Pagare'}
                </span>
            </td>
            <td>
                ${!c.pagato ? `
                    <button class="btn btn-sm btn-success" onclick="markAsPaid(${c.id})">
                        <i class="fas fa-check"></i> Segna Pagato
                    </button>
                ` : ''}
            </td>
        </tr>
    `).join('');
}

async function handleContoSubmit(e) {
    e.preventDefault();
    
    const formData = {
        prenotazione_id: document.getElementById('prenotazione-select').value,
        descrizione: document.getElementById('descrizione-conto').value,
        importo: parseFloat(document.getElementById('importo-conto').value),
        note: document.getElementById('note-conto').value
    };

    try {
        showLoading(true);
        await apiCall('conti', 'POST', formData);
        showToast('Spesa aggiunta con successo');
        closeModal('nuovo-conto');
        loadContiAperti();
    } catch (error) {
        console.error('Error creating conto:', error);
    } finally {
        showLoading(false);
    }
}

async function markAsPaid(id) {
    try {
        await apiCall('conti', 'PUT', { id, pagato: true });
        showToast('Conto segnato come pagato');
        loadContiAperti();
        if (currentTab === 'dashboard') {
            loadDashboardData();
        }
    } catch (error) {
        console.error('Error marking as paid:', error);
    }
}

// === POPULATE DROPDOWNS ===
async function populateClienteSelect() {
    const select = document.getElementById('cliente-select');
    select.innerHTML = '<option value="">Seleziona cliente...</option>';
    
    clientiData.forEach(cliente => {
        select.innerHTML += `<option value="${cliente.id}">${cliente.nome} - ${cliente.telefono}</option>`;
    });
}

async function populateTendaSelect() {
    if (tendeData.length === 0) {
        tendeData = await apiCall('tende');
    }
    
    const select = document.getElementById('tenda-select');
    select.innerHTML = '<option value="">Seleziona tenda...</option>';
    
    tendeData.forEach(tenda => {
        const disponibile = tenda.stato_attuale === 'libera';
        select.innerHTML += `
            <option value="${tenda.id}" ${!disponibile ? 'disabled' : ''}>
                ${tenda.numero_tenda} - ${tenda.zona} - ${tenda.tipo} 
                ${!disponibile ? '(Occupata)' : `- €${tenda.prezzo_giornaliero}/g`}
            </option>
        `;
    });
}

async function populatePrenotazioneSelect() {
    const today = new Date().toISOString().split('T')[0];
    const nextWeek = new Date();
    nextWeek.setDate(nextWeek.getDate() + 7);
    
    const prenotazioni = await apiCall('prenotazioni', 'GET', {
        data_inizio: today,
        data_fine: nextWeek.toISOString().split('T')[0]
    });
    
    const select = document.getElementById('prenotazione-select');
    select.innerHTML = '<option value="">Seleziona prenotazione...</option>';
    
    prenotazioni.forEach(p => {
        if (p.stato === 'confermata') {
            select.innerHTML += `
                <option value="${p.id}">
                    ${p.cliente_nome} - ${p.numero_tenda} 
                    (${formatDate(p.data_inizio)} - ${formatDate(p.data_fine)})
                </option>
            `;
        }
    });
}

async function populateServizioSelect() {
    const select = document.getElementById('servizio-select');
    select.innerHTML = '<option value="">Servizio personalizzato...</option>';
    
    serviziData.forEach(servizio => {
        select.innerHTML += `<option value="${servizio.id}">${servizio.nome} - €${servizio.prezzo}</option>`;
    });
}

async function loadServizi() {
    try {
        serviziData = await apiCall('servizi');
    } catch (error) {
        console.error('Error loading servizi:', error);
    }
}

// === UTILITY FUNCTIONS ===
function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('it-IT');
}

function formatDateTime(dateString) {
    return new Date(dateString).toLocaleString('it-IT');
}

// === KEYBOARD SHORTCUTS ===
document.addEventListener('keydown', function(e) {
    // ESC to close modals
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(modal => {
            const modalName = modal.id.replace('modal-', '');
            closeModal(modalName);
        });
    }
    
    // Ctrl+N for new booking
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        openModal('nuova-prenotazione');
    }
});

// === AUTO REFRESH ===
setInterval(() => {
    if (currentTab === 'dashboard') {
        loadDashboardData();
    }
}, 300000); // Refresh every 5 minutes