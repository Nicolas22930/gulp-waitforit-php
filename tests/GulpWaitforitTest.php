<?php
class GulpWaitforitTest extends \PHPUnit_Framework_TestCase
{
	public function test()
	{
		$this->cleanup();
		
		// Start gulp
		$waiter = new GulpWaitforit(['gulp_dir' => __DIR__]);
		$waiter->wait();
		
		// Check npm install success
		if (file_exists(__DIR__.'/npm-debug.log')) {
			throw new Exception('Error with npm install: '.file_get_contents(__DIR__.'/npm-debug.log'));
		}
		// Check dependency auto install
		if (!is_dir(__DIR__.'/node_modules/gulp-perroquet')) {
			throw new Exception('Failed to install dependency automatically');
		}
		
		// Restest gulp and it should take more than 10sec
		file_put_contents(__DIR__.'/plz.wait', 'Wait!');
		usleep(500000);
		
		$time = microtime(true);
		$waiter = new GulpWaitforit(['gulp_dir' => __DIR__]);
		$waiter->wait();
		$duration = microtime(true) - $time;
		
		// It should take 10sec - usleep
		if ($duration < 9.5) {
			throw new Exception('Failed to wait 10sec');
		}
		
		// Exit background gulp
		exec('cd '.escapeshellarg(__DIR__).'; gulp service:kill', $_, $exitcode);
		if ($exitcode != 0) {
			throw new Exception('Failed to stop the background process');
		}
	}
	
	public function cleanup()
	{
		@unlink(__DIR__.'/gulp.lock');
		@unlink(__DIR__.'/gulp.log');
		@unlink(__DIR__.'/package.json');
		@unlink(__DIR__.'/npm-debug.log');
		@unlink(__DIR__.'/plz.wait');
	}
}
?>