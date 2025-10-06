const mix = require('laravel-mix');
const tailwindcss = require('tailwindcss');

const back_end_paths = {
    // js_path   : 'js/',
    scss_path : 'scss/'
};

mix.options({
    postCss: [
        tailwindcss('./tailwind.config.js'),
    ],
    terser: {
        extractComments: false,
    }
});

/*
 |--------------------------------------------------------------------------
 | css styling
 |--------------------------------------------------------------------------
 */
mix.sass(back_end_paths.scss_path + 'style.scss', 'css').options({
    // todo remove and fix paths properly
    processCssUrls: false
})

mix.browserSync({
    proxy: 'siagraph.test',
    files: [
        'css/style.css',
    ]
});

/*
 |--------------------------------------------------------------------------
 | Javascript files for individual pages
 |--------------------------------------------------------------------------
 */
// mix.js(back_end_paths.js_path + 'pages/element-overview.js', 'build/js/templates');

/*
 |--------------------------------------------------------------------------
 | General javascript file for all files
 |--------------------------------------------------------------------------
 */
// mix.js(back_end_paths.js_path + 'base/app.js', 'build/js/base');
