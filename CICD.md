# CI/CD — cPanel pull (same model as Little Graduates)

PHP runs from source. After the GitHub repo exists:

1. cPanel → Git Version Control → clone this repo.
2. Create a subdomain and point `.cpanel.yml` rsync at that docroot.
3. Create a MySQL database, import `sql/schema.sql` (+ `seeds.sql` if wanted).
4. Copy `includes/config.example.php` → `includes/config.php` on the server (never commit it).
5. Open `/install.php` once, then delete it.

GitHub Actions currently lint PHP on every non-main push. Wire a deploy workflow (cPanel UAPI git-pull) when the Hostgator target is ready — copy the pattern from MontessoriTraineeTeacher’s `CICD.md`.
