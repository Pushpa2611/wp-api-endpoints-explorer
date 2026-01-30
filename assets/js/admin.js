document.addEventListener('DOMContentLoaded', function () {

    // Copy buttons
    document.querySelectorAll('.copy-endpoint-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const text = this.getAttribute('data-clipboard-text');

            navigator.clipboard.writeText(text).then(() => {
                const original = this.textContent;
                this.textContent = 'Copied!';
                this.style.backgroundColor = '#4caf50';
                this.style.color = 'white';

                setTimeout(() => {
                    this.textContent = original;
                    this.style.backgroundColor = '';
                    this.style.color = '';
                }, 1600);
            }).catch(() => {
                alert('Copy failed – please copy manually');
            });
        });
    });

    // Export buttons
    document.querySelectorAll('.export-docs-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {

            const format = this.getAttribute('data-format');
            const filename = this.getAttribute('data-filename');
            const url = WPApiExplorer.restBase + format;

            const originalText = this.textContent;

            fetch(url, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': WPApiExplorer.nonce
                },
                credentials: 'same-origin'
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Status ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    const blob = new Blob(
                        [JSON.stringify(data, null, 2)],
                        { type: 'application/json' }
                    );

                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = filename;

                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(link.href);

                    this.textContent = 'Downloaded!';
                    setTimeout(() => {
                        this.textContent = originalText;
                    }, 2200);
                })
                .catch(err => {
                    console.error('Export failed:', err);
                    alert('Export failed (admin access required)');
                });
        });
    });
});
