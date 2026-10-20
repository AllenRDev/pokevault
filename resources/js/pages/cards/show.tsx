import { Head } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { csrfToken } from '@/lib/csrf';

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
    holofoil: TcgPlayerVariant | null;
    reverse_holofoil: TcgPlayerVariant | null;
    first_edition: TcgPlayerVariant | null;
}

interface CardmarketPrices {
    updated: string | null;
    unit: string;
    avg: number | null;
    low: number | null;
    trend: number | null;
    avg1: number | null;
    avg7: number | null;
    avg30: number | null;
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
    isAuthenticated: boolean;
}

const conditions = [
    'Near Mint',
    'Lightly Played',
    'Moderately Played',
    'Heavily Played',
    'Damaged',
];

const languages = [
    'English',
    'Japanese',
    'Korean',
    'Chinese',
    'French',
    'German',
    'Italian',
    'Spanish',
    'Portuguese',
];

function formatPrice(
    value: number | null | undefined,
    currency = 'USD',
): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
    }).format(value);
}

function formatDate(date: string | null | undefined): string {
    if (!date) {
        return '—';
    }

    const parsed = new Date(date);

    if (Number.isNaN(parsed.getTime())) {
        return date;
    }

    return parsed.toLocaleDateString();
}

/**
 * Read a response body as JSON without throwing.
 *
 * Error responses are not always JSON — an expired CSRF token or a server
 * error returns HTML — so a parse failure must not replace the real status
 * with a JSON syntax error.
 */
async function readJson(response: Response): Promise<Record<string, unknown>> {
    try {
        const data = await response.json();

        return typeof data === 'object' && data !== null
            ? (data as Record<string, unknown>)
            : {};
    } catch {
        return {};
    }
}

/**
 * Build a readable message from a failed collection request.
 */
function requestError(
    response: Response,
    data: Record<string, unknown>,
    fallback: string,
): string {
    /*
     * A validation failure reports per-field messages, which are more useful
     * than the generic top-level one.
     */
    const errors = data.errors;

    if (typeof errors === 'object' && errors !== null) {
        const messages = Object.values(errors).flatMap((value) =>
            Array.isArray(value) ? value : [value],
        );

        const first = messages.find((message) => typeof message === 'string');

        if (first) {
            return first;
        }
    }

    if (typeof data.message === 'string' && data.message !== '') {
        return data.message;
    }

    return `${fallback} (HTTP ${response.status})`;
}

export default function Show({ card, isAuthenticated }: Props) {
    console.log(card);
    const [showCollectionModal, setShowCollectionModal] = useState(false);

    const [quantity, setQuantity] = useState(1);
    const [condition, setCondition] = useState('Near Mint');
    const [language, setLanguage] = useState('English');
    const [purchasePrice, setPurchasePrice] = useState('');
    const [purchaseDate, setPurchaseDate] = useState('');
    const [notes, setNotes] = useState('');

    const [saving, setSaving] = useState(false);
    const [success, setSuccess] = useState(false);
    const [error, setError] = useState('');

    const tcgplayer = card.prices?.tcgplayer;
    const cardmarket = card.prices?.cardmarket;

    const primaryMarketPrice =
        tcgplayer?.normal?.market ??
        tcgplayer?.holofoil?.market ??
        tcgplayer?.reverse_holofoil?.market ??
        tcgplayer?.first_edition?.market ??
        null;

    const openCollectionModal = () => {
        if (!isAuthenticated) {
            window.location.href = '/register';
            return;
        }

        setShowCollectionModal(true);
        setSuccess(false);
        setError('');
    };

    const closeCollectionModal = () => {
        if (saving) {
            return;
        }

        setShowCollectionModal(false);
        setError('');
        setSuccess(false);
    };

    const addCardToCollection = async (event: FormEvent) => {
        event.preventDefault();

        setSaving(true);
        setError('');

        const fallback = 'Unable to add card to your collection.';

        try {
            const response = await fetch('/collection/cards', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    external_id: card.external_id,
                    quantity,
                    condition,
                    language,
                    purchase_price: purchasePrice || null,
                    purchase_date: purchaseDate || null,
                    notes: notes.trim() || null,
                }),
            });

            const data = await readJson(response);

            if (!response.ok) {
                throw new Error(requestError(response, data, fallback));
            }

            setSuccess(true);
        } catch (err) {
            setError(err instanceof Error ? err.message : fallback);
        } finally {
            setSaving(false);
        }
    };

    return (
        <>
            <Head title={`${card.name} | DexVault`} />

            <div className="bg-background min-h-screen">
                <main className="mx-auto max-w-6xl px-6 py-10">
                    <div className="grid gap-10 lg:grid-cols-[380px_1fr]">
                        {/* Card image */}
                        <div className="flex items-start justify-center">
                            {card.image_large ? (
                                <img
                                    src={card.image_large}
                                    alt={card.name}
                                    className="h-auto w-full max-w-[380px] self-start rounded-2xl shadow-2xl transition-transform duration-300 hover:scale-[1.02]"
                                />
                            ) : (
                                <div className="bg-muted text-muted-foreground flex aspect-[2.5/3.5] w-full max-w-[380px] items-center justify-center rounded-2xl">
                                    No image available
                                </div>
                            )}
                        </div>

                        {/* Card information */}
                        <div>
                            <div className="mb-8">
                                <p className="text-muted-foreground mb-2 text-sm font-medium tracking-wider uppercase">
                                    {card.set.name}
                                </p>

                                <h1 className="text-4xl font-bold tracking-tight">
                                    {card.name}
                                </h1>

                                <div className="text-muted-foreground mt-3 flex flex-wrap gap-3 text-sm">
                                    <span>#{card.number}</span>

                                    {card.rarity && (
                                        <>
                                            <span>•</span>
                                            <span>{card.rarity}</span>
                                        </>
                                    )}
                                </div>
                            </div>

                            {/* Add to collection */}
                            <div className="mb-10">
                                <button
                                    type="button"
                                    onClick={openCollectionModal}
                                    className="w-full rounded-xl bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-4 text-lg font-semibold text-white shadow-lg transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl"
                                >
                                    + Add to My Collection
                                </button>
                            </div>

                            {/* Market price */}
                            <section className="mb-10">
                                <h2 className="mb-4 text-xl font-semibold">
                                    Market Price
                                </h2>

                                <div className="bg-card rounded-2xl border p-6 shadow-sm">
                                    <div className="flex items-end justify-between">
                                        <div>
                                            <p className="text-muted-foreground text-sm">
                                                Current Market
                                            </p>

                                            <p className="mt-1 text-4xl font-bold">
                                                {formatPrice(
                                                    primaryMarketPrice,
                                                )}
                                            </p>
                                        </div>

                                        <span className="bg-muted rounded-full px-3 py-1 text-xs font-medium">
                                            TCGplayer
                                        </span>
                                    </div>
                                </div>
                            </section>

                            {/* TCGplayer prices */}
                            {tcgplayer && (
                                <section className="mb-10">
                                    <div className="mb-4 flex items-center justify-between">
                                        <h2 className="text-xl font-semibold">
                                            TCGplayer Prices
                                        </h2>

                                        {tcgplayer.updated && (
                                            <span className="text-muted-foreground text-xs">
                                                Updated{' '}
                                                {formatDate(tcgplayer.updated)}
                                            </span>
                                        )}
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <div className="bg-card rounded-xl border p-5">
                                            <p className="text-muted-foreground text-sm">
                                                Low
                                            </p>

                                            <p className="mt-1 text-2xl font-semibold">
                                                {formatPrice(
                                                    tcgplayer.normal?.low ??
                                                        tcgplayer.holofoil
                                                            ?.low ??
                                                        tcgplayer
                                                            .reverse_holofoil
                                                            ?.low ??
                                                        tcgplayer.first_edition
                                                            ?.low,
                                                )}
                                            </p>
                                        </div>

                                        <div className="bg-card rounded-xl border p-5">
                                            <p className="text-muted-foreground text-sm">
                                                Market
                                            </p>

                                            <p className="mt-1 text-2xl font-semibold">
                                                {formatPrice(
                                                    tcgplayer.normal?.market ??
                                                        tcgplayer.holofoil
                                                            ?.market ??
                                                        tcgplayer
                                                            .reverse_holofoil
                                                            ?.market ??
                                                        tcgplayer.first_edition
                                                            ?.market,
                                                )}
                                            </p>
                                        </div>

                                        <div className="bg-card rounded-xl border p-5">
                                            <p className="text-muted-foreground text-sm">
                                                High
                                            </p>

                                            <p className="mt-1 text-2xl font-semibold">
                                                {formatPrice(
                                                    tcgplayer.normal?.high ??
                                                        tcgplayer.holofoil
                                                            ?.high ??
                                                        tcgplayer
                                                            .reverse_holofoil
                                                            ?.high ??
                                                        tcgplayer.first_edition
                                                            ?.high,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                </section>
                            )}

                            {/* Cardmarket */}
                            {cardmarket && (
                                <section className="mb-10">
                                    <h2 className="mb-4 text-xl font-semibold">
                                        Cardmarket
                                    </h2>

                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <div className="bg-card rounded-xl border p-5">
                                            <p className="text-muted-foreground text-sm">
                                                Average
                                            </p>

                                            <p className="mt-1 text-2xl font-semibold">
                                                {formatPrice(
                                                    cardmarket.avg,
                                                    'EUR',
                                                )}
                                            </p>
                                        </div>

                                        <div className="bg-card rounded-xl border p-5">
                                            <p className="text-muted-foreground text-sm">
                                                Low
                                            </p>

                                            <p className="mt-1 text-2xl font-semibold">
                                                {formatPrice(
                                                    cardmarket.low,
                                                    'EUR',
                                                )}
                                            </p>
                                        </div>

                                        <div className="bg-card rounded-xl border p-5">
                                            <p className="text-muted-foreground text-sm">
                                                Trend
                                            </p>

                                            <p className="mt-1 text-2xl font-semibold">
                                                {formatPrice(
                                                    cardmarket.trend,
                                                    'EUR',
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                </section>
                            )}

                            {/* Price history */}
                            <section className="mb-10">
                                <h2 className="mb-4 text-xl font-semibold">
                                    Price History
                                </h2>

                                <div className="bg-muted/30 text-muted-foreground flex h-56 items-center justify-center rounded-2xl border border-dashed text-sm">
                                    Historical price tracking coming soon.
                                </div>
                            </section>

                            {/* Set information */}
                            <section>
                                <h2 className="mb-4 text-xl font-semibold">
                                    Set Information
                                </h2>

                                <div className="bg-card rounded-2xl border p-6">
                                    <div className="flex items-center gap-4">
                                        {(card.set.logo_url ||
                                            card.set.symbol_url) && (
                                            <img
                                                src={
                                                    card.set.logo_url ||
                                                    card.set.symbol_url ||
                                                    ''
                                                }
                                                alt={card.set.name}
                                                className="h-12 w-20 object-contain"
                                            />
                                        )}

                                        <div>
                                            <h3 className="font-semibold">
                                                {card.set.name}
                                            </h3>

                                            {card.set.series && (
                                                <p className="text-muted-foreground text-sm">
                                                    {card.set.series}
                                                </p>
                                            )}

                                            {card.set.printed_total && (
                                                <p className="text-muted-foreground mt-1 text-sm">
                                                    {card.set.printed_total}{' '}
                                                    cards
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </main>
            </div>

            {/* Add to My Collection modal */}
            {showCollectionModal && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
                    onMouseDown={(event) => {
                        if (event.target === event.currentTarget) {
                            closeCollectionModal();
                        }
                    }}
                >
                    <div className="bg-background max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border shadow-2xl">
                        {/* Modal header */}
                        <div className="bg-background/95 sticky top-0 z-10 flex items-center justify-between border-b px-6 py-5 backdrop-blur">
                            <div>
                                <h2 className="text-xl font-bold">
                                    Add to My Collection
                                </h2>

                                <p className="text-muted-foreground text-sm">
                                    {card.name} #{card.number}
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={closeCollectionModal}
                                disabled={saving}
                                className="text-muted-foreground hover:bg-muted hover:text-foreground rounded-lg p-2 text-xl transition disabled:opacity-50"
                            >
                                ×
                            </button>
                        </div>

                        <div className="p-6">
                            {success ? (
                                <div className="py-10 text-center">
                                    <div className="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-green-500/15 text-3xl text-green-600">
                                        ✓
                                    </div>

                                    <h3 className="text-2xl font-bold">
                                        Card Added!
                                    </h3>

                                    <p className="text-muted-foreground mt-2">
                                        {card.name} has been added to your
                                        collection.
                                    </p>

                                    <button
                                        type="button"
                                        onClick={closeCollectionModal}
                                        className="bg-primary text-primary-foreground mt-7 rounded-xl px-6 py-3 font-semibold transition hover:opacity-90"
                                    >
                                        Done
                                    </button>
                                </div>
                            ) : (
                                <>
                                    {error && (
                                        <div className="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-600 dark:text-red-400">
                                            {error}
                                        </div>
                                    )}

                                    <form
                                        onSubmit={addCardToCollection}
                                        className="space-y-6"
                                    >
                                        {/* Quantity */}
                                        <div>
                                            <label
                                                htmlFor="quantity"
                                                className="mb-2 block text-sm font-semibold"
                                            >
                                                Quantity
                                            </label>

                                            <input
                                                id="quantity"
                                                type="number"
                                                min={1}
                                                max={999}
                                                value={quantity}
                                                onChange={(event) =>
                                                    setQuantity(
                                                        Math.max(
                                                            1,
                                                            Math.min(
                                                                999,
                                                                Number(
                                                                    event.target
                                                                        .value,
                                                                ) || 1,
                                                            ),
                                                        ),
                                                    )
                                                }
                                                className="bg-background focus:ring-primary w-full rounded-lg border px-3 py-2.5 outline-none focus:ring-2"
                                            />
                                        </div>

                                        {/* Condition + language */}
                                        <div className="grid gap-5 sm:grid-cols-2">
                                            <div>
                                                <label
                                                    htmlFor="condition"
                                                    className="mb-2 block text-sm font-semibold"
                                                >
                                                    Condition
                                                </label>

                                                <select
                                                    id="condition"
                                                    value={condition}
                                                    onChange={(event) =>
                                                        setCondition(
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="bg-background focus:ring-primary w-full rounded-lg border px-3 py-2.5 outline-none focus:ring-2"
                                                >
                                                    {conditions.map((item) => (
                                                        <option
                                                            key={item}
                                                            value={item}
                                                        >
                                                            {item}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>

                                            <div>
                                                <label
                                                    htmlFor="language"
                                                    className="mb-2 block text-sm font-semibold"
                                                >
                                                    Language
                                                </label>

                                                <select
                                                    id="language"
                                                    value={language}
                                                    onChange={(event) =>
                                                        setLanguage(
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="bg-background focus:ring-primary w-full rounded-lg border px-3 py-2.5 outline-none focus:ring-2"
                                                >
                                                    {languages.map((item) => (
                                                        <option
                                                            key={item}
                                                            value={item}
                                                        >
                                                            {item}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        </div>

                                        {/* Purchase information */}
                                        <div>
                                            <h3 className="mb-3 text-sm font-semibold">
                                                Purchase Information
                                            </h3>

                                            <div className="grid gap-5 sm:grid-cols-2">
                                                <div>
                                                    <label
                                                        htmlFor="purchase-price"
                                                        className="mb-2 block text-sm font-medium"
                                                    >
                                                        Purchase Price
                                                        <span className="text-muted-foreground ml-1 font-normal">
                                                            (optional)
                                                        </span>
                                                    </label>

                                                    <div className="relative">
                                                        <span className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 -translate-y-1/2">
                                                            $
                                                        </span>

                                                        <input
                                                            id="purchase-price"
                                                            type="number"
                                                            min={0}
                                                            step="0.01"
                                                            value={
                                                                purchasePrice
                                                            }
                                                            onChange={(event) =>
                                                                setPurchasePrice(
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="0.00"
                                                            className="bg-background focus:ring-primary w-full rounded-lg border py-2.5 pr-3 pl-7 outline-none focus:ring-2"
                                                        />
                                                    </div>
                                                </div>

                                                <div>
                                                    <label
                                                        htmlFor="purchase-date"
                                                        className="mb-2 block text-sm font-medium"
                                                    >
                                                        Purchase Date
                                                        <span className="text-muted-foreground ml-1 font-normal">
                                                            (optional)
                                                        </span>
                                                    </label>

                                                    <input
                                                        id="purchase-date"
                                                        type="date"
                                                        value={purchaseDate}
                                                        onChange={(event) =>
                                                            setPurchaseDate(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="bg-background focus:ring-primary w-full rounded-lg border px-3 py-2.5 outline-none focus:ring-2"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        {/* Notes */}
                                        <div>
                                            <label
                                                htmlFor="notes"
                                                className="mb-2 block text-sm font-semibold"
                                            >
                                                Notes
                                                <span className="text-muted-foreground ml-1 font-normal">
                                                    (optional)
                                                </span>
                                            </label>

                                            <textarea
                                                id="notes"
                                                value={notes}
                                                onChange={(event) =>
                                                    setNotes(event.target.value)
                                                }
                                                placeholder="Anything you want to remember about this card..."
                                                rows={4}
                                                maxLength={2000}
                                                className="bg-background focus:ring-primary w-full resize-none rounded-lg border px-3 py-2.5 outline-none focus:ring-2"
                                            />
                                        </div>

                                        {/* Submit */}
                                        <div className="flex gap-3 border-t pt-5">
                                            <button
                                                type="button"
                                                onClick={closeCollectionModal}
                                                disabled={saving}
                                                className="hover:bg-muted flex-1 rounded-xl border px-4 py-3 font-semibold transition disabled:opacity-50"
                                            >
                                                Cancel
                                            </button>

                                            <button
                                                type="submit"
                                                disabled={saving}
                                                className="flex-1 rounded-xl bg-gradient-to-r from-blue-600 to-purple-600 px-4 py-3 font-semibold text-white shadow-md transition hover:-translate-y-0.5 hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                {saving
                                                    ? 'Adding...'
                                                    : 'Add Card'}
                                            </button>
                                        </div>
                                    </form>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
