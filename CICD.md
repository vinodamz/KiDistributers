# CI/CD — auto-deploy to Hostgator (cPanel pull model)

## How it works

```
   git push                                       cPanel git pull
GitHub  ──────► GitHub Actions  ──── HTTPS:2083 ───────────────► GitHub
                      │                       │
                      │ POST UAPI              ▼
                      ▼                  cPanel clones / updates
              cPanel UAPI calls           /home/<user>/repos/KiDistributers
                  • VersionControl/update        │
                  • VersionControlDeployment     ▼
                    /create              runs .cpanel.yml
                                                │
                                                ▼
                                       rsync into docroot
                                  /home/<user>/kidistributers
```

No FTP. The only secret that leaves GitHub is the cPanel API token, sent over HTTPS
in an `Authorization` header. cPanel itself fetches from GitHub.

Merging (or pushing) to `main` runs lint, then tells cPanel to pull `main` and rsync
via `.cpanel.yml`. Feature branches and pull requests only run PHP lint
(`.github/workflows/lint.yml`).

## One-time setup (in cPanel)

### 1. Create the subdomain and empty docroot

1. cPanel → **Domains → Subdomains** (or **Domains**).
2. Create the Ki Distributers hostname. Document root: `/home/<cpaneluser>/kidistributers`
   (must match the rsync destination in `.cpanel.yml`).
3. Confirm AutoSSL for that hostname.

If the docroot must live elsewhere (e.g. `$HOME/example.com/kidistributers`), change the
rsync path in `.cpanel.yml` in the same commit that first enables deploy.

### 2. Enable Git Version Control and clone the repo

1. cPanel → **Git Version Control** → **Create**.
2. **Clone a Repository** ON.
3. Clone URL: `https://github.com/vinodamz/KiDistributers.git`
4. Repository path: `/home/<cpaneluser>/repos/KiDistributers`
   (use a path **outside** the docroot so `.git/` is never web-served).
5. Repository name: `KiDistributers`. **Create**.

cPanel clones for you. The first checkout takes a few seconds.

### 3. Create a cPanel API token

1. cPanel → top-right user menu → **Manage API Tokens**.
2. **Create**. Name: `gha-deploy`.
3. If your cPanel supports scopes, restrict to `VersionControl` and `VersionControlDeployment`.
4. **Create** → copy the token immediately (you cannot view it again).

### 4. Find the exact cPanel hostname

cPanel → top-right user menu → “Server Name”, or the hostname in the URL you log in with.
It looks like `s3744.bom1.stableserver.net`. That is `CPANEL_HOST`.

Use the **server hostname**, not the site domain. The TLS certificate on port 2083 is issued
for the server name; a custom domain will fail verification.

### 5. Add secrets to the GitHub repo

GitHub → repo **Settings → Secrets and variables → Actions → New repository secret**.

| Name           | Value |
|----------------|--------|
| `CPANEL_HOST`  | Server hostname, e.g. `s3744.bom1.stableserver.net` |
| `CPANEL_USER`  | cPanel username |
| `CPANEL_TOKEN` | Token from step 3 |
| `DEPLOY_URL`   | Optional live probe, e.g. `https://kd.example.com/login.php` |

`REPO_ROOT` in the workflow is `/home/<CPANEL_USER>/repos/KiDistributers`. If you clone
somewhere else, change that path in `.github/workflows/deploy.yml`.

### 6. First deploy from cPanel

Before relying on Actions, open cPanel → **Git Version Control → Manage → Pull or Deploy**
for this repo. That runs `.cpanel.yml` and fills the docroot.

After that, every merge to `main` (or **Actions → Deploy to Hostgator (cPanel pull) → Run
workflow**) repeats the same flow.

## App bootstrap (still manual, one time)

The Action ships **code**, not databases. After the first rsync:

1. cPanel → **MySQL Databases** → create DB + user + grant ALL.
2. cPanel → **phpMyAdmin** → import `sql/schema.sql` (and `sql/seeds.sql` if wanted).
3. File Manager → `/home/<cpaneluser>/kidistributers/includes/` → create `config.php` from
   `config.example.php` with real DB credentials.
   `.cpanel.yml` **excludes** `includes/config.php` from rsync, so later deploys will not
   overwrite it.
4. Visit `/install.php` once to create the first admin, then **delete** `install.php`.
   Later deploys also omit it.

## How to inspect a deploy

| Where | What you see |
|---|---|
| GitHub → **Actions** | Lint logs, UAPI JSON (`status`, `messages`, `errors`) |
| cPanel → **Git Version Control → Manage → Pull or Deploy** | Last pull/deploy timestamps and log |
| `~/last-kd-deploy.log` on the server | rsync output from `.cpanel.yml` |
| cPanel → **Errors** | PHP runtime errors after deploy |

## Manual trigger

GitHub → **Actions** → **Deploy to Hostgator (cPanel pull)** → **Run workflow** → `main`.

## Forcing a clean slate

1. File Manager → delete everything under the docroot **except** `includes/config.php`.
2. Git Version Control → **Manage → Pull or Deploy** → **Deploy HEAD**.

## Security notes

- If cPanel does not support token scopes, the token has **full account access**. Treat it
  like a root password. Rotate at least every 90 days.
- Never commit `includes/config.php`.
- The clone at `/home/<user>/repos/KiDistributers` must stay outside the docroot. Visiting
  `https://<site>/.git/` should 404.
