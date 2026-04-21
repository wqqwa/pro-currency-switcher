# Design Guidelines

This document outlines the design principles and visual standards for Pro Currency Switcher.

## 🎨 Design Philosophy

### Core Principles

1. **Simplicity** - Clean, intuitive interface that doesn't overwhelm users
2. **Consistency** - Uniform design language across all components
3. **Accessibility** - WCAG 2.1 AA compliant
4. **Performance** - Lightweight, fast-loading components
5. **Responsiveness** - Works on all devices and screen sizes

---

## 🎨 Color Palette

### Primary Colors

| Color | Hex | Usage |
|-------|-----|-------|
| Primary Blue | `#0073aa` | Buttons, links, active states |
| Primary Dark | `#005a87` | Hover states, headers |
| Primary Light | `#00a0d2` | Highlights, accents |

### Secondary Colors

| Color | Hex | Usage |
|-------|-----|-------|
| Success Green | `#46b450` | Success messages, enabled states |
| Warning Orange | `#ffb900` | Warnings, attention needed |
| Error Red | `#dc3232` | Errors, disabled states |
| Info Blue | `#00a0d2` | Information, tips |

### Neutral Colors

| Color | Hex | Usage |
|-------|-----|-------|
| White | `#ffffff` | Backgrounds, cards |
| Light Gray | `#f7f7f7` | Alternate backgrounds |
| Medium Gray | `#e5e5e5` | Borders, dividers |
| Dark Gray | `#32373c` | Text, icons |
| Black | `#23282d` | Headings, emphasis |

### Currency Colors (Optional)

| Currency | Color | Hex |
|----------|-------|-----|
| USD | Green | `#85bb65` |
| EUR | Blue | `#003399` |
| GBP | Purple | `#6b21a8` |
| JPY | Red | `#bc002d` |
| CNY | Gold | `#de2910` |

---

## 📝 Typography

### Font Stack

```css
/* Primary font stack */
font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, 
             Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;

/* Monospace (for prices, codes) */
font-family: "Menlo", "Monaco", "Consolas", "Courier New", monospace;
```

### Font Sizes

| Element | Size | Weight |
|---------|------|--------|
| Page Title | 23px | 600 |
| Section Header | 20px | 600 |
| Subsection | 16px | 600 |
| Body Text | 14px | 400 |
| Small Text | 12px | 400 |
| Price Display | 18-24px | 600 |

### Line Height

```css
/* Body text */
line-height: 1.5;

/* Headings */
line-height: 1.3;
```

---

## 📐 Spacing

### Base Unit

We use a **4px base unit** for consistent spacing:

```css
--space-xs: 4px;   /* 1 unit */
--space-sm: 8px;   /* 2 units */
--space-md: 16px;  /* 4 units */
--space-lg: 24px;  /* 6 units */
--space-xl: 32px;  /* 8 units */
--space-2xl: 48px; /* 12 units */
```

### Component Spacing

| Component | Padding | Margin |
|-----------|---------|--------|
| Buttons | 8px 16px | 4px |
| Cards | 16px | 16px |
| Form inputs | 8px 12px | 8px |
| Modal | 24px | - |
| Section | - | 32px |

---

## 🔘 Components

### Buttons

```css
/* Primary Button */
.pcs-btn-primary {
    background: #0073aa;
    color: #ffffff;
    border: none;
    border-radius: 6px;
    padding: 8px 16px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.pcs-btn-primary:hover {
    background: #005a87;
}

/* Secondary Button */
.pcs-btn-secondary {
    background: transparent;
    color: #0073aa;
    border: 1px solid #0073aa;
    /* ... same as primary */
}

/* Disabled State */
.pcs-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
```

### Currency Selector

```css
/* Dropdown Style */
.pcs-selector-dropdown {
    background: #ffffff;
    border: 1px solid #e5e5e5;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 14px;
    min-width: 150px;
    cursor: pointer;
}

.pcs-selector-dropdown:hover {
    border-color: #0073aa;
}

/* Floating Button */
.pcs-selector-floating {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #0073aa;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 9999;
}

/* Flag Grid */
.pcs-selector-flag-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 8px;
}

.pcs-flag-item {
    padding: 8px;
    border: 1px solid #e5e5e5;
    border-radius: 6px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.pcs-flag-item:hover {
    border-color: #0073aa;
    background: #f7f7f7;
}

.pcs-flag-item.active {
    border-color: #0073aa;
    background: #0073aa;
    color: #ffffff;
}
```

### Contact Widget

```css
/* Widget Container */
.pcs-contact-widget {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
}

/* Trigger Button */
.pcs-contact-trigger {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #0073aa;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Channel List */
.pcs-contact-channels {
    position: absolute;
    bottom: 70px;
    right: 0;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    padding: 16px;
    min-width: 200px;
}

/* Channel Item */
.pcs-channel-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s ease;
}

.pcs-channel-item:hover {
    background: #f7f7f7;
}
```

### Form Elements

```css
/* Input Field */
.pcs-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e5e5e5;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s ease;
}

.pcs-input:focus {
    outline: none;
    border-color: #0073aa;
    box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
}

/* Select */
.pcs-select {
    appearance: none;
    background-image: url("data:image/svg+xml,...");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
}

/* Checkbox/Radio */
.pcs-checkbox {
    width: 18px;
    height: 18px;
    border: 2px solid #e5e5e5;
    border-radius: 4px;
    cursor: pointer;
}

.pcs-checkbox:checked {
    background: #0073aa;
    border-color: #0073aa;
}
```

---

## 🏳️ Flag Icons

### Specifications

- **Size**: 24x16 pixels (3:2 aspect ratio)
- **Format**: SVG (preferred) or PNG
- **Style**: Flat design, no shadows
- **Border radius**: 2px

### Flag Implementation

```html
<!-- Using emoji flags (recommended) -->
<span class="pcs-flag">🇺🇸</span>
<span class="pcs-flag">🇪🇺</span>
<span class="pcs-flag">🇬🇧</span>

<!-- Using image sprites -->
<img src="flags/us.svg" alt="USD" class="pcs-flag-img">
```

### Flag to Currency Mapping

| Currency | Flag | Country |
|----------|------|---------|
| USD | 🇺🇸 | United States |
| EUR | 🇪🇺 | European Union |
| GBP | 🇬🇧 | United Kingdom |
| JPY | 🇯🇵 | Japan |
| CNY | 🇨🇳 | China |
| AUD | 🇦🇺 | Australia |
| CAD | 🇨🇦 | Canada |
| CHF | 🇨🇭 | Switzerland |
| HKD | 🇭🇰 | Hong Kong |
| SGD | 🇸🇬 | Singapore |

---

## 📱 Responsive Breakpoints

```css
/* Mobile First Approach */

/* Extra Small (default): < 576px */
.container { width: 100%; }

/* Small: >= 576px */
@media (min-width: 576px) { }

/* Medium: >= 768px */
@media (min-width: 768px) { }

/* Large: >= 992px */
@media (min-width: 992px) { }

/* Extra Large: >= 1200px */
@media (min-width: 1200px) { }
```

### Responsive Components

```css
/* Selector on Mobile */
@media (max-width: 767px) {
    .pcs-selector {
        width: 100%;
        margin: 8px 0;
    }
    
    .pcs-selector-floating {
        bottom: 16px;
        right: 16px;
        width: 48px;
        height: 48px;
    }
    
    .pcs-contact-widget {
        bottom: 16px;
        right: 16px;
    }
}
```

---

## ✨ Animations

### Transition Timing

```css
/* Fast (interactive elements) */
transition: all 0.15s ease;

/* Normal (most animations) */
transition: all 0.2s ease;

/* Slow (complex animations) */
transition: all 0.3s ease;
```

### Animation Examples

```css
/* Fade In */
@keyframes pcs-fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Slide Up */
@keyframes pcs-slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Scale */
@keyframes pcs-scale {
    from { transform: scale(0.95); }
    to { transform: scale(1); }
}
```

---

## ♿ Accessibility

### Color Contrast

- All text must have a contrast ratio of at least 4.5:1
- Large text (18px+) requires 3:1 minimum
- Interactive elements need visible focus states

### Focus States

```css
/* Visible focus for keyboard navigation */
.pcs-btn:focus,
.pcs-input:focus,
.pcs-select:focus {
    outline: 2px solid #0073aa;
    outline-offset: 2px;
}

/* Remove default outline when using mouse */
.pcs-btn:focus:not(:focus-visible) {
    outline: none;
}
```

### ARIA Labels

```html
<!-- Currency Selector -->
<div role="listbox" aria-label="Select currency">
    <button role="option" aria-selected="true">USD</button>
    <button role="option" aria-selected="false">EUR</button>
</div>

<!-- Contact Widget -->
<button aria-label="Open contact options" aria-expanded="false">
    <span class="pcs-icon-chat"></span>
</button>
```

### Keyboard Navigation

| Key | Action |
|-----|--------|
| Tab | Move to next element |
| Enter/Space | Activate button/select |
| Escape | Close modal/dropdown |
| Arrow Up/Down | Navigate list items |

---

## 🖼️ Icons

### Icon Library

We use a custom icon set based on WordPress Dashicons:

| Icon | CSS Class | Usage |
|------|-----------|-------|
| Currency | `.pcs-icon-currency` | Currency selector |
| Globe | `.pcs-icon-globe` | Language/region |
| Chat | `.pcs-icon-chat` | Contact widget |
| Close | `.pcs-icon-close` | Close buttons |
| Check | `.pcs-icon-check` | Selected states |

### Icon Implementation

```css
.pcs-icon {
    display: inline-block;
    width: 20px;
    height: 20px;
    fill: currentColor;
}

/* Usage */
<button class="pcs-btn">
    <svg class="pcs-icon pcs-icon-currency">...</svg>
    Select Currency
</button>
```

---

## 📋 Design Checklist

Before releasing any component:

- [ ] Follows color palette
- [ ] Uses correct typography
- [ ] Proper spacing applied
- [ ] Responsive on all breakpoints
- [ ] Has hover/focus states
- [ ] Accessible (WCAG 2.1 AA)
- [ ] Animations are smooth
- [ ] Works in all modern browsers
- [ ] Touch-friendly on mobile

---

For implementation details, see the [CSS files](../assets/css/) and [Component Templates](../templates/).
