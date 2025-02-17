const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const autoprefixer = require('gulp-autoprefixer');
const cssnano = require('gulp-cssnano');
const babel = require('gulp-babel');
const webpack = require('webpack-stream');
const terser = require('gulp-terser');
const rename = require('gulp-rename');
const sourcemaps = require('gulp-sourcemaps');
const concat = require('gulp-concat');

// مسیرهای پروژه
const paths = {
    styles: {
        src: 'assets/scss/**/*.scss',
        admin: 'assets/scss/admin.scss',
        public: 'assets/scss/public.scss',
        dest: 'assets/css'
    },
    scripts: {
        src: 'assets/js/modules/**/*.js',
        admin: 'assets/js/modules/admin.js',
        public: 'assets/js/modules/public.js',
        dest: 'assets/js/dist'
    },
    vendor: {
        styles: 'assets/css/vendor/**/*.css',
        scripts: 'assets/js/vendor/**/*.js'
    }
};

// تسک CSS عمومی
gulp.task('public-css', () => {
    return gulp.src(paths.styles.public)
        .pipe(sourcemaps.init())
        .pipe(sass().on('error', sass.logError))
        .pipe(autoprefixer())
        .pipe(cssnano())
        .pipe(rename({ suffix: '.min' }))
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest(paths.styles.dest));
});

// تسک CSS ادمین
gulp.task('admin-css', () => {
    return gulp.src(paths.styles.admin)
        .pipe(sourcemaps.init())
        .pipe(sass().on('error', sass.logError))
        .pipe(autoprefixer())
        .pipe(cssnano())
        .pipe(rename({ suffix: '.min' }))
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest(paths.styles.dest));
});

// تسک JavaScript عمومی
gulp.task('public-js', () => {
    return gulp.src(paths.scripts.public)
        .pipe(webpack({
            mode: process.env.NODE_ENV || 'development',
            output: {
                filename: 'public.min.js'
            },
            module: {
                rules: [{
                    test: /\.js$/,
                    exclude: /node_modules/,
                    use: {
                        loader: 'babel-loader',
                        options: {
                            presets: ['@babel/preset-env']
                        }
                    }
                }]
            }
        }))
        .pipe(sourcemaps.init())
        .pipe(terser())
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest(paths.scripts.dest));
});

// تسک JavaScript ادمین
gulp.task('admin-js', () => {
    return gulp.src(paths.scripts.admin)
        .pipe(webpack({
            mode: process.env.NODE_ENV || 'development',
            output: {
                filename: 'admin.min.js'
            },
            module: {
                rules: [{
                    test: /\.js$/,
                    exclude: /node_modules/,
                    use: {
                        loader: 'babel-loader',
                        options: {
                            presets: ['@babel/preset-env']
                        }
                    }
                }]
            }
        }))
        .pipe(sourcemaps.init())
        .pipe(terser())
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest(paths.scripts.dest));
});

// تسک کپی فایل‌های vendor
gulp.task('vendor', () => {
    // کپی CSS های vendor
    gulp.src(paths.vendor.styles)
        .pipe(gulp.dest(paths.styles.dest + '/vendor'));
    
    // کپی JS های vendor
    return gulp.src(paths.vendor.scripts)
        .pipe(gulp.dest(paths.scripts.dest + '/vendor'));
});

// تسک Watch
gulp.task('watch', () => {
    gulp.watch(paths.styles.src, gulp.parallel('public-css', 'admin-css'));
    gulp.watch(paths.scripts.src, gulp.parallel('public-js', 'admin-js'));
});

// تسک پیش‌فرض برای توسعه
gulp.task('default', gulp.series(
    'vendor',
    gulp.parallel('public-css', 'admin-css', 'public-js', 'admin-js'),
    'watch'
));

// تسک build برای تولید
gulp.task('build', gulp.series(
    'vendor',
    gulp.parallel('public-css', 'admin-css', 'public-js', 'admin-js')
));