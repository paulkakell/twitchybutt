# TwitchyButt

TwitchyButt is a lightweight static website that curates passive and low effort income opportunities in one place. It focuses on clarity, speed, and portability, making it suitable for GitHub Pages and other static hosts.

## What This Site Does

- Aggregates links to sweepstakes casinos, survey apps, Reddit income threads, and remote work resources  
- Uses a clean, simple front end with no build step  
- Includes SEO basics such as sitemap and structured layout  
- Designed to work out of the box on GitHub Pages using relative paths  

## Hosting on GitHub Pages

This repository is configured for **project-based GitHub Pages**.

Steps:
1. Push the repository to GitHub  
2. Go to **Settings → Pages**  
3. Set source to the `main` branch and root directory  
4. Save and wait for deployment  

No Jekyll configuration is required.

## Local Preview

You can open `index.html` directly in a browser, or serve it locally:

```bash
python -m http.server
```

Then visit:
```
http://localhost:8000
```

## Project Structure

```
.
├── index.html
├── assets/
│   ├── styles.css
│   └── scripts.js
├── sitemap.xml
└── README.md
```

## Design Goals

- Zero dependencies  
- Fast load times  
- Easy customization  
- Host-agnostic structure  

## License

This project is provided as-is. You may modify and reuse it freely.
