/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php",
    "./include/**/*.{php,html}",
    "./api/**/*.php",
    "./js/**/*.js",
    "./explorer/**/*.{php,html,js}",
    "./scss/**/*.scss",
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
