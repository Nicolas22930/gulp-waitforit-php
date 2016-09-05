var gulp = require('gulp'),
	wait4it = require('gulp-waitforit')(gulp),
	perroquet = require('gulp-perroquet');

// Wait test
gulp.task('wait', function (cb) {
	setTimeout(cb, 10000);
});

// Watcher
gulp.task('default', function () {
	gulp.watch('**/*.wait', ['wait']);
});