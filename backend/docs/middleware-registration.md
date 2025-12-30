## Register middleware aliases (Laravel 11)

Open `bootstrap/app.php` in your Laravel project, then add the alias mapping.

Example:

```php
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureOwner;
use App\Http\Middleware\AuditLogMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'ensure.owner' => EnsureOwner::class,
            'audit.log' => AuditLogMiddleware::class,
        ]);
    })
    ->create();
```
