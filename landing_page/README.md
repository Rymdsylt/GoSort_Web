# GoSort Landing Page

This is the landing page website for the GoSort Intelligent Waste Segregation System driver.

## Overview

The landing page introduces the GoSort web application and provides a download button for the driver installer. It uses Tailwind CSS and matches the GoSort webapp's theme and branding.

## Features

- **Responsive Design**: Works on desktop, tablet, and mobile devices
- **Modern UI**: Built with Tailwind CSS using GoSort's theme colors
- **Smooth Animations**: Fade-in effects and hover animations
- **Download Section**: Prominent call-to-action for driver download
- **Feature Showcase**: Highlights key capabilities of the GoSort system
- **Branding**: Uses official GoSort logos and colors

## Theme Colors

- Primary Green: `#274a17`
- Light Green: `#7AF146`
- Dark Gray: `#1f2937`
- Background: `#F3F3EF`
- Waste Category Colors:
  - Biodegradable: `#10b981`
  - Non-Biodegradable: `#ef4444`
  - Hazardous: `#f59e0b`
  - Mixed: `#6b7280`

## File Structure

```
landing_page/
├── index.html      # Main landing page
└── README.md       # This file
```

## Usage

Simply open `index.html` in a web browser or serve it through a web server. The page uses:
- Tailwind CSS via CDN
- Bootstrap Icons via CDN
- Google Fonts (Inter)
- Relative paths to parent directory assets (logos, images)

## Deployment

You can deploy this landing page as a standalone website or integrate it into your existing web server. The paths are configured to work when the landing_page folder is inside the GoSort_Web directory structure.

## Customization

To update the download link, modify the download button in the "Download Section" of `index.html`:

```html
<a href="YOUR_DOWNLOAD_URL" class="download-btn ...">
```

## License

Part of the GoSort project by BeaBunda and Members, in partnership with Pateros Catholic School.

