<?php
/**
 * Copyright 2016 Olivier blunt (business@blunt.sh)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *	  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Start gulp in a background process and wait for the taks to finish
 * Example:
 * ```php
 * $waiter = new GulpWaitforit;
 * $waiter->wait();
 * echo 'Ok all gulp tasks complete here';
 * ```
 */
class GulpWaitforit
{
	/** @var bool		True if a gulp process can be launched */
	protected $can_start = true;
	
	/** @var array[string]*		Saved options */
	protected $options;
	
	/**
	 * Example options:
	 * ```
	 * [
	 *	// Path to the directory of the gulpfile.js, default current directory
	 * 	'gulp_dir' => getcwd(),
	 * 	// Path to the data lock file, default gulp_dir/gulp.lock
	 * 	'lock_path' => 'gulp.lock',
	 * 	// Path to the created gulp process' output logs, default gulp_dir/gulp.log
	 * 	'log_path' => 'gulp.log',
	 * 	// Number of seconds maximum to wait for gulp to finish it's tasks, default 25 seconds
	 * 	'timeout' => 25,
	 * 	// The gulp task to launch, default "default"
	 * 	'task' => 'default',
	 * ]
	 * ```
	 * @param	array[string]*		$options
	 */
	public function __construct(array $options = [])
	{
		// Check that "exec" is allowed
		if (!function_exists('exec')) {
			throw new Exception(__CLASS__.': "exec" is needed to start gulp.');
		}
		
		// Options
		$defaults = [
			'gulp_dir' => getcwd(),
			'lock_path' => 'gulp.lock',
			'log_path' => 'gulp.log',
			'timeout' => 25,
			'task' => 'default',
		];
		$this->options = array_merge($defaults, $options);
		
		// Init folder, etc
		static::init();
	}
	
	/**
	 * Start gulp if not running and Wait for gulp tasks to be done
	 */
	public function wait()
	{
		// File
		$lockfile = fopen($this->options['lock_path'], 'c+');
		if (!$lockfile) {
			throw new Exception(__CLASS__.": Can't open / create the lock file");
		}
		//fwrite(STDERR, "File open, waiting lock\n");

		// Wait to aquire the lock
		$timeout = $this->options['timeout'];
		if (!static::flock($lockfile, $timeout)) {
			throw new Exception(__CLASS__.": Can't get the lock after {$timeout}sec");
		}
		//fwrite(STDERR, "Locked\n");

		// We got the lock, read the content
		$content = stream_get_contents($lockfile);
		$content = json_decode($content, true);

		// Check if the process lives
		//fwrite(STDERR, "Process data: ".var_export($content, true)."\n");
		$create_process = $this->can_start && (empty($content['pid']) || !static::processExists($content['pid']));
		if ($create_process) {
			//fwrite(STDERR, "Process dead\n");
			$pid = $this->runInBackground('gulp '.escapeshellarg($this->options['task']));
			//fwrite(STDERR, "New process $pid\n");
			
			ftruncate($lockfile, 0);
			rewind($lockfile);
			fwrite($lockfile, json_encode(['pid' => $pid]));
		}

		// Unlock
		flock($lockfile, LOCK_UN);
		fclose($lockfile);
		//fwrite(STDERR, "Unlocked\n");
		
		// Wait again after process started
		if ($create_process) {
			sleep(2);
			$this->can_start = false;
			return $this->wait();
		}
		
		// Some errors
		if (!empty($content['errors'])) {
			// Global error
			if (!empty($content['errors']['global_error'])) {
				// Detect fixable errors
				if (preg_match('/Cannot find module \'([^\']+)\'/i', $content['errors']['global_error'], $match)) {
					// Try to install
					exec('cd '.escapeshellarg($this->options['gulp_dir']).'; npm install --save-dev '.escapeshellarg($match[1]), $_, $exitcode);
					if ($exitcode === 0) {
						$this->can_start = true;
						//fwrite(STDERR, "Installed dependency, restart process\n");
						return $this->wait();
					}
				}
			}
			$this->handleErrors($content['errors']);
		}
	}
	
	/**
	 * Create default gulpfile and package.json, create folders, check npm, etc
	 */
	protected function init()
	{
		// Gulp dir
		$gulp_dir = $this->options['gulp_dir'];
		if (!is_dir($gulp_dir) && !mkdir($gulp_dir, 0750, true)) {
			throw new Exception(__CLASS__.": Can't create the gulp directory $gulp_dir");
		}
		
		// Resolve absolute pathes
		if (substr($this->options['log_path'], 0, 1) != '/') {
			$this->options['log_path'] = $gulp_dir.'/'.$this->options['log_path'];
		}
		if (substr($this->options['lock_path'], 0, 1) != '/') {
			$this->options['lock_path'] = $gulp_dir.'/'.$this->options['lock_path'];
		}
		
		// Create directories
		$log_dir = dirname($this->options['log_path']);
		if (!is_dir($log_dir) && !mkdir($log_dir, 0750, true)) {
			throw new Exception(__CLASS__.": Can't create the log directory $log_dir");
		}
		$lock_dir = dirname($this->options['lock_path']);
		if (!is_dir($lock_dir) && !mkdir($lock_dir, 0750, true)) {
			throw new Exception(__CLASS__.": Can't create the lock directory $lock_dir");
		}
			
		// Create a default gulpfile
		if (!file_exists("$gulp_dir/gulpfile.js")) {
			file_put_contents(
				"$gulp_dir/gulpfile.js",
				"var gulp = require('gulp'),\n"
				."waitforit = require('gulp-waitforit')(gulp);\n\n"
				."gulp.task('default', function () {\n"
				."	//TODO: gulp.watch(.., ..);\n"
				."});"
			);
		}
		
		// Create a default package.json
		if (!file_exists("$gulp_dir/package.json")) {
			file_put_contents(
				"$gulp_dir/package.json",
				'{'
					.'"name": "frontend",'
					.'"version": "1.0.0",'
					.'"description": "",'
					.'"main": "gulpfile.js",'
					.'"scripts": {'
						.'"test": "echo \"Error: no test specified\" && exit 1"'
					.'},'
					.'"devDependencies":{'
						.'"gulp": "^3.9.1",'
						.'"gulp-waitforit":"^1.0.0"'
					.'}'
				.'}'
			);
			exec('cd '.escapeshellarg($gulp_dir).' && npm install');
		}
	}
	
	/**
	 * Start a background process and return it's ID
	 * @param	string		$cmd		Command to run
	 * @return	int						Process ID
	 */
	protected function runInBackground($cmd)
	{
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			//TODO: 'start /B '.$cmd
		} else {
			if (empty($this->options['log_path'])) {
				$log = '/dev/null';
			} else {
				$log = escapeshellarg($this->options['log_path']);
			}
			
			$sh = 'cd '.escapeshellarg($this->options['gulp_dir']).';'
				.$cmd
				.' > '.$log
				.' 2> '.$log
				.' & echo $!';
			return intval(trim(exec($sh)));
		}
	}
	
	/**
	 * flock($fp, LOCK_EX) with a timeout
	 * @param	FileDescriptor		$fd
	 * @param	int					$timeout		Timeout in seconds
	 * @return	bool								success
	 */
	protected static function flock($fd, $timeout)
	{
		$timeout *= 1000;
		$time_spent = 0;
		while (!flock($fd, LOCK_EX | LOCK_NB, $wouldblock)) {
			if ($wouldblock) {
				if ($time_spent >= $timeout) {
					return false;
				}
				
				$time_spent += 200;
	        	usleep(200000);
			} else {
				return true;
			}
		}
		return true;
	}
	
	/**
	 * Check if a process id is still running
	 * @param	int		$pid		Process ID
	 * @return	bool				Running
	 */
	protected static function processExists($pid)
	{
		$pid = intval($pid);
		if (empty($pid)) return false;
		
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			//TODO:exec("tasklist /FI \"PID eq $pid\" 2>NUL", $_, $exitcode);
			//return $exitcode == 0;
		} else {
			exec("kill -s 0 $pid 2> /dev/null", $_, $exitcode);
			return $exitcode == 0;
		}
	}
	
	/**
	 * Handle gulp errors
	 * @param	array[string]string		$errors			Array of task name => error stack, global_error for errors not generated in a task
	 */
	protected function handleErrors($errors)
	{
		$str = '';
		foreach ($errors as $task => $stack) {
			if ($task == 'global_error') {
				$str .= "\n$stack\n";
			} else {
				$str .= "\n$task: $stack\n";
			}
		}
		throw new Exception(__CLASS__.": Errors have been detected: $str");
	}
}
?>