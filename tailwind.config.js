/** @type {import('tailwindcss').Config} */
const defaultTheme = require('tailwindcss/defaultTheme')

module.exports = {
  content: [
    "./index.php",
    // "./include/*.php",
    // "./include/*.html",
    // "./**/*.php",
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          100: '#f9e5e3',
          200: '#f1bdb9',
          300: '#e28a77',
          400: '#c0392b',
          500: '#a83225',
          600: '#902a1f',
          700: '#79231a',
          800: '#611b14',
          900: '#4a140f',
        },
        background: 'rgba(255,255,255,.05)'
      },
    },
  },
  plugins: [],
}
