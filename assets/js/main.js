// main.js
document.addEventListener('DOMContentLoaded', function () {
    // 1. Initialize Lucide Icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // 2. Sidebar & Overlay Logic
    const sidebar = document.getElementById('sidebar');
    const menuButton = document.getElementById('menu-button');
    const overlay = document.getElementById('sidebar-overlay');

    function toggleSidebar() {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
        document.body.classList.toggle('overflow-hidden');
    }

    if (menuButton) {
        menuButton.addEventListener('click', toggleSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', toggleSidebar);
    }

    // 3. Handle PHP Messages as Toasts
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        showToast(urlParams.get('success'), 'success');
    }
    if (urlParams.has('error')) {
        showToast(urlParams.get('error'), 'error');
    }

    // 4. Initialize Interactive Features
    initInteractiveTables();
    initCurrencyMasking();
});

/**
 * Toast Notification System
 * @param {string} message
 * @param {string} type - 'success' | 'error' | 'info'
 */
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-600' : type === 'error' ? 'bg-red-600' : 'bg-blue-600';
    const iconName = type === 'success' ? 'check-circle' : type === 'error' ? 'alert-circle' : 'info';

    toast.className = `flex items-center p-4 min-w-[300px] text-white rounded-xl shadow-2xl transition-all duration-300 ease-out transform translate-x-full opacity-0 ${bgColor} toast-enter`;

    toast.innerHTML = `
        <i data-lucide="${iconName}" class="w-5 h-5 mr-3"></i>
        <div class="flex-1 text-sm font-medium toast-msg"></div>
        <button class="ml-4 hover:opacity-70 transition-opacity" onclick="this.parentElement.remove()">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    `;
    toast.querySelector('.toast-msg').textContent = decodeURIComponent(message.replace(/\+/g, ' '));

    container.appendChild(toast);
    lucide.createIcons({ props: { class: 'lucide' }, node: toast });

    // Animate in
    requestAnimationFrame(() => {
        toast.classList.remove('translate-x-full', 'opacity-0');
        toast.classList.add('translate-x-0', 'opacity-100');
    });

    // Auto-remove after 5 seconds
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-x-full');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// Global hook for easy access
window.showToast = showToast;

/**
 * Interactive Tables (Search & Pagination)
 */
function initInteractiveTables() {
    const tables = document.querySelectorAll('table[data-interactive="true"]');

    tables.forEach(table => {
        const rows = Array.from(table.querySelectorAll('tbody tr'));
        const searchInputId = table.getAttribute('data-search-input');
        const itemsPerPage = parseInt(table.getAttribute('data-pagination')) || 10;
        let currentPage = 1;
        let filteredRows = [...rows];

        const searchInput = document.getElementById(searchInputId);

        function updateTable() {
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;

            rows.forEach(row => row.classList.add('hidden'));
            filteredRows.slice(start, end).forEach(row => row.classList.remove('hidden'));

            renderPaginationControls(table, filteredRows.length, itemsPerPage, currentPage, (page) => {
                currentPage = page;
                updateTable();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase();
                filteredRows = rows.filter(row => {
                    return row.textContent.toLowerCase().includes(term);
                });
                currentPage = 1;
                updateTable();
            });
        }

        updateTable();
    });
}

function renderPaginationControls(table, totalItems, itemsPerPage, currentPage, onPageChange) {
    let controlsContainer = table.parentElement.querySelector('.pagination-controls');
    if (!controlsContainer) {
        controlsContainer = document.createElement('div');
        controlsContainer.className = 'pagination-controls flex items-center justify-between mt-4 px-4 py-3 bg-white border-t border-gray-200 sm:px-6';
        table.parentElement.appendChild(controlsContainer);
    }

    const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;

    controlsContainer.innerHTML = `
        <div class="flex-1 flex justify-between sm:hidden">
            <button class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 ${currentPage === 1 ? 'opacity-50 cursor-not-allowed' : ''}" ${currentPage === 1 ? 'disabled' : ''} data-page="${currentPage - 1}">Previous</button>
            <button class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 ${currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : ''}" ${currentPage === totalPages ? 'disabled' : ''} data-page="${currentPage + 1}">Next</button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700">
                    Showing <span class="font-medium">${totalItems === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1}</span> to <span class="font-medium">${Math.min(currentPage * itemsPerPage, totalItems)}</span> of <span class="font-medium">${totalItems}</span> results
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <button class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 ${currentPage === 1 ? 'opacity-50 cursor-not-allowed' : ''}" ${currentPage === 1 ? 'disabled' : ''} data-page="${currentPage - 1}">
                        <span class="sr-only">Previous</span>
                        <i data-lucide="chevron-left" class="h-5 w-5 text-gray-400"></i>
                    </button>
                    ${generatePageButtons(totalPages, currentPage)}
                    <button class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 ${currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : ''}" ${currentPage === totalPages ? 'disabled' : ''} data-page="${currentPage + 1}">
                        <span class="sr-only">Next</span>
                        <i data-lucide="chevron-right" class="h-5 w-5 text-gray-400"></i>
                    </button>
                </nav>
            </div>
        </div>
    `;

    lucide.createIcons({ props: { class: 'lucide' }, node: controlsContainer });

    controlsContainer.querySelectorAll('button[data-page]').forEach(btn => {
        btn.addEventListener('click', () => {
            const page = parseInt(btn.getAttribute('data-page'));
            if (page >= 1 && page <= totalPages) {
                onPageChange(page);
            }
        });
    });
}

function generatePageButtons(totalPages, currentPage) {
    let buttons = '';
    for (let i = 1; i <= totalPages; i++) {
        if (i === currentPage) {
            buttons += `<span aria-current="page" class="z-10 bg-indigo-50 border-indigo-500 text-indigo-600 relative inline-flex items-center px-4 py-2 border text-sm font-medium">${i}</span>`;
        } else {
            buttons += `<button class="bg-white border-gray-300 text-gray-500 hover:bg-gray-50 relative inline-flex items-center px-4 py-2 border text-sm font-medium" data-page="${i}">${i}</button>`;
        }
    }
    return buttons;
}

/**
 * Currency Masking Logic
 */
function initCurrencyMasking() {
    const currencyInputs = document.querySelectorAll('input[data-type="currency"]');

    currencyInputs.forEach(input => {
        // Initial formatting if value exists
        if (input.value) {
            input.value = formatNumberWithCommas(input.value);
        }

        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/,/g, '');
            if (!isNaN(value) && value !== '') {
                e.target.value = formatNumberWithCommas(value);
            } else if (value === '') {
                e.target.value = '';
            } else {
                // Restore previous valid value if possible or strip non-numeric
                e.target.value = formatNumberWithCommas(value.replace(/[^\d]/g, ''));
            }
        });
    });

    // Handle form submission to strip commas
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            form.querySelectorAll('input[data-type="currency"]').forEach(input => {
                input.value = input.value.replace(/,/g, '');
            });
        });
    });
}

function formatNumberWithCommas(n) {
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

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
