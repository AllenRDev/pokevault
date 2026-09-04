import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    Layers3,
    Search,
    Sparkles,
    Star,
} from 'lucide-react';
import { ThemeToggle } from '@/components/theme-toggle';

interface FeaturedCard {
    external_id: string;
    name: string;
    number: string;
    image_large: string | null;
}

interface Props {
    featuredCards?: FeaturedCard[];
    auth?: {
        user?: {
            id: number;
            name: string;
            email: string;
        };
    };
}

export default function Welcome({ featuredCards = [], auth }: Props) {
    return (
        <>
            <Head title="PokéVault" />

            <div className="min-h-screen bg-background text-foreground">
                {/* Navbar */}
                <nav className="border-b bg-background/80 backdrop-blur-xl">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-6 lg:px-8">
                        <Link
                            href="/"
                            className="flex items-center gap-2 font-black tracking-tight"
                        >
                            <div className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 text-white shadow-lg shadow-blue-500/20 dark:from-red-600 dark:to-red-800 dark:shadow-red-950/30">
                                <Layers3 className="size-5" />
                            </div>

                            <span className="bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-xl text-transparent dark:from-red-500 dark:to-red-700">
                                PokéVault
                            </span>
                        </Link>

                        <div className="flex items-center gap-2 sm:gap-4">
                            <ThemeToggle />

                            <Link
                                href="/cards"
                                className="hidden text-sm font-semibold text-muted-foreground transition-colors hover:text-blue-500 dark:hover:text-red-500 sm:block"
                            >
                                Card Search
                            </Link>

                            {auth?.user ? (
                                <Link
                                    href="/dashboard"
                                    className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-lg"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href="/login"
                                        className="text-sm font-semibold text-muted-foreground transition-colors hover:text-blue-500 dark:hover:text-red-500"
                                    >
                                        Log in
                                    </Link>

                                    <Link
                                        href="/register"
                                        className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-lg"
                                    >
                                        Create Account
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </nav>

                {/* Hero */}
                <section className="relative overflow-hidden">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(59,130,246,0.16),transparent_35%),radial-gradient(circle_at_80%_0%,rgba(168,85,247,0.14),transparent_30%)] dark:bg-[radial-gradient(circle_at_20%_20%,rgba(220,38,38,0.18),transparent_35%),radial-gradient(circle_at_80%_0%,rgba(126,34,206,0.14),transparent_30%)]" />

                    <div className="relative mx-auto grid max-w-7xl items-center gap-14 px-6 py-20 lg:grid-cols-2 lg:px-8 lg:py-28">
                        <div>
                            <div className="mb-6 inline-flex items-center gap-2 rounded-full bg-blue-500/10 px-4 py-2 text-sm font-semibold text-blue-600 dark:bg-red-500/10 dark:text-red-400">
                                <Sparkles className="size-4" />
                                Your personal Pokémon TCG vault
                            </div>

                            <h1 className="max-w-3xl text-5xl font-black tracking-tight sm:text-6xl lg:text-7xl">
                                Collect{' '}
                                <span className="bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-600 bg-clip-text text-transparent dark:from-red-500 dark:via-red-600 dark:to-red-700">
                                    everything.
                                </span>
                                <br />
                                Track what matters.
                            </h1>

                            <p className="mt-6 max-w-xl text-lg leading-8 text-muted-foreground">
                                PokéVault makes it easy to keep track of your
                                Pokémon cards, discover new additions, and see
                                exactly what your collection is worth.
                            </p>

                            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                                <Link
                                    href="/cards"
                                    className="inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-3.5 font-bold text-white shadow-xl shadow-blue-500/20 transition-all hover:-translate-y-1 hover:shadow-2xl dark:from-red-600 dark:to-red-800 dark:shadow-red-950/30"
                                >
                                    <Search className="size-5" />
                                    Explore Cards
                                    <ArrowRight className="size-4" />
                                </Link>

                                {!auth?.user && (
                                    <Link
                                        href="/register"
                                        className="inline-flex items-center justify-center rounded-2xl border bg-card px-6 py-3.5 font-bold shadow-sm transition-all hover:-translate-y-1 hover:border-blue-500/30 hover:bg-blue-500/5 dark:hover:border-red-500/30 dark:hover:bg-red-500/5"
                                    >
                                        Start Your Collection
                                    </Link>
                                )}
                            </div>

                            <div className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-muted-foreground">
                                <div className="flex items-center gap-2">
                                    <div className="flex size-5 items-center justify-center rounded-full bg-green-500/10 text-green-600 dark:text-green-400">
                                        <Check className="size-3" />
                                    </div>
                                    Track your collection
                                </div>

                                <div className="flex items-center gap-2">
                                    <div className="flex size-5 items-center justify-center rounded-full bg-green-500/10 text-green-600 dark:text-green-400">
                                        <Check className="size-3" />
                                    </div>
                                    Browse thousands of cards
                                </div>

                                <div className="flex items-center gap-2">
                                    <div className="flex size-5 items-center justify-center rounded-full bg-green-500/10 text-green-600 dark:text-green-400">
                                        <Check className="size-3" />
                                    </div>
                                    Know your collection's value
                                </div>
                            </div>
                        </div>

                        {/* Decorative Card Stack */}
                        <div className="relative mx-auto hidden h-[500px] w-full max-w-lg lg:block">
                            <div className="absolute left-1/2 top-1/2 h-[390px] w-[280px] -translate-x-1/2 -translate-y-1/2 rotate-[-14deg] rounded-[2rem] border-4 border-white/60 bg-gradient-to-br from-purple-500/80 to-blue-600/80 shadow-2xl shadow-blue-500/20 dark:border-white/10 dark:from-purple-900 dark:to-red-900 dark:shadow-red-950/40" />

                            <div className="absolute left-1/2 top-1/2 h-[410px] w-[295px] -translate-x-1/2 -translate-y-1/2 rotate-[8deg] rounded-[2rem] border-4 border-white/70 bg-gradient-to-br from-blue-500 to-indigo-700 shadow-2xl shadow-blue-500/30 dark:border-white/10 dark:from-red-700 dark:to-purple-950 dark:shadow-red-950/50" />

                            <div className="absolute left-1/2 top-1/2 h-[430px] w-[310px] -translate-x-1/2 -translate-y-1/2 rotate-[-2deg] rounded-[2rem] border-4 border-white bg-gradient-to-br from-white to-blue-50 p-3 shadow-2xl dark:border-white/10 dark:from-neutral-900 dark:to-red-950">
                                <div className="flex h-full flex-col rounded-[1.4rem] border-2 border-blue-200 bg-gradient-to-br from-blue-100 via-white to-purple-100 p-4 dark:border-red-900/50 dark:from-red-950 dark:via-neutral-900 dark:to-purple-950">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-red-400">
                                            PokéVault
                                        </span>

                                        <Star className="size-5 fill-yellow-400 text-yellow-400" />
                                    </div>

                                    <div className="flex flex-1 items-center justify-center">
                                        <div className="flex size-36 items-center justify-center rounded-full bg-gradient-to-br from-yellow-300 to-orange-400 shadow-2xl shadow-orange-400/30 dark:from-red-600 dark:to-purple-700 dark:shadow-red-950/50">
                                            <div className="size-20 rounded-full border-8 border-white/80 dark:border-white/20" />
                                        </div>
                                    </div>

                                    <div className="rounded-xl bg-white/80 p-3 backdrop-blur dark:bg-black/30">
                                        <div className="text-xs font-semibold text-muted-foreground">
                                            COLLECTION
                                        </div>
                                        <div className="mt-1 text-lg font-black">
                                            Gotta collect 'em all.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Featured Cards */}
                <section className="border-y bg-gradient-to-br from-blue-500/[0.03] via-background to-purple-500/[0.05] dark:from-red-950/20 dark:via-background dark:to-purple-950/20">
                    <div className="mx-auto max-w-7xl px-6 py-20 lg:px-8">
                        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                            <div>
                                <p className="text-sm font-bold uppercase tracking-widest text-blue-500 dark:text-red-500">
                                    Explore the Vault
                                </p>

                                <h2 className="mt-2 text-3xl font-black tracking-tight sm:text-4xl">
                                    Find something{' '}
                                    <span className="text-purple-500 dark:text-red-500">
                                        awesome.
                                    </span>
                                </h2>

                                <p className="mt-3 max-w-xl text-muted-foreground">
                                    Search through Pokémon TCG cards and discover
                                    your next favorite addition.
                                </p>
                            </div>

                            <Link
                                href="/cards"
                                className="inline-flex items-center gap-2 text-sm font-bold text-blue-600 transition-colors hover:text-purple-600 dark:text-red-400 dark:hover:text-red-300"
                            >
                                Browse all cards
                                <ArrowRight className="size-4" />
                            </Link>
                        </div>

                        {featuredCards.length > 0 && (
                            <div className="mt-10 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                                {featuredCards.map((card, index) => (
                                    <Link
                                        key={card.external_id}
                                        href={`/cards/${card.external_id}`}
                                        className="group relative overflow-hidden rounded-2xl border bg-card shadow-sm transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl dark:hover:shadow-red-950/30"
                                    >
                                        <div
                                            className={`absolute inset-x-0 top-0 z-10 h-1 ${
                                                index % 3 === 0
                                                    ? 'bg-blue-500 dark:bg-red-500'
                                                    : index % 3 === 1
                                                      ? 'bg-purple-500 dark:bg-red-700'
                                                      : 'bg-yellow-400 dark:bg-red-900'
                                            }`}
                                        />

                                        <div className="aspect-[2.5/3.5] overflow-hidden bg-gradient-to-br from-muted to-background p-2">
                                            {card.image_large ? (
                                                <img
                                                    src={card.image_large}
                                                    alt={card.name}
                                                    className="h-full w-full object-contain transition-transform duration-500 group-hover:scale-105"
                                                />
                                            ) : (
                                                <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                                                    No image
                                                </div>
                                            )}
                                        </div>

                                        <div className="p-4">
                                            <h3 className="truncate font-bold">
                                                {card.name}
                                            </h3>

                                            <span className="mt-2 inline-flex rounded-full bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-600 dark:bg-red-500/10 dark:text-red-400">
                                                #{card.number}
                                            </span>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                </section>

                {/* Features */}
                <section className="mx-auto max-w-7xl px-6 py-20 lg:px-8">
                    <div className="mx-auto max-w-2xl text-center">
                        <p className="text-sm font-bold uppercase tracking-widest text-blue-500 dark:text-red-500">
                            Built for collectors
                        </p>

                        <h2 className="mt-2 text-3xl font-black tracking-tight sm:text-4xl">
                            Your collection,{' '}
                            <span className="text-purple-500 dark:text-red-500">
                                organized.
                            </span>
                        </h2>

                        <p className="mt-4 text-muted-foreground">
                            Everything you need to keep your collection under
                            control without losing the fun of collecting.
                        </p>
                    </div>

                    <div className="mt-12 grid gap-6 md:grid-cols-3">
                        <div className="group rounded-3xl border bg-card p-7 transition-all duration-300 hover:-translate-y-1 hover:border-blue-500/20 hover:shadow-xl dark:hover:border-red-500/20 dark:hover:shadow-red-950/20">
                            <div className="flex size-12 items-center justify-center rounded-2xl bg-blue-500/10 text-blue-500 transition-transform group-hover:scale-110 dark:bg-red-500/10 dark:text-red-400">
                                <Layers3 className="size-6" />
                            </div>

                            <h3 className="mt-5 text-xl font-bold">
                                Organize everything
                            </h3>

                            <p className="mt-2 leading-7 text-muted-foreground">
                                Keep your cards organized in collections and
                                quickly see exactly what you own.
                            </p>
                        </div>

                        <div className="group rounded-3xl border bg-card p-7 transition-all duration-300 hover:-translate-y-1 hover:border-purple-500/20 hover:shadow-xl dark:hover:border-red-500/20 dark:hover:shadow-red-950/20">
                            <div className="flex size-12 items-center justify-center rounded-2xl bg-purple-500/10 text-purple-500 transition-transform group-hover:scale-110 dark:bg-red-500/10 dark:text-red-400">
                                <Search className="size-6" />
                            </div>

                            <h3 className="mt-5 text-xl font-bold">
                                Find any card
                            </h3>

                            <p className="mt-2 leading-7 text-muted-foreground">
                                Search a huge Pokémon TCG database by Pokémon,
                                card name, set, and more.
                            </p>
                        </div>

                        <div className="group rounded-3xl border bg-card p-7 transition-all duration-300 hover:-translate-y-1 hover:border-yellow-500/20 hover:shadow-xl dark:hover:border-red-500/20 dark:hover:shadow-red-950/20">
                            <div className="flex size-12 items-center justify-center rounded-2xl bg-yellow-500/10 text-yellow-600 transition-transform group-hover:scale-110 dark:bg-red-500/10 dark:text-red-400">
                                <Star className="size-6" />
                            </div>

                            <h3 className="mt-5 text-xl font-bold">
                                Track what matters
                            </h3>

                            <p className="mt-2 leading-7 text-muted-foreground">
                                Keep tabs on quantities, conditions, purchase
                                details, and eventually your collection's value.
                            </p>
                        </div>
                    </div>
                </section>

                {/* CTA */}
                <section className="px-6 pb-20 lg:px-8">
                    <div className="relative mx-auto max-w-5xl overflow-hidden rounded-[2rem] bg-gradient-to-br from-blue-600 via-indigo-600 to-purple-700 p-10 text-center text-white shadow-2xl shadow-blue-500/20 dark:from-red-700 dark:via-red-800 dark:to-purple-950 dark:shadow-red-950/40 sm:p-14">
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(255,255,255,0.15),transparent_30%),radial-gradient(circle_at_80%_80%,rgba(255,255,255,0.1),transparent_30%)]" />

                        <div className="relative">
                            <Sparkles className="mx-auto size-8 text-yellow-300" />

                            <h2 className="mt-4 text-3xl font-black sm:text-4xl">
                                Ready to build your vault?
                            </h2>

                            <p className="mx-auto mt-4 max-w-xl text-blue-100 dark:text-red-100">
                                Start cataloging your collection and discover a
                                better way to keep track of your cards.
                            </p>

                            <Link
                                href={auth?.user ? '/dashboard' : '/register'}
                                className="mt-7 inline-flex items-center gap-2 rounded-2xl bg-white px-6 py-3.5 font-bold text-blue-700 shadow-xl transition-all hover:-translate-y-1 hover:shadow-2xl dark:text-red-700"
                            >
                                {auth?.user
                                    ? 'Go to Dashboard'
                                    : 'Create Your Vault'}
                                <ArrowRight className="size-4" />
                            </Link>
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t">
                    <div className="mx-auto flex max-w-7xl flex-col gap-3 px-6 py-8 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between lg:px-8">
                        <div className="flex items-center gap-2">
                            <div className="flex size-7 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 text-white dark:from-red-600 dark:to-red-800">
                                <Layers3 className="size-4" />
                            </div>

                            <span className="font-semibold text-foreground">
                                PokéVault
                            </span>
                        </div>

                        <p>
                            Built for collectors who never stopped saying
                            "just one more pack."
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}