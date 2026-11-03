# HourGate – Store Hours for WooCommerce

Take WooCommerce orders only while your store is open: weekly opening hours, holiday closures, special hours and a one-click pause, enforced on the Checkout block, the classic checkout and the Store API.

This repository holds the full, unminified source of the plugin published on WordPress.org:
https://wordpress.org/plugins/hourgate/

## Source layout

- `includes/` – PHP (namespace `HourGate\`, PSR-4 autoloaded by Composer)
- `src/` – JavaScript and CSS source for the admin screens, blocks and storefront script
- `build/` – compiled assets; not in this repository, generated from `src/` by the build below

## Building

Requirements: Node.js 20+, npm and Composer.

```sh
composer dump-autoload   # generates vendor/autoload.php
npm ci                   # installs the build tools
npm run build            # compiles src/ into build/ with @wordpress/scripts
```

`npm run start` rebuilds on every change while developing.

## Contributing

Bug reports and pull requests are welcome. For support, please use the [WordPress.org forum](https://wordpress.org/support/plugin/hourgate/).

## License

GPLv3 or later. See [LICENSE](LICENSE).
