import type { Worker } from 'tesseract.js';

/**
 * What a single pass of text recognition found on a card.
 */
export interface CardReading {
    /** Everything the engine read, kept for debugging and manual correction. */
    raw: string;
    name: string | null;
    number: number | null;
    total: number | null;
    /** Engine confidence for the pass the fields were taken from, 0-100. */
    confidence: number;
}

/**
 * The largest edge handed to the engine.
 *
 * Tesseract slows down sharply with pixel count and gains little from a
 * bigger input than this once the text is legible, so oversized photos are
 * scaled down before recognition.
 */
const MAX_DIMENSION = 1200;

/**
 * The corner of a card front where the name is printed.
 *
 * Taken as fractions of the card so it holds up at any capture size. The
 * right edge is excluded because the HP value sits beside the name.
 */
const NAME_CROP = { top: 0, height: 0.22, width: 0.72 };

/**
 * Lines that are never a card name.
 */
const NOT_A_NAME = new Set([
    'basic',
    'stage',
    'stage1',
    'stage2',
    'ancient',
    'trainer',
    'supporter',
    'item',
    'energy',
    'pokemon',
    'pokémon',
    'ex',
    'gx',
    'v',
    'vmax',
    'vstar',
    'star',
    'lv.x',
    'illustration',
    'illustrator',
]);

let workerPromise: Promise<Worker> | null = null;

/**
 * Where the engine and its language data are fetched from.
 *
 * Tesseract downloads the worker script, the compiled engine, and the
 * English language data on first use, and by default it takes all three
 * from a public CDN. That is fine for local use but makes recognition depend
 * on an outside host being reachable, so pointing
 * `VITE_TESSERACT_ASSET_BASE` at a self-hosted copy of the same files is
 * enough to remove the dependency.
 */
function assetOverrides(): {
    workerPath?: string;
    corePath?: string;
    langPath?: string;
} {
    const base = import.meta.env.VITE_TESSERACT_ASSET_BASE?.replace(/\/+$/, '');

    if (!base) {
        return {};
    }

    return {
        workerPath: `${base}/worker.min.js`,
        corePath: base,
        langPath: base,
    };
}

/**
 * Create the recognition worker on first use and reuse it afterwards.
 *
 * Loading the engine and its language data costs far more than a single
 * recognition pass, and a user on the scan page usually takes several photos
 * in a row, so the worker is kept for the life of the page.
 */
async function getWorker(
    onProgress?: (percent: number) => void,
): Promise<Worker> {
    workerPromise ??= import('tesseract.js')
        .then(({ createWorker }) =>
            createWorker('eng', undefined, {
                ...assetOverrides(),
                logger: (message) => {
                    if (message.status === 'recognizing text') {
                        onProgress?.(message.progress);
                    }
                },
            }),
        )
        .catch((error: unknown) => {
            // A failed load must not poison the cache for the next attempt.
            workerPromise = null;

            throw error;
        });

    return workerPromise;
}

/**
 * Draw a source image into a canvas of a workable size.
 */
function toCanvas(
    source: HTMLImageElement,
    crop?: { top: number; height: number; width: number },
    scale = 1,
): HTMLCanvasElement | null {
    const width = source.naturalWidth || source.width;
    const height = source.naturalHeight || source.height;

    if (!width || !height) {
        return null;
    }

    const region = crop
        ? {
              x: 0,
              y: Math.round(height * crop.top),
              w: Math.round(width * crop.width),
              h: Math.round(height * crop.height),
          }
        : { x: 0, y: 0, w: width, h: height };

    const shrink = Math.min(1, MAX_DIMENSION / Math.max(region.w, region.h));

    const canvas = document.createElement('canvas');

    canvas.width = Math.max(1, Math.round(region.w * shrink * scale));
    canvas.height = Math.max(1, Math.round(region.h * shrink * scale));

    const context = canvas.getContext('2d', { willReadFrequently: true });

    if (!context) {
        return null;
    }

    context.drawImage(
        source,
        region.x,
        region.y,
        region.w,
        region.h,
        0,
        0,
        canvas.width,
        canvas.height,
    );

    return canvas;
}

/**
 * Flatten to greyscale, stretch the contrast, and flip dark cards the right
 * way up.
 *
 * The engine expects dark ink on a light page. Foil cards and dark
 * printings break that assumption, and without this pass they read as noise
 * rather than text.
 */
function enhance(canvas: HTMLCanvasElement): HTMLCanvasElement | null {
    const context = canvas.getContext('2d', { willReadFrequently: true });

    if (!context) {
        return null;
    }

    const frame = context.getImageData(0, 0, canvas.width, canvas.height);
    const pixels = frame.data;

    let min = 255;
    let max = 0;
    let total = 0;
    let count = 0;

    const luma = new Uint8ClampedArray(pixels.length / 4);

    for (let index = 0; index < pixels.length; index += 4) {
        const value = Math.round(
            0.299 * pixels[index] +
                0.587 * pixels[index + 1] +
                0.114 * pixels[index + 2],
        );

        luma[index / 4] = value;

        min = Math.min(min, value);
        max = Math.max(max, value);
        total += value;
        count += 1;
    }

    /*
     * A very narrow range means a flat, washed-out region with no ink in it,
     * which stretching cannot rescue and would only amplify noise.
     */
    const span = max - min;
    const invert = count > 0 && total / count < 110 && span > 24;

    const scale = span > 24 ? 255 / span : 1;

    for (let index = 0; index < pixels.length; index += 4) {
        let value = (luma[index / 4] - min) * scale;

        if (invert) {
            value = 255 - value;
        }

        const clamped = Math.max(0, Math.min(255, Math.round(value)));

        pixels[index] = clamped;
        pixels[index + 1] = clamped;
        pixels[index + 2] = clamped;
    }

    context.putImageData(frame, 0, 0);

    return canvas;
}

/**
 * Tidy a line of recognised text into something usable as a card name.
 *
 * Card names carry the marks that separate Pokémon from anything else, so
 * letters, spaces, apostrophes, hyphens, and periods are kept and digits
 * are dropped along with a trailing HP reading.
 */
function cleanName(line: string): string | null {
    const stripped = line
        .replace(/\d+/g, ' ')
        .replace(/[^A-Za-zÀ-ÿ'’. -]/g, ' ')
        .replace(/\s*\bHP\b\s*/gi, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    if (stripped.length < 3) {
        return null;
    }

    const words = stripped.split(' ').filter(Boolean);

    if (words.every((word) => NOT_A_NAME.has(word.toLowerCase()))) {
        return null;
    }

    const meaningful = words.filter(
        (word) => word.length > 1 || /['’-]/.test(word),
    );

    if (meaningful.length === 0) {
        return null;
    }

    const name = meaningful.join(' ').slice(0, 60);

    /*
     * A real name is a word, not a few glyphs lifted out of artwork. It needs
     * several letters and more than one vowel, which is what separates
     * "Charizard" from noise like "lIl" or "I11". Requiring two rather than
     * one is deliberate: a single capital I reads as a vowel, so one vowel
     * would still admit the worst of the junk.
     */
    const letters = name.replace(/[^A-Za-z]/g, '');

    if (letters.length < 3 || (name.match(/[aeiouy]/gi) ?? []).length < 2) {
        return null;
    }

    return name;
}

/**
 * Pull the fields a scan needs out of recognised text.
 *
 * The `number/total` pair is matched leniently because the engine regularly
 * reads the slash as a backslash, a pipe, or a stray digit on a scuffed
 * corner.
 */
export function parseCardText(text: string): {
    name: string | null;
    number: number | null;
    total: number | null;
} {
    const lines = text
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);

    let name: string | null = null;

    for (const line of lines) {
        /*
         * The evolution line sits directly above the name and reads like one
         * at a glance, so it is skipped rather than cleaned into a false
         * match.
         */
        if (/evolves?\s*from/i.test(line)) {
            continue;
        }

        const candidate = cleanName(line);

        if (candidate) {
            name = candidate;

            break;
        }
    }

    let number: number | null = null;
    let total: number | null = null;

    const pair = text.match(/(\d{1,3})\s*[/\\|]\s*(\d{2,4})/);

    if (pair) {
        number = Number(pair[1]);
        total = Number(pair[2]);
    }

    /*
     * A pair where the total is the smaller number is left alone rather than
     * thrown out: promos are routinely numbered past the size of their set,
     * and a garbled pair costs nothing because the backend falls back to
     * matching on the number alone when the total resolves nothing.
     */

    return { name, number, total };
}

/**
 * Recognise a captured card photo and extract the fields used to match it.
 *
 * The whole card is read first because the number and total live in a corner.
 * The name is then read again from a crop when the first pass did not
 * produce a usable one, which is the common case for a busy card front.
 */
export async function readCard(
    dataUrl: string,
    onProgress?: (percent: number) => void,
): Promise<CardReading> {
    const image = new Image();

    await new Promise<void>((resolve, reject) => {
        image.onload = () => resolve();
        image.onerror = () =>
            reject(new Error('The captured photo could not be read.'));
        image.src = dataUrl;
    });

    const worker = await getWorker(onProgress);

    const run = async (
        canvas: HTMLCanvasElement,
    ): Promise<{ text: string; confidence: number }> => {
        const { data } = await worker.recognize(canvas);

        return { text: data.text, confidence: data.confidence };
    };

    const full = toCanvas(image);
    const enhanced = full ? enhance(full) : null;

    if (!enhanced) {
        throw new Error(
            'This browser blocked image processing. Please try again.',
        );
    }

    const whole = await run(enhanced);
    const parsed = parseCardText(whole.text);

    const base: CardReading = {
        raw: whole.text,
        name: parsed.name,
        number: parsed.number,
        total: parsed.total,
        confidence: whole.confidence,
    };

    if (base.name && base.number !== null) {
        return base;
    }

    /*
     * Fall back to the name region. It is enlarged because the name is the
     * one field worth spending a second pass on, and the card is otherwise
     * mostly artwork that only slows the engine down.
     */
    const cropped = toCanvas(image, NAME_CROP, 2);
    const sharp = cropped ? enhance(cropped) : null;

    if (!sharp) {
        return base;
    }

    const region = await run(sharp);
    const regionName = cleanName(
        region.text.split('\n').find((line) => line.trim()) ?? '',
    );

    if (!regionName) {
        return base;
    }

    return {
        ...base,
        name: regionName,
        raw: `${whole.text}\n---\n${region.text}`,
        confidence: Math.max(whole.confidence, region.confidence),
    };
}
