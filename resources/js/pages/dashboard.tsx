import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BookOpen, Layers3, Package, Search, WalletCards } from 'lucide-react';
import { dashboard } from '@/routes';

interface DashboardProps {
    overall: {
        unique_cards: number;
        total_cards: number;
        sets_collected: number;
        estimated_value: number;
    };
}

export default function Dashboard({ overall }: DashboardProps) {
    const hasCards = overall.total_cards > 0;

    const stats = [
        {
            label: 'Cards Collected',
            value: overall.total_cards.toLocaleString(),
            description: 'Total cards in your collection',
            icon: Package,
        },
        {
            label: 'Unique Cards',
            value: overall.unique_cards.toLocaleString(),
            description: 'Different cards you own',
            icon: BookOpen,
        },
        {
            label: 'Sets Collected',
            value: overall.sets_collected.toLocaleString(),
            description: 'Sets represented in your collection',
            icon: Layers3,
        },
        {
            label: 'Collection Value',
            value: `$${Number(overall.estimated_value).toLocaleString(
                'en-US',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                },
            )}`,
            description: 'Estimated market value',
            icon: WalletCards,
        },
    ];

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-8 p-6 lg:p-8">
                {/* Header */}
                <div>
                    <p className="text-sm font-medium text-primary">
                        Welcome back to
                    </p>

                    <h1 className="mt-1 text-3xl font-bold tracking-tight">
                        Your PokéVault
                    </h1>

                    <p className="mt-2 max-w-2xl text-muted-foreground">
                        Keep track of your Pokémon TCG collection, discover
                        new cards, and see what your collection is worth.
                    </p>
                </div>

                {/* Stats */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {stats.map((stat) => {
                        const Icon = stat.icon;

                        return (
                            <div
                                key={stat.label}
                                className="group rounded-xl border bg-card p-5 transition-all hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">
                                            {stat.label}
                                        </p>

                                        <p className="mt-2 text-3xl font-bold tracking-tight">
                                            {stat.value}
                                        </p>
                                    </div>

                                    <div className="rounded-lg bg-primary/10 p-2.5 text-primary transition-colors group-hover:bg-primary/15">
                                        <Icon className="size-5" />
                                    </div>
                                </div>

                                <p className="mt-3 text-xs text-muted-foreground">
                                    {stat.description}
                                </p>
                            </div>
                        );
                    })}
                </div>

                {/* Main content */}
                {hasCards ? (
                    <div className="grid flex-1 gap-6 lg:grid-cols-2">
                        <div className="rounded-xl border bg-card p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h2 className="text-lg font-semibold">
                                        Your Collection
                                    </h2>

                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Manage the cards you own.
                                    </p>
                                </div>

                                <Link
                                    href="/collection"
                                    className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                                >
                                    View collection
                                    <ArrowRight className="size-4" />
                                </Link>
                            </div>

                            <div className="mt-8 flex items-center justify-center rounded-lg border border-dashed p-10">
                                <div className="text-center">
                                    <Package className="mx-auto size-10 text-muted-foreground/50" />

                                    <p className="mt-3 text-sm text-muted-foreground">
                                        Your collection overview will appear
                                        here as you add more cards.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-xl border bg-card p-6">
                            <div>
                                <h2 className="text-lg font-semibold">
                                    Discover More Cards
                                </h2>

                                <p className="mt-1 text-sm text-muted-foreground">
                                    Search TCGdex to find cards and add them to
                                    your collection.
                                </p>
                            </div>

                            <div className="mt-8 flex flex-col items-center justify-center rounded-lg border border-dashed p-10 text-center">
                                <Search className="size-10 text-primary/60" />

                                <h3 className="mt-4 font-medium">
                                    Looking for something?
                                </h3>

                                <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                                    Search thousands of Pokémon TCG cards and
                                    explore their details and market prices.
                                </p>

                                <Link
                                    href="/cards"
                                    className="mt-5 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-opacity hover:opacity-90"
                                >
                                    <Search className="size-4" />
                                    Search Cards
                                </Link>
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="flex flex-1 items-center justify-center rounded-xl border border-dashed bg-card/50 p-8">
                        <div className="max-w-md text-center">
                            <div className="mx-auto flex size-16 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                <WalletCards className="size-8" />
                            </div>

                            <h2 className="mt-5 text-xl font-semibold">
                                Your collection is waiting
                            </h2>

                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                Search for your favorite Pokémon cards and add
                                them to PokéVault. Once you start collecting,
                                your stats and collection value will show up
                                here.
                            </p>

                            <Link
                                href="/cards"
                                className="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition-opacity hover:opacity-90"
                            >
                                <Search className="size-4" />
                                Start Searching
                            </Link>
                        </div>
                    </div>
                )}
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