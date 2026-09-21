import { create } from 'zustand';

type Theme = 'light' | 'dark';

interface ThemeState {
    theme: Theme;
    setTheme: (theme: Theme) => void;
    toggleTheme: () => void;
}

const getInitialTheme = (): Theme => {
    const savedTheme = localStorage.getItem('theme');

    if (savedTheme === 'dark' || savedTheme === 'light') {
        return savedTheme;
    }

    return 'light';
};

const applyTheme = (theme: Theme) => {
    const root = document.documentElement;

    if (theme === 'dark') {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }
};

const initialTheme = getInitialTheme();

applyTheme(initialTheme);

export const useThemeStore = create<ThemeState>((set) => ({
    theme: initialTheme,

    setTheme: (theme) => {
        localStorage.setItem('theme', theme);
        applyTheme(theme);

        set({ theme });
    },

    toggleTheme: () => {
        set((state) => {
            const newTheme = state.theme === 'light' ? 'dark' : 'light';

            localStorage.setItem('theme', newTheme);
            applyTheme(newTheme);

            return {
                theme: newTheme,
            };
        });
    },
}));