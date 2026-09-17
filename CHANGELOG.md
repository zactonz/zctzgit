# Changelog

All notable changes to Zactonz Git are recorded here. This project follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-09-17

A corrective release. Version 1.0.0 could not run: two source files contained
syntax errors, and one of them was loaded by every entry point, so the plugin
raised a fatal error the moment a cPanel user opened it. Anyone running 1.0.0
should upgrade; there is no configuration to migrate, because no configuration
could ever be written.

### Fixed

- `includes/git-config.php` contained a second `<?php` opening tag inside PHP
  code, a fatal parse error. The file is required by the interface, the action
  handler, the deploy handler and the webhook receiver, so no part of the plugin
  loaded.
- `webhook.php` contained a stray `w` on its own line, a second fatal parse
  error in the webhook path.
- The repository list was read without checking that the configuration file
  existed, so a fresh account raised a `TypeError` on PHP 8 before any
  repository could be added.
- The webhook receiver could not determine which account it was running for.
  It read the `USER` environment variable, which is unset under several PHP
  handlers, and its fallback inspected the plugin directory, which never
  contains an account path. It now resolves the account from the request paths
  supplied by the web server, then from the effective process owner.
- Home directories are now read from the environment rather than assumed to be
  `/home/<user>`, so the plugin works on servers using `/home2`, `/home3` and
  similar mount points.
- `includes/config.php` defined three unused constants and tried to create
  `data/` and `logs/` inside the root-owned plugin directory on every request,
  which produced permission warnings in the interface. The file has been
  removed.

### Security

These issues existed in 1.0.0 but could not be reached, because the plugin did
not run. They are fixed here so that they are not reachable in 1.0.1 either.

- The deploy handler wrote the submitted GitHub access token to a plaintext log
  file in the account's home directory. The debug logging left over from
  development has been removed.
- The repository path submitted by the form was concatenated onto the home
  directory without validation. The restriction was declared only in the HTML
  `pattern` attribute, which a client controls. Paths containing `..` or a null
  byte are now rejected on the server.
- Git stores the access token for a private repository inside
  `.git/config`. If the repository was deployed inside a document root, that
  file was served over the web. Deployments now write a deny rule into `.git`
  and tighten its permissions. This covers Apache and LiteSpeed; NGINX ignores
  `.htaccess` and still needs a server-level rule.
- Values interpolated into the interface are escaped with `ENT_QUOTES`, so
  single quotes can no longer break out of the JavaScript string literals in the
  repository table.
- The installer copied the entire working directory into the web-served plugin
  directory, which placed `.git` there when installing from a clone. It now
  excludes version control and documentation files, and removes files left by a
  previous version.

### Changed

- Downloads have moved to GitHub. The install and update commands documented
  previously pointed at a host that did not resolve, and therefore failed.

## [1.0.0] - 2025-06-02

Initial release. Withdrawn: see 1.0.1.
