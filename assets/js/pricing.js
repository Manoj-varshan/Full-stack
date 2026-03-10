// ============================================================
// StreamVault - pricing.js (ES6 Module)
// Checkout modal, card number formatting, payment, cancel
// ============================================================

// -- Open Checkout Modal ------------------------------------
window.openCheckout = function (planId, planName, price) {
    document.getElementById('checkoutModal').style.display = 'flex';
    document.getElementById('checkoutPlanId').value = planId;
    document.getElementById('modalPlanName').textContent = planName;
    document.getElementById('checkoutPrice').innerHTML =
        price > 0
            ? `<sup>&#8377;</sup>${price}<span style="font-size:1rem;color:var(--text-muted)">/mo</span>`
            : 'Free';
};

// -- Card Number Formatting ---------------------------------
const cardInput = document.getElementById('cardNum');
cardInput?.addEventListener('input', (e) => {
    let val = e.target.value.replace(/\D/g, '').substring(0, 16);
    val = val.replace(/(.{4})/g, '$1 ').trim();
    e.target.value = val;
});

// -- Checkout Form Submission -------------------------------
document.getElementById('checkoutForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const errEl = document.getElementById('checkoutError');
    const payBtn = document.getElementById('payBtn');
    errEl.style.display = 'none';

    const cardNum = document.querySelector('[name=card_number]')?.value.replace(/\s/g, '') ?? '';
    if (cardNum.length < 12) {
        errEl.textContent = 'Please enter a valid card number (12+ digits).';
        errEl.style.display = 'block';
        return;
    }

    payBtn.disabled = true;
    payBtn.textContent = 'Processing...';

    const fd = new FormData(e.currentTarget);

    try {
        const res = await fetch('/streamvault/api/subscribe.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            payBtn.textContent = '' + (data.message ?? 'Subscribed!');
            payBtn.style.background = 'var(--accent)';
            setTimeout(() => { window.location.href = '/streamvault/upgrade.php'; }, 1500);
        } else {
            errEl.textContent = data.message ?? 'Payment failed.';
            errEl.style.display = 'block';
            payBtn.disabled = false;
            payBtn.textContent = '\ud83d\udd12 Pay Securely';
        }
    } catch (err) {
        errEl.textContent = 'Network error. Please try again.';
        errEl.style.display = 'block';
        payBtn.disabled = false;
        payBtn.textContent = '\ud83d\udd12 Pay Securely';
    }
});

// -- Cancel Subscription ------------------------------------
document.getElementById('cancelBtn')?.addEventListener('click', () => {
    document.getElementById('cancelModal').style.display = 'flex';
});

document.getElementById('confirmCancel')?.addEventListener('click', async () => {
    const btn = document.getElementById('confirmCancel');
    btn.textContent = 'Cancelling...';
    btn.disabled = true;

    try {
        const res = await fetch('/streamvault/api/cancel.php', { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            window.location.href = '/streamvault/upgrade.php';
        }
    } catch (e) {
        alert('Error cancelling. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Yes, Cancel';
    }
});

// -- Close modals on backdrop click -------------------------
['checkoutModal', 'cancelModal'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) e.currentTarget.style.display = 'none';
    });
});
