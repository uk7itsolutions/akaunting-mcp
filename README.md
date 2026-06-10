# Akaunting MCP Server

A Laravel-based [Model Context Protocol](https://modelcontextprotocol.io) server for [Akaunting](https://akaunting.com). It exposes your Akaunting instance as MCP tools so AI clients like Claude can manage contacts, items, invoices, bills, and transactions on your behalf.

> **Compatibility:** Built and tuned for **Akaunting 3.1.21**. The tool input schemas mirror that version's API validation rules (required fields, date formats, the `payment_method` code format, etc.). This server communicates with Akaunting via its REST API (`/api`). Akaunting's API surface can change between versions — always verify compatibility against the [Akaunting changelog](https://github.com/akaunting/akaunting/releases) and the [API docs](https://akaunting.com/hc/docs/developers/restful-api/) before upgrading.
>
> **Recording payments** requires the [Offline Payments](https://akaunting.com/apps/offline-payments) app (bundled by default). `create_transaction` defaults `payment_method` to `offline-payments.cash.1`, the seeded Cash method.

## How It Works

```
MCP Client (e.g. Claude Desktop)
        │  Authorization: Basic <base64(email:password)>
        │  X-Company-ID: <optional company id>
        ▼
accounting-mcp.yourdomain.com/mcp
        │
        ├── ValidateAkauntingKey middleware
        │   Verifies the credentials against your Akaunting instance
        │   (GET /api/ping). If it fails, returns 401. If it passes, the
        │   same credentials are used for all subsequent API calls.
        │
        └── MCP Tools → Akaunting REST API
```

**Authentication** is handled entirely by Akaunting. Each user authenticates with the email and password of an Akaunting user that has the `read-api` permission (granted to the admin role by default). No separate user database is needed.

**Multi-company:** Akaunting requires a `company_id` on every request. The server uses `AKAUNTING_COMPANY_ID` from `.env` by default, and an MCP client can override it per connection by sending an `X-Company-ID` header. Use the `list_companies` tool to discover company IDs.

---

## Requirements

- PHP 8.2+
- Composer
- An Akaunting instance with API access (a user with the `read-api` permission)

---

## Installation (Plesk)

### 1. Create a Subdomain

In Plesk → **Websites & Domains** → **Add Subdomain**.
Name it `accounting-mcp` (or whatever you prefer).

### 2. Clone the Repository

Open **SSH Terminal** for the subdomain and run:

```bash
git clone https://github.com/uk7itsolutions/akaunting-mcp.git .
```

### 3. Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

This creates `.env` from `.env.example` and generates the application key automatically. If `storage/` and `bootstrap/cache/` need to be writable by the web server:

```bash
chmod -R 775 storage bootstrap/cache
```

### 4. Configure `.env`

Open `.env` and set the Akaunting URL (no trailing slash) and the default company:

```env
AKAUNTING_BASE_URL=https://accounting.yourdomain.com
AKAUNTING_COMPANY_ID=1
```

The app reads these via `config/akaunting.php` and uses them for every API call.

### 5. Set the Document Root

In Plesk → **Websites & Domains** → your subdomain → **Hosting Settings**,
set the document root to:

```
accounting-mcp.yourdomain.com/public
```

### 6. Enable nginx Rewrites (Plesk)

Plesk runs nginx in front of Apache. By default, nginx tries to serve every URL as a static file and returns 404 for routes like `/mcp`. To fix this:

1. Plesk → **Websites & Domains** → your subdomain → **Apache & nginx Settings**
2. In **Additional nginx directives**, paste:

   ```nginx
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```

3. Click **OK**.

### 7. Enable SSL

In Plesk → **SSL/TLS Certificates** → **Let's Encrypt** → check **Redirect HTTP to HTTPS** → **Get it free**.

### 8. Verify

Visit `https://accounting-mcp.yourdomain.com/` — you should see a small JSON response showing the MCP endpoint URL. That confirms Laravel and the nginx rewrite are working.

---

## Connecting an MCP Client

The `Authorization` header is HTTP Basic: base64-encode `email:password`. For example, `admin@company.com:password` becomes `YWRtaW5AY29tcGFueS5jb206cGFzc3dvcmQ=`.

Add the following to your MCP client configuration (e.g. Claude Desktop's `claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "akaunting": {
      "url": "https://accounting-mcp.yourdomain.com/mcp",
      "headers": {
        "Authorization": "Basic <base64(email:password)>",
        "X-Company-ID": "1"
      }
    }
  }
}
```

`X-Company-ID` is optional; omit it to use the `AKAUNTING_COMPANY_ID` default.

---

## Available Tools

| Tool | Description |
|---|---|
| `list_contacts` | List contacts, filtered by type (customer/vendor) or search |
| `get_contact` | Get full details for a single contact |
| `create_contact` | Create a customer or vendor |
| `update_contact` | Update fields on an existing contact |
| `list_items` | List items (products and services) |
| `create_item` | Create an item with sale/purchase prices |
| `list_documents` | List invoices and bills, filtered by type or search |
| `get_document` | Get full details for a single document, including line items |
| `create_document` | Create an invoice or bill from line items |
| `list_transactions` | List income and expense payments |
| `create_transaction` | Record an income or expense payment |
| `list_accounts` | List financial accounts (bank, cash, etc.) |
| `list_categories` | List categories, filtered by type |
| `list_taxes` | List tax rates |
| `list_currencies` | List configured currencies |
| `list_companies` | List companies the user can access (to find company IDs) |

---

## Project Structure

```
app/
├── Http/
│   └── Middleware/
│       └── ValidateAkauntingKey.php   # Validates Basic credentials against /api/ping
├── Mcp/
│   ├── Servers/
│   │   └── AkauntingServer.php        # Registers all tools
│   └── Tools/                         # One class per MCP tool (16 total)
└── Services/
    └── AkauntingClient.php            # HTTP client for the Akaunting REST API

routes/
├── ai.php    # Registers the MCP server at /mcp
└── web.php   # Health-check JSON at /

bootstrap/app.php       # Middleware alias + CSRF exclusion for /mcp
config/akaunting.php    # Reads AKAUNTING_BASE_URL and AKAUNTING_COMPANY_ID from .env
```

---

## License

This project is licensed under the [MIT License](LICENSE).

It communicates with Akaunting solely via its REST API and contains no Akaunting source code.
