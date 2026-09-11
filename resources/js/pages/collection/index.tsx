import { Head, Link } from '@inertiajs/react';
import { ArrowUpDown, Search, SlidersHorizontal } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import AppLayout from '@/layouts/app-layout';

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
    condition: string | null;
    language: string;
    purchase_price: string | number | null;
    purchase_date: string | null;
    market_price: string | number | null;
    market_price_updated_at: string | null;
    notes: string | null;
    card: Card;
}

type SortOption =
    | 'recent'
    | 'name'
    | 'price-high'
    | 'price-low'
    | 'quantity';

export default function Index() {
    const [cards, setCards] = useState<UserCard[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [sort, setSort] = useState<SortOption>('recent');

    useEffect(() => {
        const loadCollection = async () => {
            try {
                const response = await fetch('/collection/cards');

                if (!response.ok) {
                    throw new Error('Failed to load your collection.');
                }

                const result: UserCard[] = await response.json();

                setCards(result);
            } catch (error) {
                console.error(
                    'Failed to load collection:',
                    error,
                );
            } finally {
                setLoading(false);
            }
        };

        loadCollection();
    }, []);

    const formatCurrency = (value: number | string | null) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(Number(value ?? 0));
    };

    const filteredCards = useMemo(() => {
        const query = search.trim().toLowerCase();

        const filtered = cards.filter((userCard) => {
            if (!query) {
                return true;
            }

            return (
                userCard.card.name.toLowerCase().includes(query) ||
                userCard.card.set.name.toLowerCase().includes(query) ||
                userCard.card.number.toLowerCase().includes(query)
            );
        });

        return [...filtered].sort((a, b) => {
            switch (sort) {
                case 'name':
                    return a.card.name.localeCompare(b.card.name);

                case 'price-high':
                    return (
                        Number(b.market_price ?? 0) -
                        Number(a.market_price ?? 0)
                    );

                case 'price-low':
                    return (
                        Number(a.market_price ?? 0) -
                        Number(b.market_price ?? 0)
                    );

                case 'quantity':
                    return b.quantity - a.quantity;

                case 'recent':
                default:
                    return b.id - a.id;
            }
        });
    }, [cards, search, sort]);

    const totalCards = cards.reduce(
        (total, card) => total + card.quantity,
        0,
    );

    const uniqueCards = cards.length;

    const collectionValue = cards.reduce(
        (total, card) =>
            total +
            card.quantity * Number(card.market_price ?? 0),
        0,
    );

    return (
        <AppLayout>
            <Head title="My Collection" />

            <div className="flex min-h-0 flex-1 flex-col gap-6 p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            My Collection
                        </h1>

                        <p className="mt-1 text-muted-foreground">
                            Manage the Pokémon cards you own.
                        </p>
                    </div>

                    {!loading && cards.length > 0 && (
                        <div className="flex gap-6 text-sm">
                            <div>
                                <p className="text-muted-foreground">
                                    Cards
                                </p>

                                <p className="text-lg font-semibold">
                                    {totalCards.toLocaleString()}
                                </p>
                            </div>

                            <div>
                                <p className="text-muted-foreground">
                                    Unique
                                </p>

                                <p className="text-lg font-semibold">
                                    {uniqueCards.toLocaleString()}
                                </p>
                            </div>

                            <div>
                                <p className="text-muted-foreground">
                                    Value
                                </p>

                                <p className="text-lg font-semibold">
                                    {formatCurrency(collectionValue)}
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
                ) : cards.length === 0 ? (
                    /* Empty State */
                    <div className="flex flex-1 items-center justify-center rounded-2xl border border-dashed bg-card/50 p-8">
                        <div className="max-w-md text-center">
                            <div className="mx-auto flex size-16 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                <Search className="size-8" />
                            </div>

                            <h2 className="mt-5 text-xl font-semibold">
                                Your collection is empty
                            </h2>

                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                Search for a Pokémon card and add it to your
                                collection to start building your PokéVault.
                            </p>

                            <Link
                                href="/cards"
                                className="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition-opacity hover:opacity-90"
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
                                <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />

                                <input
                                    type="text"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search your collection..."
                                    className="h-10 w-full rounded-lg border bg-background pl-9 pr-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <SlidersHorizontal className="size-4 text-muted-foreground" />

                                <select
                                    value={sort}
                                    onChange={(event) =>
                                        setSort(
                                            event.target
                                                .value as SortOption,
                                        )
                                    }
                                    className="h-10 rounded-lg border bg-background px-3 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                >
                                    <option value="recent">
                                        Recently Added
                                    </option>

                                    <option value="name">
                                        Name
                                    </option>

                                    <option value="price-high">
                                        Price: High to Low
                                    </option>

                                    <option value="price-low">
                                        Price: Low to High
                                    </option>

                                    <option value="quantity">
                                        Quantity
                                    </option>
                                </select>
                            </div>
                        </div>

                        {/* Result Count */}
                        <div className="flex items-center justify-between">
                            <p className="text-sm text-muted-foreground">
                                {filteredCards.length}{' '}
                                {filteredCards.length === 1
                                    ? 'card'
                                    : 'cards'}
                                {search && ` matching "${search}"`}
                            </p>

                            <div className="hidden items-center gap-1 text-xs text-muted-foreground sm:flex">
                                <ArrowUpDown className="size-3" />
                                Sorted by{' '}
                                {sort === 'recent'
                                    ? 'recently added'
                                    : sort === 'name'
                                      ? 'name'
                                      : sort === 'price-high'
                                        ? 'highest price'
                                        : sort === 'price-low'
                                          ? 'lowest price'
                                          : 'quantity'}
                            </div>
                        </div>

                        {/* Card Grid */}
                        {filteredCards.length === 0 ? (
                            <div className="rounded-2xl border border-dashed p-10 text-center">
                                <Search className="mx-auto size-8 text-muted-foreground/50" />

                                <h2 className="mt-4 font-semibold">
                                    No cards found
                                </h2>

                                <p className="mt-1 text-sm text-muted-foreground">
                                    Try a different card name, set, or card
                                    number.
                                </p>
                            </div>
                        ) : (
                            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {filteredCards.map((userCard) => {
                                    const marketPrice = Number(
                                        userCard.market_price ?? 0,
                                    );

                                    const totalValue =
                                        marketPrice *
                                        userCard.quantity;

                                    return (
                                        <div
                                            key={userCard.id}
                                            className="group overflow-hidden rounded-2xl border bg-card shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-xl"
                                        >
                                            {/* Card Image */}
                                            <Link
                                                href={`/cards/${userCard.card.external_id}`}
                                                className="block overflow-hidden bg-muted"
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
                                                        alt={
                                                            userCard.card.name
                                                        }
                                                        className="aspect-[2.5/3.5] w-full object-contain p-3 transition duration-300 group-hover:scale-[1.03]"
                                                    />
                                                ) : (
                                                    <div className="flex aspect-[2.5/3.5] items-center justify-center text-sm text-muted-foreground">
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
                                                            className="line-clamp-1 font-semibold hover:text-primary"
                                                        >
                                                            {
                                                                userCard.card
                                                                    .name
                                                            }
                                                        </Link>

                                                        <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">
                                                            {
                                                                userCard.card
                                                                    .set.name
                                                            }
                                                        </p>
                                                    </div>

                                                    <span className="shrink-0 rounded-md bg-muted px-2 py-1 text-xs font-medium">
                                                        #
                                                        {
                                                            userCard.card
                                                                .number
                                                        }
                                                    </span>
                                                </div>

                                                <div className="mt-4 grid grid-cols-2 gap-2">
                                                    <div className="rounded-lg bg-muted/50 p-2.5">
                                                        <p className="text-[11px] text-muted-foreground">
                                                            Condition
                                                        </p>

                                                        <p className="mt-0.5 truncate text-xs font-medium">
                                                            {userCard.condition ??
                                                                'Unknown'}
                                                        </p>
                                                    </div>

                                                    <div className="rounded-lg bg-muted/50 p-2.5">
                                                        <p className="text-[11px] text-muted-foreground">
                                                            Quantity
                                                        </p>

                                                        <p className="mt-0.5 text-xs font-medium">
                                                            {userCard.quantity}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div className="mt-3 flex items-end justify-between border-t pt-3">
                                                    <div>
                                                        <p className="text-[11px] text-muted-foreground">
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
                                                        <p className="text-[11px] text-muted-foreground">
                                                            Total Value
                                                        </p>

                                                        <p className="mt-0.5 font-semibold text-primary">
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
                    </>
                )}
            </div>
        </AppLayout>
    );
}
