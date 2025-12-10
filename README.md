# 🧾 Eject

**Version:** 2.0.0-dev  
**Author:** Eric Kowalewski  
**Repository:** [github.com/emkowale/eject](https://github.com/emkowale/eject)  
**Tested up to:** WordPress 6.6.x / WooCommerce 9.x  
**Requires PHP:** 7.4+

---

## 📦 Overview

**Eject** builds vendor purchase orders from WooCommerce **On hold** orders.  
It looks at every on-hold order, groups line items by vendor code, and generates a PO per vendor with size/color breakdowns.

Each PO tracks items by **Item → Color → Size → Quantity** and marks the linked WooCommerce orders with private notes for traceability.

---

## ✳️ Core Features

- Scans all **On hold** orders and groups items by vendor code  
- Generates PO numbers as `BT-{vendorId}-{MMDDYYYY}-{###}` (sequential per vendor per day)  
- Breaks each vendor item down by **Color / Size / Qty** using size order `NB,06M,12M,18M,24M,XS,S,M,L,XL,2XL,3XL,4XL,5XL`  
- Pulls vendor cost from item meta (filterable) to estimate totals  
- Writes order notes linking WooCommerce orders to the generated PO  
- Lets you delete a PO (handy for SanMar if you have not hit $200 yet)  

---

## 🗂 Admin Screen

| Screen | Purpose |
|---------|----------|
| **Eject** | Generate POs from on-hold orders and review/delete existing POs |

---

## 🧰 Technical Details

- CPT: `eject_po`  
- Metadata includes `_vendor_id`, `_po_number`, `_items` (color/size groups), `_order_ids`, `_po_date`, `_total_cost`  
- Vendor code and vendor cost are read from order item meta (filterable via `eject_vendor_from_item`, `eject_vendor_item_from_item`, `eject_cost_from_item`)  
- PO number format: `BT-{vendorId}-{MMDDYYYY}-{###}`, counted per vendor per day  

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
│   └── class-eject-service.php
└── assets/
    ├── css/admin.css
    └── js/admin.js
```

---

## 🧾 Version History

| Version | Date | Notes |
|----------|------|-------|
| **2.0.0-dev** | 2025-12-04 | Rebuilt for on-hold orders, vendor PO numbering (BT-{vendorId}-{MMDDYYYY}-{###}), and size/color breakdowns. |
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
