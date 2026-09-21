import { useEffect, useRef, useState } from 'react';
import { Camera, RotateCcw } from 'lucide-react';

interface CardScannerProps {
    onCapture?: (image: string) => void;
}

export default function CardScanner({ onCapture }: CardScannerProps) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);

    const [image, setImage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        startCamera();

        return () => {
            stopCamera();
        };
    }, []);

    async function startCamera() {
        try {
            setError(null);

            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                },
                audio: false,
            });

            streamRef.current = stream;

            if (videoRef.current) {
                videoRef.current.srcObject = stream;
            }
        } catch (err) {
            console.error('Unable to access camera:', err);
            setError(
                'Unable to access your camera. Please make sure camera permissions are enabled.',
            );
        }
    }

    function stopCamera() {
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
    }

    function captureImage() {
        const video = videoRef.current;

        if (!video) {
            return;
        }

        const canvas = document.createElement('canvas');

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        const context = canvas.getContext('2d');

        if (!context) {
            return;
        }

        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        const capturedImage = canvas.toDataURL('image/jpeg', 0.9);

        setImage(capturedImage);
        onCapture?.(capturedImage);

        stopCamera();
    }

    function retakeImage() {
        setImage(null);
        startCamera();
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
                    className="inline-flex w-full items-center justify-center gap-2 rounded-lg border px-4 py-3 font-medium transition hover:bg-muted"
                >
                    <RotateCcw className="h-5 w-5" />
                    Retake Photo
                </button>
            </div>
        );
    }

    if (error) {
        return (
            <div className="rounded-xl border border-destructive/30 bg-destructive/10 p-6 text-center">
                <p className="text-sm text-destructive">{error}</p>

                <button
                    type="button"
                    onClick={startCamera}
                    className="mt-4 rounded-lg border px-4 py-2 text-sm font-medium transition hover:bg-muted"
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

            <button
                type="button"
                onClick={captureImage}
                className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 font-medium text-primary-foreground transition hover:bg-primary/90"
            >
                <Camera className="h-5 w-5" />
                Capture Card
            </button>
        </div>
    );
}
