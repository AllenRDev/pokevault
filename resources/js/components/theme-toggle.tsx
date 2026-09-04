import { Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';

export function ThemeToggle() {
    const [dark, setDark] = useState(false);

    useEffect(() => {
        const savedTheme = localStorage.getItem('theme');

        if (savedTheme === 'dark') {
            document.documentElement.classList.add('dark');
            setDark(true);
        } else if (savedTheme === 'light') {
            document.documentElement.classList.remove('dark');
            setDark(false);
        } else {
            const prefersDark = window.matchMedia(
                '(prefers-color-scheme: dark)',
            ).matches;

            document.documentElement.classList.toggle('dark', prefersDark);
            setDark(prefersDark);
        }
    }, []);

    function toggleTheme() {
        const nextDark = !dark;

        document.documentElement.classList.toggle('dark', nextDark);
        localStorage.setItem('theme', nextDark ? 'dark' : 'light');

        setDark(nextDark);
    }

    return (
        <button
            type="button"
            onClick={toggleTheme}
            aria-label={dark ? 'Switch to light mode' : 'Switch to dark mode'}
            className="flex size-9 items-center justify-center rounded-xl border bg-background text-muted-foreground transition-all hover:border-red-500/30 hover:bg-red-500/10 hover:text-red-500"
        >
            {dark ? (
                <Sun className="size-4" />
            ) : (
                <Moon className="size-4" />
            )}
        </button>
    );
}