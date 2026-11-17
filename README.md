# Zactonz Git

![Version](https://img.shields.io/badge/version-1.1.0-blue.svg) ![License](https://img.shields.io/badge/license-Apache--2.0-blue.svg)

A WHM plugin that keeps cPanel account directories in sync with GitHub repositories. Once a repository is configured, every push to GitHub can be deployed automatically through a webhook, or pulled on demand from the cPanel interface.

Documentation: [developers.zactonz.com/cpanel-whm/zctzgit](https://developers.zactonz.com/cpanel-whm/zctzgit/)

## What it does

- Automatic deployment from GitHub on every push, via a per-repository webhook URL
- Manual sync with one click when you want to control timing
- Private repositories through GitHub access tokens
- Any target directory inside the account, such as `public_html` or a subfolder
- Multiple repositories per account, each with its own branch and path
- No dependencies beyond Git itself
- Installs into the cPanel Jupiter theme under the **Files** group

## Requirements

| Component | Minimum |
|---|---|
| cPanel & WHM | 134 (LTS) or newer, Jupiter theme |
| Git | 2.56.1, or the patched release in your series |
| PHP | 8.1 or newer |
| Access | Root, over SSH or WHM Terminal |

Tested on CentOS 7 and 8, AlmaLinux 8 and 9, Rocky Linux and CloudLinux OS. The server needs outbound HTTPS to github.com, and GitHub must be able to reach the account's domain over HTTPS if webhooks are used. The PHP `exec` function must be enabled for the account.

Git's security release of 10 November 2026 fixed six vulnerabilities. Run a patched build: 2.56.1, 2.55.1, 2.54.1, 2.53.1, 2.52.1, 2.51.3 or 2.50.2 in the matching series. Anything below 2.50 no longer receives fixes. cPanel ships its own Git under `/usr/local/cpanel/3rdparty/bin/`, which may differ from the one on your `PATH` — check both, and confirm patch status with your distribution rather than assuming a recent-looking version number is fixed.

Zactonz Git never clones recursively and never fetches submodules, so the recursive-clone vulnerability in that release does not apply to the deployments it performs. It also only ever connects to `github.com`.

The installer registers the plugin with Jupiter only. Accounts on **Meridian**, the goal-based theme introduced in version 138, will not see it.

Automatic deployment is driven by a poller that runs from cron every five minutes, so it works on any web server and on servers that are not reachable from the internet. Webhooks are optional and verify their signature.

## Install

Run as `root`:

```bash
cd /root && mkdir -p zctzgit && curl -fsSL https://github.com/zactonz/zctzgit/archive/refs/tags/v1.0.1.tar.gz | tar -xz -C zctzgit --strip-components=1 && cd zctzgit && bash install.sh
```

GitHub names its archives after the tag, so `--strip-components=1` puts the files straight into `zctzgit/` whichever version you download. Check [Releases](https://github.com/zactonz/zctzgit/releases) for the current tag.

The installer copies the plugin into cPanel's plugin directory, registers it with the Jupiter theme and restarts the cPanel UI. Log in to any cPanel account and look for **Zactonz Git** under **Files**. If the icon does not appear straight away, log out and back in so the theme cache refreshes.

To install from this repository instead of the package:

```bash
git clone https://github.com/zactonz/zctzgit.git && cd zctzgit && bash install.sh
```

### Update

Download the current release and run the installer again, with the new tag in the URL. It replaces the plugin files in place and removes files left by the previous version. Configured repositories live in each account's home directory and are kept.

Release notes for every version are in [CHANGELOG.md](CHANGELOG.md).

### Uninstall

From the extracted package directory:

```bash
bash uninstall.sh
```

This removes the plugin files and its cPanel registration. Cloned repositories inside account directories are left untouched.

## Use

### Add a repository

1. Open cPanel and find **Zactonz Git** under **Files**.
2. Fill in the form: repository name, clone URL, branch, repository path relative to the account home, an access token for private repositories, and whether to enable auto sync.
3. Click **Save & Deploy**.

The repository is cloned into the chosen path and appears under **Configured repositories**.

![Add a repository](https://github.com/user-attachments/assets/3b273970-b0bb-426a-a379-a65489b7ae62)

### Automatic deployment with a webhook

1. In **Configured repositories**, click **Webhook** next to the repository and copy its URL.
2. In GitHub open **Settings › Webhooks › Add webhook**.
3. Paste the URL as the payload URL, set the content type to `application/json`, choose **Just the push event** and save.

Every push to the configured branch now runs `git pull` on the server. The **Last sync** column shows when the most recent deployment ran.

![Configured repositories](https://github.com/user-attachments/assets/d6f47c3e-790d-4bc2-89dc-ab5f66f5dc4e)

![Webhook URL](https://github.com/user-attachments/assets/19e461b0-fd6f-4702-92b2-55af644bfe80)

### Manual deployment

Click **Sync now** next to a repository to run `git pull` immediately. This works whether or not auto sync is enabled.

## Package layout

```
_plugin.yaml, install.json   cPanel registration
index.live.php               cPanel interface (Jupiter)
actions.php, deploy.php      form handling and deployment
webhook.php                  GitHub webhook receiver
includes/                    Git helpers and configuration
install.sh, uninstall.sh     installer and remover (run as root)
```

The plugin uses the system `git` binary and the cPanel API. It calls no external service.

## Contributing

Bug reports and pull requests are welcome. Please review the code before running it on a production server; it is provided as is, without warranty.

## License

[Apache License 2.0](LICENSE). Use it on your own servers, modify it, and redistribute or white-label it under the terms of that license.

Built by [Zactonz Technologies](https://zactonz.com).
