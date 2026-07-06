let reservationSnapshot = null;

function reservationEscapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value || '';
    return div.innerHTML;
}

function captureReservationSnapshot() {
    const table = document.getElementById('reservationTable');
    const tbody = document.getElementById('reservationTableBody');
    const headers = document.querySelectorAll('.reservation-day-header');

    if (!table || !tbody || headers.length === 0) return;

    reservationSnapshot = {
        before: table.dataset.before || '',
        tbody: tbody.innerHTML,
        headers: Array.from(headers).map(header => ({
            html: header.innerHTML,
            style: header.getAttribute('style') || ''
        }))
    };
}

function restoreReservationSnapshot() {
    const table = document.getElementById('reservationTable');
    const tbody = document.getElementById('reservationTableBody');
    const headers = document.querySelectorAll('.reservation-day-header');

    if (!reservationSnapshot || !table || !tbody || headers.length === 0) return;

    table.dataset.before = reservationSnapshot.before || '';
    tbody.innerHTML = reservationSnapshot.tbody;

    headers.forEach((header, index) => {
        const snapshotHeader = reservationSnapshot.headers[index];
        if (!snapshotHeader) return;
        header.innerHTML = snapshotHeader.html;
        header.setAttribute('style', snapshotHeader.style);
    });

    bindBulkCheckboxHandlers();
    if (typeof clearBulkSelection === 'function') clearBulkSelection();
}

function showReservationLoadError(message) {
    const container = document.querySelector('.reservation-table-container');
    if (!container) return;

    let alert = document.getElementById('reservationStatusAlert');
    if (!alert) {
        alert = document.createElement('div');
        alert.id = 'reservationStatusAlert';
        alert.className = 'alert alert-warning mb-3';
        container.parentNode.insertBefore(alert, container);
    }

    alert.textContent = message;
}

function clearReservationLoadError() {
    const alert = document.getElementById('reservationStatusAlert');
    if (alert) {
        alert.remove();
    }
}

function bindBulkCheckboxHandlers() {
    document.querySelectorAll('.bulk-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkControls);
    });
}

function buildReservationPastTextStyle(isPast) {
    return isPast ? 'color: #4a4a4a; text-shadow: 0 1px 1px rgba(255, 255, 255, 0.35);' : '';
}

function buildReservationSlotCell(slot, tempoId, salaId, date, isPast) {
    const cellClass = slot.status === 'free' ? 'bg-success' : (slot.status === 'pending' ? 'bg-warning' : 'bg-danger');
    const label = reservationEscapeHtml(slot.label);
    const requisitor = slot.requisitor ? '<br>' + reservationEscapeHtml(slot.requisitor) : '';
    const link = '/reservar/manage.php?tempo=' + encodeURIComponent(tempoId) + '&sala=' + encodeURIComponent(salaId) + '&data=' + encodeURIComponent(date);
    const checkboxValue = encodeURIComponent(tempoId) + '|' + encodeURIComponent(salaId) + '|' + encodeURIComponent(date);
    const clickableCell = slot.status === 'free' && slot.canInteract;
    const pastTextStyle = buildReservationPastTextStyle(isPast);
    let content = '';

    if (clickableCell && slot.canCreateReservation) {
        content = '<input type="checkbox" name="slots[]" value="' + checkboxValue + '" class="bulk-checkbox" style="width: 16px; height: 16px;">' +
            '<a class="reserva" href="' + link + '" style="display: block; font-size: 0.75rem; word-break: break-word;' + pastTextStyle + '">' + label + '</a>';
    } else if (slot.canInteract && slot.status !== 'free') {
        content = '<a class="reserva" href="' + link + '" style="font-size: 0.75rem; word-break: break-word;' + pastTextStyle + '">' + label + requisitor + '</a>';
    } else {
        content = '<span style="font-size: 0.75rem; word-break: break-word;' + pastTextStyle + '">' + label + requisitor + '</span>';
    }

    return '<td class="' + cellClass + ' text-white text-center' + (clickableCell ? ' availability-cell' : '') + '"' + (clickableCell ? ' data-href="' + link + '"' : '') + ' style="padding: 4px; overflow: hidden; position: relative;">' +
        '<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; min-height: 50px;">' + content + '</div></td>';
}

function setReservationSkeleton() {
    const tbody = document.getElementById('reservationTableBody');
    if (!tbody) return;

    tbody.querySelectorAll('td').forEach(cell => {
        cell.className = 'text-center';
        cell.innerHTML = '<span class="placeholder col-8">&nbsp;</span>';
    });
    if (typeof clearBulkSelection === 'function') clearBulkSelection();
}

function renderReservationStatuses(data) {
    const table = document.getElementById('reservationTable');
    const tbody = document.getElementById('reservationTableBody');
    if (!table || !tbody) return;

    table.dataset.before = data.before || '';
    document.querySelectorAll('.reservation-day-header').forEach((header, index) => {
        const day = data.days[index];
        if (!day) return;

        header.innerHTML = day.label;
        header.style.textAlign = 'center';
        header.style.fontSize = '0.75rem';
        header.style.padding = '4px';
        header.style.boxShadow = day.isToday ? 'inset 0 0 0 3px #0d6efd' : '';
        header.style.backgroundColor = day.isToday ? 'rgba(13, 110, 253, 0.1)' : '';
        header.style.color = day.isPast ? '#4a4a4a' : '';
        header.style.textShadow = day.isPast ? '0 1px 1px rgba(255, 255, 255, 0.35)' : '';
    });
    tbody.innerHTML = '';
    data.tempos.forEach(tempo => {
        let row = '<tr><th scope="row" style="font-size: 0.75rem; padding: 4px;">' + reservationEscapeHtml(tempo.horashumanos) + '</th>';
        data.days.forEach(day => {
            row += buildReservationSlotCell(data.slots[tempo.id][day.date], tempo.id, data.sala, day.date, day.isPast);
        });
        row += '</tr>';
        tbody.insertAdjacentHTML('beforeend', row);
    });

    bindBulkCheckboxHandlers();
    if (typeof clearBulkSelection === 'function') clearBulkSelection();
    clearReservationLoadError();
    captureReservationSnapshot();
}

function updateReservationLinks(data) {
    const salaId = encodeURIComponent(data.sala);
    const links = [
        ['previousWeekLink', data.previousWeek, '/reservar/?before=' + encodeURIComponent(data.previousWeek) + '&sala=' + salaId],
        ['currentWeekLink', '', '/reservar/?sala=' + salaId],
        ['nextWeekLink', data.nextWeek, '/reservar/?before=' + encodeURIComponent(data.nextWeek) + '&sala=' + salaId]
    ];

    links.forEach(([id, before, href]) => {
        const link = document.getElementById(id);
        if (link) {
            link.dataset.weekBefore = before;
            link.href = href;
        }
    });
}

function loadReservationStatuses(before, pushState) {
    const table = document.getElementById('reservationTable');
    if (!table) return;

    const params = new URLSearchParams();
    params.set('sala', table.dataset.sala);
    if (before) params.set('before', before);

    setReservationSkeleton();
    fetch('/api/reservation_statuses.php?' + params.toString(), { credentials: 'same-origin' })
        .then(response => {
            return response.json().then(data => {
                if (!response.ok) {
                    throw new Error(data && data.error ? data.error : 'Não foi possível carregar os horários.');
                }
                return data;
            });
        })
        .then(data => {
            renderReservationStatuses(data);
            updateReservationLinks(data);
            if (pushState) {
                const url = new URL(window.location.href);
                url.searchParams.set('sala', data.sala);
                if (data.before) url.searchParams.set('before', data.before);
                else url.searchParams.delete('before');
                window.history.pushState({ before: data.before || '' }, '', url.toString());
            }
        })
        .catch(error => {
            restoreReservationSnapshot();
            showReservationLoadError(error && error.message ? error.message : 'Não foi possível carregar os horários.');
        });
}

document.addEventListener('DOMContentLoaded', function() {
    captureReservationSnapshot();
    bindBulkCheckboxHandlers();

    document.querySelectorAll('[data-week-before]').forEach(link => {
        link.addEventListener('click', function(event) {
            event.preventDefault();
            loadReservationStatuses(this.dataset.weekBefore, true);
        });
    });

    window.addEventListener('popstate', function() {
        const params = new URLSearchParams(window.location.search);
        loadReservationStatuses(params.get('before') || '', false);
    });

    const table = document.getElementById('reservationTable');
    if (table) loadReservationStatuses(table.dataset.before || '', false);
});
