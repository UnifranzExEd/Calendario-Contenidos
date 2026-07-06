/**
 * UNIFRANZ Calendar - Main Application JS
 * Handles all data fetching, rendering, and interactions
 * Works with both dashboard.html (HTML entry) and views/dashboard.php (PHP entry)
 */

// These globals are set by dashboard.html or dashboard.php before this script loads

// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
// STATE
// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
const state = {
    currentTab: 'pauta',
    currentMonth: '',
    currentView: 'table', // 'table' | 'calendar'
    searchQuery: '',
    filters: {},
    contenidos: [],
    campos: {},
    dropdowns: {},
    pestanas: [],
    postproductores: [],
    editingId: null,
    sortField: null,
    sortDir: 'asc',
};

// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
// API HELPERS
// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
async function api(endpoint, params = {}) {
    const url = new URL(API_BASE + '/' + endpoint, window.location.href);
    Object.keys(params).forEach(k => {
        if (params[k] !== undefined && params[k] !== null && params[k] !== '') {
            url.searchParams.set(k, params[k]);
        }
    });
    const token = localStorage.getItem('auth_token');
    const headers = {};
    if (token) headers['X-Auth-Token'] = token;
    const res = await fetch(url, { headers });
    if (!res.ok) {
        if (res.status === 401) { localStorage.removeItem('auth_token'); window.location.href = 'index.html'; return; }
        const err = await res.json().catch(() => ({ error: 'Error del servidor' }));
        throw new Error(err.error || 'Error');
    }
    return res.json();
}

async function apiPost(endpoint, data = {}) {
    const token = localStorage.getItem('auth_token');
    const headers = { 'Content-Type': 'application/json' };
    if (token) headers['X-Auth-Token'] = token;
    const res = await fetch(API_BASE + '/' + endpoint, {
        method: 'POST',
        headers,
        body: JSON.stringify(data)
    });
    if (res.status === 401) { localStorage.removeItem('auth_token'); window.location.href = 'index.html'; return; }
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || 'Error');
    return json;
}

function getSocialSvg(red) {
    const r = (red || '').toLowerCase();
    
    // Split by non-alphanumeric characters to get individual tokens
    const tokens = r.split(/[\s\-\/\_\|]+/);
    const hasToken = (t) => tokens.includes(t);
    const containsStr = (str) => r.includes(str);
    
    let svgs = [];
    
    if (containsStr('facebook') || hasToken('fb')) {
        svgs.push(`<svg class="social-logo" style="margin-right:4px; color:#1877F2; width:12px; height:12px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 15.82 29.54" fill="currentColor" stroke="none"><path d="M10.47,29.54V16.61h4.31l.82-5.34H10.47V7.8a2.68,2.68,0,0,1,3-2.89h2.33V.36A28.6,28.6,0,0,0,11.68,0c-4.22,0-7,2.56-7,7.19v4.08H0v5.34H4.7V29.54Z"/></svg>`);
    }
    if (containsStr('instagram') || hasToken('ig')) {
        svgs.push(`<svg class="social-logo" style="margin-right:4px; color:#E1306C; width:12px; height:12px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 27.52 27.52" fill="currentColor" stroke="none"><path d="M21.05,4.91a1.62,1.62,0,1,0,0,3.23,1.62,1.62,0,0,0,0-3.23Z"/><path d="M13.87,7a6.78,6.78,0,1,0,6.78,6.78A6.78,6.78,0,0,0,13.87,7Zm0,11.12a4.34,4.34,0,1,1,4.34-4.34A4.35,4.35,0,0,1,13.87,18.1Z"/><path d="M19.25,27.52h-11A8.28,8.28,0,0,1,0,19.25v-11A8.28,8.28,0,0,1,8.27,0h11a8.28,8.28,0,0,1,8.27,8.27v11A8.28,8.28,0,0,1,19.25,27.52ZM8.27,2.59A5.69,5.69,0,0,0,2.59,8.27v11a5.69,5.69,0,0,0,5.68,5.68h11a5.69,5.69,0,0,0,5.68-5.68v-11a5.69,5.69,0,0,0-5.68-5.68Z"/></svg>`);
    }
    if (containsStr('tiktok') || hasToken('tt') || containsStr('tik tok')) {
        svgs.push(`<svg class="social-logo" style="margin-right:4px; width:12px; height:12px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 26.78 31.29" fill="currentColor" stroke="none"><path d="M26.77,12.66c-.25,0-.51,0-.77,0a8.36,8.36,0,0,1-7-3.78V21.78a9.51,9.51,0,1,1-9.5-9.5h0a5.63,5.63,0,0,1,.58,0V17a5.42,5.42,0,0,0-.58-.06,4.86,4.86,0,1,0,0,9.71,5,5,0,0,0,5-4.8L14.6,0h4.48a8.34,8.34,0,0,0,7.7,7.45v5.21"/></svg>`);
    }
    if (containsStr('linkedin') || hasToken('in')) {
        svgs.push(`<svg class="social-logo" style="margin-right:4px; color:#0A66C2; width:12px; height:12px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 27.36 27.3" fill="currentColor" stroke="none"><path d="M.45,9.06H6.13V27.3H.45ZM3.29,0A3.29,3.29,0,1,1,0,3.28,3.28,3.28,0,0,1,3.29,0"/><path d="M9.68,9.06h5.44v2.5h.08a5.93,5.93,0,0,1,5.36-3c5.74,0,6.8,3.77,6.8,8.69v10H21.7V18.43c0-2.12-.05-4.83-2.95-4.83s-3.4,2.3-3.4,4.68v9H9.68Z"/></svg>`);
    }
    if (containsStr('youtube') || hasToken('yt')) {
        svgs.push(`<svg class="social-logo" style="margin-right:4px; color:#FF0000; width:12px; height:12px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30.42 21.3" fill="currentColor" stroke="none"><path d="M30.42,6.68A6.68,6.68,0,0,0,23.75,0H6.68A6.68,6.68,0,0,0,0,6.68v7.94A6.68,6.68,0,0,0,6.68,21.3H23.75a6.68,6.68,0,0,0,6.67-6.68Zm-10,4.56L12.73,15c-.3.16-1.32,0-1.32-.4V6.86c0-.35,1-.56,1.33-.39l7.32,4 C20.37,10.63,20.69,11.07,20.38,11.24Z"/></svg>`);
    }
    
    if (svgs.length > 0) {
        return `<span style="display:inline-flex; align-items:center;">${svgs.join('')}</span>`;
    }

    return `<svg class="social-logo" style="margin-right:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>`;
}

function getSocialAbbr(red) {
    if (!red) return '';
    const r = red.toLowerCase();
    const tokens = r.split(/[\s\-\/\_\|]+/);
    const hasToken = (t) => tokens.includes(t);
    const containsStr = (str) => r.includes(str);
    
    let abbrs = [];
    if (containsStr('facebook') || hasToken('fb')) abbrs.push('FB');
    if (containsStr('instagram') || hasToken('ig')) abbrs.push('IG');
    if (containsStr('tiktok') || hasToken('tt') || containsStr('tik tok')) abbrs.push('TT');
    if (containsStr('linkedin') || hasToken('in')) abbrs.push('IN');
    if (containsStr('youtube') || hasToken('yt')) abbrs.push('YT');
    
    if (abbrs.length > 0) return abbrs.join('-');
    return (red.length > 5 ? red.substring(0, 5) : red).toUpperCase();
}

// Run init immediately if DOM already loaded (dynamic script), otherwise wait
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}

async function initApp() {
    try {
        // Load initial data in parallel
        const [pestanasRes, camposRes, dropdownsRes] = await Promise.all([
            api('campos.php', { action: 'pestanas' }),
            api('campos.php', { action: 'all' }),
            api('dropdowns.php', { action: 'all' }),
        ]);

        state.pestanas = pestanasRes.data;
        state.pestanas.unshift({
            id: 'all',
            slug: 'all',
            nombre: 'TODOS',
            color: '#ef5350',
            enlace_carpeta_base: ''
        });
        state.campos = camposRes.data;
        state.dropdowns = dropdownsRes.data;

        // Load postproductores
        try {
            const ppRes = await api('usuarios.php', { action: 'postproductores' });
            state.postproductores = ppRes.data;
        } catch (e) { state.postproductores = []; }

        renderTabsNavigation();
        renderTabsBar();

        // Restore last view from localStorage
        const savedTab = localStorage.getItem('uf_lastTab');
        const savedMonth = localStorage.getItem('uf_lastMonth');
        const savedView = localStorage.getItem('uf_lastView');
        
        if (savedView === 'calendar') setView('calendar');
        const tabToUse = (savedTab && state.pestanas.find(p => p.slug === savedTab)) ? savedTab : state.currentTab;
        setActiveTab(tabToUse);
        checkNotifications();

        // Use saved month or auto-detect current
        const meses = ['','ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
        const monthToUse = savedMonth || meses[new Date().getMonth() + 1];
        selectMonth(monthToUse);

        // Poll notifications every 60s
        setInterval(checkNotifications, 60000);
    } catch (err) {
        showToast('Error al cargar la aplicación: ' + err.message, 'error');
        console.error(err);
    }
}

// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
// TABS
// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
function renderTabsNavigation() {
    const nav = document.getElementById('tabNavigation');
    if (!nav) return;
    
    nav.innerHTML = state.pestanas.map(p => `
        <button class="nav-item ${p.slug === state.currentTab ? 'active' : ''}" 
                onclick="setActiveTab('${p.slug}')" data-tab="${p.slug}">
            <span class="nav-dot" style="background:${p.color}"></span>
            ${p.nombre}
            <span class="nav-count" id="count-${p.slug}">-</span>
        </button>
    `).join('');
}

function renderTabsBar() {
    const bar = document.getElementById('tabsBar');
    bar.innerHTML = state.pestanas.map(p => `
        <button class="tab-item ${p.slug === state.currentTab ? 'active' : ''}" 
                onclick="setActiveTab('${p.slug}')" data-tab="${p.slug}">
            <span class="tab-dot" style="background:${p.color}"></span>
            ${p.nombre}
        </button>
    `).join('');
}

function setActiveTab(slug) {
    state.currentTab = slug;
    localStorage.setItem('uf_lastTab', slug);
    
    const btnCrear = document.getElementById('btnCrear');
    if (btnCrear) btnCrear.style.display = slug === 'all' ? 'none' : '';
    
    if (slug === 'all') {
        setView('calendar'); // Default to calendar for ALL tab
    }
    
    // Update UI
    document.querySelectorAll('.nav-item[data-tab]').forEach(el => {
        el.classList.toggle('active', el.dataset.tab === slug);
    });
    document.querySelectorAll('.tab-item[data-tab]').forEach(el => {
        el.classList.toggle('active', el.dataset.tab === slug);
    });

    const p = state.pestanas.find(t => t.slug === slug);
    document.getElementById('headerTitle').textContent = p ? p.nombre : slug.toUpperCase();

    // Hide admin sections
    document.getElementById('adminSection').style.display = 'none';
    document.getElementById('tableView').style.display = state.currentView === 'table' ? '' : 'none';
    document.getElementById('calendarView').style.display = state.currentView === 'calendar' ? '' : 'none';

    loadContents();
}

// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
// MONTHS
// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
function selectMonth(month) {
    state.currentMonth = month;
    localStorage.setItem('uf_lastMonth', month);
    document.querySelectorAll('.month-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.month === month);
    });
    loadContents();
}

document.getElementById('monthGrid').addEventListener('click', (e) => {
    if (e.target.classList.contains('month-btn')) {
        selectMonth(e.target.dataset.month);
    }
});

// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
// VIEW TOGGLE
// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
function setView(view) {
    state.currentView = view;
    localStorage.setItem('uf_lastView', view);
    document.querySelectorAll('#viewToggle button').forEach(b => {
        b.classList.toggle('active', b.dataset.view === view);
    });
    document.getElementById('tableView').style.display = view === 'table' ? '' : 'none';
    document.getElementById('calendarView').style.display = view === 'calendar' ? '' : 'none';

    if (view === 'calendar') renderCalendar();
    if (view === 'table') renderTable();
}

// ══════════════════════════════════════════
// NOTIFICATIONS
// ══════════════════════════════════════════
async function checkNotifications() {
    try {
        const res = await api('contenidos.php', { action: 'notifications' });
        const count = (res && res.count) ? parseInt(res.count) : 0;
        const badge = document.getElementById('notifBadge');
        const bell = document.getElementById('notifBell');
        if (badge) {
            badge.textContent = count > 0 ? count : '';
            badge.style.display = count > 0 ? 'inline-flex' : 'none';
        }
        if (bell) {
            bell.classList.toggle('has-notif', count > 0);
        }
    } catch (e) {
        // Silently ignore notification errors
    }
}

// ══════════════════════════════════════════
// SEARCH
// ══════════════════════════════════════════
let searchTimer;
function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        state.searchQuery = document.getElementById('searchInput').value;
        loadContents();
    }, 300);
}

// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
// LOAD CONTENTS
// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
async function loadContents() {
    try {
        const res = await api('contenidos.php', {
            action: 'list',
            pestana: state.currentTab === 'all' ? '' : state.currentTab,
            mes: state.currentMonth,
            search: state.searchQuery,
            ...state.filters
        });

        state.contenidos = res.data;
        document.getElementById('contentCount').textContent = `${res.total} contenidos`;

        // Update tab count
        const countEl = document.getElementById('count-' + state.currentTab);
        if (countEl) countEl.textContent = res.total;

        if (state.currentView === 'table') {
            renderTable();
        } else {
            renderCalendar();
        }
    } catch (err) {
        showToast('Error al cargar contenidos: ' + err.message, 'error');
    }
}

// ══════════════════════════════════════════
// RENDER TABLE  
// ══════════════════════════════════════════
function renderTable() {
    let campos = state.campos[state.currentTab] ? [...state.campos[state.currentTab]] : [];
    if (state.currentTab === 'all') {
        campos = [
            { nombre_campo: 'tema', nombre_display: 'Tema/Titulo', tipo_campo: 'texto', ancho: '280px' },
            { nombre_campo: 'red_social', nombre_display: 'Red Social', tipo_campo: 'texto' },
            { nombre_campo: 'estado', nombre_display: 'Estado', tipo_campo: 'texto' },
            { nombre_campo: 'fecha', nombre_display: 'Fecha', tipo_campo: 'fecha' }
        ];
    } else {
        if (!campos.find(c => c.nombre_campo === 'tema')) {
            const fechaIdx = campos.findIndex(c => c.nombre_campo === 'fecha');
            const insertIdx = fechaIdx !== -1 ? fechaIdx + 1 : 0;
            campos.splice(insertIdx, 0, { nombre_campo: 'tema', nombre_display: 'TEMA / TITULO', tipo_campo: 'texto', ancho: '280px' });
        }
    }
    const thead = document.getElementById('tableHead');
    const tbody = document.getElementById('tableBody');
    const empty = document.getElementById('emptyState');

    const sortIcon = field => state.sortField === field ? (state.sortDir === 'asc' ? '\u25b2' : '\u25bc') : '\u2195';
    thead.innerHTML = '<tr><th style="width:40px">#</th>' +
        campos.map(c => {
            let w = c.ancho || '120px';
            if (c.tipo_campo === 'url') w = '80px';
            if (c.nombre_campo === 'horario') w = '70px';
            const a = (c.tipo_campo === 'url' || c.nombre_campo === 'horario') ? 'center' : 'left';
            return '<th style="min-width:' + w + ';width:' + w + ';text-align:' + a + ';" data-field="' + c.nombre_campo + '" onclick="sortBy(\'' + c.nombre_campo + '\')">' + c.nombre_display + '<span class="sort-icon">' + sortIcon(c.nombre_campo) + '</span></th>';
        }).join('') + '<th style="width:60px">Acc.</th></tr>';

    if (!state.contenidos.length) { tbody.innerHTML = ''; empty.style.display = ''; return; }
    empty.style.display = 'none';
    let lastWeek = '', rows = '', rowNum = 0;

    state.contenidos.forEach(item => {
        if (item.semana && item.semana !== lastWeek) {
            lastWeek = item.semana;
            rows += '<tr class="week-separator"><td colspan="' + (campos.length + 2) + '">' + escHtml(item.semana) + '</td></tr>';
        }
        rowNum++;
        rows += '<tr data-id="' + item.id + '" ondblclick="openEditModal(' + item.id + ')" oncontextmenu="showContextMenu(event,' + item.id + ')">';
        rows += '<td class="row-number">' + rowNum + '</td>';

        campos.forEach(campo => {
            let val = item[campo.nombre_campo];
            if (val === undefined || val === null) {
                val = (item.detalle && item.detalle[campo.nombre_campo] !== undefined) ? item.detalle[campo.nombre_campo] : '';
            }
            const align = (campo.tipo_campo === 'url' || campo.nombre_campo === 'horario') ? 'center' : 'left';
            rows += '<td style="text-align:' + align + '">';
            if (campo.tipo_campo === 'dropdown' && campo.dropdown_grupo) {
                if (campo.nombre_campo === 'red_social') {
                    const svgIcons = getSocialIcon(val);
                    const abbr = getSocialAbbr(val);
                    rows += '<span class="badge" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);padding:4px 10px;cursor:pointer;" onclick="showDropdownPicker(event,' + item.id + ',\'' + campo.nombre_campo + '\',\'' + campo.dropdown_grupo + '\')">' +
                        '<span style="display:flex;align-items:center;gap:4px;">' + svgIcons + '<span style="font-weight:600;font-size:0.75rem;">' + abbr + '</span></span></span>';
                } else {
                    rows += renderDropdownCell(item.id, campo.nombre_campo, val, campo.dropdown_grupo);
                }
            } else if (campo.tipo_campo === 'fecha') {
                rows += renderDateCell(item.id, campo.nombre_campo, val);
            } else if (campo.tipo_campo === 'url' && val) {
                rows += '<a href="' + escHtml(val) + '" target="_blank" class="btn btn-sm btn-secondary" style="font-size:0.75rem;padding:4px 8px;border-radius:6px;"><svg class="svg-icon" viewBox="0 0 24 24" style="width:12px;height:12px;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg> Ver</a>';
            } else if (campo.tipo_campo === 'url' && !val) {
                rows += '<span style="color:var(--text-muted);">&#8212;</span>';
            } else if (campo.tipo_campo === 'numero') {
                rows += '<span class="cell-editable" onclick="inlineEdit(this,' + item.id + ',\'' + campo.nombre_campo + '\')">' + (val || '&#8212;') + '</span>';
            } else {
                const display = val ? (val.length > 60 ? val.substring(0, 60) + '...' : val) : '&#8212;';
                rows += '<span class="cell-editable" onclick="inlineEdit(this,' + item.id + ',\'' + campo.nombre_campo + '\')" title="' + escHtml(val) + '">' + escHtml(display) + '</span>';
            }
            rows += '</td>';
        });

        rows += '<td><button class="btn-icon btn-secondary" onclick="openEditModal(' + item.id + ')" oncontextmenu="showContextMenu(event,' + item.id + ')" title="Editar"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg></button></td>';
        rows += '</tr>';
    });
    tbody.innerHTML = rows;
}


function renderDropdownCell(id, field, value, grupo) {
    const opciones = state.dropdowns[grupo] || [];
    const opcion = opciones.find(o => o.valor === value);
    const color = opcion ? opcion.color : '#6b7280';
    const badgeClass = getBadgeClass(field, value);

    return `<span class="badge ${badgeClass}" style="background:${hexToRgba(color, 0.15)}; color:${lightenColor(color)}" 
                  onclick="showDropdownPicker(event, ${id}, '${field}', '${grupo}')">
                <span class="badge-dot" style="background:${color}"></span>
                ${escHtml(value || '—')}
            </span>`;
}

function renderDateCell(id, field, val) {
    if (!val) return `<span class="cell-editable" style="color:var(--text-muted)" onclick="inlineEditDate(this, ${id}, '${field}', '')">—</span>`;
    const d = new Date(val + 'T00:00:00');
    const dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return `<span class="cell-editable" style="font-size:0.8rem" onclick="inlineEditDate(this, ${id}, '${field}', '${val}')">${dias[d.getDay()]}, ${d.getDate()} ${meses[d.getMonth()]}</span>`;
}

function inlineEditDate(el, id, field, currentVal) {

    if (el.querySelector('input')) return;

    const inputEl = document.createElement('input');
    inputEl.type = 'date';
    inputEl.className = 'cell-edit-input';
    inputEl.value = currentVal;
    inputEl.style.padding = '2px 4px';

    el.textContent = '';
    el.appendChild(inputEl);
    inputEl.focus();

    inputEl.addEventListener('blur', async () => {
        const newVal = inputEl.value;
        if (newVal === currentVal) {
            el.innerHTML = renderDateCell(id, field, currentVal).replace(/<span[^>]*>|<\/span>/g, '');
            return;
        }

        try {
            await apiPost('contenidos.php?action=update', { id, [field]: newVal });
            showToast('Fecha guardada');
            el.innerHTML = renderDateCell(id, field, newVal).replace(/<span[^>]*>|<\/span>/g, '');
            el.setAttribute('onclick', `inlineEditDate(this, ${id}, '${field}', '${newVal}')`);
        } catch (err) {
            showToast(err.message, 'error');
            el.innerHTML = renderDateCell(id, field, currentVal).replace(/<span[^>]*>|<\/span>/g, '');
        }
    });
}

// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
// INLINE EDIT
// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
function inlineEdit(el, id, field) {

    if (el.querySelector('input, textarea')) return;

    const currentVal = el.textContent.trim();
    const isTextarea = currentVal.length > 40;

    const inputEl = document.createElement(isTextarea ? 'textarea' : 'input');
    inputEl.className = 'cell-edit-input';
    inputEl.value = currentVal === 'â€”' ? '' : currentVal;
    if (isTextarea) inputEl.rows = 2;

    el.textContent = '';
    el.appendChild(inputEl);
    inputEl.focus();
    inputEl.select();

    const save = async () => {
        const newVal = inputEl.value.trim();
        el.textContent = newVal || 'â€”';

        if (newVal !== currentVal && newVal !== '') {
            try {
                await apiPost('contenidos.php?action=inline', { id, field, value: newVal });
                showToast('Campo actualizado', 'success');
                // Update local state
                const item = state.contenidos.find(c => c.id == id);
                if (item) item[field] = newVal;
            } catch (err) {
                el.textContent = currentVal;
                showToast('Error: ' + err.message, 'error');
            }
        }
    };

    inputEl.addEventListener('blur', save);
    inputEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !isTextarea) { e.preventDefault(); inputEl.blur(); }
        if (e.key === 'Escape') { el.textContent = currentVal || 'â€”'; }
    });
}

// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
// DROPDOWN PICKER (inline)
// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
function showDropdownPicker(e, id, field, grupo) {
    e.stopPropagation();
    // Remove existing picker
    document.querySelectorAll('.inline-dropdown').forEach(el => el.remove());

    const opciones = state.dropdowns[grupo] || [];
    const rect = e.target.getBoundingClientRect();

    const picker = document.createElement('div');
    picker.className = 'dropdown-menu show inline-dropdown';
    picker.style.position = 'fixed';
    picker.style.top = (rect.bottom + 4) + 'px';
    picker.style.left = rect.left + 'px';
    picker.style.zIndex = '9999';
    picker.style.maxHeight = '250px';
    picker.style.overflowY = 'auto';

    picker.innerHTML = opciones.map(o => `
        <button class="dropdown-item" onclick="selectDropdownOption(${id}, '${field}', '${escHtml(o.valor)}')">
            <span style="width:8px;height:8px;border-radius:50%;background:${o.color};flex-shrink:0"></span>
            ${escHtml(o.valor)}
        </button>
    `).join('');

    document.body.appendChild(picker);

    // Close on click outside
    setTimeout(() => {
        document.addEventListener('click', function closePicker(ev) {
            if (!picker.contains(ev.target)) {
                picker.remove();
                document.removeEventListener('click', closePicker);
            }
        });
    }, 10);
}

async function selectDropdownOption(id, field, value) {
    document.querySelectorAll('.inline-dropdown').forEach(el => el.remove());
    try {
        await apiPost('contenidos.php?action=inline', { id, field, value });
        showToast('Actualizado', 'success');
        loadContents();
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
}

// —————————————————————————————————————————————————————————————————————————
// SORT
// —————————————————————————————————————————————————————————————————————————
function sortBy(field) {
    if (state.sortField === field) {
        state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
    } else {
        state.sortField = field;
        state.sortDir = 'asc';
    }

    state.contenidos.sort((a, b) => {
        let va = a[field] || '';
        let vb = b[field] || '';
        if (typeof va === 'string') va = va.toLowerCase();
        if (typeof vb === 'string') vb = vb.toLowerCase();
        if (va < vb) return state.sortDir === 'asc' ? -1 : 1;
        if (va > vb) return state.sortDir === 'asc' ? 1 : -1;
        return 0;
    });

    renderTable();
}
// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
// CALENDAR VIEW
// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 
function renderCalendar() {
    const grid = document.getElementById('calendarGrid');
    const list = document.getElementById('calendarList');

    // Determine month
    const meses = { 'ENERO': 0, 'FEBRERO': 1, 'MARZO': 2, 'ABRIL': 3, 'MAYO': 4, 'JUNIO': 5, 'JULIO': 6, 'AGOSTO': 7, 'SEPTIEMBRE': 8, 'OCTUBRE': 9, 'NOVIEMBRE': 10, 'DICIEMBRE': 11 };
    let monthIdx = state.currentMonth ? meses[state.currentMonth] : new Date().getMonth();
    let year = 2026;

    const firstDay = new Date(year, monthIdx, 1);
    const lastDay = new Date(year, monthIdx + 1, 0);
    const rawDow = firstDay.getDay(); // 0=Sun
    const startDow = (rawDow + 6) % 7; // Mon=0, Tue=1, ..., Sun=6

    // Calculate Mini Dashboard counts
    const countsBySocial = {};
    const countsByState = {};
    let totalItems = 0;
    
    state.contenidos.forEach(c => {
        totalItems++;
        const red = c.red_social || 'Otra';
        const estado = c.estado || 'Sin estado';
        countsBySocial[red] = (countsBySocial[red] || 0) + 1;
        countsByState[estado] = (countsByState[estado] || 0) + 1;
    });

    let miniDashHtml = `<div><strong>Total:</strong> ${totalItems}</div>`;
    miniDashHtml += `<div style="border-left:1px solid var(--border-color); padding-left:15px; display:flex; gap:10px;">
        <strong style="color:var(--text-muted)">Redes:</strong>
        ${Object.entries(countsBySocial).map(([k,v]) => `<span>${escHtml(k)}: <strong>${v}</strong></span>`).join(', ')}
    </div>`;
    miniDashHtml += `<div style="border-left:1px solid var(--border-color); padding-left:15px; display:flex; gap:10px;">
        <strong style="color:var(--text-muted)">Estados:</strong>
        ${Object.entries(countsByState).map(([k,v]) => `<span>${escHtml(k)}: <strong>${v}</strong></span>`).join(', ')}
    </div>`;
    
    const miniDash = document.getElementById('miniDashboard');
    if (miniDash) {
        if (totalItems > 0) {
            miniDash.style.display = 'flex';
            miniDash.innerHTML = miniDashHtml;
        } else {
            miniDash.style.display = 'none';
        }
    }

    // Build calendar grid
    const dias = ['LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB', 'DOM'];
    let html = dias.map(d => `<div class="calendar-day-header">${d}</div>`).join('');

    // Previous month padding
    const prevMonth = new Date(year, monthIdx, 0);
    for (let i = startDow - 1; i >= 0; i--) {
        const day = prevMonth.getDate() - i;
        html += `<div class="calendar-day other-month"><div class="day-number">${day}</div></div>`;
    }

    const today = new Date();
    
    // Current month days
    for (let d = 1; d <= lastDay.getDate(); d++) {
        const dateStr = `${year}-${String(monthIdx + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const isToday = today.getDate() === d && today.getMonth() === monthIdx && today.getFullYear() === year;
        const dayContents = state.contenidos.filter(c => c.fecha === dateStr);

        html += `<div class="calendar-day ${isToday ? 'today' : ''}" onclick="openDayDetail('${dateStr}')" oncontextmenu="showCalendarDayContextMenu(event, '${dateStr}')">`;
        html += `<div class="day-number">${d}</div>`;
        dayContents.slice(0, 4).forEach(c => {
            const isPostProductor = APP_USER.rol === 'postproductor';
            const isNotSent = (!c.enviar_postproduccion || c.enviar_postproduccion == 0);
            const isGhostForPP = isPostProductor && isNotSent;
            const isGhostStyle = isNotSent;

            let ppNameDisplay = 'No asignado';
            if (c.postproductor_id && c.postproductor_id != 0) {
                const assignedPP = state.postproductores.find(pp => pp.id == c.postproductor_id);
                ppNameDisplay = 'Asignado A: ' + (assignedPP ? assignedPP.nombre : 'Desconocido');
            }

            const p = state.pestanas.find(p2 => p2.slug === c.pestana_slug);
            const color = isGhostStyle ? '#64748b' : (p ? p.color : '#e53935');
            const socialIcon = getSocialIcon(c.red_social);
            const formatoIcon = getFormatIcon(c.formato);
            const estadoColor = getEstadoColor(c.estado);
            const titulo = c.tema || c.detalle?.titulo_post || 'Sin título';
            const ghostClass = isGhostStyle ? ' ghost-content' : '';
            const onClickAttr = isGhostForPP ? '' : `onclick="event.stopPropagation(); openEditModal(${c.id})"`;
            const isAssignedToMe = APP_USER.rol === 'postproductor' && c.postproductor_id == APP_USER.id;
            const isProduced = c.estado === 'Producido y Cargado';
            let glowClass = '';
            let unassignedClass = '';
            let communityGlowClass = '';
            let producedGlowClass = '';
            
            if (isProduced) {
                producedGlowClass = ' produced-glow';
            } else {
                glowClass = isAssignedToMe ? ' assigned-glow' : '';
                unassignedClass = (!c.postproductor_id || c.postproductor_id == 0) ? ' unassigned-alert' : '';
                communityGlowClass = (c.disenador_id && c.disenador_id != 0) ? ' designed-glow' : '';
            }

            let assignedDetails = `<div style="font-size:0.6rem; color:var(--text-muted); margin-top:4px; font-weight:600;">${escHtml(ppNameDisplay)}</div>`;
            
            const isAprobado = !isGhostForPP && c.estado === 'Aprobado';

            html += `<div class="calendar-event${ghostClass}${glowClass}${unassignedClass}${communityGlowClass}${producedGlowClass}" 
                          style="border-left: 3px solid ${color}; position:relative;"
                          ${onClickAttr} 
                          oncontextmenu="showContextMenu(event, ${c.id})"
                          title="${isGhostForPP ? 'Contenido en elaboración (Por enviar a Post)' : escHtml(titulo) + ' | ' + escHtml(c.red_social||'') + ' | ' + escHtml(c.formato||'') + ' | ' + escHtml(c.estado||'')}">
                        ${isAprobado ? `<div style="position:absolute; top:0; right:0; background:#10b981; color:#fff; font-size:0.52rem; font-weight:800; padding:2px 6px; border-radius:0 4px 0 6px; letter-spacing:0.06em; display:flex; align-items:center; gap:2px; line-height:1.6; z-index:10; pointer-events:none;">
                            <svg viewBox="0 0 24 24" style="width:7px;height:7px;stroke:#fff;stroke-width:3.5;fill:none;flex-shrink:0;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            APROBADO
                        </div>` : ''}
                        <div class="cal-ev-top">
                            <span class="cal-ev-social">${isGhostForPP ? '<svg class="svg-icon" viewBox="0 0 24 24" style="opacity:0.5"><path d="M5 22h14"></path><path d="M5 2h14"></path><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"></path><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"></path></svg>' : socialIcon}</span>
                            <span class="cal-ev-format">${isGhostForPP ? '' : formatoIcon}</span>
                            <div style="display:flex; align-items:center;">
                                <span class="cal-ev-estado" style="background:${isGhostForPP ? 'transparent' : estadoColor};" title="${isGhostForPP ? '' : escHtml(c.estado||'')}"></span>
                            </div>
                        </div>
                        <div class="cal-ev-title">${isGhostForPP ? 'Por enviar a Post' : escHtml(titulo.substring(0, 28))}${!isGhostForPP && titulo.length > 28 ? '&#8230;' : ''}</div>
                        <div class="cal-ev-tab" style="color:${color};">${escHtml(p ? p.nombre : '')}</div>
                        ${assignedDetails}
                     </div>`;
        });
        if (dayContents.length > 4) {
            html += `<div style="font-size:0.65rem;color:var(--text-muted);text-align:center;padding:2px 0">+${dayContents.length - 4} más</div>`;
        }
        html += '</div>';
    }

    // Next month padding
    const totalCells = startDow + lastDay.getDate();
    const remaining = (7 - (totalCells % 7)) % 7;
    for (let i = 1; i <= remaining; i++) {
        html += `<div class="calendar-day other-month"><div class="day-number">${i}</div></div>`;
    }

    grid.innerHTML = html;

    // List view (mobile)
    let listHtml = '';
    const daysWithContent = {};
    state.contenidos.forEach(c => {
        if (c.fecha) {
            if (!daysWithContent[c.fecha]) daysWithContent[c.fecha] = [];
            daysWithContent[c.fecha].push(c);
        }
    });

    Object.keys(daysWithContent).sort().forEach(date => {
        const d = new Date(date + 'T00:00:00');
        const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        
        listHtml += `<div class="calendar-list-day">
            <div class="calendar-list-date">${dayNames[d.getDay()]}, ${d.getDate()} ${monthNames[d.getMonth()]}</div>`;
        
        daysWithContent[date].forEach(c => {
            const isPostProductor = APP_USER.rol === 'postproductor';
            const isNotSent = (!c.enviar_postproduccion || c.enviar_postproduccion == 0);
            const isGhostForPP = isPostProductor && isNotSent;
            const isGhostStyle = isNotSent;

            let ppNameDisplay = 'No asignado';
            if (c.postproductor_id && c.postproductor_id != 0) {
                const assignedPP = state.postproductores.find(pp => pp.id == c.postproductor_id);
                ppNameDisplay = 'Asignado A: ' + (assignedPP ? assignedPP.nombre : 'Desconocido');
            }

            const p = state.pestanas.find(p2 => p2.slug === c.pestana_slug);
            const color = isGhostStyle ? '#64748b' : (p ? p.color : '#e53935');
            const ghostClass = isGhostStyle ? ' ghost-content' : '';
            const onClickAttr = isGhostForPP ? '' : `onclick="event.stopPropagation(); openEditModal(${c.id})"`;
            const isAssignedToMe = APP_USER.rol === 'postproductor' && c.postproductor_id == APP_USER.id;
            const isUnassigned = !c.postproductor_id || c.postproductor_id == 0;
            const isCommunityDesigned = c.disenador_id && c.disenador_id != 0;
            const glowClass = isAssignedToMe ? ' assigned-glow' : '';
            const unassignedClass = isUnassigned ? ' unassigned-alert' : '';
            const communityGlowClass = isCommunityDesigned ? ' designed-glow' : '';

            listHtml += `<div class="calendar-list-event${ghostClass}${glowClass}${unassignedClass}${communityGlowClass}" ${onClickAttr} oncontextmenu="showContextMenu(event, ${c.id})">
                <div class="event-color" style="background:${color}"></div>
                <div class="event-info">
                    <div class="event-title">${isGhostForPP ? 'Por enviar a Post' : escHtml(c.tema || 'Sin tema')}</div>
                    <div class="event-meta">
                        ${isGhostForPP ? '<span>Contenido en progreso...</span>' : `
                        <span>${escHtml(c.red_social || '')}</span>
                        <span>${escHtml(c.formato || '')}</span>
                        <span>${escHtml(c.estado || '')}</span>
                        <span style="font-weight:600; color:var(--text-muted);">${escHtml(ppNameDisplay)}</span>
                        `}
                    </div>
                </div>
            </div>`;
        });
        listHtml += '</div>';
    });

    list.innerHTML = listHtml || '<div class="empty-state"><div class="empty-icon"><svg class="svg-icon" style="width:48px;height:48px" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg></div><h3>Sin contenidos este mes</h3></div>';
}

// —————————————————————————————————————————————————————————————————————————
// CONTENT MODAL (Create/Edit)
// —————————————————————————————————————————————————————————————————————————
function openCreateModal() {
    state.editingId = null;
    state.modalTab = state.currentTab;
    document.getElementById('modalTitle').textContent = 'Nuevo Contenido';
    
    let defaultData = {};
    if (state.currentMonth && state.currentMonth !== 'TODO') {
        const meses = { 'ENERO': 1, 'FEBRERO': 2, 'MARZO': 3, 'ABRIL': 4, 'MAYO': 5, 'JUNIO': 6, 'JULIO': 7, 'AGOSTO': 8, 'SEPTIEMBRE': 9, 'OCTUBRE': 10, 'NOVIEMBRE': 11, 'DICIEMBRE': 12 };
        const monthNum = meses[state.currentMonth];
        if (monthNum) {
            const m = monthNum.toString().padStart(2, '0');
            // Check if current year is available, else default to 2026
            const year = new Date().getFullYear() < 2026 ? 2026 : new Date().getFullYear();
            defaultData.fecha = `${year}-${m}-01`;
        }
    }
    
    renderContentForm(defaultData);
    openModal('contentModal');
}

async function openEditModal(id) {
    state.editingId = id;
    
    try {
        const res = await api('contenidos.php', { action: 'get', id });
        state.modalTab = res.data.pestana_slug; // Changed from state.currentTab so it doesn't break ALL view
        const isPP = false;
        document.getElementById('modalTitle').textContent = 'Editar Contenido';
        
        renderContentForm(res.data);
        openModal('contentModal');
    } catch (err) {
        if(typeof showToast === 'function') showToast('Error al cargar: ' + err.message, 'error');
    }
}

function renderContentForm(data) {
    const isEdit = !!(data && data.id);
    const isPP = false;
    const canEdit = true;
    
    const btnGuardar = document.getElementById('btnGuardarContenido');
    if (btnGuardar) btnGuardar.style.display = canEdit ? '' : 'none';
    
    // ── Standard fields always shown ──────────────────────────────────────
    // These are hardcoded to always appear regardless of pestana_campos DB config.
    const STANDARD_CAMPOS = [
        { nombre_campo: 'tema',          nombre_display: 'TÍTULO',            tipo_campo: 'texto',    ancho: '100%'  },
        { nombre_campo: 'fecha',         nombre_display: 'FECHA',             tipo_campo: 'fecha',    ancho: '160px' },
        { nombre_campo: 'buyer',         nombre_display: 'BUYER',             tipo_campo: 'dropdown', dropdown_grupo: 'buyer',      ancho: '160px' },
        { nombre_campo: 'pilar',         nombre_display: 'TIPO PIEZA',        tipo_campo: 'dropdown', dropdown_grupo: 'pilar',      ancho: '160px' },
        { nombre_campo: 'atributo',      nombre_display: 'ATRIBUTO',          tipo_campo: 'dropdown', dropdown_grupo: 'atributo',   ancho: '160px' },
        { nombre_campo: 'red_social',    nombre_display: 'RED SOCIAL',        tipo_campo: 'dropdown', dropdown_grupo: 'red_social', ancho: '160px' },
        { nombre_campo: 'estado',        nombre_display: 'ESTADO',            tipo_campo: 'dropdown', dropdown_grupo: 'estado',     ancho: '160px' },
        { nombre_campo: 'formato',       nombre_display: 'SERIE EDITORIAL',   tipo_campo: 'dropdown', dropdown_grupo: 'formato',    ancho: '160px' },
        { nombre_campo: 'formato_pieza', nombre_display: 'FORMATO',           tipo_campo: 'texto',    ancho: '120px' },
        { nombre_campo: 'ubicaciones',   nombre_display: 'UBICACIONES',       tipo_campo: 'texto',    ancho: '160px' },
        { nombre_campo: 'horario',       nombre_display: 'HORARIO',           tipo_campo: 'texto',    ancho: '120px' },
    ];
    const STANDARD_NAMES = new Set(STANDARD_CAMPOS.map(c => c.nombre_campo));

    // Merge: standard first, then any extra tab-specific campos from DB
    const dbCampos = (state.campos[state.modalTab] || []).filter(c =>
        !STANDARD_NAMES.has(c.nombre_campo)   // skip duplicates of standard fields
    );
    const camposTab = [...STANDARD_CAMPOS, ...dbCampos];

    let html = `<div class="editor-section">
        <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 8px 10px; margin-bottom: 12px;">`;
    const shortFields = [];
    const linkFields  = [];
    const textFields  = [];
    let temaField = null;
    
    camposTab.forEach(campo => {
        if (campo.nombre_campo === 'tema') {
            temaField = campo;
        } else if (campo.tipo_campo === 'url' && campo.nombre_campo !== 'enlace_contenido') {
            linkFields.push(campo);
        } else if (campo.tipo_campo === 'textarea' || campo.ancho === '100%') {
            textFields.push(campo);
        } else {
            shortFields.push(campo);
        }
    });


    // Helper to generate field HTML
    const renderField = (campo) => {
        const val = data[campo.nombre_campo] || '';
        const disabled = '';
        
        if (campo.nombre_campo === 'tema') {
            return `<div class="form-group" style="grid-column: 1 / -1;">
                <label>TÍTULO</label>
                <input type="text" class="form-control" id="form_${campo.nombre_campo}" spellcheck="true" lang="es" value="${escHtml(val)}" ${disabled}>
            </div>`;
        } else if (campo.tipo_campo === 'dropdown' && campo.dropdown_grupo) {
            const opciones = state.dropdowns[campo.dropdown_grupo] || [];
            return `<div class="form-group">
                <label>${escHtml(campo.nombre_display)}</label>
                <select class="form-control" id="form_${campo.nombre_campo}" ${disabled}>
                    <option value="">&#8212; Seleccionar &#8212;</option>
                    ${opciones.map(o => `<option value="${escHtml(o.valor)}" ${o.valor === val ? "selected" : ""}>${escHtml(o.valor)}</option>`).join('')}
                </select>
            </div>`;
        } else if (campo.tipo_campo === 'fecha') {
            return `<div class="form-group">
                <label>${escHtml(campo.nombre_display)}</label>
                <input type="date" class="form-control" id="form_${campo.nombre_campo}" value="${val}" ${disabled}>
            </div>`;
        } else if (campo.tipo_campo === 'numero') {
            return `<div class="form-group">
                <label>${escHtml(campo.nombre_display)}</label>
                <input type="number" class="form-control" id="form_${campo.nombre_campo}" value="${val}" ${disabled}>
            </div>`;
        } else if (campo.tipo_campo === 'url') {
            if (!isPP && campo.nombre_campo === 'enlace_contenido') {
                return `<div class="form-group" style="grid-column: 1 / -1; margin-bottom:0;">
                    <label>${escHtml(campo.nombre_display)}</label>
                    ${val ? `<a href="${escHtml(val)}" target="_blank" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;width:fit-content;"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg> Abrir Enlace de Producción</a>` : `<div style="color:var(--text-muted);font-size:0.9rem;">Aún no hay enlace de producción</div>`}
                    <input type="hidden" id="form_${campo.nombre_campo}" value="${escHtml(val)}">
                </div>`;
            }
            if (campo.nombre_campo === 'enlace_diseno') {
                return `<div class="form-group" style="margin-bottom:0;">
                    <label style="display:flex; align-items:center; justify-content:space-between; color:#10b981;">
                        <span style="display:flex; align-items:center; gap:6px;">
                            <svg class="svg-icon" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="width:1.2em;height:1.2em;">
                                <path d="M7.71 3.5L1.15 15l3.43 6 6.55-11.5M9.73 15L6.3 21h13.12l3.43-6M12.27 3.5L15.7 9.5H2.57L6 3.5z"/>
                            </svg>
                            ${escHtml(campo.nombre_display)}
                        </span>
                        ${val ? `<a href="${escHtml(val)}" target="_blank" title="Abrir enlace" style="color:var(--accent); display:flex; align-items:center; font-size:0.75rem; text-transform:none; letter-spacing:normal;"><svg class="svg-icon" style="width:14px;height:14px;margin-right:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>Abrir</a>` : ''}
                    </label>
                    <input type="url" class="form-control" id="form_${campo.nombre_campo}" value="${escHtml(val)}" 
                           placeholder="https://drive.google.com/..." ${disabled} style="border-color:#10b981; background:rgba(16,185,129,0.05);">
                </div>`;
            }
            return `<div class="form-group" style="margin-bottom:0;">
                <label style="display:flex; align-items:center; justify-content:space-between;">
                    <span>${escHtml(campo.nombre_display)}</span>
                    ${val ? `<a href="${escHtml(val)}" target="_blank" title="Abrir enlace" style="color:var(--accent); display:flex; align-items:center; font-size:0.75rem; text-transform:none; letter-spacing:normal;"><svg class="svg-icon" style="width:14px;height:14px;margin-right:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>Abrir</a>` : ''}
                </label>
                <input type="url" class="form-control" id="form_${campo.nombre_campo}" value="${escHtml(val)}" 
                       placeholder="https://..." ${disabled}>
            </div>`;
        } else if (campo.tipo_campo === 'textarea') {
            const isSmall = ['idea', 'atributo', 'observaciones'].includes(campo.nombre_campo);
            const gridCol = isSmall ? 'span 1' : '1 / -1';
            return `<div class="form-group" style="grid-column: ${gridCol};">
                <label>${escHtml(campo.nombre_display)}</label>
                <textarea class="form-control" id="form_${campo.nombre_campo}" rows="1" style="min-height:28px;resize:none;overflow:hidden;" oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'" spellcheck="true" lang="es" ${disabled}>${escHtml(val)}</textarea>
            </div>`;
        } else {
            return `<div class="form-group">
                <label>${escHtml(campo.nombre_display)}</label>
                <input type="text" class="form-control" id="form_${campo.nombre_campo}" spellcheck="true" lang="es" value="${escHtml(val)}" ${disabled}>
            </div>`;
        }
    };

    // Render TEMA (TITULO) first if exists
    if (temaField) html += renderField(temaField);

    // Render short fields
    shortFields.forEach(c => html += renderField(c));

    // Post Productor inline in grid
    if (APP_PERMS.asignar_pp || APP_USER.rol === 'admin') {
        html += `<div class="form-group">
            <label>POST-PRODUCTOR ASIGNADO</label>
            <select class="form-control" id="form_postproductor_id">
                <option value="">&#8212; Sin asignar &#8212;</option>
                ${state.postproductores.map(pp => `<option value="${pp.id}" ${data.postproductor_id == pp.id ? "selected" : ""}>${escHtml(pp.nombre)}</option>`).join('')}
            </select>
        </div>`;
    } else if (isPP && APP_PERMS.asignar_pp === 'self') {
        html += `<div class="form-group">
            <label>POST-PRODUCTOR</label>
            <button class="btn btn-sm ${data.postproductor_id == APP_USER.id ? 'btn-success' : 'btn-secondary'}"
                    onclick="assignSelfPP()" id="btnAssignPP" style="display:flex; align-items:center; gap:6px; width:100%; justify-content:center;">
                ${data.postproductor_id == APP_USER.id
                    ? '<svg class="svg-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Asignado a mí'
                    : '<svg class="svg-icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><polyline points="16 11 18 13 22 9"></polyline></svg> Asignarme esta pieza'}
            </button>
            <input type="hidden" id="form_postproductor_id" value="${data.postproductor_id || ''}">
        </div>`;
    } else {
        const assignedPP = state.postproductores.find(pp => pp.id == data.postproductor_id);
        const ppName = assignedPP ? assignedPP.nombre : 'Sin asignar';
        html += `<div class="form-group">
            <label>POST-PRODUCTOR ASIGNADO</label>
            <input type="text" class="form-control" value="${escHtml(ppName)}" disabled style="background: rgba(0,0,0,0.1); cursor: not-allowed; border: 1px dashed var(--border-color); color: var(--text-muted); font-weight: bold;">
            <input type="hidden" id="form_postproductor_id" value="${data.postproductor_id || ''}">
        </div>`;
    }

    // Text fields (observaciones, etc.) spanning full width
    textFields.forEach(c => html += renderField(c));



    html += `</div></div>`; // Close editor-grid & editor-section

    // ── 2x1 HORIZONTAL BOX (Referencia Visual + Links) ──
    html += `<div style="display:flex; gap:20px; align-items:stretch; margin-bottom:16px;">`;

    // LEFT HALF: Image Panel
    html += `<div style="flex:1; display:flex; flex-direction:column; min-width:0;">
        <div class="editor-section-title" style="margin-bottom:8px;"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg> Referencia Visual</div>
        <div id="imagePreviewArea" style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:8px;"></div>
        ${isPP ? `
        <div id="imageDropZone" style="border:2px dashed var(--border-color); border-radius:var(--radius-md); padding:16px; text-align:center; flex:1; min-height:140px; display:flex; flex-direction:column; align-items:center; justify-content:center; cursor:default; opacity:0.5; pointer-events:none;">
            <svg class="svg-icon" style="width:2rem;height:2rem;stroke-width:1; color:var(--text-muted); margin-bottom:6px;" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
            <div style="font-size:0.75rem; color:var(--text-muted);">Sin referencia visual</div>
        </div>` : `
        <div id="imageDropZone" style="border:2px dashed var(--border-color); border-radius:var(--radius-md); padding:16px; text-align:center; cursor:pointer; transition:var(--transition); flex:1; min-height:140px; display:flex; flex-direction:column; align-items:center; justify-content:center;"
             onclick="document.getElementById('imageFileInput').click()">
            <svg class="svg-icon" style="width:2rem;height:2rem;stroke-width:1; color:var(--text-muted); margin-bottom:6px;" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
            <div style="font-size:0.75rem; color:var(--text-muted);">Click para subir</div>
            <div style="font-size:0.7rem; color:var(--text-muted); margin-top:3px;"><strong>Ctrl+V</strong> para pegar</div>
            <input type="file" id="imageFileInput" accept="image/*" style="display:none" onchange="handleImageFileUpload(event)">
        </div>`}
    </div>`;

    // RIGHT HALF: Links + PP Checkbox
    html += `<div style="flex:1; display:flex; flex-direction:column; justify-content:space-between; min-width:0;">`;
    html += `<div style="display:flex; flex-direction:column; gap:12px;">`;
    
    // Google Drive Madre — link from the current tab's enlace_carpeta_base
    const currentPestana = state.pestanas.find(p => p.slug === (state.modalTab || state.currentTab));
    const driveMadre = currentPestana?.enlace_carpeta_base || '';
    html += `<div>
        <label style="font-size:0.75rem; color:var(--text-muted); margin-bottom:4px; display:block;">Drive Madre</label>
        <a href="${escHtml(driveMadre) || '#'}" target="_blank" class="btn btn-secondary"
           style="display:flex; align-items:center; gap:6px; padding:6px 12px; white-space:nowrap; text-decoration:none; width:100%; justify-content:center;"
           ${!driveMadre ? 'onclick="event.preventDefault(); showNoLinkAlert()"' : ''}>
            <svg class="svg-icon" viewBox="0 0 24 24" style="width:14px;height:14px;color:#34a853;"><path d="M4.585 18l2.97-5.143H22.51l-2.97 5.143H4.585zM2.8 14.857L10.371 1.714h5.943l-7.57 13.143H2.8zM12.115 1.714L21.43 18H15.486L6.17 1.714h5.943z"></path></svg>
            Google Drive Madre
        </a>
        <input type="hidden" id="form_enlace_contenido" value="${escHtml(data.enlace_contenido || '')}">
    </div>`;

    // Diseño Terminado
    html += `<div>
        <label style="font-size:0.75rem; color:var(--text-muted); margin-bottom:4px; display:block;">Link del Diseño Terminado</label>
        <div style="display:flex; gap:8px;">
            <div style="position:relative; flex:1;">
                <svg class="svg-icon" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); width:14px; height:14px; color:var(--text-muted);" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                <input type="url" id="form_enlace_diseno" class="form-control" style="padding-left:32px; font-size:0.8rem;" value="${escHtml(data.enlace_diseno || '')}" placeholder="https://drive.google.com/..." oninput="document.getElementById('btn_open_enlace_diseno').href = this.value || '#'; document.getElementById('btn_open_enlace_diseno').onclick = this.value ? null : function(e){e.preventDefault();showNoLinkAlert();}">
            </div>
            <a href="${escHtml(data.enlace_diseno || '#')}" target="_blank" id="btn_open_enlace_diseno" class="btn btn-secondary" style="display:flex; align-items:center; justify-content:center; padding:6px; text-decoration:none;" title="Abrir Link" ${!data.enlace_diseno ? 'onclick="event.preventDefault(); showNoLinkAlert()"' : ''}>
                <svg class="svg-icon" viewBox="0 0 24 24" style="width:16px;height:16px;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
            </a>
        </div>
    </div>`;

    // Optionally render other configured links (like enlace_publicado)
    const otherLinks = linkFields.filter(c => c.nombre_campo !== 'enlace_contenido' && c.nombre_campo !== 'enlace_diseno');
    if (otherLinks.length > 0) {
        otherLinks.forEach(c => html += renderField(c));
    }
    html += `</div>`;

    // PP Checkbox at bottom
    if (!isPP) {
        html += `<label style="display:flex; align-items:center; gap:8px; padding:12px 14px; border-radius:var(--radius-md); background:var(--bg-glass); border:1px solid var(--border-color); cursor:pointer; margin-top:12px; margin-bottom:0;">
            <input type="checkbox" id="form_enviar_postproduccion" ${data.enviar_postproduccion == 1 ? "checked" : ''} style="width:18px;height:18px; cursor:pointer; accent-color:var(--accent); flex-shrink:0;">
            <span style="font-size:0.8rem; font-weight:600; cursor:pointer; color:var(--text-primary); display:flex; align-items:center; gap:6px; text-transform:uppercase; letter-spacing:0.3px;"><svg class="svg-icon" viewBox="0 0 24 24" style="width:14px;height:14px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> MANDAR A POST-PRODUCCION</span>
        </label>`;
    }
    html += `</div>`;  // Close RIGHT HALF

    html += `</div>`;  // Close 2x1 HORIZONTAL BOX
    html += `</div>`;  // Close 2-col split
    html += `</div></div>`;  // Close RIGHT COLUMN and 2-col split

    // ── Slides/Guión section ──
    const isVideo = (data.formato || '').toLowerCase().includes('video') || (data.formato || '').toLowerCase().includes('reel');
    const sectionLabel = isVideo 
        ? '<svg class="svg-icon" viewBox="0 0 24 24"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg> Guión del Video' 
        : '<svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg> COPY';
    const itemLabel = isVideo ? 'Escena' : 'Slide';
    const addLabel = isVideo ? '+ Agregar Escena' : '+ Agregar Slide';
    
    html += `<div class="editor-section" data-is-video="${isVideo}" style="margin-top: 20px;">
        <div class="editor-section-title">${sectionLabel}${isVideo ? ' <span id="totalDuration" style="font-size:0.75rem;color:var(--text-accent);font-weight:400;text-transform:none;letter-spacing:0;"></span>' : ''}</div>
        <div id="slidesContainer" data-item-label="${itemLabel}">`;
    
    const slides = data.slides || [];
    
    // Migración: si no hay slides, usar el antiguo copy en el primer slide
    if (slides.length === 0 && !isVideo) {
        const legacyCopy = data.detalle?.copy_facebook || data.detalle?.copy_instagram || data.detalle?.copy_tiktok || data.detalle?.copy_linkedin || '';
        if (legacyCopy) {
            slides.push({ texto: legacyCopy, notas: '' });
        }
    }

    slides.forEach((slide, i) => {
        html += renderSlideItem(i, slide.texto || '', slide.notas || '', isVideo, isPP);
    });

    html += `</div>`;
    
    if (!isPP) {
        html += `<button class="add-slide-btn" onclick="addSlide()">${addLabel}</button>`;
    }
    html += `</div>`;

    // ── Copy section ──
    if (!isPP) {
        html += `<div class="editor-section">
            <div class="editor-section-title">HEADLINE</div>
            
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
                <div class="copy-section" id="boxCopyFB" style="margin-bottom:0; display:none;">
                    <div class="copy-header">
                        <div class="copy-label"><span class="social-icon" style="color:#1877F2;">${getSocialSvg('facebook')}</span> Facebook</div>
                        <button class="btn btn-sm btn-secondary btn-copy-text" onclick="copyText('formCopyFB')"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg> Copiar</button>
                    </div>
                    <textarea class="form-control" id="formCopyFB" rows="3" spellcheck="true" lang="es" placeholder="Copy para Facebook...">${escHtml(data.detalle?.copy_facebook || '')}</textarea>
                </div>
                
                <div class="copy-section" id="boxCopyIG" style="margin-bottom:0; display:none;">
                    <div class="copy-header">
                        <div class="copy-label"><span class="social-icon" style="color:#E1306C;">${getSocialSvg('instagram')}</span> Instagram</div>
                        <button class="btn btn-sm btn-secondary btn-copy-text" onclick="copyText('formCopyIG')"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg> Copiar</button>
                    </div>
                    <textarea class="form-control" id="formCopyIG" rows="3" spellcheck="true" lang="es" placeholder="Copy para Instagram...">${escHtml(data.detalle?.copy_instagram || '')}</textarea>
                </div>
                
                <div class="copy-section" id="boxCopyTT" style="margin-bottom:0; display:none;">
                    <div class="copy-header">
                        <div class="copy-label"><span class="social-icon" style="color:var(--text-color);">${getSocialSvg('tiktok')}</span> TikTok</div>
                        <button class="btn btn-sm btn-secondary btn-copy-text" onclick="copyText('formCopyTT')"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg> Copiar</button>
                    </div>
                    <textarea class="form-control" id="formCopyTT" rows="3" spellcheck="true" lang="es" placeholder="Copy para TikTok...">${escHtml(data.detalle?.copy_tiktok || '')}</textarea>
                </div>
                
                <div class="copy-section" id="boxCopyLI" style="margin-bottom:0; display:none;">
                    <div class="copy-header">
                        <div class="copy-label"><span class="social-icon" style="color:#0077b5;">${getSocialSvg('linkedin')}</span> LinkedIn</div>
                        <button class="btn btn-sm btn-secondary btn-copy-text" onclick="copyText('formCopyLI')"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg> Copiar</button>
                    </div>
                    <textarea class="form-control" id="formCopyLI" rows="3" spellcheck="true" lang="es" placeholder="Copy para LinkedIn...">${escHtml(data.detalle?.copy_linkedin || '')}</textarea>
                </div>
            </div>
            
        </div>`;
    }

    // ── Captura del Post (always visible for admin/community) ──
    if (APP_PERMS.registrar_metricas || APP_USER.rol === 'admin') {
        const capturaUrl = data.captura ? `api/uploads/${data.captura}` : '';
        html += `<div class="editor-section">
            <div class="editor-section-title"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg> Captura del Post</div>
            <div class="captura-zone" id="capturaZoneDashboard" 
                ondragover="event.preventDefault(); this.classList.add('dragover')" 
                ondragleave="this.classList.remove('dragover')" 
                ondrop="handleCapturaDropDashboard(event, ${data.id})"
                onclick="document.getElementById('capturaFileInputDashboard').click()"
                style="cursor:pointer; border: 2px dashed var(--border); border-radius: 8px; padding: 24px; text-align: center; transition: all 0.2s;"
                title="Clic para subir o arrastra una captura. También puedes pegar con Ctrl+V">
                ${capturaUrl
                  ? `<img src="${escHtml(capturaUrl)}" class="captura-preview" id="capturaPreviewDashboard" alt="Captura" style="max-width:100%; border-radius:8px; display:block; margin:0 auto;">`
                  : `<div>
                      <svg viewBox="0 0 24 24" style="width:36px;height:36px;color:var(--text-muted);margin:0 auto 8px;display:block;" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21 15 16 10 5 21"/>
                      </svg>
                      <div style="color:var(--text-secondary);font-size:0.82rem;font-weight:600;">Agregar captura del post</div>
                      <div style="color:var(--text-muted);font-size:0.72rem;margin-top:4px;">Clic, arrastra o pega (Ctrl+V)</div>
                    </div>`
                }
            </div>
            <input type="file" id="capturaFileInputDashboard" accept="image/*" style="display:none" onchange="handleCapturaFileDashboard(event, ${data.id})">
            ${capturaUrl ? `<button class="btn btn-danger btn-sm" style="width:100%;margin-top:8px;" onclick="deleteCapturaDashboard(${data.id})"><svg class="svg-icon" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2-2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Quitar captura</button>` : ''}
        </div>`;
    }

    // ── Hashtags section ──
    if (!isPP) {
        html += `<div class="editor-section">
            <div class="editor-section-title">Hashtags</div>
            <div class="hashtag-container" id="hashtagContainer">
                ${(data.hashtags || []).map(h => `
                    <span class="hashtag-pill" data-id="${h.id}">
                        ${escHtml(h.tag)}
                        <span class="tag-count">${h.veces_usado}x</span>
                        <span class="remove-tag" onclick="removeHashtag(this, ${h.id})">×</span>
                    </span>
                `).join('')}
            </div>
            <div class="hashtag-input-wrapper">
                <input type="text" class="form-control" id="hashtagInput" placeholder="Escribir hashtag y presionar Enter..."
                       onkeydown="handleHashtagInput(event)" oninput="searchHashtags()">
                <div class="hashtag-suggestions" id="hashtagSuggestions"></div>
            </div>
        </div>`;
    }

    // â”€â”€ Metrics section (only for community/admin on published content) â”€â”€
    if (APP_PERMS.registrar_metricas && data.estado === 'Publicado') {
        const m = data.metricas || {};
        html += `<div class="editor-section">
            <div class="editor-section-title"><svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Métricas</div>
            <div class="editor-grid" style="grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));">
                <div class="form-group"><label><svg class="svg-icon" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> Espectadores</label><input type="number" class="form-control" id="metEspectadores" value="${m.espectadores || 0}"></div>
                <div class="form-group"><label><svg class="svg-icon" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg> Likes</label><input type="number" class="form-control" id="metLikes" value="${m.likes || 0}"></div>
                <div class="form-group"><label><svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg> Comentarios</label><input type="number" class="form-control" id="metComentarios" value="${m.comentarios || 0}"></div>
                <div class="form-group"><label><svg class="svg-icon" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg> Compartidos</label><input type="number" class="form-control" id="metCompartidos" value="${m.compartidos || 0}"></div>
                <div class="form-group"><label><svg class="svg-icon" viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg> Guardados</label><input type="number" class="form-control" id="metGuardados" value="${m.guardados || 0}"></div>
                <div class="form-group"><label><svg class="svg-icon" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg> Alcance</label><input type="number" class="form-control" id="metAlcance" value="${m.alcance || 0}"></div>
                <div class="form-group"><label><svg class="svg-icon" viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg> Clics</label><input type="number" class="form-control" id="metClics" value="${m.clics || 0}"></div>
            </div>
            </div>
        </div>`;
        
    }

    // â”€â”€ History â”€â”€
    if (data.historial && data.historial.length) {
        html += `<div class="editor-section">
            <div class="editor-section-title">Historial</div>
            <div style="max-height:150px;overflow-y:auto;">
                ${data.historial.map(h => `
                    <div style="padding:6px 0;border-bottom:1px solid var(--border-color);font-size:0.8rem;">
                        <span style="color:var(--text-muted)">${formatDate(h.created_at)}</span> &#8212; 
                        <strong>${escHtml(h.usuario_nombre || 'Sistema')}</strong> 
                        cambió estado ${h.estado_anterior ? 'de <em>' + escHtml(h.estado_anterior) + '</em>' : ''} 
                        a <em style="color:var(--text-accent)">${escHtml(h.estado_nuevo)}</em>
                    </div>
                `).join('')}
            </div>
        </div>`;
    }

    // Delete button for admin
    if (isEdit && APP_USER.rol === 'admin') {
        html += `<div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border-color);">
            <button class="btn btn-danger btn-sm" onclick="deleteContent(${data.id})"><svg class="svg-icon" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Eliminar contenido</button>
        </div>`;
    }

    document.getElementById('modalBody').innerHTML = html;
    
    // Setup dynamic copy visibility based on red social select
    const redSocialSelect = document.getElementById('form_red_social');
    if (redSocialSelect) {
        redSocialSelect.addEventListener('change', () => updateCopyVisibility());
    }
    // Use the actual data value on first render (select may not match yet)
    setTimeout(() => updateCopyVisibility(data.red_social || ''), 10);
    
    // If video mode, calculate initial duration
    setTimeout(updateTotalDuration, 50);
    // Enable spellcheck & char counters
    setTimeout(initSpellcheckAndCounters, 60);
    // Auto-adjust height of pre-filled auto-resize textareas
    setTimeout(() => {
        document.querySelectorAll('#modalBody textarea[style*="overflow:hidden"]').forEach(ta => {
            ta.style.height = 'auto';
            ta.style.height = ta.scrollHeight + 'px';
        });
    }, 30);
}

function updateCopyVisibility(overrideValue) {
    // Use override (passed on initial render) or read from the select on change events
    const rawVal = overrideValue !== undefined
        ? overrideValue
        : (document.getElementById('form_red_social')?.value || '');
    const rs = rawVal.toUpperCase();

    const boxFB = document.getElementById('boxCopyFB');
    const boxIG = document.getElementById('boxCopyIG');
    const boxTT = document.getElementById('boxCopyTT');
    const boxLI = document.getElementById('boxCopyLI');
    
    const showAll = !rs || rs.includes('SELECCIONAR');
    
    // "Meta" = Facebook + Instagram
    const hasMeta     = rs.includes('META');
    const hasFacebook = rs.includes('FACEBOOK') || hasMeta;
    const hasInstagram= rs.includes('INSTAGRAM') || hasMeta;
    const hasTikTok   = rs.includes('TIKTOK');
    const hasLinkedIn = rs.includes('LINKEDIN');
    
    if (boxFB) boxFB.style.display = (showAll || hasFacebook)  ? 'block' : 'none';
    if (boxIG) boxIG.style.display = (showAll || hasInstagram) ? 'block' : 'none';
    if (boxTT) boxTT.style.display = (showAll || hasTikTok)    ? 'block' : 'none';
    if (boxLI) boxLI.style.display = (showAll || hasLinkedIn)  ? 'block' : 'none';
}

function initSpellcheckAndCounters() {
    const body = document.getElementById('modalBody');
    if (!body) return;
    
    // Enable spellcheck on all text inputs and textareas
    body.querySelectorAll('textarea, input[type="text"]').forEach(el => {
        el.setAttribute('spellcheck', 'true');
        el.setAttribute('lang', 'es');
    });
    
    // Add char counters to all textareas
    body.querySelectorAll('textarea').forEach(ta => {
        // Skip if already has counter
        if (ta.nextElementSibling?.classList?.contains('char-counter')) return;
        
        const counter = document.createElement('div');
        counter.className = 'char-counter';
        counter.textContent = `${ta.value.length} caracteres`;
        ta.parentNode.insertBefore(counter, ta.nextSibling);
        
        ta.addEventListener('input', () => {
            counter.textContent = `${ta.value.length} caracteres`;
        });
    });
}

function renderSlideItem(index, texto, notas, isVideo = false, isPP = false) {
    const label = isVideo ? 'Escena' : 'Slide';
    const estimatedSecs = isVideo ? calcDuration(texto) : 0;
    
    const durationField = isVideo
        ? `<div style="display:flex; align-items:center; gap:8px; margin-top:6px;">
               <span style="font-size:0.72rem; color:var(--text-muted);"><svg class="svg-icon" viewBox="0 0 24 24" style="width:12px;height:12px"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg></span>
               <span class="slide-duration" id="dur_${index}" style="font-size:0.78rem; color:var(--text-accent); font-weight:600;">${estimatedSecs}s</span>
               <span style="font-size:0.72rem; color:var(--text-muted);">estimado</span>
           </div>`
        : `<textarea class="form-control notas-pp-ta" style="margin-top:6px;font-size:0.8rem;resize:none;overflow:hidden;min-height:2.4em;line-height:1.4;" rows="2" spellcheck="true" lang="es" placeholder="Notas para el PP (opcional)" oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'" ${isPP ? 'readonly' : ''}>${escHtml(notas)}</textarea>`;
    
    const onInputAttr = isVideo ? `oninput="recalcSceneDuration(this, ${index})"` : '';
    
    return `<div class="slide-item" data-slide="${index}">
        <div class="slide-header">
            <span class="slide-number">${label} ${index + 1}</span>
            <div class="slide-actions">
                ${isVideo ? `<button class="btn-copy-text" onclick="playTTS('slide_${index}', ${index})" title="Escuchar voz en off" style="color:var(--primary-color);"><svg class="svg-icon" viewBox="0 0 24 24"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg> <span id="tts_label_${index}" class="tts-label">Voz IA</span></button>` : ''}
                <button class="btn-copy-text" onclick="copyText('slide_${index}')"><svg class="svg-icon" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg> Copiar</button>
                ${!isPP ? `<button class="btn-delete-slide" onclick="removeSlide(this)"><svg class="svg-icon" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg></button>` : ''}
            </div>
        </div>
        <textarea class="form-control slide-text" id="slide_${index}" rows="${isVideo ? 5 : 6}" 
                  style="min-height:${isVideo ? '120px' : '140px'}; resize:vertical;"
                  placeholder="${isVideo ? 'Texto/narración de la escena ' + (index + 1) + '...' : 'Texto del slide ' + (index + 1) + '...'}" 
                  spellcheck="true" lang="es" ${onInputAttr} ${isPP ? 'readonly' : ''}>${escHtml(texto)}</textarea>
        ${durationField}
    </div>`;
}

let currentUtterance = null;
let ttsInterval = null;

function formatTime(seconds) {
    const m = Math.floor(seconds / 60).toString().padStart(2, '0');
    const s = (Math.floor(seconds) % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
}

function playTTS(elementId, index) {
    if (!('speechSynthesis' in window)) {
        showToast('Tu navegador no soporta Text-to-Speech', 'error');
        return;
    }
    
    const text = document.getElementById(elementId)?.value;
    if (!text || text.trim() === '') {
        showToast('No hay texto para leer', 'error');
        return;
    }

    // Detener la reproducción actual si la hay y restaurar la UI
    window.speechSynthesis.cancel();
    clearInterval(ttsInterval);
    document.querySelectorAll('.tts-label').forEach(el => {
        el.innerHTML = 'Voz IA';
        el.parentElement.style.color = 'var(--primary-color)';
    });

    currentUtterance = new SpeechSynthesisUtterance(text);
    currentUtterance.lang = 'es-ES';
    
    const voices = window.speechSynthesis.getVoices();
    const esVoices = voices.filter(v => v.lang.startsWith('es'));
    if (esVoices.length > 0) {
        const bestVoice = esVoices.find(v => v.name.includes('Google') || v.name.includes('Premium')) || esVoices[0];
        currentUtterance.voice = bestVoice;
    }

    const estimatedSecs = calcDuration(text);
    let currentSec = 0;
    const btnLabel = document.getElementById('tts_label_' + index);
    const btn = btnLabel ? btnLabel.parentElement : null;

    currentUtterance.onstart = () => {
        if (btnLabel && btn) {
            btn.style.color = '#10b981'; // Verde para indicar reproducción
            btnLabel.innerHTML = `${formatTime(currentSec)} / ${formatTime(estimatedSecs)}`;
            ttsInterval = setInterval(() => {
                currentSec++;
                if (currentSec > estimatedSecs) currentSec = estimatedSecs; // Limitar al estimado
                btnLabel.innerHTML = `${formatTime(currentSec)} / ${formatTime(estimatedSecs)}`;
            }, 1000);
        }
    };
    
    currentUtterance.onend = () => {
        clearInterval(ttsInterval);
        if (btnLabel && btn) {
            btnLabel.innerHTML = 'Voz IA';
            btn.style.color = 'var(--primary-color)';
        }
    };

    currentUtterance.onerror = (e) => {
        clearInterval(ttsInterval);
        if (btnLabel && btn) {
            btnLabel.innerHTML = 'Voz IA';
            btn.style.color = 'var(--primary-color)';
        }
        if(e.error !== 'interrupted' && e.error !== 'canceled') showToast('Error al reproducir audio', 'error');
    };

    window.speechSynthesis.speak(currentUtterance);
}
function calcDuration(text) {
    const words = (text || '').trim().split(/\s+/).filter(w => w.length > 0).length;
    return Math.max(1, Math.round(words / 2.5));
}

function recalcSceneDuration(textarea, index) {
    const secs = calcDuration(textarea.value);
    const el = document.getElementById('dur_' + index);
    if (el) el.textContent = secs + 's';
    updateTotalDuration();
}

function addSlide() {
    const container = document.getElementById('slidesContainer');
    const count = container.children.length;
    const isVideo = container.closest('[data-is-video]')?.dataset.isVideo === 'true';
    container.insertAdjacentHTML('beforeend', renderSlideItem(count, '', '', isVideo));
    if (isVideo) updateTotalDuration();
}

function removeSlide(btn) {
    btn.closest('.slide-item').remove();
    const container = document.getElementById('slidesContainer');
    const label = container?.dataset.itemLabel || 'Slide';
    // Re-number
    document.querySelectorAll('#slidesContainer .slide-item').forEach((el, i) => {
        el.dataset.slide = i;
        el.querySelector('.slide-number').textContent = `${label} ${i + 1}`;
    });
    updateTotalDuration();
}

function updateTotalDuration() {
    const el = document.getElementById('totalDuration');
    if (!el) return;
    // Sum from all textareas directly (re-calc from text to keep in sync)
    let total = 0;
    document.querySelectorAll('#slidesContainer .slide-text').forEach(ta => {
        total += calcDuration(ta.value);
    });
    const mins = Math.floor(total / 60);
    const secs = total % 60;
    el.textContent = total > 0
        ? `— ${mins > 0 ? mins + 'min ' : ''}${secs}s total (~${total}s)`
        : '';
}

// ═══════════════════════════════════════════════════════════════════════════
// AUTO SPELLCHECK
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
async function checkSpellingErrors(data) {
    let allText = [
        data.titulo_post, data.tema, data.copy_facebook, 
        data.copy_instagram, data.copy_tiktok
    ].filter(Boolean).join(" ");
    
    if (data.slides && data.slides.length > 0) {
        allText += " " + data.slides.map(s => s.texto).filter(Boolean).join(" ");
    }
    
    allText = allText.trim();
    if (!allText || allText.length < 5) return { hasError: 0, detail: null };
    
    try {
        const params = new URLSearchParams({
            text: allText,
            language: 'es'
        });
        const res = await fetch('https://api.languagetool.org/v2/check', {
            method: 'POST',
            body: params
        });
        const result = await res.json();
        if (result.matches && result.matches.length > 0) {
            const errors = result.matches.filter(m => m.rule && m.rule.issueType === 'misspelling');
            if (errors.length > 0) {
                const details = errors.map(m => {
                    const word = m.context.text.substring(m.offset, m.offset + m.length);
                    return `"${word}": ${m.message}`;
                }).join(' | ');
                return { hasError: 1, detail: details.substring(0, 500) };
            }
        }
    } catch(e) {
        console.error("Spellcheck auto-detect failed", e);
    }
    return { hasError: 0, detail: null };
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// SAVE CONTENT
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
async function saveContent() {
    const targetTab = state.modalTab || state.currentTab;
    // Always collect these standard fields (mirrors renderContentForm logic)
    const STANDARD_CAMPOS = [
        { nombre_campo: 'tema' }, { nombre_campo: 'fecha' }, { nombre_campo: 'buyer' },
        { nombre_campo: 'pilar' }, { nombre_campo: 'atributo' }, { nombre_campo: 'red_social' },
        { nombre_campo: 'estado' }, { nombre_campo: 'formato' }, { nombre_campo: 'horario' },
        { nombre_campo: 'formato_pieza' }, { nombre_campo: 'ubicaciones' },
        { nombre_campo: 'enlace_publicado' }, { nombre_campo: 'enlace_diseno' }, { nombre_campo: 'enlace_contenido' },
    ];
    const STANDARD_NAMES = new Set(STANDARD_CAMPOS.map(c => c.nombre_campo));
    const dbCampos = (state.campos[targetTab] || []).filter(c => !STANDARD_NAMES.has(c.nombre_campo));
    const campos = [...STANDARD_CAMPOS, ...dbCampos];
    const data = { pestana: targetTab };

    campos.forEach(c => {
        const el = document.getElementById('form_' + c.nombre_campo);
        if (el) data[c.nombre_campo] = el.value;
    });


    // PostProductor
    const ppEl = document.getElementById('form_postproductor_id');
    if (ppEl) data.postproductor_id = ppEl.value || null;
    
    const sendPostEl = document.getElementById('form_enviar_postproduccion');
    if (sendPostEl) data.enviar_postproduccion = sendPostEl.checked ? 1 : 0;

    // Detail
    data.titulo_post = data.tema || '';
    data.copy_facebook = document.getElementById('formCopyFB')?.value || '';
    data.copy_instagram = document.getElementById('formCopyIG')?.value || '';
    data.copy_tiktok = document.getElementById('formCopyTT')?.value || '';
    data.copy_linkedin = document.getElementById('formCopyLI')?.value || '';
    data.cta = document.getElementById('form_cta')?.value || '';

    // Slides
    data.slides = [];
    document.querySelectorAll('#slidesContainer .slide-item').forEach(el => {
        const textarea = el.querySelector('.slide-text');
        const notasInput = el.querySelector('.notas-pp-ta');
        data.slides.push({
            texto: textarea?.value || '',
            notas: notasInput?.value || ''
        });
    });

    try {
        const btnSave = document.getElementById('btnSave');
        btnSave.innerHTML = '<span class="loading-spinner"></span> Comprobando Ortografía...';
        btnSave.disabled = true;

        const spellRes = await checkSpellingErrors(data);
        data.error_ortografico = spellRes.hasError;
        data.error_ortografico_detalle = spellRes.detail;
        
        btnSave.innerHTML = '<span class="loading-spinner"></span> Guardando...';

        if (state.editingId) {
            data.id = state.editingId;
            await apiPost('contenidos.php?action=update', data);
            showToast('Contenido actualizado', 'success');
        } else {
            await apiPost('contenidos.php?action=create', data);
            showToast('Contenido creado', 'success');
        }

        // Save metrics if visible
        if (document.getElementById('metLikes')) {
            await apiPost('metricas.php?action=save', {
                contenido_id: state.editingId,
                espectadores: document.getElementById('metEspectadores')?.value || 0,
                likes: document.getElementById('metLikes')?.value || 0,
                comentarios: document.getElementById('metComentarios')?.value || 0,
                compartidos: document.getElementById('metCompartidos')?.value || 0,
                guardados: document.getElementById('metGuardados')?.value || 0,
                alcance: document.getElementById('metAlcance')?.value || 0,
                clics: document.getElementById('metClics')?.value || 0,
            });
        }

        // Save hashtags
        if (state.editingId || data.id) {
            const contentId = state.editingId || data.id;
            const tags = [];
            document.querySelectorAll('#hashtagContainer .hashtag-pill').forEach(p => {
                const text = p.childNodes[0]?.textContent?.trim();
                if (text) tags.push(text);
            });
            const redSocial = data.red_social || 'TODAS';
            if (tags.length) {
                await apiPost('hashtags.php?action=update_content_hashtags', {
                    contenido_id: contentId,
                    tags,
                    red_social: redSocial
                });
            }
        }

        closeModal();
        loadContents();
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    } finally {
        const btnSave = document.getElementById('btnSave');
        btnSave.innerHTML = 'Guardar';
        btnSave.disabled = false;
    }
}

async function deleteContent(id) {
    if (!await customConfirm('¿Estás seguro de eliminar este contenido? Esta acción no se puede deshacer.')) return;
    try {
        await apiPost('contenidos.php?action=delete', { id });
        showToast('Contenido eliminado', 'success');
        closeModal();
        loadContents();
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// HASHTAG FUNCTIONS
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function handleHashtagInput(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const input = document.getElementById('hashtagInput');
        let tag = input.value.trim();
        if (!tag) return;
        if (tag[0] !== '#') tag = '#' + tag;

        const container = document.getElementById('hashtagContainer');
        container.insertAdjacentHTML('beforeend', `
            <span class="hashtag-pill">
                ${escHtml(tag)}
                <span class="tag-count">nuevo</span>
                <span class="remove-tag" onclick="this.parentElement.remove()">×</span>
            </span>
        `);
        input.value = '';
        document.getElementById('hashtagSuggestions').classList.remove('show');
    }
}

async function searchHashtags() {
    const input = document.getElementById('hashtagInput');
    const query = input.value.trim();
    if (query.length < 2) {
        document.getElementById('hashtagSuggestions').classList.remove('show');
        return;
    }

    try {
        const redSocial = document.getElementById('form_red_social')?.value || '';
        const res = await api('hashtags.php', { action: 'list', search: query, red_social: redSocial });
        const sugg = document.getElementById('hashtagSuggestions');
        
        if (res.data.length) {
            sugg.innerHTML = res.data.map(h => `
                <div class="hashtag-suggestion" onclick="selectHashtagSuggestion('${escHtml(h.tag)}', ${h.id}, ${h.veces_usado})">
                    <span>${escHtml(h.tag)}</span>
                    <span class="usage">${h.veces_usado}x usado</span>
                </div>
            `).join('');
            sugg.classList.add('show');
        } else {
            sugg.classList.remove('show');
        }
    } catch (e) {}
}

function selectHashtagSuggestion(tag, id, count) {
    const container = document.getElementById('hashtagContainer');
    container.insertAdjacentHTML('beforeend', `
        <span class="hashtag-pill" data-id="${id}">
            ${escHtml(tag)}
            <span class="tag-count">${count}x</span>
            <span class="remove-tag" onclick="this.parentElement.remove()">×</span>
        </span>
    `);
    document.getElementById('hashtagInput').value = '';
    document.getElementById('hashtagSuggestions').classList.remove('show');
}

function removeHashtag(btn, id) {
    btn.parentElement.remove();
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// POST-PRODUCTOR SELF ASSIGN
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function assignSelfPP() {
    const ppInput = document.getElementById('form_postproductor_id');
    const btn = document.getElementById('btnAssignPP');
    if (ppInput.value == APP_USER.id) {
        ppInput.value = '';
        btn.className = 'btn btn-sm btn-secondary';
        btn.innerHTML = '<svg class="svg-icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><polyline points="16 11 18 13 22 9"></polyline></svg> Asignarme esta pieza';
    } else {
        ppInput.value = APP_USER.id;
        btn.className = 'btn btn-sm btn-success';
        btn.innerHTML = '<svg class="svg-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Asignado a mí';
    }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// ═══════════════════════════════════════════════════════════════════
// COPY TEXT
// ═══════════════════════════════════════════════════════════════════
function copyText(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const text = el.value || el.textContent;
    navigator.clipboard.writeText(text).then(() => {
        const toast = document.getElementById('copyToast');
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 1500);
    }).catch(() => {
        // Fallback
        el.select();
        document.execCommand('copy');
        const toast = document.getElementById('copyToast');
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 1500);
    });
}

// ══════════════════════════════════════════
// SMART CONTEXT MENU
// ══════════════════════════════════════════
let currentContextId = null;

// Estado colors map
const ESTADO_COLORS = {
    'En elaboración': '#6b7280',
    'Redacción':      '#f59e0b',
    'En revisión':    '#3b82f6',
    'Producción':     '#8b5cf6',
    'Corrección':     '#ef4444',
    'Aprobado':       '#10b981',
    'Programado':     '#06b6d4',
    'Publicado':      '#22c55e',
};

function buildSmartContextMenu(item) {
    const isAdmin   = APP_USER && APP_USER.rol === 'admin';
    const isCommunity = APP_USER && APP_USER.rol === 'community';
    const estadoActual = item ? item.estado : '';
    const hasDesign = item && item.enlace_diseno;
    const hasLink   = item && (item.enlace_contenido || item.enlace_publicado);
    const isSent    = item && item.enviar_postproduccion == 1;
    const titulo    = item ? (item.tema || 'Sin título').substring(0, 30) : '';

    // Build estado submenu items (all states the user can change to)
    const ESTADOS = ['En elaboración','Redacción','En revisión','Producción','Corrección','Aprobado','Programado','Publicado'];
    const estadoItems = ESTADOS
        .filter(e => e !== estadoActual)
        .map(e => `
            <div class="cm-item cm-sub-item" onclick="handleContext('estado:${e}')">
                <span style="width:8px;height:8px;border-radius:50%;background:${ESTADO_COLORS[e] || '#6b7280'};flex-shrink:0;display:inline-block;"></span>
                ${e}
            </div>`)
        .join('');

    return `
        <!-- Header: item title -->
        <div class="cm-header">
            <svg class="svg-icon" viewBox="0 0 24 24" style="width:12px;height:12px;opacity:0.6"><rect x="3" y="3" width="18" height="18" rx="2"></rect></svg>
            <span>${escHtml(titulo)}${titulo.length >= 30 ? '…' : ''}</span>
        </div>
        <div class="cm-separator"></div>

        <!-- Primary actions -->
        <div class="cm-item" onclick="handleContext('edit')">
            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
            Editar
            <span class="cm-hint">Doble clic</span>
        </div>
        <div class="cm-item" onclick="handleContext('duplicate')">
            <svg class="svg-icon" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
            Duplicar
        </div>
        <div class="cm-item" onclick="handleContext('recorrer')">
            <svg class="svg-icon" viewBox="0 0 24 24"><polyline points="13 17 18 12 13 7"></polyline><polyline points="6 17 11 12 6 7"></polyline></svg>
            Mover +1 día
        </div>

        <!-- Estado submenu -->
        <div class="cm-separator"></div>
        <div class="cm-item cm-has-sub" id="cmSubEstadoTrigger">
            <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            Cambiar Estado
            <svg viewBox="0 0 24 24" style="width:12px;height:12px;margin-left:auto;opacity:0.5" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </div>
        <div class="cm-submenu" id="cmSubEstado">
            ${estadoItems || '<div class="cm-item" style="opacity:0.4;cursor:default">Sin más estados</div>'}
        </div>

        <!-- Links section -->
        ${hasLink || hasDesign ? `<div class="cm-separator"></div>` : ''}
        ${hasLink ? `
        <div class="cm-item" onclick="handleContext('openLink')">
            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
            Abrir Enlace Publicado
        </div>` : ''}
        ${hasDesign ? `
        <div class="cm-item" onclick="handleContext('openDesign')">
            <svg class="svg-icon" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M7.71 3.5L1.15 15l3.43 6 6.55-11.5M9.73 15L6.3 21h13.12l3.43-6M12.27 3.5L15.7 9.5H2.57L6 3.5z"/></svg>
            Abrir Diseño (Drive)
        </div>` : ''}

        <!-- PP toggle -->
        ${!isCommunity ? `
        <div class="cm-separator"></div>
        <div class="cm-item" onclick="handleContext('togglePP')">
            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            ${isSent ? 'Quitar de Post-Producción' : 'Enviar a Post-Producción'}
        </div>` : ''}

        <!-- Danger zone -->
        <div class="cm-separator"></div>
        ${isAdmin ? `
        <div class="cm-item danger" onclick="handleContext('delete')">
            <svg class="svg-icon" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            Eliminar
        </div>` : ''}
    `;
}

function showContextMenu(e, id) {
    e.preventDefault();
    e.stopPropagation();

    // Remove any existing menu
    const existing = document.getElementById('rowContextMenu');
    if (existing) existing.remove();

    currentContextId = id;
    const item = state.contenidos.find(c => c.id == id);

    const cm = document.createElement('div');
    cm.id = 'rowContextMenu';
    cm.className = 'context-menu';
    cm.innerHTML = buildSmartContextMenu(item);
    document.body.appendChild(cm);

    // Submenu hover logic
    const subTrigger = cm.querySelector('#cmSubEstadoTrigger');
    const subMenu    = cm.querySelector('#cmSubEstado');
    if (subTrigger && subMenu) {
        subTrigger.addEventListener('mouseenter', () => subMenu.classList.add('open'));
        subTrigger.addEventListener('mouseleave', () => {
            setTimeout(() => { if (!subMenu.matches(':hover')) subMenu.classList.remove('open'); }, 150);
        });
        subMenu.addEventListener('mouseleave', () => subMenu.classList.remove('open'));
    }

    // Position
    cm.style.display = 'block';
    const rect = cm.getBoundingClientRect();
    let x = e.clientX;
    let y = e.clientY;
    if (x + rect.width  > window.innerWidth)  x -= rect.width;
    if (y + rect.height > window.innerHeight) y -= rect.height;
    cm.style.left = (x + window.scrollX) + 'px';
    cm.style.top  = (y + window.scrollY) + 'px';

    // Close on outside click
    const closeMenu = (ev) => {
        if (!cm.contains(ev.target)) {
            cm.remove();
            document.removeEventListener('click', closeMenu);
        }
    };
    setTimeout(() => document.addEventListener('click', closeMenu), 10);
    window.addEventListener('scroll', () => cm.remove(), { once: true, capture: true });
    document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') cm.remove(); }, { once: true });
}

async function handleContext(action) {
    const cm = document.getElementById('rowContextMenu');
    if (cm) cm.remove();
    if (!currentContextId) return;

    const id = currentContextId;
    currentContextId = null;
    const item = state.contenidos.find(c => c.id == id);

    if (action === 'edit') {
        openEditModal(id);

    } else if (action === 'duplicate') {
        try {
            await apiPost('contenidos.php?action=duplicate', { id });
            showToast('Contenido duplicado ✓', 'success');
            loadContents();
        } catch (e) { showToast(e.message, 'error'); }

    } else if (action === 'recorrer') {
        try {
            await apiPost('contenidos.php?action=shift_date', { id });
            showToast('Fecha movida +1 día', 'success');
            loadContents();
        } catch (e) { showToast(e.message, 'error'); }

    } else if (action.startsWith('estado:')) {
        const nuevoEstado = action.replace('estado:', '');
        try {
            await apiPost('contenidos.php?action=inline', { id, field: 'estado', value: nuevoEstado });
            showToast(`Estado → ${nuevoEstado}`, 'success');
            loadContents();
        } catch (e) { showToast(e.message, 'error'); }

    } else if (action === 'togglePP') {
        const newVal = (item && item.enviar_postproduccion == 1) ? 0 : 1;
        try {
            await apiPost('contenidos.php?action=inline', { id, field: 'enviar_postproduccion', value: newVal });
            showToast(newVal ? 'Enviado a Post-Producción ✓' : 'Quitado de Post-Producción', 'success');
            loadContents();
        } catch (e) { showToast(e.message, 'error'); }

    } else if (action === 'openLink') {
        const url = item && (item.enlace_publicado || item.enlace_contenido);
        if (url) window.open(url, '_blank');

    } else if (action === 'openDesign') {
        if (item && item.enlace_diseno) window.open(item.enlace_diseno, '_blank');

    } else if (action === 'delete') {
        if (!APP_USER || APP_USER.rol !== 'admin') return;
        const ok = await customConfirm('¿Eliminar este contenido? Esta acción no se puede deshacer.');
        if (!ok) return;
        try {
            await apiPost('contenidos.php?action=delete', { id });
            showToast('Contenido eliminado', 'success');
            loadContents();
        } catch (e) { showToast(e.message, 'error'); }
    }
}

// ═══════════════════════════════════════════════════════════════════
// CUSTOM DIALOGS
// ═══════════════════════════════════════════════════════════════════
function customPrompt(title, defaultValue = '', type = 'text') {
    return new Promise(resolve => {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay active';
        overlay.style.zIndex = '9999';
        
        let inputHtml = '';
        if (type === 'color') {
            inputHtml = `<div style="display:flex; gap:10px; align-items:center;">
                <input type="color" id="cpInput" value="${defaultValue}" style="width:50px; height:40px; padding:2px; cursor:pointer; background:transparent; border:1px solid var(--border-color); border-radius:4px;">
                <input type="text" id="cpText" class="form-control" value="${defaultValue}" style="flex:1">
            </div>`;
        } else {
            inputHtml = `<input type="text" class="form-control" id="cpInput" value="${defaultValue}">`;
        }

        overlay.innerHTML = `
            <div class="modal" style="max-width: 400px; transform: scale(1); margin: auto;">
                <div class="modal-header">
                    <h2 style="font-size:1.1rem">${title}</h2>
                </div>
                <div class="modal-body" style="padding: 20px 24px;">
                    ${inputHtml}
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cpCancel">Cancelar</button>
                    <button class="btn btn-primary" id="cpOk">Guardar</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);

        const input = overlay.querySelector('#cpInput');
        const textInput = overlay.querySelector('#cpText');
        const btnOk = overlay.querySelector('#cpOk');
        const btnCancel = overlay.querySelector('#cpCancel');

        if (type === 'color' && textInput) {
            input.addEventListener('input', e => textInput.value = e.target.value);
            textInput.addEventListener('input', e => input.value = e.target.value);
        }

        setTimeout(() => (textInput || input).focus(), 50);

        const close = (val) => {
            overlay.classList.remove('active');
            setTimeout(() => overlay.remove(), 200);
            resolve(val);
        };

        btnOk.onclick = () => close((textInput || input).value);
        btnCancel.onclick = () => close(null);
        
        overlay.addEventListener('keydown', e => {
            if (e.key === 'Enter') close((textInput || input).value);
            if (e.key === 'Escape') close(null);
        });
    });
}

function customConfirm(message) {
    return new Promise(resolve => {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay active';
        overlay.style.zIndex = '9999';
        
        overlay.innerHTML = `
            <div class="modal" style="max-width: 400px; transform: scale(1); margin: auto;">
                <div class="modal-header">
                    <h2 style="font-size:1.1rem; color:#fca5a5;"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg> Confirmación</h2>
                </div>
                <div class="modal-body" style="padding: 20px 24px; font-size:0.95rem; line-height: 1.5;">
                    ${message}
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="ccCancel">Cancelar</button>
                    <button class="btn" style="background:var(--danger); color:white;" id="ccOk">Confirmar</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);

        const btnOk = overlay.querySelector('#ccOk');
        const btnCancel = overlay.querySelector('#ccCancel');

        const close = (val) => {
            overlay.classList.remove('active');
            setTimeout(() => overlay.remove(), 200);
            resolve(val);
        };

        btnOk.onclick = () => close(true);
        btnCancel.onclick = () => close(false);
    });
}

function initContextMenu() {
    let cm = document.getElementById('rowContextMenu');
    if (!cm) {
        cm = document.createElement('div');
        cm.id = 'rowContextMenu';
        cm.className = 'context-menu';
        cm.style.display = 'none';
        cm.innerHTML = `
            <div class="cm-item" onclick="handleContext('edit')"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg> Editar</div>
            <div class="cm-item" id="cmOpenLink" onclick="handleContext('openLink')" style="display:none;"><svg class="svg-icon" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg> Abrir Producción</div>
            <div class="cm-item" id="cmOpenDesign" onclick="handleContext('openDesign')" style="display:none;"><svg class="svg-icon" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M7.71 3.5L1.15 15l3.43 6 6.55-11.5M9.73 15L6.3 21h13.12l3.43-6M12.27 3.5L15.7 9.5H2.57L6 3.5z"></path></svg> Abrir Diseño</div>
            <div class="cm-item" onclick="handleContext('duplicate')"><svg class="svg-icon" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> Duplicar</div>
            <div class="cm-item" id="cmLinkPauta" onclick="handleContext('linkPauta')"><svg class="svg-icon" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg> Enlazar a Pauta</div>
            <div class="cm-item" onclick="handleContext('recorrer')"><svg class="svg-icon" viewBox="0 0 24 24" style="width:16px;height:16px"><polyline points="13 17 18 12 13 7"></polyline><polyline points="6 17 11 12 6 7"></polyline></svg> Recorrer (+1 día)</div>
            <div class="cm-item danger" onclick="handleContext('delete')"><svg class="svg-icon" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Borrar</div>
        `;
        document.body.appendChild(cm);

        document.addEventListener('click', () => {
            cm.style.display = 'none';
        });
        
        // Hide on scroll too
        window.addEventListener('scroll', () => cm.style.display = 'none', true);
    }
}

function showContextMenu(e, id) {
    e.preventDefault();
    e.stopPropagation();
    initContextMenu();
    currentContextId = id;
    const cm = document.getElementById('rowContextMenu');
    
    const item = state.contenidos.find(c => c.id == id);
    const btnLinkPauta = document.getElementById('cmLinkPauta');
    const btnOpenLink = document.getElementById('cmOpenLink');
    const btnOpenDesign = document.getElementById('cmOpenDesign');
    
    if (item) {
        if (btnLinkPauta) {
            btnLinkPauta.style.display = (item.pestana_slug === 'pauta') ? 'none' : 'flex';
        }
        if (btnOpenLink) {
            btnOpenLink.style.display = item.enlace_contenido ? 'flex' : 'none';
        }
        if (btnOpenDesign) {
            btnOpenDesign.style.display = item.enlace_diseno ? 'flex' : 'none';
        }
    }
    
    cm.style.display = 'block';
    
    // Position
    let x = e.pageX;
    let y = e.pageY;
    
    // Bounds check
    if (x + cm.offsetWidth > window.innerWidth) x -= cm.offsetWidth;
    if (y + cm.offsetHeight > window.innerHeight) y -= cm.offsetHeight;
    
    cm.style.left = x + 'px';
    cm.style.top = y + 'px';
}

function showCalendarDayContextMenu(e, dateStr) {
    e.preventDefault();
    e.stopPropagation();
    
    let cm = document.getElementById('dayContextMenu');
    if (!cm) {
        cm = document.createElement('div');
        cm.id = 'dayContextMenu';
        cm.className = 'context-menu';
        document.body.appendChild(cm);
        
        // Hide on click outside
        document.addEventListener('click', () => {
            const el = document.getElementById('dayContextMenu');
            if (el) el.style.display = 'none';
        });
        window.addEventListener('scroll', () => {
            const el = document.getElementById('dayContextMenu');
            if (el) el.style.display = 'none';
        }, true);
    }
    
    cm.innerHTML = `<button class="btn btn-sm btn-primary" style="width:100%;text-align:left;display:flex;align-items:center;" onclick="openCreateModalWithDate('${dateStr}'); document.getElementById('dayContextMenu').style.display='none';">
        <svg class="svg-icon" viewBox="0 0 24 24" style="width:14px;height:14px;margin-right:6px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> CREAR CONTENIDOS
    </button>`;
    
    cm.style.display = 'block';
    
    let x = e.pageX;
    let y = e.pageY;
    if (x + cm.offsetWidth > window.innerWidth) x -= cm.offsetWidth;
    if (y + cm.offsetHeight > window.innerHeight) y -= cm.offsetHeight;
    
    cm.style.left = x + 'px';
    cm.style.top = y + 'px';
}

function openCreateModalWithDate(dateStr) {
    openCreateModal();
    setTimeout(() => {
        const dateInput = document.getElementById('form_fecha');
        if (dateInput) {
            dateInput.value = dateStr;
        }
    }, 100);
}

async function handleContext(action) {
    const cm = document.getElementById('rowContextMenu');
    if (cm) cm.style.display = 'none';
    if (!currentContextId) return;
    
    const id = currentContextId;
    currentContextId = null;
    
    if (action === 'edit') {
        openEditModal(id);
    } else if (action === 'duplicate') {
        try {
            await apiPost('contenidos.php?action=duplicate', { id });
            showToast('Contenido duplicado', 'success');
            loadContents();
        } catch (e) {
            showToast(e.message, 'error');
        }
    } else if (action === 'linkPauta') {
        try {
            await apiPost('contenidos.php?action=link_pauta', { id });
            showToast('Contenido enlazado a pauta', 'success');
            loadContents();
        } catch (e) {
            showToast(e.message, 'error');
        }
    } else if (action === 'recorrer') {
        try {
            await apiPost('contenidos.php?action=shift_date', { id });
            showToast('Fecha recorrida +1 día', 'success');
            loadContents();
        } catch (e) {
            showToast(e.message, 'error');
        }
    } else if (action === 'openLink') {
        const item = state.contenidos.find(c => c.id == id);
        if (item && item.enlace_contenido) window.open(item.enlace_contenido, '_blank');
    } else if (action === 'openDesign') {
        const item = state.contenidos.find(c => c.id == id);
        if (item && item.enlace_diseno) window.open(item.enlace_diseno, '_blank');
    }
}

// ═══════════════════════════════════════════════════════════════════
// ADMIN SECTIONS
// ═══════════════════════════════════════════════════════════════════
function showAdminSection(section) {
    // Hide main views
    document.getElementById('tableView').style.display = 'none';
    const calView = document.getElementById('calendarView');
    if (calView) calView.style.display = 'none';
    document.getElementById('toolbar').style.display = 'none';
    document.getElementById('tabsBar').style.display = 'none';
    
    // Deselect main nav
    document.querySelectorAll('#tabNavigation .nav-item').forEach(el => el.classList.remove('active'));
    
    // Select admin nav
    document.querySelectorAll('.sidebar-nav .nav-item').forEach(el => {
        el.classList.remove('active');
        if (el.getAttribute('onclick') && el.getAttribute('onclick').includes(section)) {
            el.classList.add('active');
        }
    });

    // Update title
    document.getElementById('headerTitle').textContent = 'Admin: ' + section.charAt(0).toUpperCase() + section.slice(1);
    document.getElementById('btnCrear').style.display = 'none';

    // Show admin section
    const container = document.getElementById('adminSection');
    container.style.display = 'block';
    container.innerHTML = '<div class="loading-spinner" style="margin:40px auto;"></div>';

    if (section === 'usuarios') {
        renderUsuariosAdmin(container);
    } else if (section === 'dropdowns') {
        renderDropdownsAdmin(container);
    } else if (section === 'campos') {
        renderCamposAdmin(container);
    } else if (section === 'analytics') {
        renderAnalytics(container);
    }
}

async function renderUsuariosAdmin(container) {
    try {
        const res = await api('usuarios.php', { action: 'list' });
        const usuarios = res.data;
        
        container.innerHTML = `
            <div style="padding: 24px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                    <h3 style="margin:0;"><svg class="svg-icon" viewBox="0 0 24 24" style="width:20px;height:20px;vertical-align:middle;margin-right:8px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> Gestión de Usuarios</h3>
                    <button class="btn btn-primary" onclick="openUserCreateModal()">
                        <svg class="svg-icon" viewBox="0 0 24 24" style="width:16px;height:16px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Nuevo Usuario
                    </button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${usuarios.filter(u => u.activo !== 0 && u.activo !== false).map(u => `
                                <tr>
                                    <td><strong>${escHtml(u.nombre)}</strong></td>
                                    <td>${escHtml(u.email)}</td>
                                    <td><span class="badge" style="background:var(--bg-secondary); border:1px solid var(--border-color);">${u.rol}</span></td>
                                    <td>${u.activo 
                                        ? '<span class="badge" style="background:rgba(16,185,129,0.15); color:#10b981;">Activo</span>' 
                                        : '<span class="badge" style="background:rgba(239,68,68,0.15); color:#ef4444;">Inactivo</span>'}
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-secondary" onclick="openUserEditModal(${u.id})">Editar</button>
                                        <button class="btn btn-sm btn-secondary" onclick="deleteUser(${u.id})" style="color: var(--danger, #ef4444); border-color: rgba(239, 68, 68, 0.3); margin-left: 4px;">Eliminar</button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    } catch (err) {
        container.innerHTML = `<div class="empty-state"><p>Error: ${err.message}</p></div>`;
    }
}

async function renderDropdownsAdmin(container) {
    try {
        const res = await api('dropdowns.php', { action: 'list' });
        const groups = res.data;
        
        container.innerHTML = '<div class="config-section">' +
            Object.keys(groups).map(campo => `
                <div class="config-card">
                    <div class="config-card-header" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? '' : 'none'">
                        <h3><svg class="svg-icon" viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg> ${campo.toUpperCase()}</h3>
                        <span style="font-size:0.8rem;color:var(--text-muted)">${groups[campo].length} opciones</span>
                    </div>
                    <div class="config-card-body">
                        ${groups[campo].map(o => `
                            <div class="config-item">
                                <div class="color-swatch" style="background:${o.color}"></div>
                                <span class="config-value">${escHtml(o.valor)}</span>
                                <div class="config-actions">
                                    <button class="btn btn-sm btn-secondary" onclick="editDropdownOption(${o.id}, '${escHtml(o.valor)}', '${o.color}')"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg></button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteDropdownOption(${o.id})"><svg class="svg-icon" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>
                                </div>
                            </div>
                        `).join('')}
                        <div style="margin-top:10px; display:flex; gap:6px;">
                            <input type="text" class="form-control" id="newDD_${campo}" placeholder="Nueva opción..." style="flex:1">
                            <input type="color" value="#e53935" id="newDDColor_${campo}" style="width:40px; padding:2px; background:transparent; border:1px solid var(--border-color); border-radius:4px; cursor:pointer;">
                            <button class="btn btn-sm btn-primary" onclick="addDropdownOption('${campo}')">ï¼‹</button>
                        </div>
                    </div>
                </div>
            `).join('') +
        '</div>';
    } catch (err) {
        container.innerHTML = `<div class="empty-state"><p>Error: ${err.message}</p></div>`;
    }
}

async function addDropdownOption(campo) {
    const input = document.getElementById('newDD_' + campo);
    const colorInput = document.getElementById('newDDColor_' + campo);
    const valor = input.value.trim();
    if (!valor) return;

    try {
        await apiPost('dropdowns.php?action=create', { campo, valor, color: colorInput.value });
        showToast('Opción agregada', 'success');
        // Reload dropdowns
        const res = await api('dropdowns.php', { action: 'all' });
        state.dropdowns = res.data;
        showAdminSection('dropdowns');
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
}

async function editDropdownOption(id, oldVal, oldColor) {
    const newVal = await customPrompt("Editar nombre de la opción:", oldVal);
    if (!newVal || newVal === oldVal) return;
    
    const newColor = await customPrompt("Selecciona un color:", oldColor, 'color');
    if (newColor === null) return; // cancelled
    
    try {
        await apiPost('dropdowns.php?action=update', { id, valor: newVal, color: newColor });
        showToast('Opción actualizada');
        // reload dropdowns
        const res = await api('dropdowns.php', { action: 'all' });
        state.dropdowns = res.data;
        showAdminSection('dropdowns');
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function deleteDropdownOption(id) {
    if (!await customConfirm('Â¿Eliminar esta opciÓn?')) return;
    try {
        await apiPost('dropdowns.php?action=delete', { id });
        showToast('OpciÓn eliminada', 'success');
        const res = await api('dropdowns.php', { action: 'all' });
        state.dropdowns = res.data;
        showAdminSection('dropdowns');
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
}

function renderCamposAdmin(container) {
    const allCampos = state.campos;
    
    container.innerHTML = '<div class="config-section">' +
        state.pestanas.map(p => {
            const campos = allCampos[p.slug] || [];
            return `
                <div class="config-card">
                    <div class="config-card-header">
                        <div style="display:flex; align-items:center; gap:8px; flex:1" onclick="this.parentElement.nextElementSibling.style.display = this.parentElement.nextElementSibling.style.display === 'none' ? '' : 'none'">
                            <h3><span style="color:${p.color}">â—</span> ${p.nombre}</h3>
                            <span style="font-size:0.8rem;color:var(--text-muted)">${campos.length} campos</span>
                        </div>
                        <button class="btn btn-sm btn-secondary" onclick="editPestana(${p.id}, '${escHtml(p.nombre)}', '${p.color}', '${escHtml(p.enlace_carpeta_base || '')}')"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg></button>
                    </div>
                    <div class="config-card-body" style="display:none">
                        ${campos.map(c => `
                            <div class="config-item">
                                <span class="config-value" style="flex:1"><strong>${escHtml(c.nombre_display)}</strong> <span style="color:var(--text-muted);font-size:0.75rem">(${c.tipo_campo})</span></span>
                                <button class="btn btn-sm btn-danger" onclick="hideField(${c.id})">Ocultar</button>
                            </div>
                        `).join('')}
                    </div>
                </div>`;
        }).join('') +
    '</div>';
}

async function editPestana(id, oldNombre, oldColor, oldEnlace) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay active';
    overlay.style.zIndex = '9999';
    
    overlay.innerHTML = `
        <div class="modal" style="max-width: 450px; transform: scale(1); margin: auto;">
            <div class="modal-header">
                <h2 style="font-size:1.1rem">Editar Pestaña</h2>
                <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">×</button>
            </div>
            <div class="modal-body" style="padding: 20px 24px;">
                <div class="form-group">
                    <label>Nombre de la Pestaña:</label>
                    <input type="text" id="epNombre" class="form-control" value="${escHtml(oldNombre || '')}">
                </div>
                <div class="form-group">
                    <label>Color:</label>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input type="color" id="epColor" value="${escHtml(oldColor || '#000000')}" style="width:50px; height:40px; padding:2px; cursor:pointer; background:transparent; border:1px solid var(--border-color); border-radius:4px;">
                        <input type="text" id="epColorText" class="form-control" value="${escHtml(oldColor || '#000000')}" style="flex:1">
                    </div>
                </div>
                <div class="form-group">
                    <label>Enlace Carpeta Base (Drive):</label>
                    <input type="url" id="epEnlace" class="form-control" value="${escHtml(oldEnlace || '')}" placeholder="https://drive.google.com/...">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove()">Cancelar</button>
                <button class="btn btn-primary" id="epSave">Guardar</button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);

    const cpColor = overlay.querySelector('#epColor');
    const cpText = overlay.querySelector('#epColorText');
    cpColor.addEventListener('input', e => cpText.value = e.target.value);
    cpText.addEventListener('input', e => cpColor.value = e.target.value);

    overlay.querySelector('#epSave').addEventListener('click', async () => {
        const newNombre = overlay.querySelector('#epNombre').value.trim();
        const newColor = overlay.querySelector('#epColor').value;
        const newEnlace = overlay.querySelector('#epEnlace').value.trim();

        if (!newNombre) return showToast('El nombre es requerido', 'error');

        try {
            await apiPost('campos.php?action=update_pestana', { id, nombre: newNombre, color: newColor, enlace_carpeta_base: newEnlace });
            showToast('Pestaña actualizada');
            overlay.remove();
            
            const res = await api('campos.php', { action: 'pestanas' });
            state.pestanas = res.data;
            renderTabsNavigation(); // Update sidebar
            showAdminSection('campos'); // Refresh view
        } catch (err) {
            showToast(err.message, 'error');
        }
    });
}

async function hideField(id) {
    if (!await customConfirm('¿Ocultar este campo? Podrás restaurarlo después.')) return;
    try {
        await apiPost('campos.php?action=delete', { id });
        showToast('Campo ocultado', 'success');
        const res = await api('campos.php', { action: 'all' });
        state.campos = res.data;
        showAdminSection('campos');
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
}

async function renderAnalytics(container) {
    try {
        const res = await api('contenidos.php', { action: 'stats', pestana: '', mes: state.currentMonth });
        const stats = res.stats;

        const reportRes = await api('metricas.php', { action: 'report', mes: state.currentMonth });
        const totals = reportRes.totals;

        container.innerHTML = `
            <div class="analytics-grid">
                <div class="stat-card"><div class="stat-icon"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></div><div class="stat-value">${res.total}</div><div class="stat-label">Total Contenidos</div></div>
                ${stats.map(s => `
                    <div class="stat-card"><div class="stat-icon">${getEstadoIcon(s.estado)}</div><div class="stat-value">${s.total}</div><div class="stat-label">${s.estado}</div></div>
                `).join('')}
            </div>
            <div style="padding:0 24px"><h3 style="margin:24px 0 12px; font-size:1rem;"><svg class="svg-icon" viewBox="0 0 24 24" style="width:20px;height:20px"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Métricas Acumuladas</h3></div>
            <div class="analytics-grid">
                <div class="stat-card"><div class="stat-icon"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></div><div class="stat-value">${formatNumber(totals.espectadores)}</div><div class="stat-label">Espectadores</div></div>
                <div class="stat-card"><div class="stat-icon"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg></div><div class="stat-value">${formatNumber(totals.likes)}</div><div class="stat-label">Likes</div></div>
                <div class="stat-card"><div class="stat-icon"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg></div><div class="stat-value">${formatNumber(totals.comentarios)}</div><div class="stat-label">Comentarios</div></div>
                <div class="stat-card"><div class="stat-icon"><svg class="svg-icon" viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg></div><div class="stat-value">${formatNumber(totals.compartidos)}</div><div class="stat-label">Compartidos</div></div>
                <div class="stat-card"><div class="stat-icon"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg></div><div class="stat-value">${formatNumber(totals.guardados)}</div><div class="stat-label">Guardados</div></div>
                <div class="stat-card"><div class="stat-icon"><svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg></div><div class="stat-value">${formatNumber(totals.alcance)}</div><div class="stat-label">Alcance</div></div>
                <div class="stat-card"><div class="stat-icon"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.59-9.21l-5.94 5.94"></path></svg></div><div class="stat-value">${formatNumber(totals.clics)}</div><div class="stat-label">Clics</div></div>
            </div>
            <div style="padding:16px 24px; text-align:right;">
                <button class="btn btn-secondary" onclick="exportCSV()"><svg class="svg-icon" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Exportar Reporte CSV</button>
            </div>`;
    } catch (err) {
        container.innerHTML = `<div class="empty-state"><p>Error: ${err.message}</p></div>`;
    }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// USER MODAL
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function openUserCreateModal() {
    document.getElementById('userModalTitle').textContent = 'Nuevo Usuario';
    document.getElementById('userModalBody').innerHTML = `
        <div class="form-group"><label>Nombre</label><input type="text" class="form-control" id="formUserName"></div>
        <div class="editor-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 12px;">
            <div class="form-group" style="margin-bottom:0;"><label>Email</label><input type="email" class="form-control" id="formUserEmail"></div>
            <div class="form-group" style="margin-bottom:0;"><label>Contraseña</label><input type="password" class="form-control" id="formUserPass"></div>
        </div>
        <div class="form-group"><label>Rol</label>
            <select class="form-control" id="formUserRole">
                <option value="community">Community Manager</option>
                <option value="postproductor">Post-Productor</option>
                <option value="admin">Administrador</option>
            </select>
        </div>
    `;
    document.getElementById('btnSaveUser').dataset.userId = '';
    openModal('userModal');
}

async function openUserEditModal(id) {
    try {
        const res = await api('usuarios.php', { action: 'get', id });
        const u = res.data;
        document.getElementById('userModalTitle').textContent = 'Editar Usuario';
        document.getElementById('userModalBody').innerHTML = `
            <div class="form-group"><label>Nombre</label><input type="text" class="form-control" id="formUserName" value="${escHtml(u.nombre)}"></div>
            <div class="form-group"><label>Email</label><input type="email" class="form-control" id="formUserEmail" value="${escHtml(u.email)}"></div>
            <div class="form-group"><label>Nueva Contraseña (dejar vacío para no cambiar)</label><input type="password" class="form-control" id="formUserPass"></div>
            <div class="form-group"><label>Rol</label>
                <select class="form-control" id="formUserRole">
                    <option value="community" ${u.rol === 'community' ? 'selected' : ''}>Community Manager</option>
                    <option value="postproductor" ${u.rol === 'postproductor' ? 'selected' : ''}>Post-Productor</option>
                    <option value="admin" ${u.rol === 'admin' ? 'selected' : ''}>Administrador</option>
                </select>
            </div>
            <div class="form-group"><label>
                <input type="checkbox" id="formUserActivo" ${u.activo ? 'checked' : ''}> Activo
            </label></div>
        `;
        document.getElementById('btnSaveUser').dataset.userId = id;
        openModal('userModal');
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
}

async function saveUser() {
    const id = document.getElementById('btnSaveUser').dataset.userId;
    const data = {
        nombre: document.getElementById('formUserName').value,
        email: document.getElementById('formUserEmail').value,
        rol: document.getElementById('formUserRole').value,
    };
    
    const pass = document.getElementById('formUserPass').value;
    if (pass) data.password = pass;
    
    const activoEl = document.getElementById('formUserActivo');
    if (activoEl) data.activo = activoEl.checked ? 1 : 0;

    try {
        if (id) {
            data.id = id;
            await apiPost('usuarios.php?action=update', data);
            showToast('Usuario actualizado', 'success');
        } else {
            if (!pass) { showToast('La contraseña es requerida', 'error'); return; }
            await apiPost('usuarios.php?action=create', data);
            showToast('Usuario creado', 'success');
        }
        closeUserModal();
        showAdminSection('usuarios');
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
}

async function deleteUser(id) {
    if (!confirm('¿Estás seguro de eliminar este usuario?')) return;
    try {
        const res = await apiPost('usuarios.php?action=delete', { id });
        showToast(res.message || 'Usuario eliminado', 'success');
        showAdminSection('usuarios');
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
}

// ----------------------------------------
// EXPORT
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function exportCSV() {
    const params = new URLSearchParams({
        pestana: state.currentTab,
        mes: state.currentMonth,
        anio: 2026
    });
    window.open(API_BASE + '/export.php?' + params.toString(), '_blank');
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// MODAL HELPERS
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('contentModal').classList.remove('active');
    document.body.style.overflow = '';
    state.editingId = null;
}

function closeUserModal() {
    document.getElementById('userModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// SIDEBAR TOGGLE (mobile)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// LOGOUT
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
async function logout() {
    await api('auth.php', { action: 'logout' });
    // Detect if we are in HTML mode or PHP mode
    if (window.location.pathname.includes('dashboard.html') || window.location.pathname.endsWith('/')) {
        window.location.href = 'index.html';
    } else {
        window.location.href = '../index.php';
    }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// TOAST NOTIFICATIONS
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function showToast(message, type = 'info') {
    const icons = { success: '<svg class="svg-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>', error: '<svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>', warning: '<svg class="svg-icon" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>', info: '<svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>' };
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${icons[type] || '<svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>'}</span>
        <span>${escHtml(message)}</span>
        <span class="toast-close" onclick="this.parentElement.remove()">×</span>
    `;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// UTILITY FUNCTIONS
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function showNoLinkAlert() {
    alert('Primero guarda un enlace para abrirlo');
}


function hexToRgba(hex, alpha) {
    if (!hex || hex[0] !== '#') return `rgba(229, 57, 53, ${alpha})`;
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function lightenColor(hex) {
    if (!hex || hex[0] !== '#') return '#ef5350';
    const r = Math.min(255, parseInt(hex.slice(1, 3), 16) + 80);
    const g = Math.min(255, parseInt(hex.slice(3, 5), 16) + 80);
    const b = Math.min(255, parseInt(hex.slice(5, 7), 16) + 80);
    return `rgb(${r}, ${g}, ${b})`;
}

function getBadgeClass(field, value) {
    if (field !== 'estado') return 'badge-colored';
    const map = {
        'En elaboración': 'badge-elaboracion',
        'Redacción': 'badge-redaccion',
        'En revisión': 'badge-revision',
        'Corrección': 'badge-correccion',
        'Aprobado': 'badge-aprobado',
        'Programado': 'badge-programado',
        'Publicado': 'badge-publicado',
    };
    return map[value] || 'badge-colored';
}

function getEstadoIcon(estado) {
    const icons = {
        'En elaboración': '<svg class="svg-icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>',
        'Redacción': '<svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>',
        'En revisión': '<svg class="svg-icon" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
        'Corrección': '<svg class="svg-icon" viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>',
        'Aprobado': '<svg class="svg-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>',
        'Diseñado': '<svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>',
        'Programado': '<svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
        'Publicado': '<svg class="svg-icon" viewBox="0 0 24 24"><path d="M13.5 22s-2-2.5-2-4 1.5-3 1.5-3l1.5-1.5s2 1.5 2 3-1.5 4-1.5 4-1.5 1.5-1.5 1.5z"></path><path d="M22 2s-3.5-1-6.5 2-6 7.5-6 7.5l-3 3-4 1s2-2.5 3-4.5 1.5-3.5 1.5-3.5l1-1S6.5 4.5 9 3 17.5 1 22 2z"></path><path d="M12 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"></path></svg>'
    };
    return icons[estado] || '<svg class="svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>';
}

function getSocialIcon(red) {
    if (!red) return '';
    return getSocialSvg(red);
}

function getFormatIcon(formato) {
    if (!formato) return '';
    return `<span style="opacity:0.9;">${formato}</span>`;
}

function getEstadoColor(estado) {
    const map = {
        'En elaboración': '#64748b',
        'Redacción': '#f97316',
        'En revisión': '#e53935',
        'CorrecciÓn': '#ef4444',
        'Aprobado': '#14b8a6',
        'Diseñado': '#10b981',
        'Programado': '#06b6d4',
        'Publicado': '#84cc16',
    };
    return map[estado] || '#475569';
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const pad = n => String(n).padStart(2, '0');
    return `${pad(d.getDate())}/${pad(d.getMonth() + 1)} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function formatNumber(n) {
    if (!n) return '0';
    return parseInt(n).toLocaleString('es-BO');
}

function openDayDetail(dateStr) {
    // Could open a modal with day's content, for now just filter
    const input = document.getElementById('searchInput');
    // Nothing for now, click calendar event opens modal
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// IMAGE REFERENCE UPLOAD
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
let tempImageIds = [];


// ----------------------------------------
// AI ENHANCER (POTENCIADOR CON IA) - v2
// ----------------------------------------
let aiFetchedContents = [];

function openAIEnhancer() {
    document.getElementById('aiEnhancerModal').classList.add('active');
    const monthSel = document.getElementById('aiMonthSelect');
    if (state.currentMonth && monthSel) monthSel.value = state.currentMonth;
    fetchAIDataAndPopulateWeeks();
    generateSchemaJSON();
}

function closeAIEnhancer() {
    document.getElementById('aiEnhancerModal').classList.remove('active');
    const et = document.getElementById('aiExportText'); if (et) et.value = '';
    const it = document.getElementById('aiImportJsonText'); if (it) it.value = '';
    const fi = document.getElementById('aiImportJsonFile'); if (fi) fi.value = '';
    const fn = document.getElementById('aiFileName'); if (fn) fn.textContent = 'Ningún archivo seleccionado';
}

function switchAITab(tab) {
    const tabs = { md: 'aiTabMd', json: 'aiTabJson', import: 'aiTabImport' };
    const btns = { md: 'aiTabMdBtn', json: 'aiTabJsonBtn', import: 'aiTabImportBtn' };
    Object.keys(tabs).forEach(k => {
        const el = document.getElementById(tabs[k]);
        const btn = document.getElementById(btns[k]);
        if (!el || !btn) return;
        el.style.display = (k === tab) ? 'flex' : 'none';
        if (k === tab) {
            btn.classList.add('active');
            btn.classList.remove('btn-secondary');
            // Mantener btn-primary si aún lo usan
        } else {
            btn.classList.remove('active');
            btn.classList.remove('btn-primary');
        }
    });
}

function onAIFilterChange() {
    generateAIExport();
}

async function fetchAIDataAndPopulateWeeks() {
    try {
        const mes = document.getElementById('aiMonthSelect')?.value || state.currentMonth || '';
        const res = await api('contenidos.php', { action: 'list', mes: mes, anio: new Date().getFullYear() });
        aiFetchedContents = res.data || [];

        const weeks = new Set();
        aiFetchedContents.forEach(c => {
            if (c.fecha) {
                const dateObj = new Date(c.fecha + 'T12:00:00');
                c.semana = 'Semana ' + Math.ceil(dateObj.getDate() / 7);
            } else {
                c.semana = 'Sin Fecha';
            }
            weeks.add(c.semana);
        });

        const countEl = document.getElementById('aiContentCount');
        if (countEl) countEl.textContent = aiFetchedContents.length + ' contenidos';

        const select = document.getElementById('aiWeekSelect');
        if (!select) return;
        select.innerHTML = '<option value="">-- Todas las semanas --</option>';
        Array.from(weeks).sort().forEach(w => {
            select.innerHTML += '<option value="' + w + '">' + w + '</option>';
        });
        generateAIExport();
    } catch (err) {
        showToast('Error al cargar datos para IA: ' + err.message, 'error');
    }
}

function getFilteredAIContents() {
    const week = document.getElementById('aiWeekSelect')?.value || '';
    if (!week) return aiFetchedContents;
    return aiFetchedContents.filter(c => c.semana === week);
}

function generateAIExport() {
    const contents = getFilteredAIContents();
    const week = document.getElementById('aiWeekSelect')?.value || 'Todos';
    const ta = document.getElementById('aiExportText');
    if (!ta) return;

    if (contents.length === 0) {
        ta.value = 'No hay contenidos para exportar con los filtros seleccionados.';
        return;
    }

    const porDia = {};
    contents.forEach(c => {
        const dateKey = c.fecha || 'Sin Fecha';
        if (!porDia[dateKey]) porDia[dateKey] = [];
        porDia[dateKey].push(c);
    });

    let md = '# Contenidos - ' + week + '\n\n';
    md += 'Revisa y mejora los textos manteniendo las etiquetas <!-- POST_ID: ... --> intactas.\n\n';

    Object.keys(porDia).sort().forEach(fecha => {
        md += '## Dia: ' + fecha + '\n\n';
        porDia[fecha].forEach(c => {
            md += '<!-- POST_ID: ' + c.id + ' -->\n';
            md += '### Post: ' + (c.tema || 'Sin Tema') + '\n';
            md += '- **Pestana:** ' + (c.pestana_nombre || '') + '\n';
            if (c.red_social) md += '- **Red Social:** ' + c.red_social + '\n';
            if (c.idea) md += '- **Idea/Contexto:** ' + c.idea + '\n';
            if (c.observaciones) md += '- **Observaciones:** ' + c.observaciones + '\n';
            md += '\n**Textos (Modificables):**\n';
            md += '*Tema/Título:* ' + (c.tema || 'Sin tema') + '\n';
            md += '*Copy Facebook:* ' + (c.copy_facebook || '') + '\n';
            md += '*Copy Instagram:* ' + (c.copy_instagram || '') + '\n';
            md += '*Copy TikTok:* ' + (c.copy_tiktok || '') + '\n';
            md += '\n---\n\n';
        });
    });

    ta.value = md;
}

function downloadAIFile() {
    const text = document.getElementById('aiExportText')?.value;
    if (!text || text.startsWith('No hay')) { showToast('No hay contenido para descargar', 'error'); return; }
    const week = document.getElementById('aiWeekSelect')?.value || 'Contenidos';
    const filename = week.replace(/\s+/g, '_') + '_IA.md';
    const blob = new Blob([text], { type: 'text/markdown;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename;
    document.body.appendChild(a); a.click();
    document.body.removeChild(a); URL.revokeObjectURL(url);
    showToast('Descargado: ' + filename);
}

function generateSchemaJSON() {
    const pestanas = (state.pestanas || []).filter(p => p.slug);

    const copyField = (red) => {
        if (red === 'Facebook') return { copy_facebook: 'Copy completo para Facebook con emojis y hashtags' };
        if (red === 'Instagram') return { copy_instagram: 'Copy completo para Instagram con emojis y hashtags' };
        if (red === 'TikTok') return { copy_tiktok: 'Caption para TikTok con emojis y hashtags' };
        if (red === 'YouTube') return { copy_facebook: 'Descripcion del video de YouTube' };
        if (red === 'LinkedIn') return { copy_facebook: 'Texto profesional para LinkedIn' };
        return { copy_instagram: 'Copy para la red social indicada' };
    };

    const formatFields = (formato) => {
        if (formato === 'Carrusel') return {
            _slides_nota: 'Define el texto de cada slide. Entre 3 y 10 slides.',
            slides: [
                { numero: 1, titulo: 'Texto de portada (engancha en los primeros 2 segundos)', subtitulo: 'Subtitulo opcional' },
                { numero: 2, titulo: 'Titulo del slide 2', cuerpo: 'Texto del slide 2' },
                { numero: 3, titulo: 'Titulo del slide 3', cuerpo: 'Texto del slide 3' },
                { numero: 4, titulo: 'Slide de cierre', cuerpo: 'CTA: Comenta, Comparte, Matriculate, etc.' }
            ]
        };
        if (formato === 'Video' || formato === 'Reel') return {
            _video_nota: 'Define el guion del video. Los primeros 3 segundos son criticos.',
            guion_intro: 'Primeras palabras del video (gancho)',
            guion_desarrollo: 'Desarrollo: puntos principales a cubrir',
            guion_cierre: 'Cierre y llamada a la accion',
            duracion_segundos: 30,
            texto_pantalla: 'Texto o subtitulo superpuesto en el video'
        };
        if (formato === 'Arte simple' || formato === 'Arte compuesto') return {
            _arte_nota: 'Define los textos que se imprimiran sobre el arte grafico.',
            texto_principal: 'Texto grande y visible (titulo o frase principal)',
            texto_secundario: 'Texto secundario o subtitulo',
            cta_arte: 'Call to action visible en el arte (ej: Inscribete, Mas info)'
        };
        if (formato === 'Story') return {
            _story_nota: 'Las Stories duran 15 segundos. Texto corto y directo.',
            texto_story: 'Texto principal de la Story',
            cta_story: 'Accion: Swipe up / Encuesta / Sticker de reaccion'
        };
        if (formato === 'Podcast') return {
            _podcast_nota: 'Define el contenido del episodio.',
            titulo_episodio: 'Titulo del episodio',
            descripcion_episodio: 'Descripcion para plataforma',
            temas_a_tratar: ['Tema 1', 'Tema 2', 'Tema 3']
        };
        return {};
    };

    const hoy = new Date().toISOString().split('T')[0];
    const manana = new Date(Date.now() + 86400000).toISOString().split('T')[0];
    const pasado = new Date(Date.now() + 172800000).toISOString().split('T')[0];

    const schema = {
        _instrucciones: [
            "1. Rellena el array posts con los contenidos reales para la semana solicitada.",
            "2. Solo incluye el copy de la red_social indicada en cada post:",
            "   Instagram -> copy_instagram | Facebook -> copy_facebook | TikTok -> copy_tiktok",
            "3. Segun el formato incluye los campos especificos:",
            "   Carrusel -> slides[] | Video/Reel -> guion_* | Arte -> texto_principal/secundario | Story -> texto_story",
            "4. Devuelve SOLO el JSON con el array posts relleno, sin texto adicional."
        ],
        _campos_obligatorios: ["pestana", "fecha", "tema", "red_social", "formato"],
        _valores_red_social: ["Facebook", "Instagram", "TikTok", "YouTube", "LinkedIn"],
        _valores_formato: ["Video", "Reel", "Carrusel", "Arte simple", "Arte compuesto", "Story", "Podcast"],
        _valores_estado: ["En elaboracion", "En revision", "Aprobado", "Disenado", "Publicado"],
        _pestanas_disponibles: pestanas.map(p => ({ slug: p.slug, nombre: p.nombre })),
        posts: [
            Object.assign({
                pestana: pestanas[0]?.slug || "pauta",
                fecha: hoy,
                tema: "Ejemplo Arte Simple - Instagram",
                idea: "Frase motivacional sobre estudio universitario",
                red_social: "Instagram",
                formato: "Arte simple",
                horario: "09:00",
                buyer: "Estudiante prospecto 18-25",
                pilar: "Inspiracion",
                carrera: "",
                estado: "En elaboracion",
                observaciones: "Usar colores institucionales",
                titulo_post: "Frase motivacional"
            }, copyField("Instagram"), formatFields("Arte simple")),
            Object.assign({
                pestana: pestanas[0]?.slug || "pauta",
                fecha: manana,
                tema: "Ejemplo Carrusel - Instagram",
                idea: "5 consejos para aprobar examenes finales",
                red_social: "Instagram",
                formato: "Carrusel",
                horario: "11:00",
                buyer: "Estudiante universitario activo",
                pilar: "Educacion",
                carrera: "",
                estado: "En elaboracion",
                observaciones: "Maximo 6 slides, visual y dinamico",
                titulo_post: "5 tips para aprobar examenes"
            }, copyField("Instagram"), formatFields("Carrusel")),
            Object.assign({
                pestana: pestanas[0]?.slug || "pauta",
                fecha: pasado,
                tema: "Ejemplo Reel - TikTok",
                idea: "Video corto mostrando la vida universitaria en Unifranz",
                red_social: "TikTok",
                formato: "Reel",
                horario: "18:00",
                buyer: "Joven 16-22 indeciso sobre carrera",
                pilar: "Institucional",
                carrera: "",
                estado: "En elaboracion",
                observaciones: "Tono fresco, musica en tendencia",
                titulo_post: "Vida universitaria Unifranz"
            }, copyField("TikTok"), formatFields("Reel"))
        ]
    };

    const ta = document.getElementById('aiSchemaText');
    if (ta) ta.value = JSON.stringify(schema, null, 2);
}

function downloadSchemaJSON() {
    const text = document.getElementById('aiSchemaText')?.value;
    if (!text) return;
    const blob = new Blob([text], { type: 'application/json;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'esquema_contenidos_unifranz.json';
    document.body.appendChild(a); a.click();
    document.body.removeChild(a); URL.revokeObjectURL(url);
    showToast('Descargado: esquema_contenidos_unifranz.json');
}

function handleJSONFileUpload(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    const fn = document.getElementById('aiFileName');
    if (fn) fn.textContent = file.name;

    const reader = new FileReader();
    reader.onload = function(evt) {
        const ta = document.getElementById('aiImportJsonText');
        if (ta) ta.value = evt.target.result;
        showToast('Archivo cargado: ' + file.name);
    };
    reader.readAsText(file);
}

function validateImportJSON() {
    const text = document.getElementById('aiImportJsonText')?.value.trim();
    if (!text) { showToast('Pega un JSON primero', 'error'); return; }
    try {
        const data = JSON.parse(text);
        const posts = Array.isArray(data) ? data : (data.posts || []);
        if (!Array.isArray(posts) || posts.length === 0) {
            showToast('El JSON debe ser un array de posts o un objeto con propiedad "posts"', 'error'); return;
        }
        const missing = posts.filter(p => !p.pestana || !p.fecha || !p.tema);
        if (missing.length > 0) {
            showToast(missing.length + ' posts faltan campos obligatorios (pestana, fecha, tema)', 'error'); return;
        }
        showToast('JSON valido: ' + posts.length + ' posts listos para importar');
    } catch(e) {
        showToast('JSON invalido: ' + e.message, 'error');
    }
}

async function processJSONImport() {
    const text = document.getElementById('aiImportJsonText')?.value.trim();
    if (!text) { showToast('Pega un JSON primero', 'error'); return; }

    let posts = [];
    try {
        const data = JSON.parse(text);
        posts = Array.isArray(data) ? data : (data.posts || []);
        if (!Array.isArray(posts) || posts.length === 0) throw new Error('Array vacio o formato invalido');
    } catch(e) {
        showToast('Error al parsear JSON: ' + e.message, 'error'); return;
    }

    const invalid = posts.filter(p => !p.pestana || !p.fecha || !p.tema);
    if (invalid.length > 0) { showToast(invalid.length + ' posts con campos obligatorios faltantes', 'error'); return; }
    if (!confirm('Se van a CREAR ' + posts.length + ' nuevos contenidos. Continuar?')) return;

    const btns = document.querySelectorAll('#aiTabImport .btn');
    btns.forEach(b => { b.disabled = true; });

    let ok = 0, fail = 0;
    for (const post of posts) {
        try { await apiPost('contenidos.php?action=create', post); ok++; }
        catch(e) { fail++; console.error('Error:', post.tema, e.message); }
    }

    btns.forEach(b => { b.disabled = false; });
    showToast('Importacion: ' + ok + ' creados, ' + fail + ' con error');
    if (ok > 0) { closeAIEnhancer(); loadContents(); }
}
// ── Captura Handlers for Dashboard ───────────────────────────────
function handleCapturaDropDashboard(e, id) {
    e.preventDefault();
    document.getElementById('capturaZoneDashboard')?.classList.remove('dragover');
    const file = e.dataTransfer.files?.[0];
    if (file && file.type.startsWith('image/')) readAndUploadCapturaDashboard(file, id);
}
function handleCapturaFileDashboard(e, id) {
    const file = e.target.files?.[0];
    if (file) readAndUploadCapturaDashboard(file, id);
}
function handleCapturaPasteDashboard(e) {
    const modal = document.getElementById('contentModal');
    if (!modal || !modal.classList.contains('open')) return;
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    const id = state.editingId;
    if (!id || !document.getElementById('capturaZoneDashboard')) return;

    const items = e.clipboardData?.items || [];
    for (const item of items) {
        if (item.type.startsWith('image/')) {
            readAndUploadCapturaDashboard(item.getAsFile(), id);
            return;
        }
    }
}
document.addEventListener('paste', handleCapturaPasteDashboard);

function readAndUploadCapturaDashboard(file, id) {
    const reader = new FileReader();
    reader.onload = async (ev) => {
        const dataUrl = ev.target.result;
        const zone = document.getElementById('capturaZoneDashboard');
        if (zone) zone.innerHTML = `<img src="${dataUrl}" class="captura-preview" style="max-width:100%; border-radius:8px; display:block; margin:0 auto;" alt="captura">`;
        await saveCapturaDashboard(id, dataUrl);
    };
    reader.readAsDataURL(file);
}
async function saveCapturaDashboard(id, dataUrl) {
    try {
        const res = await apiPost('metricas.php?action=save_captura', {
            contenido_id: id,
            image_data: dataUrl || ''
        });
        if (res.success) {
            showToast('Captura guardada', 'success');
            openEditModal(id);
        } else {
            showToast('Error guardando captura', 'error');
        }
    } catch(e) {
        showToast('Error: ' + e.message, 'error');
    }
}

async function deleteCapturaDashboard(id) {
    try {
        const res = await apiPost('metricas.php?action=delete_captura', {
            contenido_id: id
        });
        if (res.success) {
            showToast('Captura eliminada', 'success');
            openEditModal(id);
        } else {
            showToast('Error eliminando captura', 'error');
        }
    } catch(e) {
        showToast('Error: ' + e.message, 'error');
    }
}
