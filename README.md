# RKD LLMs.txt Generator for Magento 2

Generate `llms.txt` and `llms-full.txt` files for your Magento 2 store, making your product catalog discoverable by AI assistants like ChatGPT, Claude, and Perplexity.

## What is llms.txt?

[llms.txt](https://llmstxt.org/) is an emerging standard (like robots.txt for search engines) that helps Large Language Models understand your website content. This module automatically generates structured, AI-optimized files from your Magento catalog.

## Features

- **llms.txt + llms-full.txt** generation (both files, spec-compliant)
- **Inventory-aware** output: excludes out-of-stock products (SQL-level filtering)
- **Variant intelligence**: configurable product options (color, size), bundle items, grouped products, customizable options
- **Niche-adaptive**: admin selects which product attributes to expose (gender for apparel, specs for electronics, ingredients for food)
- **Rich product data**: price, SKU, descriptions, custom attributes
- **Category breadcrumbs**: hierarchical category paths with product counts
- **Spec validation**: checks output against the llms.txt standard
- **Built-in sanitization**: HTML entities decoded, whitespace normalized, tables converted to markdown
- **Multi-store & multi-language**: one file set per store view with automatic cross-language discovery — each language's llms.txt links to its siblings so AI crawlers find them all
- **Cron-based auto-regeneration** with change detection (dirty flags)
- **Manual generation**: admin button, CLI command, REST API
- **Robots.txt integration**: auto-injects llms.txt references
- **Performance optimized**: cursor-based pagination (no OFFSET degradation) + batched EAV queries. Tested on catalogs up to 100K products.

## Requirements

- Magento 2.4.7 or later (tested on 2.4.7-p1 and 2.4.8)
- PHP 8.1 or later (developed on PHP 8.3)

## Installation

### Via Composer (recommended)

```bash
composer require rkd/module-llms-txt
bin/magento module:enable RKD_LlmsTxt
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

### Manual Installation

1. Create `app/code/RKD/LlmsTxt/` directory
2. Copy module files into it
3. Run:

```bash
bin/magento module:enable RKD_LlmsTxt
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Configuration

Navigate to **Stores > Configuration > RKD > LLMs.txt Generator**

### General Settings

| Field | Description | Default |
|-------|-------------|---------|
| Enable Module | Enable/disable the module | Yes |
| Auto-Regeneration | Regenerate files on a cron schedule | Yes |
| Regeneration Schedule | How often to regenerate | Daily |
| Store Description for AI | Describe your store for AI assistants (1-2 sentences) | Auto-generated |
| Generate Now | Button to trigger immediate generation | — |

### Content Sections

| Field | Description | Default |
|-------|-------------|---------|
| Include CMS Pages | Include CMS pages in output | Yes |
| Include Categories | Include product categories | Yes |
| Category Depth Limit | Maximum category tree depth | 3 |
| Include Products | Include products in output | Yes |
| Product Limit | Maximum number of products. Set to 0 for unlimited (full catalog). | 0 (unlimited) |
| Product Sort Order | How to sort products | Best Sellers |
| Include Store Metadata | Include store info section | Yes |
| Exclude Out-of-Stock | Filter out unavailable products | Yes |

### Product Data

| Field | Description | Default |
|-------|-------------|---------|
| Include Prices | Show product prices | Yes |
| Include SKU | Show product SKU | Yes |
| Include Short Description | Show product descriptions | Yes |
| Additional Product Attributes | Select extra attributes for your niche | — |

### llms-full.txt Settings

| Field | Description | Default |
|-------|-------------|---------|
| Generate llms-full.txt | Generate the complete content file | Yes |
| Max File Size (MB) | Size limit for llms-full.txt | 5 MB |
| Content Depth | How much detail per product | Detailed |

## Usage

### CLI Command

```bash
# Generate both files
bin/magento rkd:llmstxt:generate

# Generate for a specific store
bin/magento rkd:llmstxt:generate --store=1

# Preview without writing files
bin/magento rkd:llmstxt:generate --dry-run

# Generate and validate
bin/magento rkd:llmstxt:generate --validate
```

### REST API

```
POST /V1/rkd/llmstxt/generate    # Trigger generation
GET  /V1/rkd/llmstxt/preview     # Preview output
GET  /V1/rkd/llmstxt/validate    # Validate existing file
```

### URLs

After generation, the files are served at:

- `https://yourstore.com/llms.txt`
- `https://yourstore.com/llms-full.txt`

### Admin Panel

- **Marketing > LLMs.txt > Generate Files** — trigger generation
- **Marketing > LLMs.txt > Preview Output** — preview without writing
- **Marketing > LLMs.txt > Configuration** — module settings

## How It Works

1. **SectionProviders** collect data from Magento (metadata, CMS pages, categories, products)
2. **Generator** orchestrates providers, builds markdown output
3. **Validator** checks spec compliance
4. **FileWriter** writes to `var/rkd_llmstxt/` with atomic writes
5. **Router** serves the files at `/llms.txt` with proper UTF-8 headers
6. **Observers** detect entity changes and set dirty flags
7. **Cron** checks dirty flags and regenerates when needed

### Performance

The module uses **cursor-based pagination** (`WHERE entity_id > :lastId` — no OFFSET performance cliff) combined with **PHP generators** (`yield`) so each batch is processed and released before the next is loaded. Batch-level memory is bounded; total memory scales with catalog size because the final output is assembled before writing. Write-up of the technique: [Paginating Magento catalogs without OFFSET](https://devrob.in/blog/paginating-magento-catalogs-without-offset).

| Catalog Size | Peak Memory | Time | Recommended PHP `memory_limit` |
|--------------|-------------|------|--------------------------------|
| 1,000 products | ~60 MB | ~0.2s | 256M |
| 10,000 products | ~120 MB | ~2s | 512M (default cap) |
| 100,000 products | ~400 MB | ~15–20s | 1024M (raise `product_limit` in admin) |

By default `Product Limit` is **0 (unlimited)** — the full visible catalog is included. You can optionally cap it via `Stores > Configuration > RKD > LLMs.txt > Product Limit` (for example, to generate a smaller "top products" file). When a configured cap is hit, the generation result surfaces a clear warning telling you how many products were excluded. For very large catalogs (50K+ products), ensure PHP `memory_limit` is sized accordingly using the table above.

## Multi-Store and Multi-Language Support

Multi-language is a first-class feature, not an afterthought. The module generates **one file set per store view** and makes every language discoverable by AI crawlers from any entry point.

### How it works

For each active store view, the module writes:

- `var/rkd_llmstxt/{store_code}/llms.txt`
- `var/rkd_llmstxt/{store_code}/llms-full.txt`

…served at the store view's public URL:

| URL an AI fetches | What it gets |
|-------------------|--------------|
| `https://example.com/llms.txt` | Default store view's file (set in Magento's store configuration) |
| `https://example.com/et/llms.txt` | Estonian store view |
| `https://example.com/ru/llms.txt` | Russian store view |
| `https://example.com/de/llms.txt` | German store view |

### Automatic cross-language discovery

Each file includes an **"Available in Other Languages"** section listing sibling store views on the same website:

```markdown
## Available in Other Languages

This store is also available in other language versions. Each language has its own AI-readable catalog:

- [Russian](https://example.com/ru/llms.txt)
- [German](https://example.com/de/llms.txt)
```

An AI that fetches any one language's llms.txt will find the others automatically — no external sitemap or registry needed.

### Language handling internals

Content resolution follows Magento's native EAV store-scope fallback:

- Product, category, and CMS fields load at the requested store view's scope
- When a field isn't translated for that store view, the default (admin-scope) value is used — identical to Magento's storefront behavior
- UTF-8 is preserved end-to-end: Cyrillic, Greek, Chinese, Arabic, emoji, and extended Latin (`ä`, `õ`, `ß`, `ü`) all render correctly

> **Note:** For AI output to be fully localized per language, merchants should translate product names, descriptions, and attribute values for each store view. If content isn't translated, the fallback (default-scope) value appears — accurate to what the storefront serves, but potentially mixing languages in the output. This is a content-translation responsibility, not a module limitation.

## Supported Product Types

| Type | Output |
|------|--------|
| Simple | Name, URL, SKU, Price, Description |
| Configurable | + Available options (Color, Size, etc.) |
| Bundle | + Included items per bundle option |
| Grouped | + Associated products with prices |
| Virtual | + "Digital" label |
| Downloadable | + "Download" label |
| Customizable Options | + Add-on options with prices |

## Change Detection

The module tracks changes via Magento events:

- `catalog_product_save_after` — product changes
- `cataloginventory_stock_item_save_after` — stock changes
- `cms_page_save_after` — CMS page changes
- `catalog_category_save_after` — category changes

When a change is detected, a dirty flag is set. The next cron run regenerates the files.

## License

MIT License. See [LICENSE.txt](LICENSE.txt).

## Support

- **Bug reports and feature requests:** [GitHub Issues](https://github.com/iamrobindhiman/magento2-module-llms-txt/issues)
- **Questions and discussion:** [GitHub Discussions](https://github.com/iamrobindhiman/magento2-module-llms-txt/discussions)
- **Author:** [Robin Dhiman](https://devrob.in) — senior web engineer, fifteen years in Magento 2 and Hyvä. More writing at [devrob.in/blog](https://devrob.in/blog); contact hello@devrob.in for engagements.
