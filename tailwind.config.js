/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './templates/**/*.html',
    './apps/**/templates/**/*.html',
    './node_modules/unfold/**/*.{html,js}',
  ],
  theme: {
    extend: {
      // VB Desktop Application Colors (ElektraWeb)
      colors: {
        // Navy Primary (Sidebar & Main)
        'vb-navy': {
          50: '#f8f9fa',
          100: '#f1f3f5',
          200: '#e2e6eb',
          300: '#cbd5e0',
          400: '#94a3b8',
          500: '#2d3e50',  // Primary
          600: '#1e2935',  // Dark
          700: '#151e29',  // Darker
          800: '#0f1419',
          900: '#0a0f14',
        },
        // VB Button Colors
        'vb-primary': '#3498db',
        'vb-success': '#27ae60',
        'vb-danger': '#e74c3c',
        'vb-warning': '#f39c12',
        'vb-gray': '#f5f7fa',
        // Status Colors (ElektraWeb)
        'vb-clean': '#4ade80',
        'vb-dirty': '#60a5fa',
        'vb-occupied': '#f87171',
        'vb-reserved': '#fbbf24',
      },
      // VB Desktop Border Radius (Keskin köşeler)
      borderRadius: {
        'vb': '3px',
      },
      // VB Desktop Shadows (Minimal)
      boxShadow: {
        'vb-sm': '0 1px 2px rgba(0, 0, 0, 0.08)',
        'vb': '0 2px 4px rgba(0, 0, 0, 0.1)',
        'vb-md': '0 4px 6px rgba(0, 0, 0, 0.1)',
      },
      // VB Desktop Fonts
      fontFamily: {
        'vb': ['Segoe UI', 'Tahoma', 'Geneva', 'Verdana', 'sans-serif'],
      },
    },
  },
  plugins: [],
}


