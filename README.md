# PokéVault

A personal Pokémon TCG collection tracker built to make organizing, exploring, and tracking the value of your card collection simple and fun.

## ✨ Features

* 🔎 Search Pokémon TCG cards
* 🃏 View detailed card information
* 💰 Track current card market prices
* 📚 Organize cards into personal collections
* 🌙 Dark mode with a Darkrai-inspired theme
* 📈 Planned price history and collection analytics

## 🛠️ Tech Stack

* **Backend:** Laravel 13
* **Frontend:** React 19 + TypeScript
* **Framework:** Inertia.js
* **Styling:** Tailwind CSS
* **UI:** shadcn/ui
* **Database:** SQLite (PostgreSQL planned)
* **Card Data:** TCGdex API

## 🚀 Getting Started

Clone the repository and install the dependencies:

```bash
git clone https://github.com/YOUR_USERNAME/pokevault.git
cd pokevault

composer install
npm install
```

Create your environment file:

```bash
cp .env.example .env
php artisan key:generate
```

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

## 🗺️ Roadmap

* [x] Card search
* [x] Card detail pages
* [x] Market pricing
* [x] User authentication
* [x] Collection database structure
* [ ] Add cards to collections
* [ ] Collection management UI
* [ ] Historical price tracking
* [ ] Collection value analytics
* [ ] PostgreSQL production deployment
* [ ] Production deployment

## 📌 About

PokéVault is a portfolio project focused on building a polished, full-stack application around a real-world data source while exploring API integration, caching, collection management, pricing data, and data visualization.

This project will be continuously developed with new features, improvements, and enhancements, with the goal of actively maintaining and supporting PokéVault well into the future.
