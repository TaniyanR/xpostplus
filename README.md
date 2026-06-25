XPostPlus

XPostPlus is a self-hosted management tool for creating X (formerly Twitter) posts from affiliate products.

It allows you to retrieve product information from supported affiliate services via API, generate customizable post templates, manage hashtags, and prepare posts for manual publishing—all without requiring the X API.

Designed with a WordPress-like administration interface, responsive layout, and security-first architecture, XPostPlus is optimized for both desktop and smartphone use.

⸻

Features

* WordPress-style administration panel
* Responsive design (PC & Smartphone)
* Secure login system
* FANZA API support
* Sokmil API support
* DUGA API support
* Product management
* API configuration from the admin panel
* Customizable post templates
* Automatic hashtag generation
* Manual hashtag editing
* Single post generation
* Bulk post generation
* Saved post management
* Article URL management
* Sample image support
* Sample video support
* Copy-ready X post generation
* Responsive dashboard
* Multi-site ready architecture
* Security-first implementation

⸻

Main Functions

Product Management

Retrieve products from supported affiliate services and manage them from a single dashboard.

Supported services:

* FANZA
* Sokmil
* DUGA

Future services can be added without major modifications.

⸻

X Post Generator

Generate X posts from product information.

Each post can include:

* Product title
* Affiliate URL
* Article URL
* Sample video URL
* Sample image URL
* Hashtags

Posts are generated as copy-ready text.

No automatic posting is performed.

⸻

Hashtag Generator

Automatically generates hashtags using:

* Actress name
* Genre
* Service name
* Keywords extracted from the product title

Generated hashtags can be edited manually before saving.

NG words can be excluded through the settings page.

⸻

Post Templates

Create unlimited post templates using replacement tags.

Example:

{title}
Watch the sample video
{sample_movie_url}
Read more
{article_url}
{hashtags}

⸻

Bulk Post Generation

Generate multiple X posts at once.

Features include:

* Multi-selection
* Template selection
* Bulk generation
* Individual copy
* Copy all
* Duplicate prevention

⸻

Media Support

Choose how media should be used when preparing posts.

Available options:

* Sample video
* Single image
* Multiple images
* Text only

Media is displayed for easy manual posting.

The application does not upload media to X.

⸻

Site Management

Register one or more websites.

Default:

* PinkClub FANZA

Future sites can be added from the admin panel.

Example:

* FANZA
* DUGA
* Sokmil

⸻

Security

Security has been prioritized throughout the project.

Includes:

* Password hashing
* CSRF protection
* XSS protection
* SQL Injection prevention
* Session protection
* Login attempt limits
* Input validation
* Output escaping
* Authentication required
* Secure API key storage

⸻

System Requirements

* PHP 8.3+
* MySQL 8+ or SQLite
* Apache / Nginx
* HTTPS recommended

⸻

Installation

1. Upload the project to your server.
2. Create the database.
3. Open the installer.
4. Create the administrator account.
5. Configure the API settings.
6. Start retrieving products.

⸻

Administration

The administration panel is fully responsive and designed to work comfortably on:

* Desktop
* Tablet
* Smartphone

⸻

Responsive Design

The admin interface includes:

* Mobile sidebar
* Touch-friendly buttons
* Card layout for products
* Responsive forms
* Mobile-optimized copy interface

⸻

Roadmap

Planned future support includes:

* Additional affiliate services
* More template options
* Improved filtering
* Additional export formats
* Enhanced reporting

⸻

Important

XPostPlus does not automatically post to X.

It generates copy-ready content for manual publishing.

No X API is required.

⸻

License

MIT License
