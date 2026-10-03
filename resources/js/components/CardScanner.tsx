import {
    useCallback,
    useEffect,
    useImperativeHandle,
    useRef,
    useState,
} from 'react';
import { Camera, RotateCcw } from 'lucide-react';
import { forwardRef } from 'react';

export interface CardScannerHandle {
    /** Discard the captured photo and reopen the camera. */
    retake: () => void;
}

interface CardScannerProps {
    onCapture?: (image: string) => void;
    /** Disables the capture button while the photo is being processed. */
    busy?: boolean;
}

/**
 * The camera the card is framed in.
 *
 * Photo capture only. Reading the card is the caller's job, which keeps the
 * recognition engine out of this component and lets it be loaded lazily.
 */
const CardScanner = forwardRef<CardScannerHandle, CardScannerProps>(
    function CardScanner({ onCapture, busy = false }, ref) {
        const videoRef = useRef<HTMLVideoElement>(null);
        const streamRef = useRef<MediaStream | null>(null);

        const [image, setImage] = useState<string | null>(null);
        const [error, setError] = useState<string | null>(null);
        const [notice, setNotice] = useState<string | null>(null);

        /*
         * Both the first run and a retry need the same camera setup, so the
         * acquisition lives here once. `cancelled` guards the window while
         * a permission prompt is open, during which the component may
         * already have unmounted and a granted stream would never be
         * released.
         */
        const openCamera = useCallback(async (): Promise<boolean> => {
            try {
                setError(null);

                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: 'environment' },
                    },
                    audio: false,
                });

                streamRef.current?.getTracks().forEach((track) => track.stop());
                streamRef.current = stream;

                if (videoRef.current) {
                    videoRef.current.srcObject = stream;
                }

                return true;
            } catch (err) {
                console.error('Unable to access camera:', err);
                setError(
                    'Unable to access your camera. Please make sure camera permissions are enabled.',
                );

                return false;
            }
        }, []);

        useEffect(() => {
            let cancelled = false;

            void openCamera().then((opened) => {
                if (cancelled && opened) {
                    streamRef.current
                        ?.getTracks()
                        .forEach((track) => track.stop());
                    streamRef.current = null;
                }
            });

            return () => {
                cancelled = true;
                streamRef.current?.getTracks().forEach((track) => track.stop());
                streamRef.current = null;
            };
        }, [openCamera]);

        useImperativeHandle(
            ref,
            () => ({
                retake: () => {
                    setImage(null);
                    setNotice(null);
                    void openCamera();
                },
            }),
            [openCamera],
        );

        function captureImage() {
            const video = videoRef.current;

            if (!video) {
                return;
            }

            /*
             * videoWidth/videoHeight stay 0 until the first frame is decoded.
             * Capturing then produces a 0x0 canvas and a broken image, so the
             * capture is refused with a retryable message instead.
             */
            if (video.videoWidth === 0 || video.videoHeight === 0) {
                setNotice(
                    'The camera is still warming up. Wait a moment and try again.',
                );

                return;
            }

            const canvas = document.createElement('canvas');

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            const context = canvas.getContext('2d');

            if (!context) {
                setNotice(
                    'This browser blocked image capture. Please try again.',
                );

                return;
            }

            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            let capturedImage: string;

            try {
                capturedImage = canvas.toDataURL('image/jpeg', 0.9);
            } catch (err) {
                console.error('Unable to encode captured image:', err);
                setNotice('The photo could not be saved. Please try again.');

                return;
            }

            setNotice(null);
            setImage(capturedImage);
            onCapture?.(capturedImage);

            streamRef.current?.getTracks().forEach((track) => track.stop());
            streamRef.current = null;
        }

        function retakeImage() {
            setImage(null);
            setNotice(null);
            void openCamera();
        }

        if (image) {
            return (
                <div className="space-y-4">
                    <div className="overflow-hidden rounded-xl border bg-black">
                        <img
                            src={image}
                            alt="Captured Pokémon card"
                            className="mx-auto max-h-[70vh] w-full object-contain"
                        />
                    </div>

                    <button
                        type="button"
                        onClick={retakeImage}
                        disabled={busy}
                        className="hover:bg-muted inline-flex w-full items-center justify-center gap-2 rounded-lg border px-4 py-3 font-medium transition disabled:opacity-50"
                    >
                        <RotateCcw className="h-5 w-5" />
                        Retake Photo
                    </button>
                </div>
            );
        }

        if (error) {
            return (
                <div className="border-destructive/30 bg-destructive/10 rounded-xl border p-6 text-center">
                    <p className="text-destructive text-sm">{error}</p>

                    <button
                        type="button"
                        onClick={() => void openCamera()}
                        className="hover:bg-muted mt-4 rounded-lg border px-4 py-2 text-sm font-medium transition"
                    >
                        Try Again
                    </button>
                </div>
            );
        }

        return (
            <div className="space-y-4">
                <div className="relative overflow-hidden rounded-xl border bg-black">
                    <video
                        ref={videoRef}
                        autoPlay
                        playsInline
                        muted
                        className="aspect-[3/4] w-full object-cover"
                    />

                    <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div className="aspect-[2.5/3.5] w-[65%] rounded-xl border-2 border-white/80 shadow-lg" />
                    </div>
                </div>

                {notice && (
                    <p
                        role="status"
                        className="border-destructive/30 bg-destructive/10 text-destructive rounded-lg border px-4 py-2.5 text-sm"
                    >
                        {notice}
                    </p>
                )}

                <button
                    type="button"
                    onClick={captureImage}
                    disabled={busy}
                    className="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-3 font-medium transition disabled:opacity-50"
                >
                    <Camera className="h-5 w-5" />
                    Capture Card
                </button>
            </div>
        );
    },
);

export default CardScanner;
