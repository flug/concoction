<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace app\Console;

use Composer\Script\Event;

/**
 * Provides installation hooks for when this application is installed via
 * composer. Customize this class to suit your needs.
 */
class Installer {

/**
 * Does some routine installation tasks so people don't have to.
 *
 * @param Composer\Script\Event $event The composer event object.
 * @return void
 */
	public static function postInstall(Event $event) {
		$io = $event->getIO();

		$rootDir = dirname(dirname(__DIR__));
		static::createAppConfig($rootDir, $io);
		static::setTmpPermissions($rootDir, $io);
		static::setSecuritySalt($rootDir, $io);
	}

/**
 * Create the Config/app.php file if it does not exist.
 *
 * @param string $dir The application's root directory.
 * @param Composer\IO\IOInterface $io IO interface to write to console.
 * @return void
 */
	public static function createAppConfig($dir, $io) {
		$appConfig = $dir . '/app/Config/bootstrap.php';
		$defaultConfig = $dir . '/app/Config/bootstrap.php.default';
		if (!file_exists($appConfig)) {
			copy($defaultConfig, $appConfig);
			$io->write('Created `Config/bootstrap.php` file');
		}
	}

/**
 * Set globally writable permissions on the tmp directory.
 *
 * This is not the most secure default, but it gets people up and running quickly.
 *
 * @param string $dir The application's root directory.
 * @param Composer\IO\IOInterface $io IO interface to write to console.
 * @return void
 */
	public static function setTmpPermissions($dir, $io) {
		// Change the permissions on a path and output the results.
		$createTmp = function ($path, $perms, $io){
			$tree = [
					"cache" ,
					"cache/models",
					"cache/persistent",
					"logs"
				];
			foreach($tree as $key => $value){
				$fullPathTmp = $path."/".$value ;
				if(!file_exists($fullPathTmp)){
					mkdir($fullPathTmp, 0777, true);
					$io->write('Created `' . $fullPathTmp . '` file');
				}
			}
		};

		$changePerms = function ($path, $perms, $io) {
			// Get current permissions in decimal format so we can bitmask it.
			$currentPerms = octdec(substr(sprintf('%o', fileperms($path)), -4));
			if (($currentPerms & $perms) == $perms) {
				return;
			}

			$res = chmod($path, $currentPerms | $perms);
			if ($res) {
				$io->write('Permissions set on ' . $path);
			} else {
				$io->write('Failed to set permissions on ' . $path);
			}
		};

		$walker = function ($dir, $perms, $io) use (&$walker, $changePerms) {
			$files = array_diff(scandir($dir), ['.', '..']);
			foreach ($files as $file) {
				$path = $dir . '/' . $file;

				if (!is_dir($path)) {
					continue;
				}

				$changePerms($path, $perms, $io);
				$walker($path, $perms, $io);
			}
		};

		$worldWritable = bindec('0000000111');

		$createTmp($dir . '/app/tmp', $worldWritable, $io);
		$walker($dir . '/app/tmp', $worldWritable, $io);
		$changePerms($dir . '/app/tmp', $worldWritable, $io);
	}

	/**
	 * Set the security.salt value in the application's config file.
	 *
	 * @param string $dir The application's root directory.
	 * @param Composer\IO\IOInterface $io IO interface to write to console.
	 * @return void
	 */
	public static function setSecuritySalt($dir, $io) {
		$config = $dir . '/app/Config/core.php';
		$content = file_get_contents($config);

		$newKey = hash('sha256', $dir . php_uname() . microtime(true));
		$newKeyCipherSeed = str_pad(time(), 29, mt_rand(), STR_PAD_BOTH);
		$content = str_replace('DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9mi', $newKey, $content, $count);

		$content = str_replace('76859309657453542496749683645', $newKeyCipherSeed, $content, $count);
		if ($count == 0) {
			$io->write('No Security.salt placeholder to replace.');
			return;
		}

		$result = file_put_contents($config, $content);
		if ($result) {
			$io->write('Updated Security.salt value in app/Config/core.php');
			return;
		}
		$io->write('Unable to update Security.salt value.');
	}

}
