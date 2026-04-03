/** @type {import('tailwindcss').Config} */
export default {
  darkMode: ['class'],
  content: [
    './pages/**/*.{ts,tsx}',
    './components/**/*.{ts,tsx}',
    './app/**/*.{ts,tsx}',
    './src/**/*.{ts,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        border: 'var(--border)',
        input: 'var(--input)',
        ring: 'var(--ring)',
        background: 'var(--background)',
        foreground: 'var(--foreground)',
        primary: {
          DEFAULT: 'var(--primary)',
          foreground: 'var(--primary-foreground)',
        },
        secondary: {
          DEFAULT: 'var(--secondary)',
          foreground: 'var(--secondary-foreground)',
        },
        destructive: {
          DEFAULT: 'var(--destructive)',
          foreground: 'var(--destructive-foreground)',
        },
        muted: {
          DEFAULT: 'var(--muted)',
          foreground: 'var(--muted-foreground)',
        },
        accent: {
          DEFAULT: 'var(--accent)',
          foreground: 'var(--accent-foreground)',
        },
        popover: {
          DEFAULT: 'var(--popover)',
          foreground: 'var(--popover-foreground)',
        },
        card: {
          DEFAULT: 'var(--card)',
          foreground: 'var(--card-foreground)',
        },
        /* Extension tokens */
        signal: 'var(--signal)',
        success: 'var(--success)',
        warning: 'var(--warning)',
        /* Node category accent tokens */
        node: {
          input: 'var(--node-input)',
          script: 'var(--node-script)',
          visuals: 'var(--node-visuals)',
          audio: 'var(--node-audio)',
          video: 'var(--node-video)',
          utility: 'var(--node-utility)',
          output: 'var(--node-output)',
        },
      },
      borderRadius: {
        lg: 'var(--radius)',
        md: 'calc(var(--radius) - 2px)',
        sm: 'calc(var(--radius) - 4px)',
      },
      fontFamily: {
        sans: [
          'Geist Sans',
          'ui-sans-serif',
          'system-ui',
          'sans-serif',
        ],
        mono: [
          'Geist Mono',
          'JetBrains Mono',
          'ui-monospace',
          'monospace',
        ],
      },
      zIndex: {
        'canvas-bg': 'var(--z-canvas-bg)',
        'base-edges': 'var(--z-base-edges)',
        'hover-edges': 'var(--z-hover-edges)',
        'nodes': 'var(--z-nodes)',
        'selected-nodes': 'var(--z-selected-nodes)',
        'edge-labels': 'var(--z-edge-labels)',
        'resize-handles': 'var(--z-resize-handles)',
        'dropdowns': 'var(--z-dropdowns)',
        'tooltips': 'var(--z-tooltips)',
        'dialog': 'var(--z-dialog)',
        'toasts': 'var(--z-toasts)',
      },
    },
  },
  plugins: [require('tailwindcss-animate')],
}
