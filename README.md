Translation Management Service API
==================================

This project provides a robust and performant API for managing translations across multiple locales and contexts. It's built with Laravel, focusing on clean code, scalability, and secure access.

Features
--------

*   **Locale Management:** CRUD operations for defining languages (e.g., en, fr, es).

*   **Tag Management:** CRUD operations for categorizing translations by context (e.g., mobile, desktop, web).

*   **Translation Management:** CRUD operations for translation entries, linked to specific locales and tags.

*   **Flexible Search:** Search translations by key, content, associated tags, or locale.

*   **High-Performance JSON Export:** An optimized endpoint to efficiently deliver all translations for a given locale, suitable for frontend applications.

*   **Scalability Testing:** Includes a command to seed the database with over 100,000 translation records to test performance under load.

*   **API Security:** All API endpoints are protected using Laravel Sanctum for token-based authentication.


Technical Stack
---------------

*   **PHP:** 8.2+

*   **Laravel:** 10

*   **Database:** MySQL

*   **Authentication:** Laravel Sanctum


Setup Instructions
------------------

Follow these steps to get the project up and running on your local machine.

### Prerequisites

*   **PHP (8.2+):** Ensure you have the correct PHP version installed.

*   **Composer:** The PHP package manager.

*   **MySQL (8+):** Or another relational database.

*   **Web Server:** A local web server like Apache, Nginx, or using Laravel's built-in php artisan serve.


### 1\. Clone the Repository

```cmd
git clone your_repo_url translation_service
cd translation_service
```

### 2\. Install PHP Dependencies

```cmd
composer install
```

### 3\. Configure Environment Variables

Create your .env file from the example and generate an application key.

```cmd
cp .env.example .env
php artisan key:generate
```

Now, open the .env file and update your database credentials:

```env
APP_NAME="Translation Service"
APP_ENV=local
APP_KEY=base64:YOUR_GENERATED_KEY_HERE
APP_DEBUG=true
APP_URL=http://localhost:8000  # Or your local development URL

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=translation_service  # Create this database manually in MySQL
DB_USERNAME=root
DB_PASSWORD=
```

**Important:** Manually create the translation\_service database in your MySQL server (e.g., using phpMyAdmin, MySQL Workbench, or the MySQL CLI).

### 4\. Run Database Migrations

This will create all the necessary tables in your database, including those for Laravel Sanctum.

```cmd
php artisan migrate
```

### 5\. Seed Initial Data (and large dataset for testing)

Run the seeder to populate the database with initial locales, tags, and a large number of translation records (100k+). This is crucial for performance testing. This command will also ensure a test user is created via the UserSeeder.

```cmd
php artisan db:seed
```

### 6\. Start the Development Server

```cmd
php artisan serve
```

The API will be accessible at http://127.0.0.1:8000/api.

API Endpoints
-------------

All endpoints are prefixed with /api and require a Bearer token in the Authorization header, obtained via the /api/login endpoint.

### Authentication

*   POST /api/login

    *   **Body:** email, password

    *   **Returns:** {"token": "YOUR\_SANCTUM\_TOKEN"}

*   POST /api/logout

    *   **Requires:** Authorization: Bearer YOUR\_SANCTUM\_TOKEN

    *   **Returns:** {"message": "Logged out successfully!"}


### Locales (/api/locales)

*   GET /api/locales - List all locales.

*   POST /api/locales - Create a new locale. (code, name)

*   GET /api/locales/{id} - Show a specific locale.

*   PUT/PATCH /api/locales/{id} - Update a locale. (code, name)

*   DELETE /api/locales/{id} - Delete a locale.


### Tags (/api/tags)

*   GET /api/tags - List all tags.

*   POST /api/tags - Create a new tag. (name)

*   GET /api/tags/{id} - Show a specific tag.

*   PUT/PATCH /api/tags/{id} - Update a tag. (name)

*   DELETE /api/tags/{id} - Delete a tag.


### Translations (/api/translations)

*   GET /api/translations - List all translations (paginated).

*   POST /api/translations - Create a new translation. (locale\_id, key, value, optional tags \[array of tag IDs\])

*   GET /api/translations/{id} - Show a specific translation.

*   PUT/PATCH /api/translations/{id} - Update a translation. (locale\_id, key, value, optional tags \[array of tag IDs\])

*   DELETE /api/translations/{id} - Delete a translation.

*   GET /api/translations/search?key=...&content=...&tag=...&locale=... - Search translations by various parameters.

*   GET /api/translations/export/{localeCode} - Export all translations for a given locale (e.g., /api/translations/export/en). This endpoint is optimized for performance.


Design Choices and Explanations
-------------------------------

### Database Schema

The database schema is designed for clear relationships and efficient querying:

*   **locales table:** Stores language codes (en, fr) and names. code is unique and indexed for fast lookups.

*   **tags table:** Stores context tags (mobile, desktop). name is unique and indexed.

*   **translations table:** The core table.

    *   locale\_id: Foreign key to locales.

    *   key: The translation key (e.g., app.welcome\_message).

    *   value: The translated text.

    *   A unique compound index (locale\_id, key) ensures that each translation key is unique within a specific locale, preventing duplicate entries.

    *   An individual index on key allows for efficient searching across all locales.

*   **tag\_translation pivot table:** A many-to-many relationship between tags and translations, allowing a translation to have multiple tags and a tag to belong to multiple translations. This table uses foreign keys with onDelete('cascade') to ensure referential integrity.


### API Design

*   **RESTful Principles:** Endpoints largely follow REST conventions, using standard HTTP methods (GET, POST, PUT/PATCH, DELETE) for CRUD operations on resources.

*   **Resource Controllers:** Laravel's apiResource controllers simplify defining standard CRUD routes.

*   **Clear URIs:** Endpoints are intuitive (e.g., /api/locales, /api/translations).

*   **JSON Responses:** All API responses are in JSON format, facilitating easy integration with frontend applications.

*   **Pagination:** List endpoints (/api/translations) use Laravel's built-in pagination to handle large datasets efficiently.


### Performance and Scalability

*   **Optimized SQL Queries:**

    *   **Indexing:** Key columns like translations.key, translations.locale\_id, locales.code, and tags.name are indexed for rapid data retrieval during searches and relationships.

    *   **Eager Loading (with()):** Controllers use eager loading to fetch related locale and tags data when displaying translations, preventing the "N+1 query problem."

    *   **pluck('value', 'key') for Export:** The /api/translations/export/{localeCode} endpoint uses pluck() which is highly efficient. It retrieves only the key and value columns and formats them directly into an associative array, avoiding the overhead of creating Eloquent model instances for potentially hundreds of thousands of records. This design ensures the export endpoint meets the < 500ms performance requirement even with large datasets.

*   **Caching for Export:** The export endpoint's response is cached (Illuminate\\Support\\Facades\\Cache) for 1 hour. This means subsequent requests for the same locale's translations are served directly from cache, resulting in near-intantaneous responses. The cache is automatically cleared whenever a translation for that locale is created, updated, or deleted, ensuring data freshness.

*   **Seeder for Stress Testing:** The LargeTranslationSeeder generates over 100,000 translations, allowing for realistic testing of database and API performance under high data volumes. Batch inserts are used in the seeder to efficiently populate the database.


### Security

*   **Laravel Sanctum:** Provides token-based authentication for securing the API. Users authenticate once to receive a plain-text token, which is then sent in the Authorization: Bearer header for all subsequent API requests.

*   **Input Validation:** All incoming request data is rigorously validated using Laravel's built-in validation rules (required, unique, exists, max, array etc.) to prevent invalid data from reaching the database and to guard against common vulnerabilities like SQL injection and cross-site scripting (XSS) (though XSS is primarily a frontend concern, server-side validation helps).

*   **Authentication Middleware:** The auth:sanctum middleware protects all sensitive API routes, ensuring only authenticated users can access them. Unauthenticated requests are met with a 401 Unauthorized JSON response.


### Code Quality

*   **PSR-12 Standards:** Code adheres to Laravel's conventions and PHP-FIG's PSR-12 coding standard for consistency and readability.

*   **SOLID Principles:**

    *   **Single Responsibility Principle (SRP):** Controllers focus on handling HTTP requests and delegating business logic (implicitly handled by Eloquent models and built-in Laravel services). Models handle their own data persistence and relationships.

    *   **Dependency Inversion Principle (DIP):** Controllers depend on abstractions (e.g., Request objects), and Eloquent provides a high-level abstraction over the database.

*   **No External CRUD Libraries:** Core CRUD and translation logic is implemented using Laravel's native Eloquent ORM, avoiding unnecessary external dependencies for these functionalities.


Running Tests
-------------

Comprehensive unit and feature tests are provided to ensure the reliability and performance of the API.

```cmd
php artisan test 
```

Specific tests can be run using the --filter option:

```cmd
php artisan test --filter LocaleApiTest
php artisan test --filter TagApiTest 
php artisan test --filter TranslationApiTest
php artisan test --filter TranslationExportPerformanceTest
```