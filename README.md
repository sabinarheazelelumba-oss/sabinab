# Product Management API — LavaLust

REST API for Laboratory Exercise No. 6 (CRUD + JWT authentication), built on **LavaLust v4**.
Database: **Aiven MySQL**. Deployment: **Render** (Docker).

* Endpoints, setup, deployment and troubleshooting: see the **master `README.md`** that ships next to this folder
  (sections 1–9). Quick start:

```bash
cp .env.example .env
php lava jwt:generate
# fill DB_* in .env (Aiven), put the Aiven CA certificate in ./ca.pem
php lava migration run     # or run ../database/aiven_setup.sql on Aiven
php lava serve             # http://127.0.0.1:3000
```

Important files

| Path | Purpose |
|---|---|
| `app/controllers/AuthController.php` | register / login / refresh / logout / me |
| `app/controllers/ProductController.php` | product CRUD (JWT-protected) |
| `app/models/ProductModel.php` | SQL for the `products` table |
| `app/controllers/MigrationController.php`, `app/commands/Migration.php` | migration routes + `php lava migration ...` |
| `app/migrations/` | `000`…`004` (migrations, users, refresh_tokens, **products**, admin seed) |
| `app/config/routes.php` | all routes |
| `Dockerfile`, `render.yaml` | Render deployment |

Default account after migrating: `admin@example.com` / `Admin@12345` — change it for anything public.
Never commit `.env`; use environment variables on Render.
