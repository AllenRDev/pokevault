import CardScanner from '@/components/CardScanner';
import { Head } from '@inertiajs/react';

export default function Scan() {
    return (
        <>
            <Head title="Scan Card" />

            <div className="mx-auto w-full max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold tracking-tight">
                        Scan a Pokémon Card
                    </h1>

                    <p className="mt-2 text-sm text-muted-foreground">
                        Position your card inside the frame and take a photo.
                    </p>
                </div>

                <CardScanner
                    onCapture={(image) => {
                        console.log('Captured card image:', image);
                    }}
                />
            </div>
        </>
    );
}