import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Search, Sparkles } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface PokemonCard {
    external_id: string;
    name: string;
    number: string;
    rarity: string | null;
    image_small: string | null;
    image_large: string | null;
    set: null;
}

interface SearchResponse {
    data: PokemonCard[];
    count: number;
}

export default function CardIndex() {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<PokemonCard[]>([]);
    const [loading, setLoading] = useState(false);
    const [searched, setSearched] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function handleSearch(event: FormEvent) {
        event.preventDefault();

        if (query.trim().length < 2) {
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const response = await fetch(
                `/api/cards/search?q=${encodeURIComponent(query.trim())}`,
            );

            if (!response.ok) {
                throw new Error('Unable to search for cards.');
            }

            const data: SearchResponse = await response.json();

            setResults(data.data);
            setSearched(true);
        } catch {
            /*
             * Previous results are cleared so a failed search does not leave
             * the previous query's cards on screen next to the error.
             */
            setResults([]);
            setSearched(false);
            setError('Something went wrong while searching for cards.');
        } finally {
            setLoading(false);
        }
    }

    return (
        <>
            <Head title="Card Search" />

            <div className="bg-background min-h-screen">
                {/* Header */}
                <section className="relative overflow-hidden border-b">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(59,130,246,0.14),transparent_35%),radial-gradient(circle_at_80%_0%,rgba(168,85,247,0.12),transparent_30%)] dark:bg-[radial-gradient(circle_at_20%_20%,rgba(220,38,38,0.16),transparent_35%),radial-gradient(circle_at_80%_0%,rgba(126,34,206,0.12),transparent_30%)]" />

                    <div className="relative mx-auto max-w-7xl px-6 py-14 lg:px-8 lg:py-20">
                        <div className="mx-auto max-w-3xl text-center">
                            <div className="mb-4 inline-flex items-center gap-2 rounded-full bg-purple-500/10 px-4 py-2 text-sm font-semibold text-purple-600 dark:bg-red-500/10 dark:text-red-400">
                                <Sparkles className="size-4" />
                                Pokémon TCG Database
                            </div>

                            <h1 className="text-4xl font-black tracking-tight sm:text-5xl">
                                Find your{' '}
                                <span className="bg-gradient-to-r from-blue-500 to-purple-500 bg-clip-text text-transparent dark:from-red-500 dark:to-red-700">
                                    cards.
                                </span>
                            </h1>

                            <p className="text-muted-foreground mt-4">
                                Search the vault to discover cards, artwork,
                                sets, and more.
                            </p>

                            <form onSubmit={handleSearch} className="mt-8">
                                <div className="flex flex-col gap-3 sm:flex-row">
                                    <div className="relative flex-1">
                                        <Search className="absolute top-1/2 left-4 size-5 -translate-y-1/2 text-blue-500 dark:text-red-500" />

                                        <input
                                            type="text"
                                            value={query}
                                            onChange={(event) =>
                                                setQuery(event.target.value)
                                            }
                                            placeholder="Try Charizard, Pikachu, Mew..."
                                            className="bg-background/90 placeholder:text-muted-foreground/60 h-14 w-full rounded-2xl border pr-4 pl-12 shadow-lg shadow-blue-500/5 transition-all outline-none focus:border-blue-500/40 focus:ring-4 focus:ring-blue-500/10 dark:shadow-red-500/5 dark:focus:border-red-500/40 dark:focus:ring-red-500/10"
                                        />
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={
                                            loading || query.trim().length < 2
                                        }
                                        className="h-14 rounded-2xl bg-gradient-to-r from-blue-500 to-indigo-600 px-7 font-bold text-white shadow-lg shadow-blue-500/20 transition-all hover:-translate-y-0.5 hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0 dark:from-red-600 dark:to-red-800 dark:shadow-red-900/30"
                                    >
                                        {loading ? 'Searching...' : 'Search'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>

                {/* Results */}
                <main className="mx-auto max-w-7xl px-6 py-10 lg:px-8 lg:py-14">
                    {error && (
                        <div className="mx-auto max-w-3xl rounded-2xl border border-red-500/20 bg-red-500/10 p-4 text-center text-sm font-medium text-red-600 dark:border-red-500/30 dark:bg-red-950/30 dark:text-red-400">
                            {error}
                        </div>
                    )}

                    {searched && !loading && results.length === 0 && !error && (
                        <div className="mx-auto mt-8 max-w-xl rounded-3xl border bg-gradient-to-br from-blue-500/5 to-purple-500/5 p-10 text-center dark:border-red-500/20 dark:from-red-950/20 dark:to-purple-950/20">
                            <div className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-purple-500/10 text-purple-500 dark:bg-red-500/10 dark:text-red-400">
                                <Search className="size-6" />
                            </div>

                            <h2 className="mt-5 text-xl font-bold">
                                No cards found
                            </h2>

                            <p className="text-muted-foreground mt-2 text-sm">
                                We couldn't find any cards matching "{query}".
                            </p>

                            <p className="text-muted-foreground mt-4 text-xs">
                                Try a Pokémon name like Charizard or Pikachu.
                            </p>
                        </div>
                    )}

                    {results.length > 0 && (
                        <div>
                            <div className="mb-6 flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-semibold">
                                        Search results
                                    </p>

                                    <p className="text-muted-foreground mt-1 text-sm">
                                        {results.length}{' '}
                                        {results.length === 1
                                            ? 'card'
                                            : 'cards'}{' '}
                                        found for{' '}
                                        <span className="text-foreground font-medium">
                                            "{query}"
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                                {results.map((card, index) => (
                                    <Link
                                        key={card.external_id}
                                        href={`/cards/${card.external_id}`}
                                        className="group bg-card relative overflow-hidden rounded-2xl border shadow-sm transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl dark:hover:shadow-red-950/30"
                                    >
                                        <div
                                            className={`absolute inset-x-0 top-0 z-10 h-1 ${
                                                index % 3 === 0
                                                    ? 'bg-blue-500 dark:bg-red-500'
                                                    : index % 3 === 1
                                                      ? 'bg-purple-500 dark:bg-red-700'
                                                      : 'bg-yellow-400 dark:bg-red-900'
                                            }`}
                                        />

                                        <div className="from-muted to-background aspect-[2.5/3.5] overflow-hidden bg-gradient-to-br p-2">
                                            {card.image_large ? (
                                                <img
                                                    src={card.image_large}
                                                    alt={card.name}
                                                    className="h-full w-full object-contain transition-transform duration-500 group-hover:scale-105"
                                                />
                                            ) : (
                                                <div className="text-muted-foreground flex h-full items-center justify-center text-sm">
                                                    No image available
                                                </div>
                                            )}
                                        </div>

                                        <div className="p-3.5">
                                            <h2 className="truncate font-bold">
                                                {card.name}
                                            </h2>

                                            <div className="mt-2 flex items-center justify-between gap-2">
                                                <span className="rounded-full bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-600 dark:bg-red-500/10 dark:text-red-400">
                                                    #{card.number}
                                                </span>

                                                <ArrowRight className="text-muted-foreground group-hover:text-primary size-4 opacity-0 transition-all group-hover:translate-x-1 group-hover:opacity-100" />
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    )}
                </main>
            </div>
        </>
    );
}
