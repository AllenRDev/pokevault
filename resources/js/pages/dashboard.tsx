import { Head } from '@inertiajs/react';
import { dashboard } from '@/routes';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Welcome to PokéVault
                    </h1>

                    <p className="mt-1 text-muted-foreground">
                        Track, manage, and explore your Pokémon TCG collection.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-xl border p-6">
                        <p className="text-sm text-muted-foreground">
                            Cards Collected
                        </p>
                        <p className="mt-2 text-3xl font-semibold">0</p>
                    </div>

                    <div className="rounded-xl border p-6">
                        <p className="text-sm text-muted-foreground">
                            Sets Collected
                        </p>
                        <p className="mt-2 text-3xl font-semibold">0</p>
                    </div>

                    <div className="rounded-xl border p-6">
                        <p className="text-sm text-muted-foreground">
                            Collection Value
                        </p>
                        <p className="mt-2 text-3xl font-semibold">$0.00</p>
                    </div>
                </div>

                <div className="flex flex-1 items-center justify-center rounded-xl border border-dashed">
                    <div className="text-center">
                        <h2 className="text-lg font-medium">
                            Your collection is empty
                        </h2>

                        <p className="mt-1 text-sm text-muted-foreground">
                            Search for a card to start building your collection.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};