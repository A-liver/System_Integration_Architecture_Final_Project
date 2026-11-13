const root = document.documentElement;

document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    const label = () => {
        button.textContent = root.dataset.theme === 'dark' ? 'Light theme' : 'Dark theme';
    };
    label();
    button.addEventListener('click', () => {
        root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
        try {
            localStorage.setItem('theme', root.dataset.theme);
        } catch (e) {
            // Storage can be blocked; the theme still applies to this page.
        }
        label();
    });
});

document.querySelectorAll('[data-busy-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('[data-busy-label]');
        if (!button) return;
        button.textContent = button.dataset.busyLabel;
        button.disabled = true;
        form.setAttribute('aria-busy', 'true');
    });
});

// A back navigation can restore a page with its button still disabled.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) window.location.reload();
});

const slip = document.querySelector('[data-slip]');
if (slip) {
    const rate = Number(slip.dataset.rate);
    const misc = Number(slip.dataset.misc);
    const currency = slip.dataset.currency;
    const boxes = [...slip.querySelectorAll('input[name="course_ids[]"]')];
    const list = slip.querySelector('[data-slip-list]');
    const money = (cents) =>
        `${currency} ${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const render = () => {
        const picked = boxes.filter((box) => box.checked);
        const units = picked.reduce((sum, box) => sum + Number(box.dataset.units), 0);

        list.replaceChildren(
            ...picked.map((box) => {
                const item = document.createElement('li');
                const code = document.createElement('span');
                const count = document.createElement('span');
                code.textContent = box.dataset.code;
                count.textContent = box.dataset.units;
                item.append(code, count);
                return item;
            }),
        );

        slip.querySelector('[data-slip-empty]').hidden = picked.length > 0;
        slip.querySelector('[data-slip-units]').textContent = units;
        slip.querySelector('[data-slip-tuition]').textContent = money(units * rate);
        slip.querySelector('[data-slip-total]').textContent = money(picked.length ? units * rate + misc : 0);
    };

    boxes.forEach((box) => box.addEventListener('change', render));
    render();
}
