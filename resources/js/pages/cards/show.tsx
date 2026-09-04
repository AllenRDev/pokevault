import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowUpRight,
    BarChart3,
    DollarSign,
    Plus,
    Sparkles,
} from 'lucide-react';

interface CardSet {
    external_id: string;
    name: string;
    series: string | null;
    printed_total: number | null;
    release_date: string | null;
    logo_url: string | null;
    symbol_url: string | null;
}

interface TcgPlayerVariant {
    low: number | null;
    mid: number | null;
    high: number | null;
    market: number | null;
    direct_low: number | null;
}

interface TcgPlayerPrices {
    updated: string | null;
    unit: string;
    normal: TcgPlayerVariant | null;
    reverse: TcgPlayerVariant | null;
    holo: TcgPlayerVariant | null;
    first_edition: TcgPlayerVariant | null;
    first_edition_holo: TcgPlayerVariant | null;
    unlimited: TcgPlayerVariant | null;
    unlimited_holo: TcgPlayerVariant | null;
}

interface CardmarketPrices {
    updated: string | null;
    unit: string;
    average: number | null;
    low: number | null;
    trend: number | null;
    average_1d: number | null;
    average_7d: number | null;
    average_30d: number | null;
    average_holo: number | null;
    low_holo: number | null;
    trend_holo: number | null;
    average_1d_holo: number | null;
    average_7d_holo: number | null;
    average_30d_holo: number | null;
}

interface CardPrices {
    tcgplayer?: TcgPlayerPrices;
    cardmarket?: CardmarketPrices;
}

interface PokemonCard {
    external_id: string;
    name: string;
    number: string;
    rarity: string | null;
    image_small: string | null;
    image_large: string | null;
    prices: CardPrices | null;
    set: CardSet;
}

interface Props {
    card: PokemonCard;
}

function formatUsd(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value);
}

function formatEur(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return new Intl.NumberFormat('de-DE', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}

function PriceStat({
    label,
    value,
}: {
    label: string;
    value: string;
}) {
    return (
        <div className="rounded-2xl border bg-background/60 p-4 dark:border-red-500/10">
            <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                {label}
            </p>

            <p className="mt-1 text-lg font-bold">
                {value}
            </p>
        </div>
    );
}

export default function CardShow({ card }: Props) {
    const tcgplayer = card.prices?.tcgplayer;
    const cardmarket = card.prices?.cardmarket;

    // USD / TCGplayer is the primary price for PokéVault.
    // Fall back from normal market price to holo market price.
    const primaryPrice =
        tcgplayer?.normal?.market ??
        tcgplayer?.holo?.market ??
        null;

    const primaryPriceLabel =
        tcgplayer?.normal?.market !== null &&
        tcgplayer?.normal?.market !== undefined
            ? 'TCGplayer Market'
            : tcgplayer?.holo?.market !== null &&
                tcgplayer?.holo?.market !== undefined
              ? 'TCGplayer Holo Market'
              : 'Price unavailable';

    return (
        <>
            <Head title={card.name} />

            <div className="min-h-screen bg-gradient-to-br from-background via-background to-primary/5 dark:from-background dark:via-background dark:to-red-950/10">
                <div className="mx-auto max-w-6xl px-6 py-10 lg:py-14">
                    <Link
                        href="/cards"
                        className="mb-8 inline-flex items-center gap-2 text-sm font-medium text-muted-foreground transition-colors hover:text-primary"
                    >
                        <ArrowLeft className="size-4" />
                        Back to Card Search
                    </Link>

                    <div className="grid gap-10 lg:grid-cols-[minmax(300px,460px)_1fr] lg:items-start">
                        {/* Card image */}
                        <div className="relative">
                            <div className="absolute -inset-6 rounded-[3rem] bg-gradient-to-br from-primary/20 via-purple-500/10 to-blue-500/20 blur-3xl dark:from-red-600/20 dark:via-red-950/20 dark:to-purple-900/20" />

                            <div className="relative overflow-hidden rounded-3xl border bg-card p-4 shadow-2xl dark:border-red-500/20 dark:shadow-red-950/20">
                                {card.image_large ? (
                                    <img
                                        src={card.image_large}
                                        alt={card.name}
                                        className="mx-auto w-full max-w-md rounded-2xl"
                                    />
                                ) : (
                                    <div className="flex aspect-[2.5/3.5] items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                                        No image available
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Card information */}
                        <div className="lg:pt-6">
                            <div className="mb-4 flex flex-wrap items-center gap-2">
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                                    <Sparkles className="size-3.5" />
                                    Pokémon TCG
                                </span>

                                {card.rarity && (
                                    <span className="rounded-full bg-purple-500/10 px-3 py-1 text-xs font-semibold text-purple-600 dark:bg-red-500/10 dark:text-red-400">
                                        {card.rarity}
                                    </span>
                                )}
                            </div>

                            <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">
                                {card.name}
                            </h1>

                            <p className="mt-3 text-lg text-muted-foreground">
                                {card.set.name}
                            </p>

                            <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <div className="rounded-2xl border bg-card/80 p-4 dark:border-red-500/10">
                                    <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                        Card #
                                    </p>

                                    <p className="mt-1 text-lg font-semibold">
                                        {card.number}
                                        {card.set.printed_total
                                            ? ` / ${card.set.printed_total}`
                                            : ''}
                                    </p>
                                </div>

                                <div className="rounded-2xl border bg-card/80 p-4 dark:border-red-500/10">
                                    <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                        Set
                                    </p>

                                    <p className="mt-1 truncate text-lg font-semibold">
                                        {card.set.name}
                                    </p>
                                </div>

                                {card.set.series && (
                                    <div className="rounded-2xl border bg-card/80 p-4 dark:border-red-500/10">
                                        <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            Series
                                        </p>

                                        <p className="mt-1 truncate text-lg font-semibold">
                                            {card.set.series}
                                        </p>
                                    </div>
                                )}
                            </div>

                            {/* Pricing */}
                            <div className="mt-8 overflow-hidden rounded-3xl border bg-card shadow-sm dark:border-red-500/20">
                                {/* Primary USD price */}
                                <div className="border-b bg-gradient-to-r from-blue-500/5 to-purple-500/10 p-6 dark:from-red-950/30 dark:to-purple-950/30">
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <div className="flex size-10 items-center justify-center rounded-xl bg-blue-500/10 text-blue-500 dark:bg-red-500/10 dark:text-red-400">
                                                    <DollarSign className="size-5" />
                                                </div>

                                                <div>
                                                    <p className="text-sm font-semibold text-muted-foreground">
                                                        Current Market Value
                                                    </p>

                                                    <p className="text-3xl font-black tracking-tight">
                                                        {primaryPrice !== null
                                                            ? formatUsd(
                                                                  primaryPrice,
                                                              )
                                                            : 'No price'}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        {primaryPrice !== null && (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-green-500/10 px-3 py-1.5 text-xs font-semibold text-green-600 dark:text-green-400">
                                                <ArrowUpRight className="size-3.5" />
                                                Market
                                            </span>
                                        )}
                                    </div>

                                    <p className="mt-4 text-xs text-muted-foreground">
                                        {primaryPriceLabel} · USD
                                    </p>
                                </div>

                                {/* TCGplayer Normal */}
                                {tcgplayer?.normal && (
                                    <div className="p-6">
                                        <div className="mb-4 flex items-center justify-between">
                                            <div>
                                                <h2 className="font-semibold">
                                                    TCGplayer
                                                </h2>

                                                <p className="text-xs text-muted-foreground">
                                                    USD
                                                </p>
                                            </div>

                                            <span className="rounded-full bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-600 dark:bg-red-500/10 dark:text-red-400">
                                                Normal
                                            </span>
                                        </div>

                                        <div className="grid grid-cols-3 gap-3">
                                            <PriceStat
                                                label="Low"
                                                value={formatUsd(
                                                    tcgplayer.normal.low,
                                                )}
                                            />

                                            <PriceStat
                                                label="Market"
                                                value={formatUsd(
                                                    tcgplayer.normal.market,
                                                )}
                                            />

                                            <PriceStat
                                                label="High"
                                                value={formatUsd(
                                                    tcgplayer.normal.high,
                                                )}
                                            />
                                        </div>
                                    </div>
                                )}

                                {/* TCGplayer Holo */}
                                {tcgplayer?.holo && (
                                    <div className="border-t p-6 dark:border-red-500/10">
                                        <div className="mb-4 flex items-center justify-between">
                                            <div>
                                                <h2 className="font-semibold">
                                                    Holo Pricing
                                                </h2>

                                                <p className="text-xs text-muted-foreground">
                                                    TCGplayer · USD
                                                </p>
                                            </div>

                                            <span className="rounded-full bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-600 dark:bg-red-500/10 dark:text-red-400">
                                                Holo
                                            </span>
                                        </div>

                                        <div className="grid grid-cols-3 gap-3">
                                            <PriceStat
                                                label="Low"
                                                value={formatUsd(
                                                    tcgplayer.holo.low,
                                                )}
                                            />

                                            <PriceStat
                                                label="Market"
                                                value={formatUsd(
                                                    tcgplayer.holo.market,
                                                )}
                                            />

                                            <PriceStat
                                                label="High"
                                                value={formatUsd(
                                                    tcgplayer.holo.high,
                                                )}
                                            />
                                        </div>
                                    </div>
                                )}

                                {/* Cardmarket */}
                                {cardmarket && (
                                    <div className="border-t p-6 dark:border-red-500/10">
                                        <div className="mb-4 flex items-center justify-between">
                                            <div>
                                                <h2 className="font-semibold">
                                                    Cardmarket
                                                </h2>

                                                <p className="text-xs text-muted-foreground">
                                                    EUR · European market
                                                </p>
                                            </div>

                                            <span className="rounded-full bg-yellow-500/10 px-2.5 py-1 text-xs font-semibold text-yellow-600 dark:bg-red-500/10 dark:text-red-400">
                                                Europe
                                            </span>
                                        </div>

                                        <div className="grid grid-cols-3 gap-3">
                                            <PriceStat
                                                label="Low"
                                                value={formatEur(
                                                    cardmarket.low,
                                                )}
                                            />

                                            <PriceStat
                                                label="Average"
                                                value={formatEur(
                                                    cardmarket.average,
                                                )}
                                            />

                                            <PriceStat
                                                label="Trend"
                                                value={formatEur(
                                                    cardmarket.trend,
                                                )}
                                            />
                                        </div>

                                        <div className="mt-4 grid grid-cols-3 gap-3">
                                            <PriceStat
                                                label="1 Day"
                                                value={formatEur(
                                                    cardmarket.average_1d,
                                                )}
                                            />

                                            <PriceStat
                                                label="7 Days"
                                                value={formatEur(
                                                    cardmarket.average_7d,
                                                )}
                                            />

                                            <PriceStat
                                                label="30 Days"
                                                value={formatEur(
                                                    cardmarket.average_30d,
                                                )}
                                            />
                                        </div>
                                    </div>
                                )}

                                {/* No pricing */}
                                {!card.prices && (
                                    <div className="p-8 text-center">
                                        <BarChart3 className="mx-auto size-8 text-muted-foreground/50" />

                                        <h2 className="mt-3 font-semibold">
                                            Pricing unavailable
                                        </h2>

                                        <p className="mt-1 text-sm text-muted-foreground">
                                            This card isn't currently listed
                                            with our supported marketplaces.
                                        </p>
                                    </div>
                                )}
                            </div>

                            {/* Price history */}
                            <div className="mt-6 rounded-3xl border bg-card p-6 dark:border-red-500/10">
                                <div className="flex items-center gap-3">
                                    <div className="flex size-10 items-center justify-center rounded-xl bg-purple-500/10 text-purple-500 dark:bg-red-500/10 dark:text-red-400">
                                        <BarChart3 className="size-5" />
                                    </div>

                                    <div>
                                        <h2 className="font-semibold">
                                            Price History
                                        </h2>

                                        <p className="text-sm text-muted-foreground">
                                            Historical price tracking is coming
                                            next.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Collection CTA */}
                            <div className="mt-6 rounded-3xl border bg-gradient-to-br from-primary/10 via-purple-500/5 to-blue-500/10 p-6 dark:border-red-500/20 dark:from-red-950/40 dark:via-red-950/20 dark:to-purple-950/30">
                                <div className="flex items-start gap-4">
                                    <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-lg dark:shadow-red-950/40">
                                        <Plus className="size-5" />
                                    </div>

                                    <div className="flex-1">
                                        <h2 className="font-semibold">
                                            Add this card to your collection
                                        </h2>

                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Start building your PokéVault
                                            collection by adding cards you own.
                                        </p>

                                        <button
                                            type="button"
                                            className="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-lg transition-all hover:-translate-y-0.5 hover:shadow-xl dark:shadow-red-950/30"
                                        >
                                            <Plus className="size-4" />
                                            Add to Collection
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {/* Set information */}
                            <div className="mt-8">
                                <h2 className="text-lg font-semibold">
                                    Set Information
                                </h2>

                                <div className="mt-4 flex items-center gap-4 rounded-2xl border bg-card p-5 dark:border-red-500/10">
                                    {card.set.logo_url && (
                                        <img
                                            src={card.set.logo_url ?? card.set.symbol_url}
                                            alt={card.set.name}
                                            className="h-12 w-20 object-contain"
                                        />
                                    )}

                                    <div>
                                        <p className="font-semibold">
                                            {card.set.name}
                                        </p>

                                        {card.set.series && (
                                            <p className="text-sm text-muted-foreground">
                                                {card.set.series}
                                            </p>
                                        )}

                                        {card.set.release_date && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Released{' '}
                                                {card.set.release_date}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}