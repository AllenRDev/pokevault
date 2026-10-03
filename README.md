# DexVault

A personal Pokémon TCG collection tracker built to make organizing, exploring, and tracking the value of your card collection simple and fun.

## ✨ Features

- 🔎 Search Pokémon TCG cards
- 🃏 View detailed card information
- 💰 Track current card market prices
- 📚 Organize cards into personal collections
- 📷 Scan physical cards with the device camera
- 🌙 Dark mode with a Darkrai-inspired theme
- 📈 Planned price history and collection analytics

## 🛠️ Tech Stack

- **Backend:** Laravel 13
- **Frontend:** React 19 + TypeScript
- **Framework:** Inertia.js
- **Styling:** Tailwind CSS
- **UI:** shadcn/ui
- **Database:** SQLite (PostgreSQL planned)
- **Card Data:** TCGdex API

## 🚀 Getting Started

Clone the repository and install the dependencies:

```bash
git clone https://github.com/YOUR_USERNAME/dexvault.git
cd dexvault

composer install
npm install
```

Create your environment file:

```bash
cp .env.example .env
php artisan key:generate
```

Card data comes from the public [TCGdex](https://github.com/tcgdex/cards) API. The
base URL and the Pokémon used for the landing page featured cards can both be
overridden via `TCGDEX_API_URL` and `TCGDEX_FEATURED_CARD` in `.env`.

Configure your database in `.env`, then run:

```bash
php artisan migrate
```

Start the development servers:

```bash
php artisan serve
npm run dev
```

The application will be available at:

```text
http://localhost:8000
```

## 🔎 Card scanning

The scanner at `/cards/scan` recognises a photographed card in the browser and
matches it against TCGdex, so a card can go straight into a collection without
being typed out by hand.

1. Take a photo of the card's **name line and the number/total in the
   bottom-left corner**. Cropping to the top of the card keeps the recognition
   fast and avoids the artwork being read as text.
2. The card name and the printed `number/total` are read on-device with
   [Tesseract.js](https://tesseract.com/), then looked up through
   `GET /api/cards/match`.
3. Matching uses the name and number to shortlist candidates, then uses the
   total to tell printings apart: the shortlist keeps the cards whose set
   contains that many cards. One remaining card is a confident match; several
   are shown as a list to choose from, so a misread never adds the wrong card
   silently.

Nothing is uploaded: the photo never leaves the device. Only the recognised
name and number are sent to the server to look the card up.

### Self-hosting the recognition engine

Tesseract downloads its worker script, engine, and English language data the
first time it is used, and takes all three from a public CDN by default. That
is fine locally, but deployments that cannot rely on an outside host can serve
the same files themselves by setting a single variable:

```dotenv
VITE_TESSERACT_ASSET_BASE=/tesseract
```

Point it at a directory containing `worker.min.js` from `tesseract.js`, the
files from `tesseract.js-core`, and `eng.traineddata.gz` from
`tessdata_fast`. Leave it unset to use the CDN defaults.

## 🗺️ Roadmap

- [x] Card search
- [x] Card detail pages
- [x] Market pricing
- [x] User authentication
- [x] Collection database structure
- [x] Add cards to collections
- [x] Camera OCR card matching
- [x] Collection search, sorting, and pagination
- [ ] Collection management UI
- [ ] Historical price tracking
- [ ] Collection value analytics
- [ ] PostgreSQL production deployment
- [ ] Production deployment

## 📌 About

DexVault is a portfolio project focused on building a polished, full-stack application around a real-world data source while exploring API integration, caching, collection management, pricing data, and data visualization.

This project will be continuously developed with new features, improvements, and enhancements, with the goal of actively maintaining and supporting DexVault well into the future.
