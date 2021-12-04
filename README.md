# Simple_Blog

## install package
```bash
composer install
```

## create the `.env` file
```bash
cp .env.example .env
```

## generate secret key
```bash
php artisan key:generate
php artisan jwt:secret
```

## config the database

edit the .env file to config the database

create the `Simple_blog` database，then run `migrate` 

```bash
php artisan migrate
php artisan db:seed
```

default admin account

username:`admin`   password:`admin`
