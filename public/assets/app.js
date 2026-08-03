// Minta konfirmasi sebelum membatalkan match.
// Ini hanya lapisan kenyamanan. Aturan sebenarnya dijaga di sisi server.
document.addEventListener('submit', function (event) {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const action = form.querySelector('input[name="action"]');

    if (action === null || action.value !== 'cancel_assignment') {
        return;
    }

    if (!window.confirm('Cancel this match? The record stays for reporting with a cancelled status.')) {
        event.preventDefault();
    }
});
