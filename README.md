# WP Sync DB CLI
An addon for [WP Sync DB](https://github.com/slang800/wp-sync-db) that allows you to execute migrations using a function call or via WP-CLI

## Example usage
The following two variations of the `wpsdb migrate` command are possible by supplying either a profile id or connection info string. 

#### Target a user created profile already stored in the system/database
`wp wpsdb migrate --profile=1`

**profile** represents the profile number as seen in the Migrate tab's "saved migration profiles" list in WP.

#### Manually target a connection string (no profile)
`wp wpsdb migrate --connection-info=https://example.com\n6AvE1jnBHIZtITuNCXj2eZArNM8uqNXC --action=pull --create-backup=1`

**connection-info** is the string found on the target site's Settings tab. The line break is replaced with the `\n` equivalent character in order to pass the whole string on a single line.

**action** can be set to either `pull` or `push` depending on the direction of the DB sync

**create-backup** is a bit field that indicates whether the DB should be backed up prior to a transfer. This defaults to 1.
