// main.js
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const menuButton = document.getElementById('menu-button');
    const content = document.querySelector('.main-content');

    // Toggle sidebar on mobile
    if (menuButton) {
        menuButton.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }

    // Close sidebar when clicking outside of it on mobile
    document.addEventListener('click', (e) => {
        if (window.innerWidth < 769 && sidebar && !sidebar.contains(e.target) && !menuButton.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    });

    // Device detection to manage layout
    function handleResize() {
        if (window.innerWidth < 769) {
            content.style.marginLeft = '0';
        } else {
            content.style.marginLeft = '16rem'; // Default sidebar width
        }
    }

    // Initial check
    handleResize();

    // Listen for window resize events
    window.addEventListener('resize', handleResize);
});

// --- WebAuthn Helper Functions ---
function bufferDecode(value) {
    return Uint8Array.from(atob(value.replace(/_/g, '/').replace(/-/g, '+')), c => c.charCodeAt(0));
}

function bufferEncode(value) {
    return btoa(String.fromCharCode.apply(null, new Uint8Array(value)))
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=/g, '');
}
