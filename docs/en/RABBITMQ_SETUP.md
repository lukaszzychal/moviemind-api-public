# RabbitMQ Setup for Laravel

## 📦 Installation
```bash
cd api
composer require vladimir-yuldashev/laravel-queue-rabbitmq
php artisan vendor:publish --provider="VladimirYuldashev\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider"
```

## ⚙️ Configuration
- Update `config/queue.php` with the `rabbitmq` connection (host, port, vhost, credentials).  
- Set `QUEUE_CONNECTION=rabbitmq` in `.env` for environments using RabbitMQ.  
- Horizon supports RabbitMQ starting from the package’s integration (check compatibility).

## 🚀 Usage
- Run `php artisan queue:work rabbitmq` or configure Horizon to manage workers.  
- Jobs behave like other queue drivers (retries, backoff, timeouts).  
- Ensure RabbitMQ server is provisioned (Docker Compose, managed service, etc.).

**Polish source:** [`../pl/RABBITMQ_SETUP.md`](../pl/RABBITMQ_SETUP.md)
