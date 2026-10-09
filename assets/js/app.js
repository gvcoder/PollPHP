/**
 * PollPHP Client JavaScript Helpers
 */

document.addEventListener('DOMContentLoaded', () => {
    // Copy link helper
    document.querySelectorAll('[data-copy-url]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const url = btn.getAttribute('data-copy-url');
            try {
                await navigator.clipboard.writeText(url);
                const originalText = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.classList.add('btn-primary');
                setTimeout(() => {
                    btn.textContent = originalText;
                }, 2000);
            } catch (err) {
                prompt('Copy this link:', url);
            }
        });
    });

    // Native Web Share API helper (WhatsApp, Socials)
    document.querySelectorAll('[data-share]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const title = btn.getAttribute('data-share-title') || document.title;
            const text = btn.getAttribute('data-share-text') || 'Vote in this poll:';
            const url = btn.getAttribute('data-share-url') || window.location.href;

            if (navigator.share) {
                try {
                    await navigator.share({ title, text, url });
                } catch (err) {
                    // User dismissed share dialog
                }
            } else {
                // Fallback to WhatsApp direct link
                const waUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(text + ' ' + url)}`;
                window.open(waUrl, '_blank');
            }
        });
    });
});
