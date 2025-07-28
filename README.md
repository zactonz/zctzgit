# Zactonz Git – WHM/cPanel Plugin ![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)

**Zactonz Git** is a powerful WHM plugin that enables automatic and manual deployment of GitHub repositories directly to your cPanel server. Designed for simplicity and security, it streamlines your development workflow by syncing changes via webhooks or on-demand pulls.

---

## 🚀 Features

- ⚡ **Automatic Deployment** via GitHub webhooks
- 🔒 **Supports Private Repositories** using token authentication
- 🔁 **Manual Deployment** via UI
- 📁 Deploy to **custom directory paths**
- 🎨 Seamless integration with cPanel (Jupiter theme)
- 🧩 **No third-party dependencies**
- 🛡️ Secure and production-ready

---

## 📥 Installation

### 🛠 Requirements

- WHM/cPanel server (Jupiter theme)
- WHM version 106+
- Git 2.18+ installed
- PHP 7.2+
- Apache, LiteSpeed, or NGINX
- **Root access** to the server

### ✅ Compatible Operating Systems

- CentOS 7 / 8
- AlmaLinux 8 / 9
- CloudLinux OS
- Rocky Linux

### ⚙ Quick Install via Terminal

> Run the following command as `root` (via SSH or WHM Terminal):

```bash
cd /root && wget -N https://packages.zactonz.com/cpanel/plugins/zctzgit/latest/zctzgit.tar.gz && tar -xzvf zctzgit.tar.gz && cd zctzgit && bash install.sh
```

To uninstall:

```bash
cd /root/zctzgit && bash uninstall.sh
```
---

## 🧑‍💻 How to Use

### ➕ Add a Repository

1. Go to your cPanel dashboard.  
2. Locate **Zactonz Git** under the **Files** section.  
3. Fill out the repository form:
   - **Repository Name**
   - **Clone URL**
   - **Branch** (e.g., `main` or `master`)
   - **Repository Path** (e.g., `public_html`)
   - **Access Token** (only for private repos)
   - Enable **Auto Sync** if needed  
4. Click **Save & Deploy**  

✅ Your repository is now cloned and deployed.

---

### 🔁 Enable Auto Deployment (Webhook)

1. After saving a repository, locate it under **Configured Repositories**  
2. Click the **Webhook** button  
3. Copy the provided webhook URL  
4. In your GitHub repository:  
   - Go to **Settings → Webhooks → Add webhook**  
   - Paste the URL  
   - Set **Content-Type** to `application/json`  
   - Choose **Push events**  
   - Save the webhook  

Now, every push to GitHub will trigger automatic deployment on cPanel.

---

### 🔄 Manual Deployment

In the **Configured Repositories** section:

- Click **Sync now** to manually trigger a pull  
- View the **Last sync** timestamp for status tracking  

---

## Screenshots

![Add new repository to zctzgit](https://github.com/user-attachments/assets/3b273970-b0bb-426a-a379-a65489b7ae62)

![Configured repositories](https://github.com/user-attachments/assets/d6f47c3e-790d-4bc2-89dc-ab5f66f5dc4e)

Copy webhook URL from this popup for each of the repo and add to your Github webhooks.
![Webhook URL](https://github.com/user-attachments/assets/19e461b0-fd6f-4702-92b2-55af644bfe80)


## 🧩 Architecture & Plugin Package

- cPanel UI (Jupiter theme)  
- Backend PHP deployment logic  
- `install.sh` and `uninstall.sh` scripts  
- Uses native `git` and cPanel API  
- Does not rely on any external services or packages  

---

## 💼 Developed By

**Zactonz Technologies**  
🌐 [https://zactonz.com](https://zactonz.com)

---

## 🆓 License & Usage

**Zactonz Git is free and open source.**

You are free to:

- Use it on your own servers  
- Modify or extend the code  
- Repackage, resell, or white-label it under your own brand  

> ⚠️ Provided as-is with no warranties. Use at your own risk.  
> 🔍 Always review the code before using in production.

---

## 🤝 Contributing

We welcome contributions! Feel free to fork the repo, open issues, or submit pull requests.  

---

## 📎 Useful Links

- [Official Website](https://developers.zactonz.com/whm-cpanel/zctzgit/docs/)
- [cPanel Plugin Documentation](https://docs.cpanel.net)  
- [GitHub Webhooks Guide](https://docs.github.com/en/webhooks)