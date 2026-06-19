import { ref } from 'vue';

// Shared light/dark theme state for the Inertia/Vue surface. Reuses the EXACT
// mechanism the Blade layout uses — the `data-md-theme` attribute on <html>, the
// `md_theme` cookie and `md-theme` localStorage key — so the theme choice is
// continuous across Blade <-> Inertia pages during migration. The initial value
// is applied pre-paint by the root view (resources/views/app.blade.php).

type Theme = 'light' | 'dark';

function currentTheme(): Theme {
    if (typeof document === 'undefined') {
        return 'dark';
    }
    return document.documentElement.getAttribute('data-md-theme') === 'light' ? 'light' : 'dark';
}

const theme = ref<Theme>(currentTheme());

export function useTheme() {
    function setTheme(next: Theme): void {
        theme.value = next;
        document.documentElement.setAttribute('data-md-theme', next);
        document.cookie = `md_theme=${next};path=/;max-age=31536000;samesite=lax`;
        try {
            localStorage.setItem('md-theme', next);
        } catch {
            /* ignore storage failures (private mode etc.) */
        }
    }

    function toggleTheme(): void {
        setTheme(theme.value === 'dark' ? 'light' : 'dark');
    }

    return { theme, setTheme, toggleTheme };
}
