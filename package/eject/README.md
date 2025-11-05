# 🧾 Eject

**Version:** 1.0.0  
**Author:** Eric Kowalewski  
**Repository:** [github.com/emkowale/eject](https://github.com/emkowale/eject)  
**Tested up to:** WordPress 6.6.x / WooCommerce 9.x  
**Requires PHP:** 7.4+

---

## 📦 Overview

**Eject** is a WordPress/WooCommerce plugin that automates vendor purchasing and work-order preparation.  
It scans all new WooCommerce orders entering the **Processing** state, groups items by their **Vendor Code**, and builds purchase orders (POs) per vendor run.

Each PO tracks items by **Item → Color → Size → Quantity** and marks the linked WooCommerce orders with private notes for traceability.

---

## ✳️ Core Features

- Automatically detects *Processing* orders and groups them by vendor  
- Creates one **PO per vendor per day**, using format `BT-MMDDYYYY-<vendor>-###`  
- Live “Runs” interface to manage vendor carts before placing an order  
- Manual “Mark Ordered / Not Ordered” control with WooCommerce admin spinner feedback  
- Purchase Order history with per-vendor filtering  
- Lightweight Settings screen for vendor blacklist and permissions  
- Built on WordPress **custom post types (CPTs)** for durability and backups  
- Fully mobile-friendly admin interface  

---

## 🗂 Admin Screens

| Screen | Purpose |
|---------|----------|
| **Queue** | Intake for new *Processing* orders not yet assigned to a vendor run |
| **Runs** | Main workspace: grouped by vendor, ready for “Mark Ordered” |
| **POs** | View or reopen historical purchase orders |
| **Settings** | Configure vendor blacklist, permissions, and reset tools |

---

## 🧰 Technical Details

- CPT: `eject_run`  
- Status: `draft` = Not Ordered, `publish` = Ordered  
- Metadata includes `_vendor_name`, `_po_number`, `_items`, `_exceptions`, `_order_ids`, `_po_date`, `_created_by_user_id`  
- Hooks into WooCommerce order status changes via `woocommerce_order_status_processing`  
- AJAX endpoints handle add/remove/mark actions with WooCommerce’s native admin spinner and disabled buttons  

---

## 🚀 Installation

1. Download the latest release ZIP from [GitHub Releases](https://github.com/emkowale/eject/releases).  
2. Upload via **Plugins → Add New → Upload Plugin** in WordPress.  
3. Activate **Eject** (requires WooCommerce).  
4. A new **Eject** menu will appear in the left WordPress admin sidebar.  

---

## 🔄 Update Policy

- WordPress 6.5+ detects updates automatically via  
  ```
  Update URI: https://github.com/emkowale/eject
  ```  
- You can also run your own `release.sh` to tag and deploy updates to GitHub.  

---

## 🧩 File Structure
```
eject/
├── eject.php
├── includes/
│   ├── class-eject-cpt.php
│   ├── class-eject-admin.php
│   ├── class-eject-ajax.php
│   ├── eject-hooks.php
│   └── views/
│       ├── view-queue.php
│       ├── view-runs.php
│       ├── view-pos.php
│       └── view-settings.php
└── assets/
    ├── css/admin.css
    └── js/admin.js
```

---

## 🧾 Version History

| Version | Date | Notes |
|----------|------|-------|
| **1.0.0** | 2025-11-03 | Initial scaffold with CPT, admin UI structure, AJAX stubs, and GitHub auto-update headers. |

---

## ⚖️ License

GPL-2.0 or later  
© 2025 Eric Kowalewski. All rights reserved.

---

## 🐻 The Bear Traxs Ecosystem

**Eject** integrates with upcoming Bear Traxs plugins:  
- **Tracks** – converts Eject purchase orders into in-house work orders  
- **Soundwave** – synchronizes orders across affiliate sites  
- **Bumblebee** – product metadata and vendor code generation  

Together they form the Bear Traxs production workflow.
