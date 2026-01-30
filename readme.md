WP API Endpoints Explorer
========================

WP API Endpoints Explorer is a WordPress admin plugin that helps developers
view, manage, and export REST API endpoints available on a WordPress site.

It provides a clean admin UI to explore WordPress core, custom post types,
and WooCommerce REST endpoints, with support for exporting documentation
to OpenAPI (Swagger) and Postman formats.

--------------------------------------------------

FEATURES
--------

- View WordPress REST API endpoints in one place
- Supports Posts, Pages, Custom Post Types, and WooCommerce
- Copy endpoint URLs with one click
- Export API documentation as:
  - OpenAPI (Swagger) JSON
  - Postman Collection v2.1
- Secure admin-only access
- Clean WordPress coding standards
- CSS and JS loaded as separate assets

--------------------------------------------------

INSTALLATION
------------

1. Upload the plugin folder to:
   wp-content/plugins/wp-api-endpoints-explorer
2. Activate the plugin from WordPress Admin → Plugins
3. Go to Admin → API Explorer

--------------------------------------------------

PERMISSIONS
-----------

The plugin uses a custom capability:

    manage_api_explorer

This capability is automatically assigned to Administrators
on plugin activation.

Only users with this capability can:
- Access the API Explorer admin page
- Export OpenAPI / Postman documentation
- Access export REST endpoints

--------------------------------------------------

PLUGIN STRUCTURE
----------------

wp-api-endpoints-explorer/
│
├── wp-api-endpoints-explorer.php
├── includes/
│   └── class-wp-api-endpoints-explorer-admin.php
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js

--------------------------------------------------

EXPORT ENDPOINTS
----------------

OpenAPI (Swagger):
/wp-json/endpoints-explorer/v1/openapi

Postman Collection:
/wp-json/endpoints-explorer/v1/postman

Note:
These endpoints are accessible only to users
with admin privileges.

--------------------------------------------------

COMPATIBILITY
-------------

- WordPress 6.x
- PHP 8.0+
- WooCommerce (optional)

--------------------------------------------------

LICENSE
-------

GPL v2 or later

--------------------------------------------------

AUTHOR
------

WP API Endpoints Explorer
Built for WordPress developers working with REST APIs.
