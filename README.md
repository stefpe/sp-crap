# sp-crap-check

Zero-dependency PHP CLI to gate PHPUnit **crap4j** reports on CRAP score — compatible with [php-crap-checker](https://github.com/LucianoVandi/php-crap-checker), plus JSON config and ignore rules.

**Requirements:** PHP 8.3+ (8.5 compatible), `ext-simplexml`

## Install from release

```bash
curl -fsSL https://github.com/stefpe/sp-crap/releases/latest/download/sp-crap-check.phar \
  -o /usr/local/bin/sp-crap-check
chmod +x /usr/local/bin/sp-crap-check
```

Or download the `.zip` asset, extract, and run `php sp-crap-check` from the folder.

## Usage

After PHPUnit writes crap4j:

```bash
php -d pcov.enabled=1 vendor/bin/phpunit --coverage-crap4j var/coverage/crap4j.xml

sp-crap-check check var/coverage/crap4j.xml --threshold=30 --max-violations=111 -v
```

With a project config file `.crap-checker.json` (see `.crap-checker.json.example`):

```bash
sp-crap-check check -v
sp-crap-check doctor
```

### Config (JSON)

| Key | Description |
|-----|-------------|
| `report` | Default path to crap4j.xml |
| `threshold` | Max CRAP per method (default `30`) |
| `max_violations` | Ratchet: allow N methods over threshold |
| `ignore_files` / `ignore_coverage_files` | Path globs |
| `ignore_classes` | FQCN globs (`App\\Legacy\\*`) |
| `ignore_methods` | `Class::method`, `Class::*`, or method name |

CLI flags override config. `--config=/path/to.json` selects a file; otherwise `.crap-checker.json` is searched upward from the current directory.

## Exit codes

| Code | Meaning |
|-----:|---------|
| 0 | OK |
| 1 | Threshold / violation budget exceeded |
| 2 | Invalid input |
| 3 | Report not found |
| 4 | Invalid XML |
| 5 | No methods |
| 6 | Stale report (`--max-age`) |

## Development

```bash
php sp-crap-check check tests/fixtures/crap4j.xml --threshold=30
php -d phar.readonly=0 tools/build-phar.php
```
