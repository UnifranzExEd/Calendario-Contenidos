<?php
/**
 * Dashboard Principal - UNIFRANZ Calendar
 */
require_once __DIR__ . '/../config/auth.php';
$user = requireAuth();
$perms = getPermissions($user['rol']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="UNIFRANZ Online - Calendario de Contenidos">
    <title>Dashboard | UNIFRANZ Online Calendar</title>
    <link rel="stylesheet" href="../assets/css/app.css?v=<?= time() ?>">
</head>
<body>
<div class="app-wrapper">

    <!-- Sidebar overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- ── SIDEBAR ── -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="brand" style="width: 100%; display: flex; align-items: center; justify-content: center; padding: 0 10px;">
                <img src="../assets/img/logo.svg" alt="UNIFRANZ Online" style="width: 100%; max-width: 210px; height: auto; object-fit: contain;">
            </div>
        </div>

        <!-- Month Selector -->
        <div class="month-selector">
            <label><svg class="svg-icon" style="width:14px;height:14px;" viewBox="0 0 24 24"><path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"></path></svg> Filtrar por mes</label>
            <div class="month-grid" id="monthGrid">
                <button class="month-btn" data-month="">TODO</button>
                <button class="month-btn" data-month="ENERO">ENE</button>
                <button class="month-btn" data-month="FEBRERO">FEB</button>
                <button class="month-btn" data-month="MARZO">MAR</button>
                <button class="month-btn" data-month="ABRIL">ABR</button>
                <button class="month-btn" data-month="MAYO">MAY</button>
                <button class="month-btn" data-month="JUNIO">JUN</button>
                <button class="month-btn" data-month="JULIO">JUL</button>
                <button class="month-btn" data-month="AGOSTO">AGO</button>
                <button class="month-btn" data-month="SEPTIEMBRE">SEP</button>
                <button class="month-btn" data-month="OCTUBRE">OCT</button>
                <button class="month-btn" data-month="NOVIEMBRE">NOV</button>
                <button class="month-btn" data-month="DICIEMBRE">DIC</button>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section" id="tabsNavSection">
                <div class="nav-section-title">Pestañas</div>
                <div id="tabNavigation"></div>
            </div>

            <?php if ($perms['gestionar_usuarios'] || $perms['config_dropdowns']): ?>
            <div class="nav-section">
                <div class="nav-section-title">Administración</div>
                <?php if ($perms['gestionar_usuarios']): ?>
                <button class="nav-item" onclick="showAdminSection('usuarios')">
                    <span><svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span> Usuarios
                </button>
                <?php endif; ?>
                <?php if ($perms['config_dropdowns']): ?>
                <button class="nav-item" onclick="showAdminSection('dropdowns')">
                    <span><svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg></span> Dropdowns
                </button>
                <button class="nav-item" onclick="showAdminSection('campos')">
                    <span><svg class="svg-icon" viewBox="0 0 24 24"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg></span> Campos
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($perms['exportar']): ?>
            <div class="nav-section">
                <div class="nav-section-title">Herramientas</div>
                <button class="nav-item" onclick="showAdminSection('analytics')">
                    <span><svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg></span> Análisis
                </button>
                <button class="nav-item" onclick="exportCSV()">
                    <span><svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg></span> Exportar CSV
                </button>
                <button class="nav-item" onclick="openAIEnhancer()">
                    <span><svg class="svg-icon" viewBox="0 0 24 24"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path></svg></span> Potenciador con IA
                </button>
            </div>
            <?php endif; ?>
        </nav>

        <!-- User info -->
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user['nombre'], 0, 2)) ?></div>
                <div class="user-details">
                    <div class="name"><?= htmlspecialchars($user['nombre']) ?></div>
                    <div class="role"><?= htmlspecialchars($user['rol']) ?></div>
                </div>
                <button class="btn-icon btn-secondary" onclick="logout()" title="Cerrar sesión">
                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                </button>
            </div>
        </div>
    </aside>

    <!-- ── MAIN CONTENT ── -->
    <main class="main-content">

        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <button class="hamburger" onclick="toggleSidebar()"><svg class="svg-icon" viewBox="0 0 24 24" stroke="white"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></button>
                <h1 id="headerTitle">PAUTA</h1>
                <div class="view-toggle" id="viewToggle">
                    <button class="active" data-view="table" onclick="setView('table')">
                        <svg class="svg-icon" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg> Tabla
                    </button>
                    <button data-view="calendar" onclick="setView('calendar')">
                        <svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> Calendario
                    </button>
                </div>
            </div>
            <div class="header-right">
                <div class="header-search">
                    <span class="search-icon"><svg class="svg-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></span>
                    <input type="text" id="searchInput" placeholder="Buscar contenidos..." oninput="debounceSearch()">
                </div>
                <button class="notif-btn" onclick="toggleNotifications()" id="notifBtn">
                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    <span class="notif-badge" id="notifBadge"></span>
                </button>
                <?php if ($perms['crear_contenido']): ?>
                <button class="btn btn-primary btn-sm" onclick="openCreateModal()" id="btnCrear">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Nuevo
                </button>
                <?php endif; ?>
            </div>
        </header>

        <!-- Tabs bar -->
        <div class="tabs-bar" id="tabsBar"></div>

        <!-- Toolbar (filters) -->
        <div class="toolbar" id="toolbar">
            <div class="toolbar-left" id="filterChips"></div>
            <div class="toolbar-right">
                <span id="contentCount" style="font-size: 0.8rem; color: var(--text-muted);"></span>
            </div>
        </div>

        <!-- TABLE VIEW -->
        <div class="table-wrapper" id="tableView">
            <table class="data-table" id="dataTable">
                <thead id="tableHead"></thead>
                <tbody id="tableBody"></tbody>
            </table>
            <div class="empty-state" id="emptyState" style="display:none;">
                <div class="empty-icon">
                    <svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </div>
                <h3>No hay contenidos</h3>
                <p>No se encontraron contenidos para los filtros seleccionados. Intenta cambiar los filtros o crea uno nuevo.</p>
                <button class="btn btn-primary" onclick="openCreateModal()" style="margin-top: 15px;">＋ Añadir Contenido</button>
            </div>
        </div>

        <!-- CALENDAR VIEW -->
        <div id="calendarView" style="display:none;">
            <div class="calendar-grid" id="calendarGrid"></div>
            <div class="calendar-list" id="calendarList"></div>
        </div>

        <!-- ADMIN SECTIONS -->
        <div id="adminSection" style="display:none;"></div>

    </main>
</div>

<!-- ── NOTIFICATION PANEL ── -->
<div class="notif-panel" id="notifPanel">
    <div class="notif-panel-header">
        <h3><svg class="svg-icon" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg> Notificaciones</h3>
        <div>
            <button class="btn btn-sm btn-secondary" onclick="markAllRead()">Marcar todo leído</button>
            <button class="modal-close" onclick="toggleNotifications()">×</button>
        </div>
    </div>
    <div class="notif-list" id="notifList"></div>
</div>

<!-- ── CONTENT MODAL ── -->
<div class="modal-overlay" id="contentModal">
    <div class="modal" style="max-width: 820px;">
        <div class="modal-header">
            <h2 id="modalTitle">Nuevo Contenido</h2>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
        <div class="modal-footer" id="modalFooter">
            <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
            <button class="btn btn-primary" onclick="saveContent()" id="btnSave">Guardar</button>
        </div>
    </div>
</div>

<!-- ── USER MODAL ── -->
<div class="modal-overlay" id="userModal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h2 id="userModalTitle">Nuevo Usuario</h2>
            <button class="modal-close" onclick="closeUserModal()">×</button>
        </div>
        <div class="modal-body" id="userModalBody"></div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeUserModal()">Cancelar</button>
            <button class="btn btn-primary" onclick="saveUser()" id="btnSaveUser">Guardar</button>
        </div>
    </div>
</div>

<!-- ── AI ENHANCER MODAL ── -->
<div class="modal-overlay" id="aiEnhancerModal">
    <div class="modal ai-modal" style="max-width: 850px; height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header ai-modal-header">
            <div class="ai-modal-title">
                <div class="ai-icon-bg"><svg class="svg-icon" viewBox="0 0 24 24"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path></svg></div>
                <h2>Potenciador con IA</h2>
            </div>
            <button class="modal-close" onclick="closeAIEnhancer()">×</button>
        </div>
        
        <div class="modal-body ai-modal-body">
            <!-- Filter Section -->
            <div class="ai-filter-section">
                <div class="ai-filter-group">
                    <label>Semana a Procesar</label>
                    <select id="aiWeekSelect" class="form-control" onchange="generateAIExport()"></select>
                </div>
                <div class="ai-filter-group">
                    <label>Mes</label>
                    <select id="aiMonthSelect" class="form-control" onchange="onAIFilterChange()">
                        <option value="">Todos los meses</option>
                        <option>ENERO</option><option>FEBRERO</option><option>MARZO</option><option>ABRIL</option>
                        <option>MAYO</option><option>JUNIO</option><option>JULIO</option><option>AGOSTO</option>
                        <option>SEPTIEMBRE</option><option>OCTUBRE</option><option>NOVIEMBRE</option><option>DICIEMBRE</option>
                    </select>
                </div>
                <div class="ai-count-badge" id="aiContentCount"></div>
            </div>

            <!-- Modern Tabs -->
            <div class="ai-tabs-container">
                <button id="aiTabMdBtn" class="ai-tab-btn active" onclick="switchAITab('md')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    Exportar .MD
                </button>
                <button id="aiTabJsonBtn" class="ai-tab-btn" onclick="switchAITab('json')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    IA Maker (JSON)
                </button>
                <button id="aiTabImportBtn" class="ai-tab-btn" onclick="switchAITab('import')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                    Importar JSON
                </button>
            </div>

            <!-- Tab 1: Export .MD -->
            <div id="aiTabMd" class="ai-tab-content active">
                <div class="ai-info-box">
                    <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    <span>Exporta el contenido de la semana/mes seleccionado en formato Markdown para editar con IA (ChatGPT, Claude, etc).</span>
                </div>
                <textarea id="aiExportText" class="ai-textarea form-control" readonly></textarea>
                <div class="ai-actions">
                    <button class="btn btn-primary ai-action-btn" onclick="downloadAIFile()">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Descargar .MD
                    </button>
                </div>
            </div>

            <!-- Tab 2: IA Maker JSON -->
            <div id="aiTabJson" class="ai-tab-content" style="display:none;">
                <div class="ai-maker-cards">
                    <div class="ai-maker-card primary">
                        <div class="card-icon"><svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg></div>
                        <h4>¿Qué hace esto?</h4>
                        <p>Descarga un archivo <strong>esquema.json</strong> con la estructura completa de campos del sistema. Pégalo en una IA con la instrucción: <em>"Rellena este JSON con contenidos reales para la semana del [fecha] en formato array de posts"</em>. Luego importa el JSON que te devuelva en la pestaña <strong>Importar JSON</strong>.</p>
                    </div>
                    <div class="ai-maker-card secondary">
                        <div class="card-icon"><svg class="svg-icon" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg></div>
                        <h4>Campos disponibles</h4>
                        <div class="fields-grid">
                            <span>pestana</span><span>fecha</span><span>tema</span><span>idea</span>
                            <span>red_social</span><span>formato</span><span>horario</span><span>buyer</span>
                            <span>pilar</span><span>carrera</span><span>titulo_post</span><span>copy_facebook</span>
                            <span>copy_instagram</span><span>copy_tiktok</span><span>observaciones</span><span>estado</span>
                        </div>
                    </div>
                </div>
                <textarea id="aiSchemaText" class="ai-textarea form-control" readonly></textarea>
                <div class="ai-actions">
                    <button class="btn btn-secondary ai-action-btn" onclick="generateSchemaJSON()">
                        <svg class="svg-icon" viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 .49-3.39"></path></svg>
                        Regenerar
                    </button>
                    <button class="btn btn-primary ai-action-btn" onclick="downloadSchemaJSON()">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Descargar esquema.json
                    </button>
                </div>
            </div>

            <!-- Tab 3: Import JSON -->
            <div id="aiTabImport" class="ai-tab-content" style="display:none;">
                <div class="ai-info-box success">
                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <span>Pega aquí el JSON de contenidos generado por la IA (array de posts) o sube el archivo <code>.json</code>. El sistema <strong>creará</strong> los nuevos contenidos automáticamente.</span>
                </div>
                <div class="ai-file-upload">
                    <label for="aiImportJsonFile" class="file-upload-btn">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        Seleccionar Archivo .JSON
                    </label>
                    <input type="file" id="aiImportJsonFile" accept=".json" style="display:none;" onchange="handleJSONFileUpload(event)">
                    <span id="aiFileName" class="file-name">Ningún archivo seleccionado</span>
                </div>
                <textarea id="aiImportJsonText" class="ai-textarea form-control" placeholder='[{"pestana":"pauta","fecha":"2026-06-10","tema":"Mi post","red_social":"Instagram",...}]'></textarea>
                <div class="ai-actions">
                    <button class="btn btn-secondary ai-action-btn" onclick="validateImportJSON()">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        Validar JSON
                    </button>
                    <button class="btn btn-primary ai-action-btn" onclick="processJSONImport()">
                        <svg class="svg-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Importar Contenidos
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Copy toast -->
<div class="copy-toast" id="copyToast"><svg class="svg-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Texto copiado al portapapeles</div>

<!-- Pass user data to JS -->
<script>
    const APP_USER = <?= json_encode(['id' => $user['id'], 'nombre' => $user['nombre'], 'rol' => $user['rol']], JSON_UNESCAPED_UNICODE) ?>;
    const APP_PERMS = <?= json_encode($perms, JSON_UNESCAPED_UNICODE) ?>;
    const API_BASE = '../api';
</script>

<script src="../assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
