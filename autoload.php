<?php
function autoload_NWQxNTQ3Njg3MWM4NQ($className)
{
	if ($className == 'NoreSources\ArrayUtil')
	{
		require_once(__DIR__ . '/vendor/noresources/ns-php-core/ArrayUtil.php');
	}
 	elseif ($className == 'NoreSources\DataTree')
	{
		require_once(__DIR__ . '/vendor/noresources/ns-php-core/DataTree.php');
	}
 	elseif ($className == 'NoreSources\SourceToken')
	{
		require_once(__DIR__ . '/vendor/noresources/ns-php-core/SourceFile.php');
	}
 	elseif ($className == 'NoreSources\TokenVisitor')
	{
		require_once(__DIR__ . '/vendor/noresources/ns-php-core/SourceFile.php');
	}
 	elseif ($className == 'NoreSources\SourceFile')
	{
		require_once(__DIR__ . '/vendor/noresources/ns-php-core/SourceFile.php');
	}
 	elseif ($className == 'NoreSources\SemanticPostfixedData')
	{
		require_once(__DIR__ . '/vendor/noresources/ns-php-core/SemanticVersion.php');
	}
 	elseif ($className == 'NoreSources\SemanticVersion')
	{
		require_once(__DIR__ . '/vendor/noresources/ns-php-core/SemanticVersion.php');
	}
 	elseif ($className == 'NoreSources\PathUtil')
	{
		require_once(__DIR__ . '/vendor/noresources/ns-php-core/PathUtil.php');
	}
 	elseif ($className == 'NoreSources\UrlUtil')
	{
		require_once(__DIR__ . '/vendor/noresources/ns-php-core/UrlUtil.php');
	}
 	elseif ($className == 'NoreSources\ReporterInterface')
	{
		include_once(__DIR__ . '/vendor/noresources/ns-php-core/Reporter.inc.php');
	}
 	elseif ($className == 'NoreSources\Reporter')
	{
		include_once(__DIR__ . '/vendor/noresources/ns-php-core/Reporter.inc.php');
	}
 	elseif ($className == 'NoreSources\DummyReporterInterface')
	{
		include_once(__DIR__ . '/vendor/noresources/ns-php-core/Reporter.inc.php');
	}
 	elseif ($className == 'NoreSources\XSLT\XSLTStylesheet')
	{
		require_once(__DIR__ . '/vendor/noresources/ns-php-xslt/XSLTStylesheet.php');
	}
 	elseif ($className == 'NoreSources\XSLT\XSLTProcessor')
	{
		require_once(__DIR__ . '/vendor/noresources/ns-php-xslt/XSLTProcessor.php');
	}
 }
spl_autoload_register('autoload_NWQxNTQ3Njg3MWM4NQ');
