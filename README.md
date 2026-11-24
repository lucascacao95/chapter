# My Library

A personal ebook library for uploading and organizing EPUB and PDF files, extracting metadata and covers, rating books, and sharing selected activity with people you follow. It also includes a Send to Kindle proof of concept using Amazon's email workflow. The product initially targets Brazilian readers, with a Brazilian Portuguese interface.

Built with Laravel, Inertia.js, Vue 3, TypeScript, and Tailwind CSS. The local environment uses Docker Compose with PostgreSQL and Redis.

## Local development

Requirements: Docker Desktop with Docker Compose.

```bash
docker compose up --build -d
```

The application is available at [http://localhost:8001](http://localhost:8001). Vite serves assets at `http://localhost:5173`.

The database and Redis services are accessible only through the internal Compose network. To run migrations:

```bash
docker compose exec app php artisan migrate
```

To run tests:

```bash
docker compose exec app php artisan test
```

To stop the containers without removing local data:

```bash
docker compose down
```

## Authentication

- Registration: http://localhost:8001/register (name, email, and a password of at least 8 characters).
- Login: http://localhost:8001/login. After signing in, the personal dashboard is at `/dashboard`.
- Password recovery: http://localhost:8001/forgot-password.
- Local email: http://localhost:8025 (Mailpit). SMTP uses `mailpit:1025` inside Docker and does not send email to external recipients.

Registration signs the user in automatically, without email confirmation. Password reset links expire after 60 minutes, are single-use, and can only be requested again after 60 seconds. The request displays the same message for registered and unknown email addresses.

Sessions and password reset tokens use the existing PostgreSQL tables. Tests use an in-memory SQLite database, isolated from local data. The project's PHP image includes `pdo_sqlite`.

```bash
docker compose exec app vendor/bin/pint --test
docker compose exec vite npm run typecheck
docker compose exec vite npm run build
```

To check the local flow: create an account, sign out, sign in again, request a password reset, open the email in Mailpit, reset the password, and sign in with the new password.

Flow details and limits: [authentication](docs/development/authentication.md).

## Home and feed

The authenticated home page at [http://localhost:8001/dashboard](http://localhost:8001/dashboard) shows the three most recently updated books and activities shared by you and the people you follow, with infinite scrolling. Uploads, manually added books, and ratings only appear in the feed when sharing is enabled. Files and unshared data remain private.

To load the local demo after running migrations:

```bash
docker compose restart queue
docker compose exec app php artisan db:seed --class=FeedDemoSeeder
```

Sign in with **`ana@feed-demo.example.test`**, password **`FeedDemo123!`**. The worker will process the synthetic EPUB/PDF files and publish the activities. The seeder creates only demo accounts, can be rerun without duplicating the original records, and does not send email. Followers, contracts, states, and other accounts: [feed and demo](docs/development/feed.md).

## Library backend

The JSON endpoints for [collections](docs/development/collections.md) and [books](docs/development/books.md) use authenticated sessions and CSRF protection. Each user can access only their own records. Book CRUD supports metadata management and assignment to the user's own collections; [EPUB/PDF uploads](docs/development/book-uploads.md) accept one file per request, up to 50 MB, and extract metadata through queued jobs.

The book interface is available at [My books](http://localhost:8001/books), with a card-based library and an [add books page](http://localhost:8001/books/create) that prioritizes uploads and offers manual entry in a secondary tab. Cards show processing progress and automatically update the title, author, and cover. When no cover is available, they use a typographic placeholder. The library supports creating collections through a dialog, browsing an accordion with pagination per group, and organizing individual books. Users can also select or create a collection while adding a book.

## Book ratings

Each card's **Actions** menu includes **Rate book**, **Organize**, and **Send to Kindle**. Ratings support half-star increments from 0 to 5 and an optional comment. Each save creates a version, available in the **History** tab. The **By rating** view groups books by their latest rating range, with counts and pagination, including an **Unrated** group. Run migrations before using this feature. Details: [ratings and grouping](docs/development/book-ratings.md).

## Files and local processing

Original files are stored on the private local disk (`storage/app/private`), following the initial development decision. The `queue` service processes metadata and cleans up deleted files; it starts through Docker Compose. EPUB processing uses `kiwilan/php-ebook`; the PHP image includes GD for cover processing and `poppler-utils` for PDFs.

After updating the application:

```bash
docker compose up --build -d
docker compose exec app php artisan migrate
```

Details, states, and limitations: [book uploads](docs/development/book-uploads.md).

## Send to Kindle proof of concept

Book cards let users request delivery of the original file to Kindle through real Gmail SMTP, using queued jobs. The temporary destination uses `KINDLE_EMAIL` in `.env`; credentials use `KINDLE_SMTP_USERNAME` and `KINDLE_SMTP_PASSWORD`, falling back to `MAIL_USERNAME` and `MAIL_PASSWORD`. The sender's Gmail address must be authorized in the Amazon account.

This proof of concept has an 18 MB limit per file. The interface tracks queue status, attempts, and SMTP acceptance without claiming delivery to the device. A dedicated table records attempts; run migrations before using the action. Installation does not trigger automatic sending. Details: [Kindle proof of concept](docs/development/kindle-poc.md).
