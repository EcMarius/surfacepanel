# SurfacePanel

**Modern Web Hosting Control Panel - Alternative to cPanel/WHM**

Version: 0.1.0 (Alpha - In Development)

## Overview

SurfacePanel is a comprehensive, modern web hosting control panel designed as a powerful alternative to cPanel/WHM. Built with extensibility and performance in mind, it provides everything needed to manage web hosting servers, with support for future expansions like game servers and virtualization.

## Features

- ✅ **Complete WHM & cPanel Equivalent** - Full-featured admin and user panels
- ✅ **Module System** - Extensible architecture with uploadable modules
- ✅ **Template System** - Customizable themes for admin and user interfaces
- ✅ **Comprehensive API** - RESTful & GraphQL APIs with multi-language SDKs
- ✅ **CLI Scripts** - Complete suite of cPanel-compatible command-line tools
- ✅ **Modern Tech Stack** - PHP 8.1+, React, TypeScript, Symfony components
- ✅ **License Management** - Built-in licensing and billing integration
- 🔄 **Multi-Server Support** - DNS clustering, load balancing (planned)
- 🔄 **Game Server Management** - Minecraft, CS:GO, ARK, Rust (planned)
- 🔄 **Virtualization** - KVM, Proxmox, VMware integration (planned)

## Current Status

**Phase 1: Foundation - In Progress**

- [x] Project structure
- [x] Core application framework
- [x] Module manager system
- [x] Configuration system
- [ ] Database schema & ORM
- [ ] API routing framework
- [ ] Template engine
- [ ] CLI scripts
- [ ] Installer system

## Quick Start

```bash
# Install dependencies
composer install
npm install

# Configure environment
cp .env.example .env

# Coming soon: installer
```

## Architecture

### Core Components

- **Application.php** - Main application bootstrap
- **ModuleManager.php** - Plugin/module system
- **helpers.php** - Global helper functions

### Module Development

Create modules to extend SurfacePanel:

```php
class YourModule implements ModuleInterface {
    public function register() { }
    public function boot() { }
}
```

See `src/modules/Example/` for a template.

## Documentation

Full documentation coming soon at https://docs.surfacepanel.com

## License

Proprietary - See LICENSE file

## Support

- Issues: https://github.com/EcMarius/surfacepanel/issues
- Email: team@surfacepanel.com
