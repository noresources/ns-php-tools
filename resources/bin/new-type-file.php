<?php
namespace 
{
	use Nette\PhpGenerator\PhpFile;
	use NoreSources\Container\Container;

	class Application
	{
		public static function main($argv)
		{
			$info = new \Program\newTypeFileProgramInfo();
			$parser = new \Parser\Parser($info);
			$usage = new \Parser\UsageFormat();
			$result = $parser->parse($argv, 1);

			if (!$result())
			{
				if ($result->displayHelp())
				{
					echo ($info->usage($usage));
					return (0);
				}

				foreach ($result->getMessages() as $m)
				{
					error_log(' - ' . $m . PHP_EOL);
				}

				$usage->format = Parser\UsageFormat::SHORT_TEXT;
				error_log($info->usage($usage));
				return (1);
			}

			if ($result->displayHelp())
			{
				echo ($info->usage($usage));
				return (0);
			}

			$running = \Phar::running();

			$workingDirectory = new \SplFileInfo(getcwd());

			$loaderFile = __DIR__ . '/../vendor/autoload.php';
			if (!empty($running))
				$loaderFile = __DIR__ . '/../../vendor/autoload.php';

			if ($result->phpBootstrapFile->isSet)
				$loaderFile = $result->phpBootstrapFile();

			if (\file_exists($loaderFile))
				$loader = require ($loaderFile);

			$typeName = Container::keyValue($result, 0, false);

			if (!$typeName)
				throw new \ErrorException('Missing type name');

			$typeName = \str_replace('\\', '/', $typeName);

			$typeFilePath = new \SplFileInfo(
				$workingDirectory->getRealPath() . '/' . $typeName .
				'.php');

			if ($typeFilePath->isReadable())
				throw new \Exception('File already exists');

			if (!\is_dir($typeFilePath->getPath()))
				if (!@mkdir($typeFilePath->getPath(), 0755, true))
					throw new \Exception(
						'Failed to create ' . $typeFilePath->getPath());

			$composerRootPath = $typeFilePath->getPath();
			$composerFilePath = null;

			while ($composerRootPath != '/')
			{
				$composerFilePath = $composerRootPath . '/composer.json';
				if (\file_exists($composerFilePath))
				{
					$composerFilePath = new \SplFileInfo(
						$composerFilePath);
					break;
				}

				$composerRootPath = \dirname($composerRootPath);
			}

			if (!($composerFilePath instanceof \SplFileInfo))
				throw new \Exception('No composer file');

			echo ('Composer project directory: ' . dirname ($composerFilePath) . PHP_EOL);
				
			$composer = \json_decode(
				\file_get_contents($composerFilePath->getRealPath()),
				true);
			
			$namespaceName = null;
			foreach (['autoload', 'autoload-dev'] as $key) 
			{
				$autoload = Container::keyValue($composer, $key, []);
				if (!Container::keyExists($autoload, 'psr-4')) continue;
				foreach ($autoload['psr-4'] as $n => $p)
				{
					$path = $composerRootPath . '/' . $p;
					$part = \rtrim ($path, '/');
					$part = \substr(\strval($typeFilePath), 0,
						\strlen($path));

					// var_dump (['path' => $path, 'part' => $part]);
					
					if ($part != $path) continue;
					
					$n = \rtrim($n, '\\');
					$namespaceParts = [];
					if (!empty ($n)) 
						$namespaceParts[] = $n;
					$sub = \dirname(
						\substr(\strval($typeFilePath),
							\strlen($part)));
					if (!empty($sub) && $sub != '.')
						$namespaceParts[] = \str_replace('/', '\\', $sub);
					
					$namespaceName = \implode ('\\', $namespaceParts);
					break;
				}
				if ($namespaceName) break;
			}

			echo ("Namespace: " . ($namespaceName ? $namespaceName : 'N/A') . PHP_EOL);
			
			$typeName = \basename($typeName);
			$typeType = 'Class';
			if (\preg_match('/.+Interface$/', $typeName))
				$typeType = 'Interface';
			if (\preg_match('/.+Trait$/', $typeName))
				$typeType = 'Trait';

			$headerFilename = 'resources/templates/file-header.txt';
			$header = '';
									foreach ([
										$composerRootPath
									] as $path)
									{
				$headerFilePath = $path . '/' . $headerFilename;
				if (\file_exists($headerFilePath))
					$header = \file_get_contents($headerFilePath);
			}
			$header = \str_replace('{year}', date('Y'), $header);

			$typeFile = new PhpFile();
			$typeFile->addComment($header);
			$ns = $typeFile;
			if ($namespaceName)
				$ns = $typeFile->addNamespace($namespaceName);
										
										$type = \call_user_func([
											$ns,
											'add' . $typeType
										], $typeName);

			\file_put_contents(\strval($typeFilePath),
				\strval($typeFile));
		}
	}
	exit(Application::main($_SERVER['argv']));
}




