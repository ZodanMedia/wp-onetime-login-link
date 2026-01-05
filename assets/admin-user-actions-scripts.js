/**
 * Confirm script for sending a link to all users
 * 
 */
document.addEventListener('DOMContentLoaded', function () {

    const btn = document.querySelector('.zodanloginonce-send-all');
    if (!btn) return;

    btn.addEventListener('click', function (e) {
        e.preventDefault();

        let seconds = 5;
        const msg = zOnetimeLoginLinkUserActionsBulkVars.triggerAllUsersMessage;
        
        const interval = setInterval(function () {
            if (seconds <= 0) {
                clearInterval(interval);
                window.location.href = btn.href;
            } else {
                btn.textContent = msg + ' ' + seconds + '…';
                seconds--;
            }
        }, 1000);

        if (!confirm(zOnetimeLoginLinkUserActionsBulkVars.confirmAllUsersMessage)) {
            clearInterval(interval);
            btn.textContent = zOnetimeLoginLinkUserActionsBulkVars.allUsersButtonText;
        }
    });
});
/**
 * Confirm script for sending a link to selected users
 * 
 */
document.addEventListener('DOMContentLoaded', function () {
    const bulkApply = document.getElementById('doaction');
    const bulkApply2 = document.getElementById('doaction2');

    if (!bulkApply) return;
    if (!bulkApply2) return;

    function confirmBulk(e) {
        const action = document.getElementById('bulk-action-selector-top').value;
        if (action === 'zodanloginonce_send') {
            if (!confirm(zOnetimeLoginLinkUserActionsBulkVars.confirmSelectedUsersMessage)) {
                e.preventDefault();
            }
        }
    }
    if (bulkApply) bulkApply.addEventListener('click', confirmBulk);
    if (bulkApply2) bulkApply2.addEventListener('click', confirmBulk);
});