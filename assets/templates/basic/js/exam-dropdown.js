(() => {
    'use strict';

    const triggers = document.querySelectorAll('.retake-menu-trigger');
    let activeTrigger = null;
    let activeMenu = null;

    const closeMenu = () => {
        if (!activeMenu) return;
        activeMenu.hidden = true;
        activeTrigger?.setAttribute('aria-expanded', 'false');
        activeMenu = null;
        activeTrigger = null;
    };

    const placeMenu = () => {
        if (!activeMenu || !activeTrigger) return;
        const rect = activeTrigger.getBoundingClientRect();
        const gap = 6;
        const edge = 8;
        const menuWidth = activeMenu.offsetWidth;
        const menuHeight = activeMenu.offsetHeight;
        const roomBelow = window.innerHeight - rect.bottom;
        const roomAbove = rect.top;
        const openAbove = menuHeight + gap > roomBelow && roomAbove > roomBelow;
        const top = openAbove
            ? Math.max(edge, rect.top - menuHeight - gap)
            : Math.min(rect.bottom + gap, window.innerHeight - menuHeight - edge);
        const preferredLeft = activeTrigger.dataset.menuAlign === 'start'
            ? rect.left
            : rect.right - menuWidth;
        const left = Math.max(edge, Math.min(preferredLeft, window.innerWidth - menuWidth - edge));
        activeMenu.style.top = `${top}px`;
        activeMenu.style.left = `${left}px`;
    };

    triggers.forEach((trigger) => {
        const menu = document.getElementById(trigger.getAttribute('aria-controls'));
        if (!menu) return;
        document.body.appendChild(menu);

        trigger.addEventListener('click', () => {
            if (activeMenu === menu) {
                closeMenu();
                return;
            }
            closeMenu();
            activeTrigger = trigger;
            activeMenu = menu;
            menu.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            placeMenu();
        });
    });

    document.addEventListener('change', (event) => {
        if (event.target.id === 'randomizeQuestions') {
            const order = document.getElementById('questionOrder');
            if (order) order.value = event.target.checked ? 'random' : 'original';
        }
    });

    document.addEventListener('click', (event) => {
        const timerChoice = event.target.closest('[data-timer-value]');
        if (timerChoice) {
            const timerValue = document.getElementById('timerValue');
            const timerLabel = document.getElementById('timerLabel');
            if (timerValue && timerLabel) {
                timerValue.value = timerChoice.dataset.timerValue;
                timerLabel.textContent = timerChoice.dataset.timerLabel;
                document.querySelectorAll('[data-timer-value]').forEach((choice) => choice.classList.toggle('text--base', choice === timerChoice));
            }
            closeMenu();
            return;
        }
        if (activeMenu && !activeMenu.contains(event.target) && !activeTrigger?.contains(event.target)) closeMenu();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMenu();
    });
    window.addEventListener('resize', placeMenu);
    window.addEventListener('scroll', placeMenu, true);
})();
