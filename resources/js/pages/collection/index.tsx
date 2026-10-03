import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface Card {
    id: number;
    external_id: string;
    name: string;
    number: string;
    rarity: string | null;
    image_small: string | null;
    image_large: string | null;
    set: {
        id: number;
        external_id: string;
        name: string;
        series: string | null;
    };
}

interface UserCard {
    id: number;
    quantity: number;
    condition: string;
    language: string;
    purchase_price: string | number | null;
    purchase_date: string | null;
    market_price: string | number | null;
    market_price_updated_at: string | null;
    notes: string | null;
    card: Card;
}

interface CollectionSummary {
    total_cards: number;
    unique_cards: number;
    sets_collected: number;
    estimated_value: number;
    portfolio_value: number;
}

interface CollectionMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

interface CollectionResponse {
    data: UserCard[];
    summary: CollectionSummary;
    meta: CollectionMeta;
}

type SortOption = 'recent' | 'name' | 'price-high' | 'price-low' | 'quantity';

const SORT_LABELS: Record<SortOption, string> = {
    recent: 'recently added',
    name: 'name',
    'price-high': 'highest price',
    'price-low': 'lowest price',
    quantity: 'quantity',
};

/**
 * A compact window of page numbers around the current page.
 */
function pageWindow(current: number, last: number): (number | 'gap')[] {
    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const pages = new Set([1, last, current, current - 1, current + 1]);

    const sorted = [...pages]
        .filter((page) => page >= 1 && page <= last)
        .sort((a, b) => a - b);

    const result: (number | 'gap')[] = [];

    sorted.forEach((page, index) => {
        if (index > 0 && page - (sorted[index - 1] as number) > 1) {
            result.push('gap');
        }

        result.push(page);
    });

    return result;
}

export default function Index() {
    const [cards, setCards] = useState<UserCard[]>([]);
    const [summary, setSummary] = useState<CollectionSummary | null>(null);
    const [meta, setMeta] = useState<CollectionMeta | null>(null);

    const [loading, setLoading] = useState(true);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const [searchInput, setSearchInput] = useState('');
    const [query, setQuery] = useState('');
    const [sort, setSort] = useState<SortOption>('recent');
    const [page, setPage] = useState(1);

    /*
     * Searching, sorting, and paging are resolved by the server so the
     * response stays the same size no matter how large the collection is.
     * The input is debounced into `query` so a request is not fired on
     * every keystroke.
     */
    useEffect(() => {
        const timer = window.setTimeout(() => {
            setQuery(searchInput.trim());
            setPage(1);
        }, 300);

        return () => window.clearTimeout(timer);
    }, [searchInput]);

    useEffect(() => {
        const controller = new AbortController();

        const loadCollection = async () => {
            const params = new URLSearchParams({
                sort,
                page: String(page),
            });

            if (query) {
                params.set('search', query);
            }

            setPending(true);

            try {
                const response = await fetch(`/collection/cards?${params}`, {
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error('Failed to load your collection.');
                }

                const result: CollectionResponse = await response.json();

                setCards(result.data);
                setSummary(result.summary);
                setMeta(result.meta);
                setError(null);
            } catch (loadError) {
                // An aborted request means the component unmounted mid-flight.
                if (
                    loadError instanceof DOMException &&
                    loadError.name === 'AbortError'
                ) {
                    return;
                }

                console.error('Failed to load collection:', loadError);

                /*
                 * The failure is surfaced rather than swallowed, otherwise a
                 * failed request renders as the "collection is empty" state
                 * and reads as data loss.
                 */
                setError(
                    'We could not load your collection. Please try again.',
                );
            } finally {
                setPending(false);
                setLoading(false);
            }
        };

        void loadCollection();

        return () => controller.abort();
    }, [query, sort, page]);

    const formatCurrency = (value: number | string | null) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(Number(value ?? 0));
    };

    const total = meta?.total ?? 0;
    const currentPage = meta?.current_page ?? 1;
    const lastPage = meta?.last_page ?? 1;

    const hasCards = !loading && !error && total > 0;

    const searchLabel = query ? ` matching "${query}"` : '';

    return (
        <>
            <Head title="My Collection" />

            <div className="flex min-h-0 flex-1 flex-col gap-6 p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            My Collection
                        </h1>

                        <p className="text-muted-foreground mt-1">
                            Manage the Pokémon cards you own.
                        </p>
                    </div>

                    {hasCards && summary && (
                        <div className="flex flex-wrap gap-6 text-sm">
                            <div>
                                <p className="text-muted-foreground">Cards</p>

                                <p className="text-lg font-semibold">
                                    {summary.total_cards.toLocaleString()}
                                </p>
                            </div>

                            <div>
                                <p className="text-muted-foreground">Unique</p>

                                <p className="text-lg font-semibold">
                                    {summary.unique_cards.toLocaleString()}
                                </p>
                            </div>

                            <div>
                                <p className="text-muted-foreground">Sets</p>

                                <p className="text-lg font-semibold">
                                    {summary.sets_collected.toLocaleString()}
                                </p>
                            </div>

                            <div>
                                <p className="text-muted-foreground">Value</p>

                                <p className="text-lg font-semibold">
                                    {formatCurrency(summary.portfolio_value)}
                                </p>
                            </div>
                        </div>
                    )}
                </div>

                {loading ? (
                    <div className="flex min-h-[400px] items-center justify-center">
                        <p className="text-muted-foreground">
                            Loading your collection...
                        </p>
                    </div>
                ) : error ? (
                    /* Load Failure */
                    <div className="border-destructive/30 bg-destructive/5 flex flex-1 items-center justify-center rounded-2xl border p-8">
                        <div className="max-w-md text-center">
                            <div className="bg-destructive/10 text-destructive mx-auto flex size-16 items-center justify-center rounded-2xl">
                                <AlertTriangle className="size-8" />
                            </div>

                            <h2 className="mt-5 text-xl font-semibold">
                                Something went wrong
                            </h2>

                            <p className="text-muted-foreground mt-2 text-sm leading-6">
                                {error}
                            </p>

                            <button
                                type="button"
                                onClick={() => window.location.reload()}
                                className="bg-primary text-primary-foreground mt-6 inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-medium transition-opacity hover:opacity-90"
                            >
                                Try Again
                            </button>
                        </div>
                    </div>
                ) : total === 0 ? (
                    /* Empty State */
                    <div className="bg-card/50 flex flex-1 items-center justify-center rounded-2xl border border-dashed p-8">
                        <div className="max-w-md text-center">
                            <div className="bg-primary/10 text-primary mx-auto flex size-16 items-center justify-center rounded-2xl">
                                <Search className="size-8" />
                            </div>

                            <h2 className="mt-5 text-xl font-semibold">
                                Your collection is empty
                            </h2>

                            <p className="text-muted-foreground mt-2 text-sm leading-6">
                                Search for a Pokémon card and add it to your
                                collection to start building your DexVault.
                            </p>

                            <Link
                                href="/cards"
                                className="bg-primary text-primary-foreground mt-6 inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-medium transition-opacity hover:opacity-90"
                            >
                                <Search className="size-4" />
                                Search Cards
                            </Link>
                        </div>
                    </div>
                ) : (
                    <>
                        {/* Search & Sort */}
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />

                                <input
                                    type="search"
                                    value={searchInput}
                                    onChange={(event) =>
                                        setSearchInput(event.target.value)
                                    }
                                    placeholder="Search your collection..."
                                    aria-label="Search your collection"
                                    className="bg-background focus:border-primary focus:ring-primary/20 h-10 w-full rounded-lg border pr-4 pl-9 text-sm transition outline-none focus:ring-2"
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <SlidersHorizontal className="text-muted-foreground size-4" />

                                <select
                                    value={sort}
                                    onChange={(event) => {
                                        setSort(
                                            event.target.value as SortOption,
                                        );
                                        setPage(1);
                                    }}
                                    aria-label="Sort your collection"
                                    className="bg-background focus:border-primary focus:ring-primary/20 h-10 rounded-lg border px-3 text-sm transition outline-none focus:ring-2"
                                >
                                    <option value="recent">
                                        Recently Added
                                    </option>

                                    <option value="name">Name</option>

                                    <option value="price-high">
                                        Price: High to Low
                                    </option>

                                    <option value="price-low">
                                        Price: Low to High
                                    </option>

                                    <option value="quantity">Quantity</option>
                                </select>
                            </div>
                        </div>

                        {/* Result Count */}
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-sm">
                                {meta?.from !== null && meta?.from !== undefined
                                    ? `Showing ${meta.from}–${meta.to} of ${total}`
                                    : `${total} ${total === 1 ? 'card' : 'cards'}`}
                                {searchLabel}
                            </p>

                            <div className="text-muted-foreground hidden items-center gap-1 text-xs sm:flex">
                                <ArrowUpDown className="size-3" />
                                Sorted by {SORT_LABELS[sort]}
                            </div>
                        </div>

                        {/* Card Grid */}
                        {cards.length === 0 ? (
                            <div className="rounded-2xl border border-dashed p-10 text-center">
                                <Search className="text-muted-foreground/50 mx-auto size-8" />

                                <h2 className="mt-4 font-semibold">
                                    No cards found
                                </h2>

                                <p className="text-muted-foreground mt-1 text-sm">
                                    {currentPage > 1
                                        ? 'This page is past the end of your collection.'
                                        : 'Try a different card name, set, or card number.'}
                                </p>

                                {currentPage > 1 && (
                                    <button
                                        type="button"
                                        onClick={() => setPage(1)}
                                        className="bg-primary text-primary-foreground mt-6 inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-medium transition-opacity hover:opacity-90"
                                    >
                                        Back to first page
                                    </button>
                                )}
                            </div>
                        ) : (
                            <div
                                aria-busy={pending}
                                className={`grid gap-5 transition-opacity sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 ${
                                    pending ? 'opacity-60' : ''
                                }`}
                            >
                                {cards.map((userCard) => {
                                    const marketPrice = Number(
                                        userCard.market_price ?? 0,
                                    );

                                    const totalValue =
                                        marketPrice * userCard.quantity;

                                    return (
                                        <div
                                            key={userCard.id}
                                            className="group bg-card overflow-hidden rounded-2xl border shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-xl"
                                        >
                                            {/* Card Image */}
                                            <Link
                                                href={`/cards/${userCard.card.external_id}`}
                                                className="bg-muted block overflow-hidden"
                                            >
                                                {userCard.card.image_large ||
                                                userCard.card.image_small ? (
                                                    <img
                                                        src={
                                                            userCard.card
                                                                .image_large ??
                                                            userCard.card
                                                                .image_small ??
                                                            ''
                                                        }
                                                        alt={userCard.card.name}
                                                        className="aspect-[2.5/3.5] w-full object-contain p-3 transition duration-300 group-hover:scale-[1.03]"
                                                    />
                                                ) : (
                                                    <div className="text-muted-foreground flex aspect-[2.5/3.5] items-center justify-center text-sm">
                                                        No image
                                                    </div>
                                                )}
                                            </Link>

                                            {/* Card Information */}
                                            <div className="p-4">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="min-w-0">
                                                        <Link
                                                            href={`/cards/${userCard.card.external_id}`}
                                                            className="hover:text-primary line-clamp-1 font-semibold"
                                                        >
                                                            {userCard.card.name}
                                                        </Link>

                                                        <p className="text-muted-foreground mt-0.5 line-clamp-1 text-xs">
                                                            {
                                                                userCard.card
                                                                    .set.name
                                                            }
                                                        </p>
                                                    </div>

                                                    <span className="bg-muted shrink-0 rounded-md px-2 py-1 text-xs font-medium">
                                                        #{userCard.card.number}
                                                    </span>
                                                </div>

                                                <div className="mt-4 grid grid-cols-2 gap-2">
                                                    <div className="bg-muted/50 rounded-lg p-2.5">
                                                        <p className="text-muted-foreground text-[11px]">
                                                            Condition
                                                        </p>

                                                        <p className="mt-0.5 truncate text-xs font-medium">
                                                            {userCard.condition}
                                                        </p>
                                                    </div>

                                                    <div className="bg-muted/50 rounded-lg p-2.5">
                                                        <p className="text-muted-foreground text-[11px]">
                                                            Quantity
                                                        </p>

                                                        <p className="mt-0.5 text-xs font-medium">
                                                            {userCard.quantity}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div className="mt-3 flex items-end justify-between border-t pt-3">
                                                    <div>
                                                        <p className="text-muted-foreground text-[11px]">
                                                            Market Price
                                                        </p>

                                                        <p className="mt-0.5 font-semibold">
                                                            {marketPrice > 0
                                                                ? formatCurrency(
                                                                      marketPrice,
                                                                  )
                                                                : 'N/A'}
                                                        </p>
                                                    </div>

                                                    <div className="text-right">
                                                        <p className="text-muted-foreground text-[11px]">
                                                            Total Value
                                                        </p>

                                                        <p className="text-primary mt-0.5 font-semibold">
                                                            {marketPrice > 0
                                                                ? formatCurrency(
                                                                      totalValue,
                                                                  )
                                                                : 'N/A'}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        {/* Pagination */}
                        {lastPage > 1 && (
                            <nav
                                aria-label="Collection pages"
                                className="flex items-center justify-center gap-1 pt-2"
                            >
                                <button
                                    type="button"
                                    onClick={() => setPage(currentPage - 1)}
                                    disabled={currentPage <= 1 || pending}
                                    aria-label="Previous page"
                                    className="border-muted hover:bg-accent inline-flex size-9 items-center justify-center rounded-lg border transition disabled:pointer-events-none disabled:opacity-40"
                                >
                                    <ChevronLeft className="size-4" />
                                </button>

                                {pageWindow(currentPage, lastPage).map(
                                    (entry, index) =>
                                        entry === 'gap' ? (
                                            <span
                                                key={`gap-${index}`}
                                                className="text-muted-foreground px-1 text-sm"
                                            >
                                                …
                                            </span>
                                        ) : (
                                            <button
                                                key={entry}
                                                type="button"
                                                onClick={() => setPage(entry)}
                                                disabled={pending}
                                                aria-current={
                                                    entry === currentPage
                                                        ? 'page'
                                                        : undefined
                                                }
                                                className={`inline-flex size-9 items-center justify-center rounded-lg text-sm font-medium transition ${
                                                    entry === currentPage
                                                        ? 'bg-primary text-primary-foreground'
                                                        : 'border-muted hover:bg-accent border'
                                                }`}
                                            >
                                                {entry}
                                            </button>
                                        ),
                                )}

                                <button
                                    type="button"
                                    onClick={() => setPage(currentPage + 1)}
                                    disabled={
                                        currentPage >= lastPage || pending
                                    }
                                    aria-label="Next page"
                                    className="border-muted hover:bg-accent inline-flex size-9 items-center justify-center rounded-lg border transition disabled:pointer-events-none disabled:opacity-40"
                                >
                                    <ChevronRight className="size-4" />
                                </button>
                            </nav>
                        )}
                    </>
                )}
            </div>
        </>
    );
}
