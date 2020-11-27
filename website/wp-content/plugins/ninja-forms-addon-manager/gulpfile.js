/**
 * Gulpfile
 *
 * Rename and Minify JavaScript... and more (later).
 *
 * Install Command:
 * npm install
 *
 * Running Command:
 * npm run gulp
 */

var gulp              = require('gulp');
var uglify            = require('gulp-uglify');
var sourcemaps        = require('gulp-sourcemaps');
var sass              = require('gulp-sass');
var cssnano           = require('cssnano');
var postcss           = require('gulp-postcss');
var autoprefixer      = require('autoprefixer');

gulp.task('js', function(){
    gulp.src('resources/assets/js/dashboard.js')
    .pipe(sourcemaps.init())
    .pipe(uglify())
    .pipe(sourcemaps.write('/'))
    .pipe(gulp.dest('public/js/'));
});

gulp.task('css', function(){
    gulp.src('resources/assets/scss/dashboard.scss')
    .pipe(sourcemaps.init())
    .pipe(sass().on('error', sass.logError))
    .pipe(postcss([
        autoprefixer({browsers: ['last 2 versions']}),
        cssnano({
            safe: true,
            autoprefixer: false,
            convertValues: false,
        }),
    ]))
    .pipe(sourcemaps.write('/'))
    .pipe(gulp.dest('public/css'));
});

gulp.task('build', ['js','css']);

// Default Task
gulp.task('default', ['build']);
