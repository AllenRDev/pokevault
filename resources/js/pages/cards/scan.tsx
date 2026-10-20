import { Head, Link } from '@inertiajs/react';
import { Check, Loader2, ScanLine, Search } from 'lucide-react';
import { useRef, useState } from 'react';
import CardScanner, { type CardScannerHandle } from '@/components/CardScanner';
import { csrfToken } from '@/lib/csrf';
import { readCard, type CardReading } from '@/lib/ocr';

interface MatchCandidate {
    external_id: string;
    name: string;
    number: string;
    rarity: string | null;
    image_small: string | null;
    image_large: string | null;
    set: {
        external_id: string;
        name: string;
        series: string | null;
        printed_total: number | null;
    } | null;
}

interface MatchResponse {
    confidence: 'exact' | 'ambiguous' | 'none';
    candidates: MatchCandidate[];
    query: {
        name: string;
        number: number | null;
        total: number | null;
    };
}

type Stage = 'ready' | 'reading' | 'matching' | 'done' | 'failed';

export default function Scan() {
    const scannerRef = useRef<CardScannerHandle>(null);

    const [stage, setStage] = useState<Stage>('ready');
    const [progress, setProgress] = useState(0);
    const [reading, setReading] = useState<CardReading | null>(null);
    const [match, setMatch] = useState<MatchResponse | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [added, setAdded] = useState<string | null>(null);
    const [adding, setAdding] = useState<string | null>(null);

    const busy = stage === 'reading' || stage === 'matching';

    async function handleCapture(image: string) {
        setError(null);
        setMatch(null);
        setAdded(null);
        setReading(null);
        setProgress(0);

        try {
            setStage('reading');

            const result = await readCard(image, (percent) =>
                setProgress(Math.round(percent * 100)),
            );

            setReading(result);

            /*
             * Without a name there is nothing to look up. The number alone is
             * not enough, since every set has a card 4.
             */
            if (!result.name) {
                setStage('failed');
                setError(
                    'The card name could not be read. Try again with more light, filling the frame, and the card flat.',
                );

                return;
            }

            setStage('matching');

            const params = new URLSearchParams({ name: result.name });

            if (result.number !== null) {
                params.set('number', String(result.number));
            }

            if (result.total !== null) {
                params.set('total', String(result.total));
            }

            const response = await fetch(`/api/cards/match?${params}`);

            if (!response.ok) {
                throw new Error('The card lookup failed.');
            }

            const payload: MatchResponse = await response.json();

            setMatch(payload);
            setStage('done');
        } catch (caught) {
            console.error('Card recognition failed:', caught);
            setStage('failed');
            setError(
                'That card could not be identified. Try again with the card flat, filling the frame, and avoiding glare.',
            );
        }
    }

    async function addToCollection(candidate: MatchCandidate) {
        setAdding(candidate.external_id);
        setError(null);

        try {
            const response = await fetch('/collection/cards', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    external_id: candidate.external_id,
                    quantity: 1,
                    condition: 'Near Mint',
                    language: 'English',
                }),
            });

            if (!response.ok) {
                throw new Error('Unable to add the card.');
            }

            setAdded(candidate.external_id);
        } catch (caught) {
            console.error('Unable to add the card:', caught);
            setError('That card could not be added to your collection.');
        } finally {
            setAdding(null);
        }
    }

    function startOver() {
        setStage('ready');
        setReading(null);
        setMatch(null);
        setError(null);
        setAdded(null);
        setProgress(0);
        scannerRef.current?.retake();
    }

    const stageLabel =
        stage === 'reading'
            ? `Reading the card${progress > 0 ? ` (${progress}%)` : ''}...`
            : 'Looking up the card...';

    return (
        <>
            <Head title="Scan Card" />

            <div className="mx-auto w-full max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold tracking-tight">
                        Scan a Pokémon Card
                    </h1>

                    <p className="text-muted-foreground mt-2 text-sm">
                        Fill the frame with the card front, keep it flat, and
                        avoid glare. The name and card number are read from the
                        photo to find the exact printing.
                    </p>
                </div>

                <CardScanner
                    ref={scannerRef}
                    busy={busy}
                    onCapture={(image) => void handleCapture(image)}
                />

                {busy && (
                    <div
                        role="status"
                        className="text-muted-foreground mt-4 flex items-center justify-center gap-2 text-sm"
                    >
                        <Loader2 className="size-4 animate-spin" />
                        {stageLabel}
                    </div>
                )}

                {error && (
                    <div
                        role="alert"
                        className="border-destructive/30 bg-destructive/10 text-destructive mt-4 rounded-xl border p-4 text-sm"
                    >
                        <p>{error}</p>

                        <button
                            type="button"
                            onClick={startOver}
                            className="hover:bg-destructive/10 mt-3 rounded-lg border border-current px-4 py-2 text-sm font-medium transition"
                        >
                            Try Again
                        </button>
                    </div>
                )}

                {reading && stage !== 'reading' && (
                    <div className="bg-muted/50 mt-4 rounded-xl p-4 text-sm">
                        <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                            Text read
                        </p>

                        <dl className="mt-2 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                            <dt className="text-muted-foreground">Name</dt>

                            <dd className="font-medium">
                                {reading.name ?? (
                                    <span className="text-muted-foreground italic">
                                        not read
                                    </span>
                                )}
                            </dd>

                            <dt className="text-muted-foreground">Number</dt>

                            <dd className="font-medium">
                                {reading.number !== null &&
                                reading.total !== null ? (
                                    `${reading.number}/${reading.total}`
                                ) : (
                                    <span className="text-muted-foreground italic">
                                        not read
                                    </span>
                                )}
                            </dd>
                        </dl>

                        <details className="mt-3">
                            <summary className="text-muted-foreground cursor-pointer text-xs">
                                Show everything the scanner read
                            </summary>

                            <pre className="text-muted-foreground mt-2 max-h-40 overflow-auto text-xs whitespace-pre-wrap">
                                {reading.raw.trim()}
                            </pre>
                        </details>
                    </div>
                )}

                {match && stage === 'done' && (
                    <div className="mt-6">
                        <h2 className="flex items-center gap-2 text-lg font-semibold">
                            <ScanLine className="size-5" />

                            {match.confidence === 'exact'
                                ? 'Card identified'
                                : match.confidence === 'ambiguous'
                                  ? 'Pick the right printing'
                                  : 'No matching card found'}
                        </h2>

                        {match.confidence === 'exact' && (
                            <p className="text-muted-foreground mt-1 text-sm">
                                The name and card number matched a single
                                printing.
                            </p>
                        )}

                        {match.confidence === 'ambiguous' && (
                            <p className="text-muted-foreground mt-1 text-sm">
                                Several cards share this name and number. Choose
                                the one whose set looks right.
                            </p>
                        )}

                        {match.confidence === 'none' && (
                            <p className="text-muted-foreground mt-1 text-sm">
                                Nothing in the catalogue matched{' '}
                                <span className="font-medium">
                                    {match.query.name}
                                </span>
                                . You can still{' '}
                                <Link
                                    href={`/cards?search=${encodeURIComponent(match.query.name)}`}
                                    className="text-primary hover:underline"
                                >
                                    search for it
                                </Link>
                                .
                            </p>
                        )}

                        {match.candidates.length > 0 && (
                            <ul className="mt-4 space-y-3">
                                {match.candidates.map((candidate) => (
                                    <li
                                        key={candidate.external_id}
                                        className="bg-card flex items-center gap-4 rounded-xl border p-3"
                                    >
                                        <div className="bg-muted size-16 shrink-0 overflow-hidden rounded-lg">
                                            {candidate.image_small ||
                                            candidate.image_large ? (
                                                <img
                                                    src={
                                                        candidate.image_small ??
                                                        candidate.image_large ??
                                                        ''
                                                    }
                                                    alt={candidate.name}
                                                    className="size-full object-contain"
                                                />
                                            ) : null}
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <Link
                                                href={`/cards/${candidate.external_id}`}
                                                className="hover:text-primary line-clamp-1 font-semibold"
                                            >
                                                {candidate.name}
                                            </Link>

                                            <p className="text-muted-foreground mt-0.5 line-clamp-1 text-xs">
                                                {candidate.set
                                                    ? `${candidate.set.name} · #${candidate.number}`
                                                    : `#${candidate.number}`}
                                            </p>

                                            {candidate.set?.printed_total !=
                                                null && (
                                                <p className="text-muted-foreground mt-0.5 text-xs">
                                                    {
                                                        candidate.set
                                                            .printed_total
                                                    }{' '}
                                                    cards in set
                                                </p>
                                            )}
                                        </div>

                                        <button
                                            type="button"
                                            onClick={() =>
                                                void addToCollection(candidate)
                                            }
                                            disabled={
                                                adding !== null ||
                                                added !== null
                                            }
                                            className="bg-primary text-primary-foreground inline-flex shrink-0 items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition disabled:opacity-60"
                                        >
                                            {adding ===
                                            candidate.external_id ? (
                                                <Loader2 className="size-4 animate-spin" />
                                            ) : added ===
                                              candidate.external_id ? (
                                                <Check className="size-4" />
                                            ) : null}

                                            {added === candidate.external_id
                                                ? 'Added'
                                                : 'Add'}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {added && (
                            <p className="text-muted-foreground mt-4 flex items-center gap-2 text-sm">
                                <Check className="size-4 text-emerald-600" />
                                Added to your collection.{' '}
                                <Link
                                    href="/collection"
                                    className="text-primary hover:underline"
                                >
                                    View collection
                                </Link>
                            </p>
                        )}

                        <button
                            type="button"
                            onClick={startOver}
                            className="hover:bg-muted mt-5 inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition"
                        >
                            <Search className="size-4" />
                            Scan another card
                        </button>
                    </div>
                )}
            </div>
        </>
    );
}
