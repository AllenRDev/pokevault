import { Moon, Sun } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';

export function ThemeToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();

    const dark = resolvedAppearance === 'dark';

    function toggleTheme() {
        updateAppearance(dark ? 'light' : 'dark');
    }

    return (
        <button
            type="button"
            onClick={toggleTheme}
            aria-label={dark ? 'Switch to light mode' : 'Switch to dark mode'}
            className="bg-background text-muted-foreground flex size-9 items-center justify-center rounded-lg border transition-all hover:border-red-500/30 hover:bg-red-500/10 hover:text-red-500"
        >
            {dark ? <Sun className="size-4" /> : <Moon className="size-4" />}
        </button>
    );
}
