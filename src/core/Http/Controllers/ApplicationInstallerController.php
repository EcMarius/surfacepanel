<?php

namespace VirPanel\Core\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use VirPanel\Core\Application;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Auth\Auth;
use Doctrine\DBAL\Connection;

class ApplicationInstallerController
{
    private Connection $db;
    private TemplateEngine $template;
    private string $prefix;

    private array $availableApps = [
        'wordpress' => [
            'name' => 'WordPress',
            'version' => '6.4.2',
            'download_url' => 'https://wordpress.org/latest.zip',
            'description' => 'The world\'s most popular CMS and blogging platform',
            'category' => 'CMS',
            'icon' => 'wordpress.png',
            'requires' => ['php' => '7.4', 'mysql' => '5.7'],
        ],
        'joomla' => [
            'name' => 'Joomla',
            'version' => '5.0',
            'download_url' => 'https://downloads.joomla.org/cms/joomla5/5-0-0/Joomla_5-0-0-Stable-Full_Package.zip',
            'description' => 'Flexible and powerful CMS',
            'category' => 'CMS',
            'icon' => 'joomla.png',
            'requires' => ['php' => '8.1', 'mysql' => '5.6'],
        ],
        'drupal' => [
            'name' => 'Drupal',
            'version' => '10.2',
            'download_url' => 'https://www.drupal.org/download-latest/zip',
            'description' => 'Open source CMS for ambitious digital experiences',
            'category' => 'CMS',
            'icon' => 'drupal.png',
            'requires' => ['php' => '8.1', 'mysql' => '5.7'],
        ],
        'prestashop' => [
            'name' => 'PrestaShop',
            'version' => '8.1',
            'download_url' => 'https://download.prestashop.com/download/releases/prestashop_8.1.3.zip',
            'description' => 'Open source e-commerce solution',
            'category' => 'E-Commerce',
            'icon' => 'prestashop.png',
            'requires' => ['php' => '7.4', 'mysql' => '5.6'],
        ],
        'magento' => [
            'name' => 'Magento',
            'version' => '2.4',
            'download_url' => '',
            'description' => 'Powerful e-commerce platform',
            'category' => 'E-Commerce',
            'icon' => 'magento.png',
            'requires' => ['php' => '8.1', 'mysql' => '8.0'],
        ],
        'phpbb' => [
            'name' => 'phpBB',
            'version' => '3.3',
            'download_url' => 'https://download.phpbb.com/pub/release/3.3/3.3.11/phpBB-3.3.11.zip',
            'description' => 'Free and open source forum software',
            'category' => 'Forum',
            'icon' => 'phpbb.png',
            'requires' => ['php' => '7.1', 'mysql' => '5.5'],
        ],
        'mediawiki' => [
            'name' => 'MediaWiki',
            'version' => '1.40',
            'download_url' => 'https://releases.wikimedia.org/mediawiki/1.40/mediawiki-1.40.0.tar.gz',
            'description' => 'Wiki software powering Wikipedia',
            'category' => 'Wiki',
            'icon' => 'mediawiki.png',
            'requires' => ['php' => '7.4', 'mysql' => '5.7'],
        ],
        'nodejs' => [
            'name' => 'Node.js Application',
            'version' => '20.x',
            'download_url' => '',
            'description' => 'Set up Node.js application with PM2 process manager',
            'category' => 'Development',
            'icon' => 'nodejs.png',
            'requires' => ['nodejs' => '18.0'],
            'type' => 'runtime',
        ],
        'express' => [
            'name' => 'Express.js App',
            'version' => '4.18',
            'download_url' => '',
            'description' => 'Express.js web application framework for Node.js',
            'category' => 'Development',
            'icon' => 'expressjs.png',
            'requires' => ['nodejs' => '18.0'],
            'type' => 'runtime',
        ],
        'nextjs' => [
            'name' => 'Next.js App',
            'version' => '14.0',
            'download_url' => '',
            'description' => 'React framework for production with SSR and SSG',
            'category' => 'Development',
            'icon' => 'nextjs.png',
            'requires' => ['nodejs' => '18.0'],
            'type' => 'runtime',
        ],
    ];

    public function __construct()
    {
        $app = Application::getInstance();
        $this->db = $app->get('database');
        $this->template = new TemplateEngine($app);
        $this->prefix = config('database.prefix', 'vp_');
    }

    public function index(Request $request): Response
    {
        $user = Auth::user();

        // Get account
        $account = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}accounts WHERE user_id = ?",
            [$user->getId()]
        );

        if (!$account) {
            return new Response($this->template->render('errors/no-account.html.twig'), 403);
        }

        // Get installed applications
        $installed = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}installed_apps WHERE account_id = ? ORDER BY installed_at DESC",
            [$account['id']]
        );

        // Group apps by category
        $appsByCategory = [];
        foreach ($this->availableApps as $appId => $app) {
            $category = $app['category'];
            if (!isset($appsByCategory[$category])) {
                $appsByCategory[$category] = [];
            }
            $appsByCategory[$category][$appId] = $app;
        }

        return new Response($this->template->render('user/apps/index.html.twig', [
            'account' => $account,
            'apps_by_category' => $appsByCategory,
            'installed_apps' => $installed,
        ]));
    }

    public function showInstall(Request $request, string $app): Response
    {
        $user = Auth::user();

        // Get account
        $account = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}accounts WHERE user_id = ?",
            [$user->getId()]
        );

        if (!$account) {
            return new Response($this->template->render('errors/no-account.html.twig'), 403);
        }

        // Check if app exists
        if (!isset($this->availableApps[$app])) {
            return new Response('Application not found', 404);
        }

        $appInfo = $this->availableApps[$app];

        // Get available domains
        $domains = $this->getAccountDomains($account['id']);

        // Get available databases (for database selection or creation)
        $databases = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}databases WHERE account_id = ?",
            [$account['id']]
        );

        return new Response($this->template->render('user/apps/install.html.twig', [
            'account' => $account,
            'app_id' => $app,
            'app' => $appInfo,
            'domains' => $domains,
            'databases' => $databases,
        ]));
    }

    public function install(Request $request, string $app): Response
    {
        $user = Auth::user();

        // Get account
        $account = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}accounts WHERE user_id = ?",
            [$user->getId()]
        );

        if (!$account) {
            return new JsonResponse(['error' => 'Account not found'], 403);
        }

        // Check if app exists
        if (!isset($this->availableApps[$app])) {
            return new JsonResponse(['error' => 'Application not found'], 404);
        }

        $appInfo = $this->availableApps[$app];

        // Get installation parameters
        $domain = $request->request->get('domain');
        $directory = trim($request->request->get('directory', ''), '/');
        $siteName = $request->request->get('site_name');
        $adminUsername = $request->request->get('admin_username');
        $adminPassword = $request->request->get('admin_password');
        $adminEmail = $request->request->get('admin_email');

        // Database options
        $createDatabase = $request->request->get('create_database') === 'yes';
        $databaseName = $request->request->get('database_name');
        $databaseUser = $request->request->get('database_user');
        $databasePassword = $request->request->get('database_password');

        // Validate inputs
        if (empty($domain) || empty($adminUsername) || empty($adminPassword) || empty($adminEmail)) {
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }

        // Determine installation path
        $installPath = "/home/{$account['username']}/public_html";
        if ($domain !== $account['domain']) {
            // Installing to addon domain
            $addonDomain = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}addon_domains WHERE account_id = ? AND domain = ?",
                [$account['id'], $domain]
            );
            if ($addonDomain) {
                $installPath = $addonDomain['document_root'];
            }
        }

        if (!empty($directory)) {
            $installPath .= '/' . $directory;
        }

        // Create installation directory if it doesn't exist
        if (!is_dir($installPath)) {
            mkdir($installPath, 0755, true);
        }

        // Create database if requested
        if ($createDatabase) {
            $dbName = $account['username'] . '_' . $databaseName;
            $dbUser = $account['username'] . '_' . $databaseUser;

            $this->createDatabase($account['id'], $dbName, $dbUser, $databasePassword);
        } else {
            $dbName = $databaseName;
            $dbUser = $databaseUser;
        }

        // Download and install application
        try {
            $installId = $this->performInstallation(
                $app,
                $appInfo,
                $installPath,
                $domain,
                $directory,
                [
                    'site_name' => $siteName,
                    'admin_username' => $adminUsername,
                    'admin_password' => $adminPassword,
                    'admin_email' => $adminEmail,
                    'db_name' => $dbName,
                    'db_user' => $dbUser,
                    'db_password' => $databasePassword,
                ]
            );

            // Record installation
            $this->db->insert($this->prefix . 'installed_apps', [
                'account_id' => $account['id'],
                'app_name' => $app,
                'app_version' => $appInfo['version'],
                'domain' => $domain,
                'directory' => $directory,
                'install_path' => $installPath,
                'database_name' => $dbName,
                'admin_url' => $this->getAdminUrl($app, $domain, $directory),
                'installed_at' => date('Y-m-d H:i:s'),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => "{$appInfo['name']} has been successfully installed!",
                'url' => 'http://' . $domain . ($directory ? '/' . $directory : ''),
                'admin_url' => $this->getAdminUrl($app, $domain, $directory),
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    public function uninstall(Request $request, int $id): Response
    {
        $user = Auth::user();

        // Get account
        $account = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}accounts WHERE user_id = ?",
            [$user->getId()]
        );

        // Get installed app
        $installedApp = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}installed_apps WHERE id = ? AND account_id = ?",
            [$id, $account['id']]
        );

        if (!$installedApp) {
            return new JsonResponse(['error' => 'Application not found'], 404);
        }

        // Delete files
        if (is_dir($installedApp['install_path'])) {
            $this->recursiveDelete($installedApp['install_path']);
        }

        // Delete database entry
        $this->db->delete($this->prefix . 'installed_apps', ['id' => $id]);

        return new JsonResponse(['success' => true, 'message' => 'Application uninstalled successfully']);
    }

    private function getAccountDomains(int $accountId): array
    {
        // Get primary domain
        $account = $this->db->fetchAssociative(
            "SELECT domain FROM {$this->prefix}accounts WHERE id = ?",
            [$accountId]
        );

        $domains = [$account['domain']];

        // Get addon domains
        $addonDomains = $this->db->fetchAllAssociative(
            "SELECT domain FROM {$this->prefix}addon_domains WHERE account_id = ?",
            [$accountId]
        );

        foreach ($addonDomains as $addon) {
            $domains[] = $addon['domain'];
        }

        return $domains;
    }

    private function createDatabase(int $accountId, string $dbName, string $dbUser, string $dbPassword): void
    {
        // Create database
        $this->db->executeStatement("CREATE DATABASE IF NOT EXISTS `{$dbName}`");

        // Create user and grant privileges
        $hashedPassword = password_hash($dbPassword, PASSWORD_ARGON2ID);

        $this->db->executeStatement(
            "CREATE USER IF NOT EXISTS '{$dbUser}'@'localhost' IDENTIFIED BY '{$dbPassword}'"
        );

        $this->db->executeStatement(
            "GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'localhost'"
        );

        $this->db->executeStatement("FLUSH PRIVILEGES");

        // Record in database
        $this->db->insert($this->prefix . 'databases', [
            'account_id' => $accountId,
            'database_name' => $dbName,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->insert($this->prefix . 'database_users', [
            'account_id' => $accountId,
            'username' => $dbUser,
            'password' => $hashedPassword,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function performInstallation(string $app, array $appInfo, string $installPath, string $domain, string $directory, array $config): int
    {
        // Download application
        $downloadUrl = $appInfo['download_url'];
        $tempFile = tempnam(sys_get_temp_dir(), 'app_');

        file_put_contents($tempFile, file_get_contents($downloadUrl));

        // Extract archive
        if (str_ends_with($downloadUrl, '.zip')) {
            $zip = new \ZipArchive();
            if ($zip->open($tempFile) === true) {
                $zip->extractTo($installPath);
                $zip->close();
            }
        } elseif (str_ends_with($downloadUrl, '.tar.gz') || str_ends_with($downloadUrl, '.tgz')) {
            exec("tar -xzf " . escapeshellarg($tempFile) . " -C " . escapeshellarg($installPath));
        }

        unlink($tempFile);

        // Configure application based on type
        switch ($app) {
            case 'wordpress':
                $this->configureWordPress($installPath, $config);
                break;
            case 'joomla':
                $this->configureJoomla($installPath, $config);
                break;
            case 'nodejs':
            case 'express':
            case 'nextjs':
                $this->configureNodeJsApp($app, $installPath, $domain, $config);
                break;
            // Add more apps as needed
        }

        return 0;
    }

    private function configureWordPress(string $installPath, array $config): void
    {
        // Create wp-config.php
        $wpConfigSample = $installPath . '/wordpress/wp-config-sample.php';
        $wpConfig = $installPath . '/wordpress/wp-config.php';

        if (file_exists($wpConfigSample)) {
            $content = file_get_contents($wpConfigSample);

            $content = str_replace('database_name_here', $config['db_name'], $content);
            $content = str_replace('username_here', $config['db_user'], $content);
            $content = str_replace('password_here', $config['db_password'], $content);
            $content = str_replace('localhost', 'localhost', $content);

            // Generate security keys
            $salts = file_get_contents('https://api.wordpress.org/secret-key/1.1/salt/');
            $content = preg_replace('/\/\*\*#@\+.*@\-\*\//', $salts, $content);

            file_put_contents($wpConfig, $content);

            // Move files from wordpress subdirectory to install path
            exec("mv {$installPath}/wordpress/* {$installPath}/");
            exec("rm -rf {$installPath}/wordpress");
        }
    }

    private function configureJoomla(string $installPath, array $config): void
    {
        // Joomla configuration
        // This would typically require running the Joomla installer via CLI or web
    }

    private function configureNodeJsApp(string $appType, string $installPath, string $domain, array $config): void
    {
        // Initialize Node.js project based on type
        switch ($appType) {
            case 'express':
                $this->setupExpressApp($installPath, $config);
                break;
            case 'nextjs':
                $this->setupNextJsApp($installPath, $config);
                break;
            default:
                $this->setupBasicNodeApp($installPath, $config);
                break;
        }

        // Set up PM2 process manager
        $this->setupPM2($installPath, $domain, $appType);

        // Set up reverse proxy (nginx)
        $this->setupNodeReverseProxy($domain, $config['port'] ?? 3000);
    }

    private function setupBasicNodeApp(string $installPath, array $config): void
    {
        // Create package.json
        $packageJson = [
            'name' => basename($installPath),
            'version' => '1.0.0',
            'description' => 'Node.js application',
            'main' => 'index.js',
            'scripts' => [
                'start' => 'node index.js',
                'dev' => 'nodemon index.js',
            ],
            'dependencies' => [],
        ];

        file_put_contents($installPath . '/package.json', json_encode($packageJson, JSON_PRETTY_PRINT));

        // Create basic index.js
        $indexJs = <<<'JS'
const http = require('http');

const hostname = '127.0.0.1';
const port = 3000;

const server = http.createServer((req, res) => {
  res.statusCode = 200;
  res.setHeader('Content-Type', 'text/html');
  res.end('<h1>Hello from Node.js!</h1>');
});

server.listen(port, hostname, () => {
  console.log(`Server running at http://${hostname}:${port}/`);
});
JS;

        file_put_contents($installPath . '/index.js', $indexJs);
    }

    private function setupExpressApp(string $installPath, array $config): void
    {
        // Create Express.js app
        exec("cd " . escapeshellarg($installPath) . " && npx -y express-generator .");

        // Create package.json with Express
        $packageJson = [
            'name' => basename($installPath),
            'version' => '1.0.0',
            'private' => true,
            'scripts' => [
                'start' => 'node ./bin/www',
                'dev' => 'nodemon ./bin/www',
            ],
            'dependencies' => [
                'express' => '^4.18.0',
                'morgan' => '~1.9.1',
                'cookie-parser' => '~1.4.4',
                'debug' => '~2.6.9',
            ],
        ];

        file_put_contents($installPath . '/package.json', json_encode($packageJson, JSON_PRETTY_PRINT));

        // Install dependencies
        exec("cd " . escapeshellarg($installPath) . " && npm install");
    }

    private function setupNextJsApp(string $installPath, array $config): void
    {
        // Create Next.js app
        exec("cd " . escapeshellarg(dirname($installPath)) . " && npx -y create-next-app@latest " . escapeshellarg(basename($installPath)) . " --typescript --tailwind --app --no-src-dir --import-alias '@/*'");
    }

    private function setupPM2(string $installPath, string $domain, string $appType): void
    {
        $appName = str_replace('.', '_', $domain);

        // Create PM2 ecosystem file
        $ecosystem = [
            'apps' => [[
                'name' => $appName,
                'cwd' => $installPath,
                'script' => $appType === 'nextjs' ? 'npm' : 'index.js',
                'args' => $appType === 'nextjs' ? 'start' : '',
                'instances' => 1,
                'autorestart' => true,
                'watch' => false,
                'max_memory_restart' => '1G',
                'env' => [
                    'NODE_ENV' => 'production',
                    'PORT' => 3000,
                ],
            ]],
        ];

        file_put_contents($installPath . '/ecosystem.config.js', 'module.exports = ' . var_export($ecosystem, true) . ';');

        // Start with PM2
        exec("pm2 start " . escapeshellarg($installPath . '/ecosystem.config.js'));
        exec("pm2 save");
    }

    private function setupNodeReverseProxy(string $domain, int $port): void
    {
        // Create nginx reverse proxy configuration
        $nginxConfig = <<<NGINX
server {
    listen 80;
    server_name {$domain};

    location / {
        proxy_pass http://127.0.0.1:{$port};
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
    }
}
NGINX;

        $configPath = "/etc/nginx/sites-available/{$domain}-node";
        file_put_contents($configPath, $nginxConfig);

        // Enable site
        $enabledPath = "/etc/nginx/sites-enabled/{$domain}-node";
        if (!file_exists($enabledPath)) {
            symlink($configPath, $enabledPath);
        }

        // Reload nginx
        exec("nginx -t && systemctl reload nginx");
    }

    private function getAdminUrl(string $app, string $domain, string $directory): string
    {
        $baseUrl = 'http://' . $domain . ($directory ? '/' . $directory : '');

        switch ($app) {
            case 'wordpress':
                return $baseUrl . '/wp-admin';
            case 'joomla':
                return $baseUrl . '/administrator';
            case 'drupal':
                return $baseUrl . '/admin';
            case 'phpbb':
                return $baseUrl . '/adm';
            default:
                return $baseUrl;
        }
    }

    private function recursiveDelete(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->recursiveDelete($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
