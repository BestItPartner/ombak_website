<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'i4854038_wp1');

/** MySQL database username */
define('DB_USER', 'i4854038_wp1');

/** MySQL database password */
define('DB_PASSWORD', 'H.NQigELySDyZt5vxDn19');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '8VL47iFiSdh3qY47ejsi8Ij3POzR3aEdbuIs8v0ppSM4PZjvtrz2yoWs8tFgIdIF');
define('SECURE_AUTH_KEY',  'dthlrYzrYxRxGSeT5wpiF6nV3eRymMZXgzaZ5gqQHJN0bR0mqaKfQaZ8cQemv4V9');
define('LOGGED_IN_KEY',    'KHQsOiHvi3pa6kH6B8h2G2HzceynOits8ly6bMUO4agJKvhb7sO1FuLs5XSDPCAY');
define('NONCE_KEY',        'LPKCyL34CASLPIvg4kOdxloFobGFe9v2GXv1ems4vecdvk95X44a7KmbqfdB19oI');
define('AUTH_SALT',        'pQceI6wVeu0q86Sf2ME0saVde6DScw3aWqPFuGRw84dsInr0LxOZ4myd72Ut7Isj');
define('SECURE_AUTH_SALT', 'g39uPBtDI7Wl8qzDMWwPsTTMWVMjE35HiNJFh3NtnNWFK8WlmBThdAx52Nk4qUYJ');
define('LOGGED_IN_SALT',   'MvIf7SApZT6prwD1VSRUZlEHxgRWag0sG5S33BU0YdGHwnuiwSHp11SVIsN9l8tq');
define('NONCE_SALT',       'BkqNjvAhkjm68u6uaam6uF2V0z8Y0mZk9Fli0nreI4PVrRlQYGxMCiN2Q4fBrMn6');

/**
 * Other customizations.
 */
define('FS_METHOD','direct');define('FS_CHMOD_DIR',0755);define('FS_CHMOD_FILE',0644);
define('WP_TEMP_DIR',dirname(__FILE__).'/wp-content/uploads');

/**
 * Turn off automatic updates since these are managed upstream.
 */
define('AUTOMATIC_UPDATER_DISABLED', true);


/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
