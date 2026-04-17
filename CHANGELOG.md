# Changelog

All notable changes to the RKD LLMs.txt Generator module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-04-17

### Added

- **llms.txt generation** following the [llms.txt specification](https://llmstxt.org/)
- **llms-full.txt generation** with complete product content for AI context windows
- **Product type support**: Simple, Configurable (with color/size options), Bundle (with selections), Grouped (with associated products), Virtual, Downloadable, and Customizable Options
- **Inventory-aware filtering**: excludes out-of-stock products at SQL level
- **Niche-adaptive attributes**: admin-selectable multiselect for extra product attributes (gender, material, brand, warranty, etc.)
- **Category breadcrumb paths**: hierarchical category display (e.g., "Women > Tops > Jackets")
- **CMS page content**: includes active CMS pages with HTML-to-markdown conversion
- **Store metadata**: store name, description, base URL, currency
- **Text sanitization**: HTML entity decoding, whitespace normalization, bullet point conversion, HTML table to markdown table conversion
- **Spec validation**: checks output against llms.txt format requirements
- **Custom admin config panel** under Stores > Config > RKD > LLMs.txt Generator
- **Store description field**: merchant-written store description for AI context
- **CLI command**: `bin/magento rkd:llmstxt:generate` with --dry-run, --validate, --store options
- **REST API**: generate, preview, and validate endpoints
- **Cron-based auto-regeneration** with configurable schedule (hourly/daily/weekly)
- **Change detection**: observers on product, category, CMS page, and stock changes with section-level dirty flags
- **Manual "Generate Now" button** in admin config page
- **Admin preview** page for reviewing output before generation
- **Robots.txt integration**: auto-injects llms.txt references
- **Custom frontend router**: serves `/llms.txt` and `/llms-full.txt` with proper UTF-8 headers
- **Atomic file writes**: temp file + rename pattern prevents corruption on failure
- **Metadata footer**: generation timestamp, version, currency, and catalog counts
- **Multi-store support**: separate files per store view
- **Cursor-based pagination** (`WHERE entity_id > :lastId`) — avoids OFFSET performance cliff on large catalogs
- **PHP generators (`yield`)** — per-batch memory bounded, each batch released before the next is loaded
- **Batch variant loading**: 1 SQL query per product type per batch (no N+1)
- **Default safety cap of 10,000 products** (configurable) — prevents OOM on very large catalogs
- **ACL permissions** for admin actions
- **Admin sidebar menu** under Marketing > LLMs.txt
- **MIT License**
