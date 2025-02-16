const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const autoprefixer = require('gulp-autoprefixer');
const cssnano = require('gulp-cssnano');
const babel = require('gulp-babel');
const webpack = require('webpack-stream');
const terser = require('gulp-terser');
const rename = require('gulp-rename');
const sourcemaps = require('gulp-sourcemaps');
const clean = require('gulp-clean');
const browserSync = require('browser-sync').create();

// مسیرهای پروژه
const paths = {
    styles: {
        src: 'assets/scss/**/*.scss',
        dest: 'assets/css'
    },
    scripts: {
        src: 'assets/js/modules/**/*.js',
        entry: 'assets/js/modules/index.js',
        dest: 'assets/js'
    }
};

// پاکسازی فایل‌های قبلی
gulp.task('clean', () => {
    return gulp.src(['assets/css/*.min.css', 'assets/js/*.min.js'], { read: false })
        .pipe(clean());
});

// کامپایل SCSS به CSS
gulp.task('styles', () => {
    return gulp.src(paths.styles.src)
        .pipe(sourcemaps.init())
        .pipe(sass({
            outputStyle: 'expanded',
            includePaths: ['node_modules']
        }).on('error', sass.logError))
        .pipe(autoprefixer({
            cascade: false,
            grid: 'autoplace'
        }))
        .pipe(cssnano({
            preset: ['default', {
                discardComments: { removeAll: true }
            }]
        }))
        .pipe(rename({ suffix: '.min' }))
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest(paths.styles.dest))
        .pipe(browserSync.stream());
});

// کامپایل JavaScript با Webpack
gulp.task('scripts', () => {
    return gulp.src(paths.scripts.entry)
        .pipe(webpack({
            mode: process.env.NODE_ENV === 'production' ? 'production' : 'development',
            entry: {
                'asg-script': paths.scripts.entry,
                'asg-admin': './assets/js/modules/admin.js'
            },
            output: {
                filename: '[name].min.js'
            },
            module: {
                rules: [{
                    test: /\.js$/,
                    exclude: /node_modules/,
                    use: {
                        loader: 'babel-loader',
                        options: {
                            presets: ['@babel/preset-env'],
                            plugins: ['@babel/plugin-transform-runtime']
                        }
                    }
                }]
            },
            optimization: {
                minimize: true,
                splitChunks: {
                    chunks: 'all'
                }
            }
        }))
        .pipe(sourcemaps.init())
        .pipe(terser({
            compress: {
                drop_console: process.env.NODE_ENV === 'production'
            }
        }))
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest(paths.scripts.dest))
        .pipe(browserSync.stream());
});

// تماشای تغییرات فایل‌ها
gulp.task('watch', () => {
    browserSync.init({
        proxy: "localhost/your-wordpress-site",
        notify: false
    });

    gulp.watch(paths.styles.src, gulp.series('styles'));
    gulp.watch(paths.scripts.src, gulp.series('scripts'));
    gulp.watch('**/*.php').on('change', browserSync.reload);
});

// تسک اصلی برای توسعه
gulp.task('dev', gulp.series('clean', gulp.parallel('styles', 'scripts'), 'watch'));

// تسک برای محیط تولید
gulp.task('build', gulp.series('clean', gulp.parallel('styles', 'scripts')));

// تسک پیش‌فرض
gulp.task('default', gulp.series('dev'));

// بررسی اندازه فایل‌ها
gulp.task('size', () => {
    const fileSize = require('gulp-size');
    
    console.log('CSS Files:');
    gulp.src('assets/css/*.min.css')
        .pipe(fileSize({
            showFiles: true,
            title: 'Minified CSS'
        }));
        
    console.log('JS Files:');
    gulp.src('assets/js/*.min.js')
        .pipe(fileSize({
            showFiles: true,
            title: 'Minified JS'
        }));
});

// تسک تست
gulp.task('test', (done) => {
    console.log('Running tests...');
    // اینجا می‌توانید تست‌های خود را اضافه کنید
    done();
});