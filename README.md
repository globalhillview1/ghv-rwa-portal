# Global Hill View RWA Portal

WordPress plugin for Global Hill View RWA voter search, membership applications, correction requests, Aadhaar/payment uploads, admin dashboard, CSV import, and CSV export.

## Install

1. Upload this folder/ZIP as a WordPress plugin.
2. Activate **Global Hill View RWA Portal**.
3. Create a page and add shortcode:

```text
[rwa_voter_portal]
```

## Admin

After activation, go to **RWA Portal** in WordPress admin.

## Notes

- Initial voter data is loaded from `data/voters.csv` during activation.
- Uploaded Aadhaar/payment files are stored in WordPress uploads under `ghv-rwa-portal/`.
- Applications and correction requests are stored in custom WordPress database tables.
