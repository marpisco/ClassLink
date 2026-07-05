function reservationEscapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value || '';
    return div.innerHTML;
}

function buildReservationSlotCell(slot, tempoId, salaId, date) {
    const cellClass = slot.status === 'free' ? 'bg-success' : (slot.status === 'pending' ? 'bg-warning' : 'bg-danger');
    const label = reservationEscapeHtml(slot.label);
    const requisitor = slot.requisitor ? '<br>' + reservationEscapeHtml(slot.requisitor) : '';
    const link = '/reservar/manage.php?tempo=' + encodeURIComponent(tempoId) + '&sala=' + encodeURIComponent(salaId) + '&data=' + encodeURIComponent(date);
    const checkboxValue = encodeURIComponent(tempoId) + '|' + encodeURIComponent(salaId) + '|' + encodeURIComponent(date);
    let content = '';

    if (slot.status === 'free' && slot.canCreateReservation && slot.canInteract) {
        content = '<input type="checkbox" name="slots[]" value="' + checkboxValue + '" class="bulk-checkbox" style="width: 16px; height: 16px;">' +
            '<a class="reserva" href="' + link + '" style="display: block; font-size: 0.75rem; word-break: break-word;">' + label + '</a>';
    } else if (slot.canInteract && slot.status !== 'free') {
        content = '<a class="reserva" href="' + link + '" style="font-size: 0.75rem; word-break: break-word;">' + label + requisitor + '</a>';
    } else {
        content = '<span style="font-size: 0.75rem; word-break: break-word;">' + label + requisitor + '</span>';
    }

    return '<td class="' + cellClass + ' text-white text-center" style="padding: 4px; overflow: hidden; position: relative;">' +
        '<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; min-height: 50px;' + (!slot.canInteract ? ' opacity: 0.5;' : '') + '">' + content + '</div></td>';
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
        if (data.days[index]) header.innerHTML = data.days[index].label;
    });
    tbody.innerHTML = '';
    data.tempos.forEach(tempo => {
        let row = '<tr><th scope="row" style="font-size: 0.75rem; padding: 4px;">' + reservationEscapeHtml(tempo.horashumanos) + '</th>';
        data.days.forEach(day => {
            row += buildReservationSlotCell(data.slots[tempo.id][day.date], tempo.id, data.sala, day.date);
        });
        row += '</tr>';
        tbody.insertAdjacentHTML('beforeend', row);
    });

    document.querySelectorAll('.bulk-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkControls);
    });
    if (typeof clearBulkSelection === 'function') clearBulkSelection();
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
            if (!response.ok) throw new Error('Erro');
            return response.json();
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
        .catch(() => window.location.reload());
}

document.addEventListener('DOMContentLoaded', function() {
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
