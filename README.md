# WP Sync DB CLI
An addon for [WP Sync DB](https://github.com/slang800/wp-sync-db) that allows you to execute migrations using a function call or via WP-CLI

The plugin is to be installed as a standard WordPress plugin, ensure you have wp-sync-db installed as well and have WP-CLI installed on your server.

Firstly, you'll want to set up a profile for the sync. So go ahead in your WP backend and set up the migration in WP Sync DB. Save the profile. You can have multiple profiles if you'd like.

Profiles are numerical, saved in order of how they're listed in the backend.

Commands for WP-CLI are:

```wp wpsdb migrate [profile-number]```

Example:
```wp wpsdb migrate 1```
