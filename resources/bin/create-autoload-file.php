<?php

namespace 
{

	class TraversalContext
	{
		/**
		 * @var string
		 */
		public $workingPath;
		
		/**
		 * @var \Parser\ProgramResult
		 */
		public $options;
	
		/**
		 * @var \ArrayObject
		 */
		public $classMap;
	}
	
	class Application
	{

		private static function pathCleanup($path)
		{
			$path = str_replace('\\', '/', $path);
			$path = preg_replace(chr(1) . '/[^/]+/\.\.(/|$)' . chr(1), '\1', $path);
			$path = preg_replace(chr(1) . '/\.(/|$)' . chr(1), '\1', $path);
			return $path;
		}

		private static function getRelativePath($from, $to)
		{
			$from = trim(self::pathCleanup($from), '/');
			$to = trim(self::pathCleanup($to), '/');

			$from = explode('/', $from);
			$to = explode('/', $to);
			$fromCount = count($from);
			$toCount = count($to);
			$min = ($fromCount < $toCount) ? $fromCount : $toCount;
			$commonPartsCount = 0;
			$result = array ();
			while (($commonPartsCount < $min) && ($from[$commonPartsCount] == $to[$commonPartsCount]))
			{
				$commonPartsCount++;
			}

			for ($i = $commonPartsCount; $i < $fromCount; $i++)
			{
				$result[] = '..';
			}

			for ($i = $commonPartsCount; $i < $toCount; $i++)
			{
				$result[] = $to[$i];
			}

			if (count($result) == 0)
			{
				return '.';
			}

			return implode('/', $result);
		}

		private static function processTree(TraversalContext $traversalContext, $outputFile, $treeRoot, $treeNode = null)
		{
			if (!$treeNode)
			{
				$treeNode = $treeRoot;
			}

			$iterator = opendir($treeNode);
			while ($item = readdir($iterator))
			{
				if (substr($item, 0, 1) == '.')
				{
					continue;
				}

				$itemPath = $treeNode . '/' . $item;

				if (is_dir($itemPath))
				{
					self::processTree($traversalContext, $outputFile, $treeRoot, $itemPath);
				}
				else if (is_file($itemPath))
				{
					self::processTreeFile($traversalContext, $outputFile, $treeRoot, $itemPath);
				}
			}

			closedir($iterator);
		}

		private static function processTreeFile(TraversalContext $traversalContext, $outputFile, $treeRoot, $treeFile)
		{
			if (mime_content_type ($treeFile) != 'text/x-php') 
				return;
			
			$relativeToWorkingPath = self::getRelativePath($traversalContext->workingPath, $treeFile);
			
			foreach ($traversalContext->options->excludePatterns() as $pattern) 
			{
				if (preg_match ($pattern, $relativeToWorkingPath)) return;
			}
			
			echo($relativeToWorkingPath . PHP_EOL);
						
			$outputDirectory = realpath(dirname($outputFile));
			$relativeToOutput = self::getRelativePath($outputDirectory, $treeFile);

			$tokens = token_get_all(file_get_contents($treeFile));
			$count = count($tokens);
			$context = "";
			for ($i = 2; $i < $count; $i++)
			{
				if ($tokens[$i - 2][0] == T_NAMESPACE && $tokens[$i - 1][0] == T_WHITESPACE && $tokens[$i][0] == T_STRING)
				{
					$context = $tokens[$i][1];
					$i += 2;
					while (($i < $count) && ($tokens[$i - 1][0] == T_NS_SEPARATOR))
					{
						$context .= '\\' . $tokens[$i][1];
						$i += 2;
					}

					continue;
				}

				if ((($tokens[$i - 2][0] == T_CLASS) || ($tokens[$i - 2][0] == T_INTERFACE)) && $tokens[$i - 1][0] == T_WHITESPACE && $tokens[$i][0] == T_STRING)
				{

					$class_name = $tokens[$i][1];
					if (strlen($context))
					{
						$class_name = $context . '\\' . $class_name;
					}

					$traversalContext->classMap->offsetSet($class_name, $relativeToOutput);
				}
			}
		}

		public static function main($argv)
		{
			$info = new \Program\createAutoloadFileProgramInfo();
			$parser = new \Parser\Parser($info);
			$usage = new \Parser\UsageFormat();
			$result = $parser->parse($argv, 1);
			$traversalContext = new TraversalContext();
			$traversalContext->workingPath =  realpath (getcwd());
			
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
				error_log ($info->usage($usage));
				return (1);
			}

			if ($result->displayHelp())
			{
				echo ($info->usage($usage));
				return (0);
			}

			$outputDirectory = dirname($result->outputFile());
			
			$running = \Phar::running();
			if (!is_dir($outputDirectory))
			{
				if (!mkdir($outputDirectory, 0777, true))
				{
					error_log ('Failed to create ' . $result->outputFile . PHP_EOL);
					return (2);
				}
			}
			
			$traversalContext->options = $result;
			$traversalContext->classMap = new \ArrayObject();
			foreach ($result as $path)
			{
				if (is_file($path))
				{
					$path = realpath($path);
					self::processTreeFile($traversalContext, $result->outputFile, dirname($path), $path);
				}
				elseif (is_dir($path))
				{
					self::processTree($traversalContext, $result->outputFile, realpath($path));
				}
			}

			$content = <<< EOF
<?php
spl_autoload_register(function(\$className) {

EOF;
			$first = true;
			foreach ($traversalContext->classMap as $name => $file)
			{
				$prefix = ($first) ? "\t" : ' else';
				$first = false;
				$content .= <<< EOF
${prefix}if (\$className == '$name') {
		require_once(__DIR__ . '/$file');
	}
EOF;
			} // foreach
			$content .= <<< EOF

});
EOF;
			if ($result->outputFile->isSet)
			{
				file_put_contents($result->outputFile(), $content);
			}
			else
			{
				echo $content;
			}
		} // main
	} // Application class
	exit(Application::main($_SERVER['argv']));
} // namespace















