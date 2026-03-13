# Firebase Hosting Guide (RideGuide)

This guide explains how to deploy your public web pages to Firebase Hosting.

## What this is for

Use this guide to publish static pages like:
- Home page
- Privacy Policy
- Terms of Service
- Data Deletion page

## Important note

Firebase Hosting serves static files only.
It does **not** run Laravel PHP code.

So, these files work on Firebase Hosting:
- HTML
- CSS
- JavaScript
- Images

Laravel routes, controllers, and database features must run on your backend server.

## Prerequisites

1. Node.js is installed
2. Firebase CLI is installed
3. You are in the project root folder

If PowerShell blocks `npm`, use `npm.cmd` instead.

## Step 1: Login to Firebase

```bash
firebase.cmd login
```

## Step 2: Set the Firebase project

```bash
firebase.cmd use --add
```

Choose this project:
- `rideguide-e47d1`

## Step 3: Initialize Firebase Hosting

```bash
firebase.cmd init hosting
```

Use these options:
- Select existing project: `rideguide-e47d1`
- Public directory: `public`
- Configure as single-page app (rewrite all urls to /index.html): `No`

## Step 4: Check Hosting config

Make sure your `firebase.json` has this:

```json
{
  "hosting": {
    "public": "public",
    "cleanUrls": true,
    "ignore": [
      "firebase.json",
      "**/.*",
      "**/node_modules/**"
    ]
  }
}
```

`cleanUrls: true` lets URLs work without `.html`.

Example:
- `/legal/privacy-policy` works for `public/legal/privacy-policy.html`

## Step 5: Deploy

```bash
firebase.cmd deploy --only hosting
```

After deploy, open:
- `https://rideguide-e47d1.web.app/`
- `https://rideguide-e47d1.web.app/legal/privacy-policy`
- `https://rideguide-e47d1.web.app/legal/terms-of-service`
- `https://rideguide-e47d1.web.app/legal/data-deletion`

## Update flow

When you edit files in `public/`, deploy again:

```bash
firebase.cmd deploy --only hosting
```

## Troubleshooting

### Error: `npm.ps1 cannot be loaded`

Cause: PowerShell script policy.

Use:
```bash
npm.cmd <command>
```

Examples:
```bash
npm.cmd install
npm.cmd run build
```

### Legal URL returns 404

Check these:
1. File exists in `public/legal/`
2. File name is correct (example: `privacy-policy.html`)
3. `cleanUrls` is enabled in `firebase.json`
4. You deployed again after changes

## Meta setup values (quick copy)

- Privacy Policy URL: `https://rideguide-e47d1.web.app/legal/privacy-policy`
- Terms of Service URL: `https://rideguide-e47d1.web.app/legal/terms-of-service`
- Data Deletion URL: `https://rideguide-e47d1.web.app/legal/data-deletion`
