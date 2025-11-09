# VirPanel Module Development Guide

This directory contains VirPanel modules. Modules allow you to extend VirPanel functionality without modifying the core system.

## Module Structure

A VirPanel module is a directory containing:
- `module.json` - Module manifest file
- Module class file(s) implementing `VirPanel\Core\Contracts\ModuleInterface`
- Additional files (controllers, views, assets, etc.)

## Creating a Module

### 1. Create module.json

```json
{
  "name": "my-awesome-module",
  "version": "1.0.0",
  "class": "MyModule\\MyAwesomeModule",
  "description": "A sample module for VirPanel",
  "author": "Your Name",
  "email": "you@example.com",
  "homepage": "https://example.com",
  "license": "MIT",
  "dependencies": {},
  "enabled": true,
  "install_script": "install.php",
  "uninstall_script": "uninstall.php"
}
```

### 2. Create Your Module Class

```php
<?php

namespace MyModule;

use VirPanel\Core\Application;
use VirPanel\Core\Contracts\ModuleInterface;

class MyAwesomeModule implements ModuleInterface
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function register(): void
    {
        // Register services, routes, etc.
    }

    public function boot(): void
    {
        // Boot module (called after all modules are registered)
    }

    public function getName(): string
    {
        return 'my-awesome-module';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'A sample module for VirPanel';
    }
}
```

### 3. Package Your Module

Create a ZIP file with your module directory:

```bash
zip -r my-awesome-module.zip my-awesome-module/
```

The ZIP should contain:
```
my-awesome-module/
├── module.json
├── MyAwesomeModule.php
└── (other files)
```

### 4. Install Your Module

1. Navigate to Admin → Modules in VirPanel
2. Click "Upload Module"
3. Select your ZIP file
4. Click "Upload & Install"

## Module Features

### Adding Routes

```php
public function register(): void
{
    $router = $this->app->get('router');

    $router->get('/my-module/hello', function($request) {
        return new Response('Hello from My Module!');
    });
}
```

### Adding Controllers

```php
use VirPanel\Core\Http\Controllers\Controller;

class MyModuleController extends Controller
{
    public function index()
    {
        return new Response('My Module Index');
    }
}
```

### Adding Database Tables

Create `install.php`:

```php
<?php
// Run when module is installed

use VirPanel\Core\Application;

$app = Application::getInstance();
$config = require __DIR__ . '/../../config/database.php';
$db = \Doctrine\DBAL\DriverManager::getConnection($config);

$db->executeQuery("
    CREATE TABLE IF NOT EXISTS my_module_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");
```

Create `uninstall.php`:

```php
<?php
// Run when module is uninstalled

use VirPanel\Core\Application;

$app = Application::getInstance();
$config = require __DIR__ . '/../../config/database.php';
$db = \Doctrine\DBAL\DriverManager::getConnection($config);

$db->executeQuery("DROP TABLE IF EXISTS my_module_table");
```

### Adding Template Views

Store templates in your module directory:

```
my-awesome-module/
├── module.json
├── MyAwesomeModule.php
└── views/
    └── index.html.twig
```

Render in your controller:

```php
use VirPanel\Core\Template\TemplateEngine;

$template = new TemplateEngine($this->app);
return new Response($template->render('modules/my-awesome-module/views/index.html.twig', [
    'title' => 'My Module'
]));
```

### Module Hooks

You can hook into VirPanel events:

```php
public function boot(): void
{
    $this->app->on('account.created', function($account) {
        // Do something when an account is created
    });
}
```

## Best Practices

1. **Namespace your classes** - Use a unique namespace to avoid conflicts
2. **Validate dependencies** - Check that required modules are installed
3. **Handle errors gracefully** - Don't break the main application
4. **Clean up on uninstall** - Remove all database tables and files
5. **Version your modules** - Use semantic versioning (MAJOR.MINOR.PATCH)
6. **Document your module** - Include a README with installation and usage instructions
7. **Test thoroughly** - Test on a development instance before production

## Security Considerations

- Validate all user inputs
- Use prepared statements for database queries
- Sanitize output to prevent XSS
- Check permissions before performing actions
- Don't store sensitive data in plain text

## Example Modules

Look in the `/docs/examples/modules/` directory for complete example modules.

## Module API Reference

### ModuleInterface Methods

- `__construct(Application $app)` - Module constructor
- `register(): void` - Register module services
- `boot(): void` - Boot module (called after all modules are registered)
- `getName(): string` - Get module name
- `getVersion(): string` - Get module version
- `getDescription(): string` - Get module description

### Available Services

Access services via the Application instance:

```php
$db = $this->app->get('db');
$router = $this->app->get('router');
$template = $this->app->get('template');
```

## Troubleshooting

### Module not loading
- Check module.json is valid JSON
- Ensure class name matches in module.json
- Verify class implements ModuleInterface

### Dependencies not met
- Install required dependencies first
- Check dependency version requirements

### Signature verification failed
- Ensure module is properly signed (if using signatures)
- Contact module author for valid signature

## Support

For module development support:
- Documentation: https://docs.virpanel.com/modules
- Community Forum: https://community.virpanel.com
- GitHub Issues: https://github.com/virpanel/virpanel/issues
