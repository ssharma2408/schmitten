<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'schmitten_local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'zOJoxkapS>b=xA7WH:Lu8PYaB8JN;e@`&&f]Cy]#n%aQ5NrvIDr~S|1$7M:wtsV&' );
define( 'SECURE_AUTH_KEY',  '=R5cNP~P*)32N dJ*8c9#VZVl.c;0N#63+=$9XC3[*C/=+ eQ|mXR`-l8,!@ARCn' );
define( 'LOGGED_IN_KEY',    '|/idUTt&fYhdk7(HG}._Z|lIR;}:HX<=Q/q#2uh^0_f5(YHzRa4p!Wcy)/e5Zrd1' );
define( 'NONCE_KEY',        '}#@tA.H41#?x<kGD%sj6MM&,MeX?A$2^b39,:U:M(Y^u&Q=!72RE6&7AI6Da,Ya0' );
define( 'AUTH_SALT',        'Bh;Ycdz6U`YhML,5gULqDfuom9G%C=y mvEnl{%(X`5.|87r_1?d!(?6@;;T$y 3' );
define( 'SECURE_AUTH_SALT', 'qy5mbATrKlB/_zd``]/k 6T$WqARM)R0c12s8(%T+;311jV}mVWF*dU09P7LUo@O' );
define( 'LOGGED_IN_SALT',   '$^yjV07Xw71=%rtAu]g}b[>XAMIQ381wOi92K(0GvPC0xZXzLyc>-aq];7RXPJ;g' );
define( 'NONCE_SALT',       'A9?P1u2cY?79v+-uR4 .*VdG8(3nWSrRM<aJ}TR,m4Cir3C fL9q8jhC-nY|4L=a' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
