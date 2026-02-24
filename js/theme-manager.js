/**
 * Global Theme Management Script
 * Manages dark mode across all pages using localStorage
 * Include this script in all pages: <script src="js/theme-manager.js"></script>
 */

class ThemeManager {
    constructor() {
        this.THEME_KEY = 'gosort-theme';
        this.DARK_MODE_CLASS = 'dark-mode';
        this.init();
    }

    init() {
        // Load saved theme on page load
        this.loadTheme();
        // Watch for changes in other tabs/windows
        window.addEventListener('storage', (e) => this.onStorageChange(e));
    }

    loadTheme() {
        const savedTheme = localStorage.getItem(this.THEME_KEY) || 
                          (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        this.applyTheme(savedTheme);
        this.updateThemeToggle(savedTheme);
    }

    applyTheme(theme) {
        if (theme === 'dark') {
            document.body.classList.add(this.DARK_MODE_CLASS);
            localStorage.setItem(this.THEME_KEY, 'dark');
        } else {
            document.body.classList.remove(this.DARK_MODE_CLASS);
            localStorage.setItem(this.THEME_KEY, 'light');
        }
    }

    toggleTheme() {
        const currentTheme = localStorage.getItem(this.THEME_KEY) || 'light';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        this.applyTheme(newTheme);
        this.updateThemeToggle(newTheme);
        console.log('Theme switched to:', newTheme);
    }

    updateThemeToggle(theme) {
        const toggles = document.querySelectorAll('#theme-toggle, [data-theme-toggle]');
        toggles.forEach(toggle => {
            if (toggle.type === 'checkbox') {
                toggle.checked = theme === 'dark';
            }
        });
    }

    onStorageChange(e) {
        if (e.key === this.THEME_KEY) {
            const newTheme = e.newValue || 'light';
            this.applyTheme(newTheme);
            this.updateThemeToggle(newTheme);
        }
    }

    getCurrentTheme() {
        return localStorage.getItem(this.THEME_KEY) || 'light';
    }
}

// Initialize theme manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.themeManager = new ThemeManager();
});

// Fallback for inline toggleTheme() calls
function toggleTheme() {
    if (window.themeManager) {
        window.themeManager.toggleTheme();
    }
}
