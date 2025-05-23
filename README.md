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

````bash
git clone https://github.com/igmeMarcial/phone-calls-twilio.git
cd phone-calls

##Install dependencies
composer install
npm install

## Copy environment file and generate application key
cp .env.example .env
php artisan key:generate


## Add your database credentials and twilio credentials to the .env file

TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_PHONE_NUMBER=
TWILIO_VERIFY_SERVICE_SID=
#Change to true if you want to use the twilio phone number instead of the user's phone number
TWILIO_USE_PHONE_NUMBER=false

## Setup database
php artisan migrate --seed

## Run the server
php artisan serve
## Run the frontend
npm run dev

##test
php artisan test


#data base migrations
php artisan migrate:fresh --seed
#check routes
php artisan route:list


## add to php.ini
extension=pdo_pgsql
extension=pgsql


### Docker
## Build the Docker image
docker build -t laravel-app .
## Run the Docker container
docker run -p 8000:80 laravel-app

## 🛠 Additional Configuration (SSL errors)

If you encounter SSL certificate errors with Twilio, make sure to download and link the latest `cacert.pem` file in your `php.ini`:

1. Download from https://curl.se/ca/cacert.pem
2. Set the path in `php.ini`:
   ```ini
   openssl.cafile=/path/to/cacert.pem
````

```

```
