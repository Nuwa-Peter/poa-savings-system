// assets/js/theme.js
document.addEventListener('DOMContentLoaded', () => {
    const themeToggleButton = document.getElementById('theme-toggle');
    const lightThemeIcon = document.getElementById('theme-toggle-light-icon');
    const darkThemeIcon = document.getElementById('theme-toggle-dark-icon');
    const logo = document.getElementById('logo');

    // Function to set the theme and save preference
    const setTheme = (theme) => {
        if (theme === 'dark') {
            document.body.classList.add('dark-theme');
            document.body.classList.remove('light-theme');
            if(darkThemeIcon) darkThemeIcon.classList.remove('hidden');
            if(lightThemeIcon) lightThemeIcon.classList.add('hidden');
            if(logo) logo.src = 'assets/images/poa_dark.png';
            localStorage.setItem('theme', 'dark');
        } else {
            document.body.classList.add('light-theme');
            document.body.classList.remove('dark-theme');
            if(darkThemeIcon) darkThemeIcon.classList.add('hidden');
            if(lightThemeIcon) lightThemeIcon.classList.remove('hidden');
            if(logo) logo.src = 'assets/images/poa_light.png';
            localStorage.setItem('theme', 'light');
        }
    };

    // Apply the saved theme on initial load
    const savedTheme = localStorage.getItem('theme') || 'light';
    setTheme(savedTheme);

    // Add event listener to the toggle button
    if (themeToggleButton) {
        themeToggleButton.addEventListener('click', () => {
            const currentTheme = localStorage.getItem('theme') === 'dark' ? 'light' : 'dark';
            setTheme(currentTheme);
        });
    }
});
