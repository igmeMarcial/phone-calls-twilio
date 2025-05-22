# 🚀 Phone Calls

This project uses Laravel 12 with built-in authentication and a Vue.js frontend.

---

## ✅ Requirements

- PHP >= 8.2
- Composer
- Node.js >= 18.x
- npm

---

## ⚙️ Installation

clone the repository and cd into it

```bash
git clone https://github.com/igmeMarcial/phone-calls-twilio.git
cd phone-calls

##Install dependencies
composer install
npm install

## Copy environment file and generate application key
cp .env.example .env
php artisan key:generate

## Setup database
php artisan migrate --seed

## Run the server
php artisan serve
## Run the frontend
npm run dev

##test
php artisan test

## test call
php artisan test tests/Feature/Calls/PhoneCallFeatureTest.php

#data base migrations
php artisan migrate:fresh --seed
#check routes
php artisan route:list


## php.ini activate
extension=pdo_pgsql
extension=pgsql
```
