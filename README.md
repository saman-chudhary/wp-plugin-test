# WordPress Plugin Test Environment

A disposable WordPress + MySQL environment for testing the **Content Health SEO**
plugin (already mounted at `wp-content/plugins/seo-content-health`), runnable
in GitHub Codespaces or locally with Docker — much faster than a constrained
local WordPress app.

## Option 1 — GitHub Codespaces (recommended if local is slow)

1. Create a new **empty** GitHub repository (e.g. `wp-plugin-test`).
2. Push this folder to it:
   ```bash
   cd wp-test-environment
   git init
   git add .
   git commit -m "WP test environment with Content Health SEO plugin"
   git branch -M main
   git remote add origin https://github.com/YOUR_USERNAME/wp-plugin-test.git
   git push -u origin main
   ```
3. On the repo page on github.com, click the green **Code** button → **Codespaces** tab → **Create codespace on main**.
4. Wait ~1-2 minutes for the container to build. It will prompt to forward port **8080** — click it (or check the "Ports" tab in the bottom panel and open port 8080 in browser).
5. Visit `http://localhost:8080/wp-admin/install.php` (Codespaces will proxy this for you) and complete the 1-minute WordPress setup wizard.
6. Go to **Plugins**, activate "Content Health SEO", and test away.
7. When done, just delete the Codespace (or let it auto-stop) — nothing is left running and costs nothing beyond your free Codespaces hours.

## Option 2 — Run locally with Docker (no GitHub needed)

If you have Docker Desktop installed, this alone is usually much faster than
"Local":
```bash
cd wp-test-environment
docker compose up -d
```
Then open **http://localhost:8080** and run the WordPress install wizard.

To stop:
```bash
docker compose down
```

To reset the database completely:
```bash
docker compose down -v
```

## Updating the plugin

Any time you edit files in `wp-content/plugins/seo-content-health/`, just
refresh the browser — Docker mounts that folder live, no rebuild needed.
