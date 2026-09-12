document.querySelectorAll('[data-activity]').forEach((button) => {
    button.addEventListener('click', () => {
        const modal = document.getElementById('activityModal');
        const select = modal?.querySelector('[name="activity_type"]');
        if (select) select.value = button.dataset.activity;
        if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
    });
});

const stageSelect = document.querySelector('[name="stage"]');
const lostReason = document.querySelector('[name="lost_reason"]')?.closest('.lost-reason-wrap');
const updateLostReason = () => {
    if (lostReason) {
        lostReason.classList.toggle('d-none', stageSelect?.value !== 'LOST');
    }
};
stageSelect?.addEventListener('change', updateLostReason);
updateLostReason();
