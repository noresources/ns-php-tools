<?php
// Main code
namespace 
{
	
	require_once("phar://create-autoload-file/__parser.php");
	require_once("phar://create-autoload-file/__programinfo.php");

	function path_cleanup($path)
	{
		$path = str_replace('\\', '/', $path);
		$path = preg_replace(chr(1) . '/[^/]+/\.\.(/|$)' . chr(1), '\1', $path);
		$path = preg_replace(chr(1) . '/\.(/|$)' . chr(1), '\1', $path);
		return $path;
	}

	function path_get_relative($from, $to)
	{
		$from = trim(path_cleanup($from), '/');
		$to = trim(path_cleanup($to), '/');
		
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

	function processTree(&$classArray, $outputFile, $treeRoot, $treeNode = null)
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
			
			echo ($itemPath . PHP_EOL);
			if (is_dir($itemPath))
			{
				processTree($classArray, $outputFile, $treeRoot, $itemPath);
			}
			else if (is_file($itemPath))
			{
				processTreeFile($classArray, $outputFile, $treeRoot, $itemPath);
			}
		}
		
		closedir($iterator);
	}

	function processTreeFile(&$classArray, $outputFile, $treeRoot, $treeFile)
	{
		$outputDirectory = realpath(dirname($outputFile));
		$relativeToOutput = path_get_relative($outputDirectory, $treeFile);
		
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
				
				$classArray[$class_name] = $relativeToOutput;
			}
		}
	}
	
	$info = new \Program\createAutoloadFileProgramInfo();
	$parser = new \Parser\Parser($info);
	$usage = new \Parser\UsageFormat();
	$result = $parser->parse($_SERVER['argv'], 1);
	
	if (!$result())
	{
		if ($result->displayHelp())
		{
			echo ($info->usage($usage));
			exit(0);
		}
		
		foreach ($result->getMessages() as $m)
		{
			echo (' - ' . $m . PHP_EOL);
		}
		
		$usage->format = Parser\UsageFormat::SHORT_TEXT;
		echo ($info->usage($usage));
		exit(1);
	}
	
	if ($result->displayHelp())
	{
		echo ($info->usage($usage));
		exit(0);
	}
	
	$outputDirectory = dirname($result->outputFile);
	
	if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true))
	{
		echo ('Failed to create ' . $result->outputFile . PHP_EOL);
		exit(2);
	}
	
	$classArray = array ();
	foreach ($result as $path)
	{
		echo $path . "\n";
		if (is_file($path))
		{
			$path = realpath($path);
			processTreeFile($classArray, $result->outputFile, dirname($path), $path);
		}
		elseif (is_dir($path))
		{
			processTree($classArray, $result->outputFile, realpath($path));
		}
	}
	
	$functionName = ($result->functionName->isSet) ? $result->functionName() : '';
	if (!is_string($functionName) || (strlen($functionName) == 0))
	{
		$functionName = base64_encode(uniqid());
		$functionName = 'autoload_' . substr($functionName, 0, strlen($functionName) - 2);
	}
	
	$content = <<< EOF
<?php
function ${functionName}(\$className)
{

EOF;
	
	$includeExtensionPattern = $result->includeExtension();
	if (is_string($includeExtensionPattern) && strlen($includeExtensionPattern))
	{
		$includeExtensionPattern = chr(1) . '.*' . str_replace('.', '\\.', $result->includeExtension()) . '$' . chr(1);
	}
	else
	{
		$includeExtensionPattern = false;
	}
	$first = true;
	foreach ($classArray as $name => $file)
	{
		$prefix = ($first) ? '' : 'else';
		$method = ($includeExtensionPattern && preg_match($includeExtensionPattern, $file)) ? 'include_once' : 'require_once';
		$first = false;
		$content .= <<< EOF
	${prefix}if (\$className == '$name')
	{
		$method(__DIR__ . '/$file');
	}
 
EOF;
	}
	$content .= <<< EOF
}
spl_autoload_register('${functionName}');

EOF;
	
	if ($result->outputFile->isSet)
	{
		file_put_contents($result->outputFile(), $content);
	}
	else
	{
		echo $content;
	}
}
