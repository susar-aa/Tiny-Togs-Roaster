// app.js - Client-side logic for Tiny Togs Shift Management System

document.addEventListener('DOMContentLoaded', () => {
    // Current Global State
    let state = {
        year: parseInt(document.getElementById('select-year').value),
        month: parseInt(document.getElementById('select-month').value),
        employees: [],
        calendarDays: [],
        leaveRequests: [],
        activeLeaveEmpId: null,
        rosterData: [],
        users: []
    };

    let liveGridCache = {}; 

    // Initialize UI
    initTabs();
    initSelectors();
    initEmployeesForm();
    initCalendarModal();
    initLeavePlanner();
    initRosterBoard();
    initSwapModal();
    initUsersForm();

    // Initial Data Load
    loadAllData();

    // -------------------------------------------------------------
    // Global Data Loading
    // -------------------------------------------------------------
    function loadAllData() {
        Promise.all([
            fetchEmployees(),
            fetchCalendar()
        ]).then(() => {
            fetchLeaveRequests();
            fetchRoster();
        });
    }

    function fetchEmployees() {
        return fetch(`api.php?action=get_employees`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    state.employees = res.data;
                    renderEmployeesTable();
                    renderLeaveEmployeeList();
                } else showToast(res.message, 'error');
            })
            .catch(err => showToast('Error loading employees: ' + err.message, 'error'));
    }

    function fetchCalendar() {
        return fetch(`api.php?action=get_calendar&year=${state.year}&month=${state.month}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    state.calendarDays = res.data;
                    renderCalendarTable();
                    renderLeaveCalendarGrid();
                } else showToast(res.message, 'error');
            })
            .catch(err => showToast('Error loading calendar: ' + err.message, 'error'));
    }

    function fetchLeaveRequests() {
        return fetch(`api.php?action=get_leave_requests&year=${state.year}&month=${state.month}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    state.leaveRequests = res.data;
                    updateLeaveEmployeeBadges();
                    renderLeaveCalendarGrid(); 
                } else showToast(res.message, 'error');
            })
            .catch(err => showToast('Error loading leave requests: ' + err.message, 'error'));
    }

    function fetchRoster() {
        return fetch(`api.php?action=get_roster&year=${state.year}&month=${state.month}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    state.rosterData = res.data;
                    renderRosterGrid();

                    // Update Generate button status
                    const btnGen = document.getElementById('btn-generate-roster');
                    if (btnGen) {
                        if (state.rosterData && state.rosterData.length > 0) {
                            btnGen.disabled = true;
                            btnGen.title = "Roster already generated. Clear it to regenerate.";
                            btnGen.style.opacity = '0.5';
                            btnGen.style.cursor = 'not-allowed';
                        } else {
                            btnGen.disabled = false;
                            btnGen.title = "";
                            btnGen.style.opacity = '1';
                            btnGen.style.cursor = 'pointer';
                        }
                    }

                    // Update Undo/Redo buttons
                    const btnUndo = document.getElementById('btn-undo');
                    const btnRedo = document.getElementById('btn-redo');
                    if (btnUndo) btnUndo.disabled = !res.can_undo;
                    if (btnRedo) btnRedo.disabled = !res.can_redo;
                } else showToast(res.message, 'error');
            })
            .catch(err => showToast('Error loading roster: ' + err.message, 'error'));
    }

    // -------------------------------------------------------------
    // Tab Controller
    // -------------------------------------------------------------
    function initTabs() {
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetTab = btn.getAttribute('data-tab');
                
                tabBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                tabContents.forEach(content => {
                    content.classList.remove('active');
                    if (content.id === targetTab) content.classList.add('active');
                });

                document.body.className = 'active-tab-' + targetTab;

                if (targetTab === 'roster-tab') fetchRoster();
                else if (targetTab === 'leave-tab') fetchLeaveRequests();
                else if (targetTab === 'calendar-tab') fetchCalendar();
                else if (targetTab === 'employees-tab') fetchEmployees();
                else if (targetTab === 'users-tab') fetchUsers();
            });
        });
    }

    function initSelectors() {
        const selectYear = document.getElementById('select-year');
        const selectMonth = document.getElementById('select-month');

        const handlePeriodChange = () => {
            state.year = parseInt(selectYear.value);
            state.month = parseInt(selectMonth.value);
            loadAllData();
        };

        selectYear.addEventListener('change', handlePeriodChange);
        selectMonth.addEventListener('change', handlePeriodChange);
    }

    // -------------------------------------------------------------
    // TAB: Employees Dashboard
    // -------------------------------------------------------------
    function initEmployeesForm() {
        const form = document.getElementById('form-employee');
        const btnCancel = document.getElementById('btn-cancel-edit');
        const formTitle = document.getElementById('employee-form-title');

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(form);

            fetch(`api.php?action=save_employee`, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showToast(res.message);
                    form.reset();
                    document.getElementById('emp-id').value = '';
                    btnCancel.style.display = 'none';
                    formTitle.textContent = 'Add Employee';
                    fetchEmployees();
                } else showToast(res.message, 'error');
            })
            .catch(err => showToast('Error saving employee: ' + err.message, 'error'));
        });

        btnCancel.addEventListener('click', () => {
            form.reset();
            document.getElementById('emp-id').value = '';
            btnCancel.style.display = 'none';
            formTitle.textContent = 'Add Employee';
        });
    }

    function renderEmployeesTable() {
        const tbody = document.querySelector('#table-employees tbody');
        const countBadge = document.getElementById('employee-count');
        tbody.innerHTML = '';
        countBadge.textContent = `${state.employees.length} Employees`;

        if (state.employees.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:2rem;">No employees registered yet.</td></tr>`;
            return;
        }

        state.employees.forEach(emp => {
            const tr = document.createElement('tr');
            
            const skillClass = emp.skill_level === 'Good' ? 'badge-success' : 'badge-normal';
            
            const genderColor = emp.gender === 'Male' ? '#e0f2fe' : '#fce7f3';
            const genderTextColor = emp.gender === 'Male' ? '#0369a1' : '#be185d';
            const genderBorder = emp.gender === 'Male' ? '#bae6fd' : '#fbcfe8';
            const genderText = emp.gender || 'Female';
            
            let roleTags = `<span class="badge" style="margin-right:0.35rem; background-color: ${genderColor}; color: ${genderTextColor}; border: 1px solid ${genderBorder};">${genderText}</span>`;
            
            if (emp.role === 'Cashier') roleTags += `<span class="badge badge-warning" style="margin-right:0.35rem;">Cashier</span>`;
            else if (emp.role === 'Anchor') roleTags += `<span class="badge badge-info" style="margin-right:0.35rem;">Anchor (F)</span>`;
            else if (emp.role === 'Manager') roleTags += `<span class="badge" style="margin-right:0.35rem; background-color: #f3e8ff; color: #581c87; border: 1px solid #d8b4fe;">Manager</span>`;
            else if (emp.role === 'Assistant_Manager') roleTags += `<span class="badge" style="margin-right:0.35rem; background-color: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe;">Assistant Manager</span>`;
            else roleTags += `<span class="badge badge-normal" style="color:#64748b; background-color:#f1f5f9; border: 1px solid #e2e8f0;">Rotating Staff</span>`;

            tr.innerHTML = `
                <td style="font-weight: 600; color: #1e293b;">${escapeHtml(emp.name)}</td>
                <td><span class="badge ${skillClass}">${emp.skill_level}</span></td>
                <td>${roleTags}</td>
                <td style="text-align: right;">
                    <div style="display: inline-flex; gap: 0.25rem; justify-content: flex-end; align-items: center; white-space: nowrap;">
                        <button class="btn btn-secondary edit-btn" style="padding: 0.35rem 0.6rem; font-size: 0.75rem;">Edit</button>
                        <button class="btn btn-danger delete-btn" style="padding: 0.35rem 0.6rem; font-size: 0.75rem;">Delete</button>
                    </div>
                </td>
            `;

            tr.querySelector('.edit-btn').addEventListener('click', () => {
                document.getElementById('emp-id').value = emp.emp_id;
                document.getElementById('emp-name').value = emp.name;
                document.getElementById('emp-skill').value = emp.skill_level;
                document.getElementById('emp-gender').value = emp.gender || 'Female';
                document.getElementById('emp-role').value = emp.role;

                document.getElementById('btn-cancel-edit').style.display = 'inline-flex';
                document.getElementById('employee-form-title').textContent = 'Edit Employee';
                document.getElementById('emp-name').focus();
            });

            tr.querySelector('.delete-btn').addEventListener('click', () => {
                if (confirm('Are you sure you want to delete this employee? This will clear all associated leaves and schedules.')) {
                    const fd = new FormData();
                    fd.append('emp_id', emp.emp_id);
                    
                    fetch(`api.php?action=delete_employee`, { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) { showToast(res.message); fetchEmployees(); } 
                        else showToast(res.message, 'error');
                    })
                    .catch(err => showToast('Error deleting employee: ' + err.message, 'error'));
                }
            });

            tbody.appendChild(tr);
        });
    }

    // -------------------------------------------------------------
    // TAB: Calendar settings
    // -------------------------------------------------------------
    function initCalendarModal() {
        const modal = document.getElementById('calendar-edit-modal');
        const btnClose = document.getElementById('btn-close-calendar-modal');
        const btnCancel = document.getElementById('btn-cancel-calendar-modal');
        const form = document.getElementById('form-edit-calendar');

        const closeModal = () => modal.style.display = 'none';
        btnClose.addEventListener('click', closeModal);
        btnCancel.addEventListener('click', closeModal);

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const fd = new FormData();
            fd.append('date', document.getElementById('cal-date-input').value);
            fd.append('day_type', document.getElementById('cal-day-type').value);
            fd.append('description', document.getElementById('cal-desc').value);

            fetch(`api.php?action=update_day_type`, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) { showToast(res.message); closeModal(); fetchCalendar(); } 
                else showToast(res.message, 'error');
            })
            .catch(err => showToast('Error updating calendar: ' + err.message, 'error'));
        });
    }

    function renderCalendarTable() {
        const grid = document.getElementById('calendar-grid-admin');
        if (!grid) return;
        grid.innerHTML = '';

        const weekdaysShort = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        weekdaysShort.forEach(name => {
            const head = document.createElement('div');
            head.className = 'calendar-picker-header';
            head.textContent = name;
            grid.appendChild(head);
        });

        if (state.calendarDays.length === 0) return;

        const firstDate = new Date(state.calendarDays[0].date);
        let dayOfWeek = firstDate.getDay(); 
        dayOfWeek = dayOfWeek === 0 ? 7 : dayOfWeek;
        
        for (let i = 1; i < dayOfWeek; i++) {
            const pad = document.createElement('div');
            pad.className = 'calendar-day-cell disabled';
            pad.style.backgroundColor = '#f8fafc';
            pad.style.border = '1px dashed #e2e8f0';
            grid.appendChild(pad);
        }

        state.calendarDays.forEach(day => {
            const cell = document.createElement('div');
            cell.className = 'calendar-day-cell';
            cell.style.cursor = 'pointer';
            cell.style.transition = 'all 0.2s ease';
            
            cell.addEventListener('mouseenter', () => cell.style.transform = 'translateY(-2px)');
            cell.addEventListener('mouseleave', () => cell.style.transform = 'translateY(0)');
            
            const dateObj = new Date(day.date);
            const dateNum = dateObj.getDate();
            const dayOfWeekShort = dateObj.toLocaleDateString('en-US', { weekday: 'short' }); 
            const label = `${dayOfWeekShort} ${dateNum}`;

            let badgeClass = 'badge-normal';
            if (day.day_type === 'Weekend') {
                badgeClass = 'badge-success';
                cell.style.backgroundColor = '#fff1f2'; 
                cell.style.borderColor = '#fecdd3';
            } else if (day.day_type === 'Poya') {
                badgeClass = 'badge-warning';
                cell.style.backgroundColor = '#fffbeb'; 
                cell.style.borderColor = '#fde68a';
            } else if (day.day_type === 'Public Holiday') {
                badgeClass = 'badge-info';
                cell.style.backgroundColor = '#eff6ff'; 
                cell.style.borderColor = '#bfdbfe';
            } else {
                cell.style.backgroundColor = '#ffffff';
            }
            
            const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const longWeekdayStr = dateObj.toLocaleDateString('en-US', { weekday: 'long' });

            let descriptionHtml = '';
            if (day.description && day.description !== day.day_type && day.description !== dayOfWeekShort && day.description !== longWeekdayStr) {
                descriptionHtml = `<span style="font-size:0.65rem; color:#475569; font-weight:500; font-style:italic; line-height:1.1;">${escapeHtml(day.description)}</span>`;
            }

            cell.innerHTML = `
                <span class="day-number" style="font-size:0.8rem; font-weight:700; color:#1e293b;">${label}</span>
                <div class="day-badge-container" style="margin-top:0.6rem; display:flex; flex-direction:column; gap:0.3rem;">
                    <span class="badge ${badgeClass}" style="font-size:0.65rem;padding:0.2rem 0.4rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">${day.day_type}</span>
                    ${descriptionHtml}
                </div>
            `;

            cell.addEventListener('click', () => {
                document.getElementById('cal-date-input').value = day.date;
                document.getElementById('cal-date-display').value = formattedDate;
                document.getElementById('cal-day-type').value = day.day_type;
                document.getElementById('cal-desc').value = day.description || '';
                document.getElementById('calendar-edit-modal').style.display = 'flex';
            });

            grid.appendChild(cell);
        });
    }

    // -------------------------------------------------------------
    // TAB: Leave Planner (Pre-Generation)
    // -------------------------------------------------------------
    function initLeavePlanner() {}

    function renderLeaveEmployeeList() {
        const listContainer = document.getElementById('leave-employee-list');
        listContainer.innerHTML = '';

        if (state.employees.length === 0) {
            listContainer.innerHTML = `<p style="font-size:0.85rem;color:var(--text-muted);text-align:center;padding:2rem;">No employees registered.</p>`;
            return;
        }

        state.employees.forEach(emp => {
            const card = document.createElement('div');
            card.className = `select-employee-card ${state.activeLeaveEmpId === emp.emp_id ? 'active' : ''}`;
            card.setAttribute('data-id', emp.emp_id);
            
            const count = getEmployeeLeaveCount(emp.emp_id);
            const countClass = (4 - count) <= 0 ? 'badge-success' : 'badge-normal';
            let formattedRole = emp.role ? emp.role.replace('_', ' ') : 'Rotating';

            card.innerHTML = `
                <div>
                    <strong style="font-size:0.95rem; color:#1e293b; font-weight: 600;">${escapeHtml(emp.name)}</strong>
                    <div style="font-size:0.75rem; color:#64748b; margin-top:0.2rem; font-weight: 500;">
                        ${formattedRole}
                    </div>
                </div>
                <span class="employee-leaves-badge ${countClass}" id="leave-badge-${emp.emp_id}" style="box-shadow: 0 1px 2px rgba(0,0,0,0.05);">Available: ${4 - count}</span>
            `;

            card.addEventListener('click', () => {
                document.querySelectorAll('.select-employee-card').forEach(c => c.classList.remove('active'));
                card.classList.add('active');
                state.activeLeaveEmpId = emp.emp_id;
                document.getElementById('leave-active-employee-name').textContent = emp.name;
                renderLeaveCalendarGrid();
            });

            listContainer.appendChild(card);
        });

        if (state.activeLeaveEmpId === null && state.employees.length > 0) {
            const firstCard = listContainer.querySelector('.select-employee-card');
            if (firstCard) firstCard.click();
        }
    }

    function getEmployeeLeaveCount(empId) {
        return state.leaveRequests.filter(req => req.emp_id == empId).length;
    }

    function updateLeaveEmployeeBadges() {
        state.employees.forEach(emp => {
            const badge = document.getElementById(`leave-badge-${emp.emp_id}`);
            if (badge) {
                const count = getEmployeeLeaveCount(emp.emp_id);
                badge.textContent = `Available: ${4 - count}`;
                badge.className = `employee-leaves-badge ${(4 - count) <= 0 ? 'badge-success' : 'badge-normal'}`;
            }
        });
    }

    function renderLeaveCalendarGrid() {
        const grid = document.getElementById('leave-calendar-grid');
        grid.innerHTML = '';

        const weekdaysShort = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        weekdaysShort.forEach(name => {
            const head = document.createElement('div');
            head.className = 'calendar-picker-header';
            head.textContent = name;
            grid.appendChild(head);
        });

        if (state.calendarDays.length === 0) return;

        const firstDate = new Date(state.calendarDays[0].date);
        let dayOfWeek = firstDate.getDay(); 
        dayOfWeek = dayOfWeek === 0 ? 7 : dayOfWeek;
        
        for (let i = 1; i < dayOfWeek; i++) {
            const pad = document.createElement('div');
            pad.className = 'calendar-day-cell disabled';
            pad.style.backgroundColor = '#f8fafc';
            pad.style.border = '1px dashed #e2e8f0';
            grid.appendChild(pad);
        }

        state.calendarDays.forEach(day => {
            const cell = document.createElement('div');
            cell.className = 'calendar-day-cell';
            cell.style.transition = 'all 0.2s ease';
            
            cell.addEventListener('mouseenter', () => cell.style.transform = 'translateY(-2px)');
            cell.addEventListener('mouseleave', () => cell.style.transform = 'translateY(0)');
            
            const dateObj = new Date(day.date);
            const dateNum = dateObj.getDate();
            const dayOfWeekShort = dateObj.toLocaleDateString('en-US', { weekday: 'short' }); 
            const label = `${dayOfWeekShort} ${dateNum}`;
            
            const hasLeave = state.leaveRequests.some(req => req.emp_id == state.activeLeaveEmpId && req.requested_date === day.date);
            const leaveReq = state.leaveRequests.find(req => req.emp_id == state.activeLeaveEmpId && req.requested_date === day.date);

            if (hasLeave) {
                cell.classList.add('selected');
                cell.style.backgroundColor = '#ecfdf5';
                cell.style.borderColor = '#6ee7b7';
                cell.style.boxShadow = '0 4px 6px -1px rgba(16, 185, 129, 0.1)';
            } else cell.style.backgroundColor = '#ffffff';

            let dayTypeBadgeHtml = '';
            if (day.day_type !== 'Weekday') {
                let badgeStyleClass = 'badge-normal';
                if (day.day_type === 'Poya') badgeStyleClass = 'badge-warning';
                if (day.day_type === 'Public Holiday') badgeStyleClass = 'badge-info';
                dayTypeBadgeHtml = `<span class="badge ${badgeStyleClass}" style="font-size:0.6rem;padding:0.15rem 0.25rem;">${day.day_type}</span>`;
            }

            let approvedBadgeHtml = '';
            if (hasLeave) approvedBadgeHtml = `<span class="badge badge-success" style="font-size:0.65rem;padding:0.2rem 0.4rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">Approved Off</span>`;

            cell.innerHTML = `
                <span class="day-number" style="font-size:0.8rem; font-weight:700; color:#1e293b;">${label}</span>
                <div class="day-badge-container" style="margin-top:0.6rem; display:flex; flex-direction:column; gap:0.3rem;">
                    ${approvedBadgeHtml}
                    ${dayTypeBadgeHtml}
                </div>
            `;

            cell.addEventListener('click', () => {
                if (!state.activeLeaveEmpId) { showToast('Please select an employee first.', 'error'); return; }

                if (hasLeave) {
                    const fd = new FormData(); fd.append('request_id', leaveReq.request_id);
                    fetch(`api.php?action=delete_leave_request`, { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) { showToast(res.message); fetchLeaveRequests(); } 
                        else showToast(res.message, 'error');
                    })
                    .catch(err => showToast('Error deleting leave: ' + err.message, 'error'));
                } else {
                    const currentCount = getEmployeeLeaveCount(state.activeLeaveEmpId);
                    if (currentCount >= 4) { showToast('Leave quota reached! Limit is exactly 4 off days per month.', 'error'); return; }

                    const fd = new FormData();
                    fd.append('emp_id', state.activeLeaveEmpId); fd.append('date', day.date); fd.append('status', 'Approved');
                    fetch(`api.php?action=save_leave_request`, { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) { showToast(res.message); fetchLeaveRequests(); } 
                        else showToast(res.message, 'error');
                    })
                    .catch(err => showToast('Error saving leave: ' + err.message, 'error'));
                }
            });

            grid.appendChild(cell);
        });
    }

    // -------------------------------------------------------------
    // Live Rendering Algorithm Helper (Skeleton)
    // -------------------------------------------------------------
    function renderLiveGridSkeleton() {
        const container = document.getElementById('roster-container');
        container.innerHTML = '';
        liveGridCache = {};

        const viewport = document.createElement('div');
        viewport.className = 'roster-viewport';
        const grid = document.createElement('div');
        grid.className = 'roster-grid-table';
        grid.style.gridTemplateColumns = `var(--emp-col-width, 160px) repeat(${state.calendarDays.length}, minmax(32px, 1fr))`;
        grid.style.setProperty('--days-count', state.calendarDays.length);

        const emptyHead = document.createElement('div');
        emptyHead.className = 'roster-cell roster-header-cell roster-row-label';
        emptyHead.textContent = 'Employee';
        grid.appendChild(emptyHead);

        state.calendarDays.forEach(day => {
            const cell = document.createElement('div');
            const dateObj = new Date(day.date);
            cell.className = `roster-cell roster-header-cell ${day.day_type === 'Weekend' ? 'hl-weekend' : 'hl-weekday'}`;
            cell.textContent = `${dateObj.toLocaleDateString('en-US', { weekday: 'short' })} ${dateObj.getDate()}`;
            grid.appendChild(cell);
        });

        state.employees.forEach(emp => {
            liveGridCache[emp.emp_id] = {};
            
            const labelCell = document.createElement('div');
            labelCell.className = 'roster-cell roster-row-label';
            let formattedRole = emp.role ? emp.role.replace('_', ' ') : 'Rotating';
            
            labelCell.innerHTML = `
                <div class="roster-emp-name" title="${escapeHtml(emp.name)}">
                    ${escapeHtml(emp.name)}
                </div>
                <div class="roster-emp-role">
                    ${formattedRole}
                </div>
            `;
            grid.appendChild(labelCell);

            state.calendarDays.forEach(day => {
                const cell = document.createElement('div');
                cell.className = 'roster-cell cell-empty'; 
                cell.dataset.shift = ''; 
                grid.appendChild(cell);
                liveGridCache[emp.emp_id][day.date] = cell; 
            });
        });

        viewport.appendChild(grid);
        container.appendChild(viewport);
    }

    // -------------------------------------------------------------
    // Export Functionality (With Dynamic Print View and Legend)
    // -------------------------------------------------------------
    function triggerExport(type) {
        if (state.rosterData.length === 0) { 
            showToast('No roster generated to export!', 'error'); 
            return; 
        }

        const btn = type === 'image' ? document.getElementById('btn-export-image') : document.getElementById('btn-export-pdf');
        const originalText = btn.textContent;
        btn.textContent = 'Exporting...';
        btn.disabled = true;

        // Create a perfect, invisible container designed specifically for the export snapshot
        const exportWrapper = document.createElement('div');
        exportWrapper.classList.add('export-mode');
        exportWrapper.style.position = 'absolute';
        exportWrapper.style.left = '-9999px';
        exportWrapper.style.top = '0';
        exportWrapper.style.padding = '30px';
        exportWrapper.style.backgroundColor = '#ffffff';
        
        // Calculate the exact width we need to fit the entire table + padding/margins
        const daysCount = state.calendarDays.length;
        const tableWidth = 160 + (daysCount * 42); // 160px for employee column + 42px per day
        const totalExportWidth = tableWidth + 60; // 30px padding on left/right
        
        exportWrapper.style.width = `${totalExportWidth}px`;
        
        // 1. Add Professional Document Title
        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        const title = document.createElement('h2');
        title.textContent = `Tiny Togs Shift Timetable - ${monthNames[state.month - 1]} ${state.year}`;
        title.style.fontFamily = "'Inter', sans-serif";
        title.style.textAlign = 'center';
        title.style.color = '#1e293b';
        title.style.marginBottom = '25px';
        title.style.fontSize = '24px';
        exportWrapper.appendChild(title);

        // 2. Clone the existing fully-rendered Roster Grid
        const gridClone = document.getElementById('roster-container').cloneNode(true);
        exportWrapper.appendChild(gridClone);

        // 3. Append the Interactive Legend
        const legend = document.createElement('div');
        legend.innerHTML = `
            <div style="margin-top: 30px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: 'Inter', sans-serif; display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; background: #f8fafc; color: #334155; font-size: 0.85rem;">
                <div style="display:flex; align-items:center; gap:8px;"><span style="width:18px;height:18px;background:#fef08a;border:1px solid #fde047;border-radius:4px;"></span> <b>F:</b> Full Day (8:30am - 10:00pm)</div>
                <div style="display:flex; align-items:center; gap:8px;"><span style="width:18px;height:18px;background:#bae6fd;border:1px solid #7dd3fc;border-radius:4px;"></span> <b>M:</b> Morning (8:30am - 5:30pm)</div>
                <div style="display:flex; align-items:center; gap:8px;"><span style="width:18px;height:18px;background:#fca5a5;border:1px solid #f87171;border-radius:4px;"></span> <b>N:</b> Night (1:00pm - 10:00pm)</div>
                <div style="display:flex; align-items:center; gap:8px;"><span style="width:18px;height:18px;background:#a7f3d0;border:1px solid #6ee7b7;border-radius:4px;"></span> <b>Mw:</b> Morning Wknd (8:30am - 8:30pm)</div>
                <div style="display:flex; align-items:center; gap:8px;"><span style="width:18px;height:18px;background:#fbcfe8;border:1px solid #f9a8d4;border-radius:4px;"></span> <b>Nw:</b> Night Wknd (11:00am - 10:00pm)</div>
                <div style="display:flex; align-items:center; gap:8px;"><span style="width:18px;height:18px;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:4px;"></span> <b>No:</b> Cashier (8:30am - 7:30pm)</div>
                <div style="display:flex; align-items:center; gap:8px;"><span style="width:18px;height:18px;background:#fda4af;border:1px solid #fb7185;border-radius:4px;"></span> <b>Nh:</b> Cashier Night (10:30am - 9:30pm)</div>
                <div style="display:flex; align-items:center; gap:8px;"><span style="width:18px;height:18px;background:#bbf7d0;border:1px solid #86efac;border-radius:4px;"></span> <b>Off:</b> Off Day</div>
            </div>
        `;
        exportWrapper.appendChild(legend);

        // Mount to body temporarily to render
        document.body.appendChild(exportWrapper);

        html2canvas(exportWrapper, {
            scale: 2,
            backgroundColor: '#ffffff',
            width: totalExportWidth,
            windowWidth: totalExportWidth
        }).then(canvas => {
            if (type === 'image') {
                const link = document.createElement('a');
                link.download = `TinyTogs_Roster_${state.year}_${state.month}.png`;
                link.href = canvas.toDataURL('image/png');
                link.click();
                showToast('Image exported successfully!');
            } else if (type === 'pdf') {
                const imgData = canvas.toDataURL('image/png');
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('landscape', 'mm', 'a4');
                
                const pdfWidth = pdf.internal.pageSize.getWidth();
                const pdfHeight = pdf.internal.pageSize.getHeight();
                
                const margin = 10;
                const printableWidth = pdfWidth - (margin * 2);
                const printableHeight = pdfHeight - (margin * 2);
                
                const canvasAspect = canvas.width / canvas.height;
                const printableAspect = printableWidth / printableHeight;
                
                let renderWidth, renderHeight;
                if (canvasAspect > printableAspect) {
                    renderWidth = printableWidth;
                    renderHeight = printableWidth / canvasAspect;
                } else {
                    renderHeight = printableHeight;
                    renderWidth = printableHeight * canvasAspect;
                }
                
                const x = (pdfWidth - renderWidth) / 2;
                const y = (pdfHeight - renderHeight) / 2;
                
                pdf.addImage(imgData, 'PNG', x, y, renderWidth, renderHeight);
                pdf.save(`TinyTogs_Roster_${state.year}_${state.month}.pdf`);
                showToast('PDF exported successfully!');
            }

            // Cleanup
            document.body.removeChild(exportWrapper);
            btn.textContent = originalText;
            btn.disabled = false;
        }).catch(err => {
            document.body.removeChild(exportWrapper);
            btn.textContent = originalText;
            btn.disabled = false;
            showToast('Failed to export.', 'error');
            console.error(err);
        });
    }

    // -------------------------------------------------------------
    // TAB: Roster Board (Init)
    // -------------------------------------------------------------
    function initRosterBoard() {
        const btnGen = document.getElementById('btn-generate-roster');
        const btnClear = document.getElementById('btn-clear-roster');
        const btnExportImage = document.getElementById('btn-export-image');
        const btnExportPdf = document.getElementById('btn-export-pdf');
        const btnUndo = document.getElementById('btn-undo');
        const btnRedo = document.getElementById('btn-redo');
 
        // Hook up the new dynamic export functions
        btnExportImage.addEventListener('click', () => triggerExport('image'));
        btnExportPdf.addEventListener('click', () => triggerExport('pdf'));

        if (btnUndo) {
            btnUndo.addEventListener('click', () => {
                btnUndo.disabled = true;
                const fd = new FormData();
                fd.append('year', state.year);
                fd.append('month', state.month);
                fetch('api.php?action=undo', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        showToast(res.message, 'success');
                        fetchRoster();
                    } else {
                        showToast(res.message, 'error');
                        btnUndo.disabled = false;
                    }
                })
                .catch(err => {
                    showToast('Error executing Undo: ' + err.message, 'error');
                    btnUndo.disabled = false;
                });
            });
        }
 
        if (btnRedo) {
            btnRedo.addEventListener('click', () => {
                btnRedo.disabled = true;
                const fd = new FormData();
                fd.append('year', state.year);
                fd.append('month', state.month);
                fetch('api.php?action=redo', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        showToast(res.message, 'success');
                        fetchRoster();
                    } else {
                        showToast(res.message, 'error');
                        btnRedo.disabled = false;
                    }
                })
                .catch(err => {
                    showToast('Error executing Redo: ' + err.message, 'error');
                    btnRedo.disabled = false;
                });
            });
        }

        btnGen.addEventListener('click', () => {
            btnGen.textContent = 'Generating...';
            btnGen.disabled = true;

            const progContainer = document.getElementById('roster-progress-container');
            const progText = document.getElementById('progress-text');
            const progPercent = document.getElementById('progress-percent');
            const progFill = document.getElementById('progress-bar-fill');
            
            progContainer.style.display = 'block';
            progContainer.style.opacity = '0';
            setTimeout(() => { progContainer.style.opacity = '1'; }, 10);
            
            progText.textContent = 'Waking up CSP solver...';
            progText.style.color = 'var(--primary-color)';
            progFill.style.backgroundColor = 'var(--primary-color)';
            progPercent.textContent = '0%';
            progFill.style.width = '0%';

            renderLiveGridSkeleton();

            let pollInterval = setInterval(() => {
                fetch('progress.json?t=' + Date.now())
                    .then(async r => {
                        let raw = await r.text();
                        const jsonStart = raw.indexOf('{');
                        const jsonEnd = raw.lastIndexOf('}');
                        if (jsonStart !== -1 && jsonEnd !== -1) return JSON.parse(raw.substring(jsonStart, jsonEnd + 1));
                        throw new Error("Invalid format");
                    })
                    .then(data => {
                        progText.textContent = data.message;
                        progPercent.textContent = data.percent + '%';
                        progFill.style.width = data.percent + '%';
                        
                        if (data.roster) {
                            let delayCounter = 0; 
                            for (const empId in data.roster) {
                                for (const date in data.roster[empId]) {
                                    const shiftCode = data.roster[empId][date];
                                    const cell = liveGridCache[empId]?.[date];
                                    
                                    if (cell) {
                                        const currentShift = cell.dataset.shift || null;
                                        if (shiftCode !== currentShift) {
                                            if (shiftCode === null || shiftCode === '') {
                                                cell.className = 'roster-cell cell-empty';
                                                cell.textContent = '';
                                                cell.dataset.shift = '';
                                                cell.style.removeProperty('--pop-delay');
                                            } else {
                                                cell.className = `roster-cell shift-${shiftCode} cell-pop`;
                                                cell.textContent = shiftCode;
                                                cell.dataset.shift = shiftCode;
                                                cell.style.setProperty('--pop-delay', `${delayCounter * 0.015}s`);
                                                delayCounter++;
                                                void cell.offsetWidth;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        if (data.status === 'error') {
                            progText.style.color = 'var(--danger-color)';
                            progFill.style.backgroundColor = 'var(--danger-color)';
                        }
                    }).catch(() => { });
            }, 500);

            const fd = new FormData();
            fd.append('year', state.year);
            fd.append('month', state.month);

            fetch(`generate_roster.php`, { method: 'POST', body: fd })
            .then(async res => {
                let raw = await res.text();
                const jsonStart = raw.indexOf('{');
                const jsonEnd = raw.lastIndexOf('}');
                if (jsonStart !== -1 && jsonEnd !== -1) raw = raw.substring(jsonStart, jsonEnd + 1);
                
                try { return JSON.parse(raw); } 
                catch (e) { throw new Error("Server returned an invalid response. See console for details."); }
            })
            .then(res => {
                clearInterval(pollInterval);
                btnGen.textContent = 'Generate Timetable';
                btnGen.disabled = false;

                if (res.debug_log && res.debug_log.length > 0) {
                    console.groupCollapsed("CSP Solver Debug Logs");
                    res.debug_log.forEach(log => console.log(log));
                    console.groupEnd();
                }

                if (res.success) {
                    progPercent.textContent = '100%';
                    progFill.style.width = '100%';
                    progText.textContent = res.message;
                    progText.style.color = 'var(--success-color)';
                    progFill.style.backgroundColor = 'var(--success-color)';
                    
                    showToast("Timetable generated successfully!");
                    fetchRoster(); 
                    
                    setTimeout(() => { 
                        progContainer.style.opacity = '0';
                        setTimeout(() => { progContainer.style.display = 'none'; }, 300);
                    }, 4000);
                } else {
                    progText.textContent = "Failed: " + res.message;
                    progText.style.color = 'var(--danger-color)';
                    progFill.style.backgroundColor = 'var(--danger-color)';
                    showToast('Generation failed. Check console for details.', 'error');
                }
            })
            .catch(err => {
                clearInterval(pollInterval);
                btnGen.textContent = 'Generate Timetable';
                btnGen.disabled = false;
                progText.textContent = 'Fatal Error: ' + err.message;
                progText.style.color = 'var(--danger-color)';
                progFill.style.backgroundColor = 'var(--danger-color)';
                showToast('Error generating roster. Check console.', 'error');
            });
        });

        btnClear.addEventListener('click', () => {
            if (confirm('Are you sure you want to clear this month\'s roster? All generated shifts and post-generation swaps will be deleted.')) {
                const fd = new FormData();
                fd.append('year', state.year);
                fd.append('month', state.month);

                fetch(`api.php?action=clear_roster`, { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.success) { showToast("Roster cleared successfully."); fetchRoster(); } 
                    else showToast(res.message, 'error');
                })
                .catch(err => { showToast('Error clearing roster: ' + err.message, 'error'); });
            }
        });
    }

    // Direct Drag-and-Drop execution call
    function executeDirectSwap(emp1_id, emp2_id, date, forceVal = 0) {
        const fd = new FormData();
        fd.append('emp1_id', emp1_id);
        fd.append('emp2_id', emp2_id);
        fd.append('date', date);
        fd.append('force', forceVal);

        fetch(`api.php?action=direct_swap`, { method: 'POST', body: fd })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                showToast(res.message);
                fetchRoster(); 
            } else if (res.is_warning) {
                if (confirm(res.message + "\n\nDo you want to proceed and force this swap as an emergency override?")) {
                    executeDirectSwap(emp1_id, emp2_id, date, 1);
                }
            } else {
                showToast(res.message, 'error');
            }
        })
        .catch(err => showToast('Error executing swap: ' + err.message, 'error'));
    }

    function renderRosterGrid() {
        const container = document.getElementById('roster-container');
        container.innerHTML = '';

        if (state.rosterData.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; padding: 4rem 2rem; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 0.5rem;">
                    <svg style="width: 4rem; height: 4rem; color: #94a3b8; margin: 0 auto 1rem auto;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <p style="color: #475569; font-weight: 500; font-size: 1.1rem;">No roster generated yet for the selected month.</p>
                    <p style="color: #64748b; font-size: 0.9rem; margin-top: 0.5rem;">Click <strong>Generate Timetable</strong> to build one.</p>
                </div>
            `;
            return;
        }

        const viewport = document.createElement('div');
        viewport.className = 'roster-viewport';

        const grid = document.createElement('div');
        grid.className = 'roster-grid-table';
        
        const daysCount = state.calendarDays.length;
        grid.style.gridTemplateColumns = `var(--emp-col-width, 160px) repeat(${daysCount}, minmax(32px, 1fr))`;
        grid.style.setProperty('--days-count', daysCount);

        const emptyHead = document.createElement('div');
        emptyHead.className = 'roster-cell roster-header-cell roster-row-label';
        emptyHead.textContent = 'Employee';
        grid.appendChild(emptyHead);

        state.calendarDays.forEach((day) => {
            const cell = document.createElement('div');
            const dateObj = new Date(day.date);
            const dateNum = dateObj.getDate();
            const dayOfWeekShort = dateObj.toLocaleDateString('en-US', { weekday: 'short' });
            const label = `${dayOfWeekShort} ${dateNum}`;
            
            let hlClass = 'hl-weekday';
            if (day.day_type === 'Weekend') hlClass = 'hl-weekend';
            if (day.day_type === 'Poya') hlClass = 'hl-poya';
            if (day.day_type === 'Public Holiday') hlClass = 'hl-public';

            cell.className = `roster-cell roster-header-cell ${hlClass}`;
            cell.textContent = label;
            cell.title = `${day.description || label} (${day.day_type})`;

            grid.appendChild(cell);
        });

        const rosterMap = {};
        state.rosterData.forEach(row => {
            if (!rosterMap[row.emp_id]) rosterMap[row.emp_id] = {};
            rosterMap[row.emp_id][row.date] = row;
        });

        state.employees.forEach(emp => {
            const labelCell = document.createElement('div');
            labelCell.className = 'roster-cell roster-row-label';
            
            let formattedRole = emp.role ? emp.role.replace('_', ' ') : 'Rotating';
            
            let offDaysCount = 0;
            state.calendarDays.forEach(day => {
                const assignment = rosterMap[emp.emp_id]?.[day.date];
                const shiftCode = assignment ? assignment.shift_code : 'Off';
                if (shiftCode === 'Off') offDaysCount++;
            });
            const availableLeaves = Math.max(0, 4 - offDaysCount);
            
            const badgeBg = availableLeaves === 0 ? '#fef2f2' : '#ecfdf5';
            const badgeColor = availableLeaves === 0 ? '#b91c1c' : '#047857';
            const badgeBorder = availableLeaves === 0 ? '#fecaca' : '#a7f3d0';
            
            labelCell.innerHTML = `
                <div class="roster-emp-name" title="${escapeHtml(emp.name)}" style="display: flex; align-items: center; justify-content: space-between; gap: 0.25rem; width: 100%;">
                    <span>${escapeHtml(emp.name)}</span>
                    <span class="badge" style="font-size:0.6rem; padding:0.1rem 0.25rem; background-color:${badgeBg}; color:${badgeColor}; border: 1px solid ${badgeBorder}; font-weight: 500; border-radius: 4px; white-space: nowrap;">Avail: ${availableLeaves}</span>
                </div>
                <div class="roster-emp-role">
                    ${formattedRole}
                </div>
            `;
            grid.appendChild(labelCell);

            state.calendarDays.forEach(day => {
                const cell = document.createElement('div');
                const assignment = rosterMap[emp.emp_id]?.[day.date];
                const shiftCode = assignment ? assignment.shift_code : 'Off';
                const isSwapped = assignment ? assignment.is_emergency_swap == 1 : false;

                cell.className = `roster-cell shift-${shiftCode}`;
                cell.textContent = shiftCode;
                
                if (isSwapped) {
                    cell.classList.add('roster-cell-swapped');
                    cell.title = `Emergency Swap Active! Click to manage.`;
                } else {
                    cell.title = `${emp.name} - ${day.date} [Shift ${shiftCode}]`;
                }

                // DRAG AND DROP BINDINGS
                cell.setAttribute('draggable', 'true');
                cell.style.cursor = 'pointer';

                cell.addEventListener('dragstart', (e) => {
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('application/json', JSON.stringify({
                        empId: emp.emp_id,
                        date: day.date,
                        shiftCode: shiftCode
                    }));
                    setTimeout(() => cell.classList.add('dragging'), 0);
                });

                cell.addEventListener('dragend', () => cell.classList.remove('dragging'));

                cell.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    if (!cell.classList.contains('dragging')) cell.classList.add('drag-over');
                });

                cell.addEventListener('dragleave', () => cell.classList.remove('drag-over'));

                cell.addEventListener('drop', (e) => {
                    e.preventDefault();
                    cell.classList.remove('drag-over');
                    
                    try {
                        const dataStr = e.dataTransfer.getData('application/json');
                        if (!dataStr) return;
                        const sourceData = JSON.parse(dataStr);
                        
                        if (sourceData.date !== day.date) {
                            showToast('You can only swap shifts within the exact same day!', 'error');
                            return;
                        }
                        if (sourceData.empId === emp.emp_id) return;
                        
                        if (confirm(`Execute Drag & Drop Swap between these two employees on ${day.date}?`)) {
                            executeDirectSwap(sourceData.empId, emp.emp_id, day.date);
                        }
                    } catch (err) {}
                });

                // Fallback Modal click binding for all shifts
                cell.addEventListener('click', () => {
                    openSwapModal(emp, day.date, shiftCode);
                });

                grid.appendChild(cell);
            });
        });

        viewport.appendChild(grid);
        container.appendChild(viewport);
    }

    // -------------------------------------------------------------
    // Post-Generation: Emergency Swap Dialog/Modal Logic
    // -------------------------------------------------------------
    function initSwapModal() {
        const modal = document.getElementById('swap-modal');
        const btnClose = document.getElementById('btn-close-modal');
        const btnCancel = document.getElementById('btn-cancel-swap');
        const form = document.getElementById('form-emergency-swap');
        const actionType = document.getElementById('swap-action-type');
        const swapGroup = document.getElementById('swap-action-swap-group');
        const changeGroup = document.getElementById('swap-action-change-group');

        const closeModal = () => { modal.style.display = 'none'; };

        btnClose.addEventListener('click', closeModal);
        btnCancel.addEventListener('click', closeModal);

        actionType.addEventListener('change', () => {
            const warningBox = document.getElementById('swap-validation-warning');
            warningBox.style.display = 'none';
            if (actionType.value === 'swap') {
                swapGroup.style.display = 'block';
                changeGroup.style.display = 'none';
            } else {
                swapGroup.style.display = 'none';
                changeGroup.style.display = 'block';
            }
        });

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const empId = document.getElementById('swap-emp-id').value;
            const date = document.getElementById('swap-date').value;

            if (actionType.value === 'swap') {
                const replacementId = document.getElementById('select-replacement').value;
                if (!replacementId) { showToast('Please select a replacement employee.', 'error'); return; }

                const executeSwapFetch = (forceVal = 0) => {
                    const fd = new FormData();
                    fd.append('emp_id', empId); fd.append('replacement_id', replacementId);
                    fd.append('date', date); fd.append('force', forceVal);

                    fetch(`api.php?action=execute_swap`, { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) { showToast(res.message); closeModal(); fetchRoster(); } 
                        else if (res.is_warning) {
                            if (confirm(res.message + "\n\nDo you want to proceed and force this swap as an emergency override?")) {
                                executeSwapFetch(1); 
                            }
                        } else showToast(res.message, 'error');
                    })
                    .catch(err => { showToast('Error executing swap: ' + err.message, 'error'); });
                };

                executeSwapFetch(0);
            } else {
                const newShiftCode = document.getElementById('select-new-shift').value;
                if (!newShiftCode) { showToast('Please select a new shift.', 'error'); return; }

                const executeChangeFetch = (forceVal = 0) => {
                    const fd = new FormData();
                    fd.append('emp_id', empId);
                    fd.append('date', date);
                    fd.append('shift_code', newShiftCode);
                    fd.append('force', forceVal);

                    fetch(`api.php?action=change_shift`, { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) { showToast(res.message); closeModal(); fetchRoster(); } 
                        else if (res.is_warning) {
                            if (confirm(res.message + "\n\nDo you want to proceed and force this shift change as an emergency override?")) {
                                executeChangeFetch(1); 
                            }
                        } else showToast(res.message, 'error');
                    })
                    .catch(err => { showToast('Error changing shift: ' + err.message, 'error'); });
                };

                executeChangeFetch(0);
            }
        });
    }

    function openSwapModal(employee, date, shiftCode) {
        document.getElementById('swap-modal-emp-name').textContent = employee.name;
        document.getElementById('swap-modal-date').textContent = new Date(date).toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' });
        document.getElementById('swap-modal-shift-code').textContent = shiftCode;

        document.getElementById('swap-emp-id').value = employee.emp_id;
        document.getElementById('swap-date').value = date;
        document.getElementById('swap-original-shift').value = shiftCode;

        const actionType = document.getElementById('swap-action-type');
        const selectRepl = document.getElementById('select-replacement');
        const warningBox = document.getElementById('swap-validation-warning');
        
        selectRepl.innerHTML = '<option value="">Loading replacements...</option>';
        warningBox.style.display = 'none';

        if (shiftCode === 'Off') {
            actionType.options[0].disabled = true;
            actionType.value = 'change';
        } else {
            actionType.options[0].disabled = false;
            actionType.value = 'swap';
        }
        actionType.dispatchEvent(new Event('change'));

        if (shiftCode !== 'Off') {
            fetch(`api.php?action=get_replacements&emp_id=${employee.emp_id}&date=${date}`)
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        selectRepl.innerHTML = '';
                        
                        if (res.data.length === 0) {
                            selectRepl.innerHTML = '<option value="">No qualified matching staff available</option>';
                            return;
                        }

                        const defaultOpt = document.createElement('option');
                        defaultOpt.value = ''; defaultOpt.textContent = '-- Select Replacement Employee --';
                        selectRepl.appendChild(defaultOpt);

                        res.data.forEach(c => {
                            const opt = document.createElement('option');
                            opt.value = c.emp_id;
                            
                            let label = "";
                            if (c.type === 'Upgrade') label = `${c.name} (Working ${c.shift_code} - Upgrade to F)`;
                            else label = `${c.name} (Off - Swap to ${shiftCode})`;
                            
                            label += ` [${c.skill_level} Skill`;
                            if (c.role && c.role !== 'Rotating') label += `, ${c.role.replace('_', ' ')}`;
                            label += ']';

                            if (!c.is_valid) {
                                label += ` - ERROR: ${c.reason}`;
                                opt.disabled = true;
                                opt.style.color = 'var(--danger-color)';
                            } else if (c.warning) {
                                label += ` - WARNING: Results in gender imbalance`;
                            }
                            
                            opt.textContent = label;
                            selectRepl.appendChild(opt);
                        });

                        selectRepl.onchange = () => {
                            const selectedVal = selectRepl.value;
                            const candidate = res.data.find(c => c.emp_id == selectedVal);
                            if (candidate) {
                                if (!candidate.is_valid) {
                                    warningBox.textContent = candidate.reason;
                                    warningBox.style.display = 'block'; warningBox.style.backgroundColor = '#fee2e2';
                                    warningBox.style.color = '#991b1b'; warningBox.style.borderColor = '#fecaca';
                                } else if (candidate.warning) {
                                    warningBox.textContent = candidate.warning + " Click Confirm Action to proceed anyways (Emergency Override).";
                                    warningBox.style.display = 'block'; warningBox.style.backgroundColor = '#fef3c7'; 
                                    warningBox.style.color = '#92400e'; warningBox.style.borderColor = '#fcd34d';
                                } else warningBox.style.display = 'none';
                            } else warningBox.style.display = 'none';
                        };
                    } else showToast(res.message, 'error');
                })
                .catch(err => { showToast('Error fetching replacements: ' + err.message, 'error'); });
        } else {
            selectRepl.innerHTML = '<option value="">Cannot swap an OFF shift</option>';
        }

        const selectNewShift = document.getElementById('select-new-shift');
        selectNewShift.innerHTML = '<option value="">Loading shifts...</option>';
        
        fetch('api.php?action=get_shifts')
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    selectNewShift.innerHTML = '';
                    res.data.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.shift_code;
                        opt.textContent = `${s.shift_name} (${s.shift_code})`;
                        if (s.shift_code === shiftCode) {
                            opt.selected = true;
                        }
                        selectNewShift.appendChild(opt);
                    });
                } else {
                    selectNewShift.innerHTML = '<option value="">Error loading shifts</option>';
                }
            })
            .catch(() => {
                selectNewShift.innerHTML = '<option value="">Error loading shifts</option>';
            });

        document.getElementById('swap-modal').style.display = 'flex';
    }

    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        let icon = type === 'success' 
            ? `<svg style="width:1.25rem;height:1.25rem;margin-right:0.6rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`
            : `<svg style="width:1.25rem;height:1.25rem;margin-right:0.6rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>`;

        toast.innerHTML = `<div style="display:flex;align-items:center;">${icon}<span style="font-weight: 500;">${escapeHtml(message)}</span></div>`;
        
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideIn 0.3s ease-in reverse';
            setTimeout(() => { toast.remove(); }, 300);
        }, 4000);
    }

    // -------------------------------------------------------------
    // TAB: User Management
    // -------------------------------------------------------------
    function fetchUsers() {
        return fetch(`api.php?action=get_users`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    state.users = res.data;
                    renderUsersTable();
                } else showToast(res.message, 'error');
            })
            .catch(err => showToast('Error loading users: ' + err.message, 'error'));
    }

    function renderUsersTable() {
        const tbody = document.querySelector('#table-users tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
 
        if (state.users.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding: 1.5rem;">No users found.</td></tr>`;
            return;
        }
 
        state.users.forEach(user => {
            const tr = document.createElement('tr');
            
            const createdDate = new Date(user.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
 
            tr.innerHTML = `
                <td style="font-weight:600; color:var(--text-main);">${escapeHtml(user.username)}</td>
                <td><span class="badge ${user.role === 'Admin' ? 'badge-danger' : 'badge-normal'}">${escapeHtml(user.role || 'Manager')}</span></td>
                <td style="color:var(--text-muted); font-size:0.85rem;">${createdDate}</td>
                <td style="text-align:right;">
                    <div style="display:inline-flex; gap:0.5rem; justify-content:flex-end; align-items:center; white-space:nowrap;">
                        <button class="btn btn-secondary btn-edit-user" data-id="${user.id}" style="padding:0.25rem 0.6rem; font-size:0.75rem; min-height:auto;">Edit</button>
                        <button class="btn btn-clear btn-delete-user" data-id="${user.id}" style="padding:0.25rem 0.6rem; font-size:0.75rem; min-height:auto; border-color: rgba(239, 68, 68, 0.2); color: #ef4444;">Delete</button>
                    </div>
                </td>
            `;
 
            tr.querySelector('.btn-edit-user').addEventListener('click', () => {
                editUser(user);
            });
 
            tr.querySelector('.btn-delete-user').addEventListener('click', () => {
                deleteUser(user.id, user.username);
            });
 
            tbody.appendChild(tr);
        });
    }

    function editUser(user) {
        document.getElementById('user-id').value = user.id;
        document.getElementById('user-username').value = user.username;
        document.getElementById('user-password').value = '';
        document.getElementById('user-password').placeholder = 'Leave blank to keep unchanged';
        document.getElementById('user-password').removeAttribute('required');
        document.getElementById('password-help-text').style.display = 'block';
        document.getElementById('label-user-password').textContent = 'New Password (Optional)';
        document.getElementById('user-role').value = user.role || 'Manager';
        document.getElementById('user-form-title').textContent = 'Edit User Settings';
        document.getElementById('btn-cancel-user-edit').style.display = 'inline-block';
        document.getElementById('btn-save-user').textContent = 'Update User';
    }

    function resetUserForm() {
        document.getElementById('user-id').value = '';
        document.getElementById('user-username').value = '';
        document.getElementById('user-password').value = '';
        document.getElementById('user-password').placeholder = 'Enter password (min 6 characters)';
        document.getElementById('user-password').setAttribute('required', 'required');
        document.getElementById('password-help-text').style.display = 'none';
        document.getElementById('label-user-password').textContent = 'Password';
        document.getElementById('user-role').value = 'Manager';
        document.getElementById('user-form-title').textContent = 'Create New User';
        document.getElementById('btn-cancel-user-edit').style.display = 'none';
        document.getElementById('btn-save-user').textContent = 'Create User';
    }

    function initUsersForm() {
        const form = document.getElementById('form-user');
        if (!form) return; // Restrict execution if manager
        const btnCancel = document.getElementById('btn-cancel-user-edit');
 
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const userId = document.getElementById('user-id').value;
            const username = document.getElementById('user-username').value.trim();
            const password = document.getElementById('user-password').value;
            const role = document.getElementById('user-role').value;
 
            if (!userId && !password) {
                showToast('Password is required for new users.', 'error');
                return;
            }
            if (password && password.length < 6) {
                showToast('Password must be at least 6 characters long.', 'error');
                return;
            }
 
            const formData = new FormData();
            if (userId) formData.append('user_id', userId);
            formData.append('username', username);
            formData.append('password', password);
            formData.append('role', role);
 
            fetch(`api.php?action=save_user`, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showToast(res.message, 'success');
                    resetUserForm();
                    fetchUsers();
                } else {
                    showToast(res.message, 'error');
                }
            })
            .catch(err => showToast('Error saving user: ' + err.message, 'error'));
        });
 
        btnCancel.addEventListener('click', resetUserForm);
    }

    function deleteUser(userId, username) {
        if (!confirm(`Are you sure you want to delete user "${username}"?`)) return;

        const formData = new FormData();
        formData.append('user_id', userId);

        fetch(`api.php?action=delete_user`, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                showToast(res.message, 'success');
                fetchUsers();
            } else {
                showToast(res.message, 'error');
            }
        })
        .catch(err => showToast('Error deleting user: ' + err.message, 'error'));
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
});