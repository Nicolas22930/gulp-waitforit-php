[![Build Status](https://travis-ci.org/blunt1337/gulp-waitforit-php.svg?branch=master)](https://travis-ci.org/blunt1337/gulp-waitforit-php)

![alt text](https://camo.githubusercontent.com/367756cc67f8c9a25a5c53a4dca14e52375d9ba5/687474703a2f2f32342e6d656469612e74756d626c722e636f6d2f74756d626c725f6d33783634387778626a317275393971766f315f3530302e706e67 "wait for it")

This module helps PHP to wait for gulp to finish all it's tasks.

## What is it really for
- Prevent the loading of your pages when gulp is still building your assets
- Run gulp without opening a terminal
- Install automatically new modules from require(...)
- Show gulp errors on your page (yeah still no terminal)

## Usage
You must use the PHP class GulpWaitforit available with [composer](https://packagist.org/packages/blunt1337/gulp-waitforit), or just from this github, it's a single file.
Then use it as follow:
```php
// We wait on gulp
// should be called before your script closes, so after rendering your view
$gulp = new GulpWaitforit();
$gulp->wait();
```
Now gulp need to tell us when to wait.
So first let's get our module ```npm install --save-dev gulp-waitforit```. And add a simple gulpfile.js that compiles sass files:
```js
var gulp = require('gulp'),
	wait4it = require('gulp-waitforit')(gulp),
	// Now that waitforit is instanciated, errors are catched
	sass = require('sass');

// A simple sass compilation
gulp.task('sass', function () {
	return gulp.src('**/*.scss')
		.pipe(sass())
		.pipe(gulp.dest('static/css/'));
});

// A watch for sass files
gulp.task('default', function () {
	gulp.watch('**/*.scss', ['sass']);
});
```
It's now all setup. No need for a packages.json, a default one will be installed, and npm install will be called.

## Options
A few options are available in the PHP class and the gulp module.
For the php class:
```php
$gulp = new GulpWaitforit([
	// Path to the directory of the gulpfile.js, default current directory
	'gulp_dir' => getcwd(),
	// The gulp task to launch, default "default"
	'task' => 'default',
	// Number of seconds maximum to wait for gulp to finish it's tasks, default 25 seconds
	'timeout' => 25,
	// Path to the lock file, default gulp_dir/gulp.lock
	'lock_path' => 'gulp.lock',
	// Path to the created gulp process' output logs, default gulp_dir/gulp.log. Null to ignore logs.
	'log_path' => 'gulp.log',
]);
```
A bit less options for the gulp module:
```js
var gulp = require('gulp'),
	wait4it = require('gulp-waitforit')(gulp, {
		// Time to live after no tasks have been started (in seconds), default 10 minutes
		afk_ttl: 600,
		// Path to the lock file, it need to be the same as configured in PHP, default gulp_dir/gulp.lock
		lock_path: process.cwd() + '/gulp.lock'
	});
```

## Kill me
If your want to kill the background process that PHP has started. You can open a terminal, go to the gulpfile's directory, and type `gulp waitforit:kill`

##### To suggest a feature, report a bug, or general discussion: 
http://github.com/blunt1337/gulp-waitforit-php/issues/