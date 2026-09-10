(() => {
    const calendar = document.querySelector('[data-availability-calendar]');
    if (!calendar) return;

    const grid = calendar.querySelector('[data-calendar-grid]');
    const title = calendar.querySelector('[data-month-title]');
    const status = calendar.querySelector('[data-calendar-status]');
    const previous = calendar.querySelector('[data-previous-month]');
    const next = calendar.querySelector('[data-next-month]');
    const endpoint = calendar.dataset.endpoint;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const firstAllowed = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastAllowed = new Date(today.getFullYear(), today.getMonth() + 18, 1);
    let visibleMonth = new Date(firstAllowed);
    const cache = new Map();

    const pad = (number) => String(number).padStart(2, '0');
    const isoDate = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    const monthKey = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
    const monthLabel = (date) => new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' }).format(date);
    const dayLabel = (date) => new Intl.DateTimeFormat('pt-BR', { day: 'numeric', month: 'long', year: 'numeric' }).format(date);
    const isOccupied = (date, ranges) => Array.isArray(ranges) && ranges.some((range) => date >= range.start && date < range.end);

    function createDay(date, inMonth, ranges) {
        const iso = isoDate(date);
        const cell = document.createElement('div');
        const number = document.createElement('span');
        number.className = 'day-number';
        number.textContent = String(date.getDate());
        cell.className = 'calendar-day';
        cell.setAttribute('role', 'gridcell');
        cell.appendChild(number);

        if (!inMonth) {
            cell.classList.add('outside');
            cell.setAttribute('aria-hidden', 'true');
            return cell;
        }

        if (!Array.isArray(ranges)) {
            cell.classList.add('unknown');
            cell.setAttribute('aria-label', `${dayLabel(date)} — disponibilidade não consultada`);
            cell.title = `${dayLabel(date)} — disponibilidade não consultada`;
            return cell;
        }

        const unavailable = isOccupied(iso, ranges);
        const past = date < today;
        const current = iso === isoDate(today);
        let availability = 'livre';
        if (past) availability = 'data passada';
        if (unavailable) availability = 'indisponível';

        if (past) cell.classList.add('past');
        if (current) cell.classList.add('today');
        if (unavailable) {
            cell.classList.add('unavailable');
            const mark = document.createElement('span');
            mark.className = 'unavailable-mark';
            mark.setAttribute('aria-hidden', 'true');
            mark.textContent = '×';
            cell.appendChild(mark);
        }
        cell.setAttribute('aria-label', `${dayLabel(date)} — ${availability}`);
        cell.title = `${dayLabel(date)} — ${availability}`;
        return cell;
    }

    function render(ranges) {
        title.textContent = monthLabel(visibleMonth);
        grid.replaceChildren();
        const year = visibleMonth.getFullYear();
        const month = visibleMonth.getMonth();
        const offset = (new Date(year, month, 1).getDay() + 6) % 7;
        const gridStart = new Date(year, month, 1 - offset);

        for (let index = 0; index < 42; index += 1) {
            const date = new Date(gridStart);
            date.setDate(gridStart.getDate() + index);
            grid.appendChild(createDay(date, date.getMonth() === month, ranges));
        }

        previous.disabled = visibleMonth <= firstAllowed;
        next.disabled = visibleMonth >= lastAllowed;
    }

    async function loadMonth() {
        const key = monthKey(visibleMonth);
        title.textContent = monthLabel(visibleMonth);
        status.className = 'calendar-status';
        status.textContent = 'Consultando as reservas...';
        previous.disabled = true;
        next.disabled = true;

        try {
            let ranges = cache.get(key);
            if (!ranges) {
                const response = await fetch(`${endpoint}?month=${encodeURIComponent(key)}`, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' },
                });
                const payload = await response.json();
                if (!response.ok || !payload.ok || !Array.isArray(payload.occupied)) throw new Error(payload.message || 'Consulta indisponível.');
                ranges = payload.occupied;
                cache.set(key, ranges);
            }
            render(ranges);
            status.textContent = 'Calendário atualizado.';
        } catch (error) {
            render(null);
            status.className = 'calendar-status error';
            status.textContent = error.message || 'Não foi possível consultar o calendário.';
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.textContent = 'Tentar novamente';
            retry.addEventListener('click', loadMonth, { once: true });
            status.appendChild(retry);
        }
    }

    previous.addEventListener('click', () => {
        if (visibleMonth <= firstAllowed) return;
        visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() - 1, 1);
        loadMonth();
    });
    next.addEventListener('click', () => {
        if (visibleMonth >= lastAllowed) return;
        visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + 1, 1);
        loadMonth();
    });

    const toggle = document.querySelector('.menu-toggle');
    const menu = document.getElementById('availability-menu');
    toggle?.addEventListener('click', () => {
        const open = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
        menu?.classList.toggle('open', open);
    });

    loadMonth();
})();
