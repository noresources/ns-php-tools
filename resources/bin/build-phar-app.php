<?php

namespace NoreSources\Tools
{

	use NoreSources as ns;
	use NoreSources\XSLT as xslt;

	class ApplicationContext
	{

		/**
		 * When running as a Phar, this represent the base URI of the
		 * phar archive virtual filesystem.
		 * (Ex: phar://my-app).
		 * Otherwise @c false
		 *
		 * @var string
		 */
		public $pharBaseURI;

		/**
		 * Result of the command line option parsing
		 * @var \Parser\ProgramResult
		 */
		public $options;

		/**
		 * Program interface definition of the target application
		 * @var \DOMDocument
		 */
		public $programDocument;

		/**
		 * Metadata of the target application
		 * @var \ArrayObject
		 */
		public $metadata;

		/**
		 * List of all embedded PHP files
		 * @var \ArrayObject
		 */
		public $sourceFiles;

		/**
		 * filesystem path -> localname
		 * @var \ArrayObject
		 */
		public $files;

		/**
		 * Phar archive of the target application
		 * @var \Phar
		 */
		public $archive;

		public function __construct()
		{
			$this->pharBaseURI = false;
			$this->metadata = new \ArrayObject();
			$this->sourceFiles = new \ArrayObject();
			$this->files = new \ArrayObject();
		}
	}

	class Application
	{
		const XML_NAMESPACE_PROGRAM = 'http://xsd.nore.fr/program';
		const XML_NAMESPACE_XSD = 'http://www.w3.org/2001/XMLSchema';
		const XML_NAMESPACE_XSLT = 'http://www.w3.org/1999/XSL/Transform';

		public static function prerequisite()
		{
			$errorCount = 0;
			$readOnly = ini_get('phar.readonly');
			if (intval ($readOnly))
			{
				$errorCount++;
				error_log('PHP setting phar.readonly must be set to Off');
			}
			
			foreach (array (
					'dom',
					'xsl',
					'libxml',
					'phar'
			) as $extension)
			{
				if (!extension_loaded($extension))
				{
					$errorCount++;
					error_log($extension . ' extension not loaded');
				}
			}

			foreach (array (
					'NoreSources\PathUtil',
					'NoreSources\XSLT\Stylesheet'
			) as $className)
			{
				if (class_exists($className) === false)
				{
					error_log('Class ' . $className . ' not found. Use --bootstrap');
					$errorCount++;
				}
			}

			return ($errorCount == 0);
		}

		public static function main($argv)
		{
			$context = new ApplicationContext();
			$info = new \Program\buildPharAppProgramInfo();
			$parser = new \Parser\Parser($info);
			$usage = new \Parser\UsageFormat();
			$context->options = $parser->parse($argv, 1);

			if (!\Parser\ProgramResult::success($context->options))
			{
				if ($context->options->displayHelp())
				{
					echo ($info->usage($usage));
					return (0);
				}

				foreach ($context->options->getMessages() as $m)
				{
					echo (' - ' . $m . "\n");
				}

				$usage->format = Parser\UsageFormat::SHORT_TEXT;
				error_log($info->usage($usage));
				return (1);
			}

			if ($context->options->displayHelp())
			{
				echo ($info->usage($usage));
				return (0);
			}

			$app = new Application();
			$app->context = $context;

			if ($context->options->phpBootstrapFile->isSet)
			{
				$f = $context->options->phpBootstrapFile();
				$loader = require ($f);
			}

			if (!self::prerequisite())
			{
				return (1);
			}

			if (!$context->options->nsxmlPath->isSet)
			{
				$running = \Phar::running();
				if (strlen($running))
				{
					$self = new \Phar($running);
					$context->pharBaseURI = 'phar://' . $self->getAlias();
					$context->options->nsxmlPath->isSet = true;
					$context->options->nsxmlPath->argument = $context->pharBaseURI . '/ns';
				}
				else
				{
					error_log('ns-xml path option is required on non-phar binary');
				}
			}

			$context->programDocument = new \DOMDocument('1.0', 'utf-8');
			$context->programDocument->load($context->options->xmlProgramDescriptionPath());
			$context->programDocument->xinclude();

			$context->metadata['version'] = $context->programDocument->documentElement->getAttributeNode('version')->value;

			foreach (array (
					'author',
					'copyright',
					'license'
			) as $attribute)
			{
				if ($context->programDocument->documentElement->hasAttribute($attribute))
					$context->metadata[$attribute] = $context->programDocument->documentElement->getAttribute($attribute)->value;
			}

			if (!$context->options->skipValidation->isSet)
			{
				$schemaFile = $context->options->nsxmlPath() . '/xsd/program/' . $context->metadata['version'] . '/program.xsd';
				if (!file_exists($schemaFile))
				{
					error_log('XSD schema not found (' . $schemaFile . ').');
					return 1;
				}

				$schema = new \DOMDocument('1.0', 'utf-8');
				$schema->load($schemaFile);
				$schema->xinclude();

				$valid = $context->programDocument->schemaValidate($schemaFile);
				if (!$valid)
				{
					error_log('XML schema validation failure');
					return 1;
				}
			}

			if ($app->buildPhar($context))
			{
				if ($context->options->chmod->isSet)
				{
					$v = intval($context->options->chmod());
					$u = intval($v / 100);
					$g = intval($v / 10) % 10;
					$o = $v % 10;
					$v = $o + ($g * 8) + ($u * (64));
					chmod($context->options->outputScriptFilePath(), $v);
				}
			}
		}

		private function buildPhar(ApplicationContext $context)
		{
			$xsltOptions = array ();
			if ($context->options->parserNamespace->isSet)
				$xsltOptions['prg.php.parser.namespace'] = $context->options->parserNamespace();

			if ($context->options->programNamespace->isSet)
				$xsltOptions['prg.php.programinfo.namespace'] = $context->options->programNamespace();

			$applicationAlias = pathinfo($context->options->outputScriptFilePath, \PATHINFO_FILENAME);

			$applicationContent = file_get_contents($context->options->programFilePath);

			$context->archive = new \Phar($context->options->outputScriptFilePath, \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_FILENAME, $applicationAlias);

			if ($context->options->embeddedResourceListFile->isSet)
			{
				$directory = dirname($context->options->embeddedResourceListFile());
				$json = json_decode(file_get_contents($context->options->embeddedResourceListFile()), true);
				if (!\is_array($json))
				{
					error_log('Invalid embedded resources description file.');
					return 1;
				}

				foreach ($json as $key => $entry)
				{
					if (!\is_array($entry) || !\array_key_exists('source', $entry))
					{
						error_log('Invalid embedded resources entry ' . $key . '.');
						return 1;
					}

					$path = $directory . '/' . $entry['source'];

					$local = \array_key_exists('target', $entry) ? $entry['target'] : $entry['source'];
					$mimeType = \array_key_exists('type', $entry) ? $entry['type'] : false;

					if (is_file($path))
					{
						if ($mimeType == false)
							$mimeType = mime_content_type($path);
						$this->log(' - Add ' . $mimeType . ' ' . realpath($path));
						self::addFile($context, $path, $local, $mimeType);
					}
					elseif (is_dir($path))
					{
						$this->log(' - Add folder ' . $path);
						self::addFolder($context, $path, $local);
					}
				}
			}

			if ($context->options->embeddedResources->isSet)
			{
				$resources = $context->options->embeddedResources();
				foreach ($resources as $resource)
				{
					$path = $resource;
					$local = $resource;
					if (preg_match(chr(1) . '(.*?)=(.*)' . chr(1), $resource, $m))
					{
						$path = $m[1];
						$local = $m[2];
					}

					$local = rtrim($local, '/\t ');
					$path = rtrim($path, '/\t ');

					if (is_file($path))
					{
						$this->log(' - Add file ' . $path);
						self::addFile($context, $path, $local, mime_content_type($path));
					}
					elseif (is_dir($path))
					{
						$this->log(' - Add folder ' . $path);
						self::addFolder($context, $path, $local);
					}
				}
			}

			$files = array (
					'parser' => array (
							'xsl' => $context->options->nsxmlPath() . '/xsl/program/' . $context->metadata['version'] . '/php/parser.xsl',
							'localname' => '__parser.php'
					),
					'info' => array (
							'xsl' => $context->options->nsxmlPath() . '/xsl/program/' . $context->metadata['version'] . '/php/programinfo.xsl',
							'localname' => '__programinfo.php'
					)
			);

			$applicationLoader = '<?php' . PHP_EOL;
			$applicationLoader .= 'namespace {' . PHP_EOL;

			foreach ($files as $key => $value)
			{
				$xslFile = $value['xsl'];
				if (!file_exists($xslFile))
				{
					error_log($key . ' XSLT file not found.');
					return 1;
				}

				$xsl = new \DOMDocument('1.0', 'utf-8');
				$xsl->load($xslFile);

				$xsl->xinclude();

				$xslt = new \XSLTProcessor();
				$xslt->importStylesheet($xsl);

				foreach ($xsltOptions as $n => $v)
				{
					$xslt->setParameter('', $n, $v);
				}

				$context->archive->addFromString($value['localname'], $xslt->transformToXml($context->programDocument));
				$context->sourceFiles->append($value['localname']);
			}

			foreach ($context->sourceFiles as $localname)
			{
				$applicationLoader .= 'require("phar://' . $applicationAlias . '/' . $localname . '");' . PHP_EOL;
			}

			$applicationLoader .= '} // namespace' . PHP_EOL;
			$applicationLoader .= '?>' . PHP_EOL;

			$context->archive->addFromString('index.php', $applicationLoader . $applicationContent);

			if ($context->options->compressFiles->isSet)
			{
				if ($context->archive->canCompress(\Phar::BZ2))
				{
					$context->archive->compressFiles(\Phar::BZ2);
				}
				elseif ($context->archive->canCompress(\Phar::GZ))
				{
					$context->archive->compressFiles(\Phar::GZ);
				}
			}

			$context->archive->setMetadata($context->metadata);

			$context->archive->setStub('#!/usr/bin/env php' . "\n" . $context->archive->createDefaultStub('index.php'));

			return true;
		}

		private static function addFile(ApplicationContext $context, $path, $localName, $mimeType = false)
		{
			$context->files->offsetSet($path, $localName);
			if (preg_match(chr(1) . '.*?/xml' . chr(1), $mimeType))
			{
				self::addXmlFile($context, $path, $localName);
			}
			else
			{
				if ($mimeType == 'text/x-php')
				{
					$context->sourceFiles->append($localName);
				}
				
				$context->archive->addFile($path, $localName);
			}
			
			return 'phar://' . $context->archive->getAlias() . '/' . $localName;
		}

		private static function addXmlFile(ApplicationContext $context, $path, $localName)
		{
			$directory = dirname(realpath($path));
			$dom = new \DOMDocument('1.0', 'utf-8');
			$dom->load($path);
			$dom->xinclude();

			$xpath = new \DOMXPath($dom);

			// Cleanup XSD file
			{
				$xpath->registerNamespace('xsd', self::XML_NAMESPACE_XSD);
				$xsdImportNodes = $xpath->evaluate('//xsd:import');
				foreach ($xsdImportNodes as $node)
				{
					if ($node->hasAttribute('schemaLocation'))
					{
						$location = $node->getAttribute('schemaLocation');
						$location = $directory . '/' . $location;
						if (file_exists($location))
						{
							$location = realpath($location);

							// Temp
							$comment = $dom->createComment($node->getAttribute('schemaLocation'));
							$node->parentNode->insertBefore($comment, $node);

							$localLocationName = '';
							if ($context->files->offsetExists($location))
							{
								$localLocationName = $context->files->offsetGet($location);
							}
							else
							{
								$localLocationName = base64_encode(uniqid(basename($location), true));
								$uri = self::addFile($context, $location, $localLocationName, 'text/xml');
								$node->setAttribute('schemaLocation', $uri);
							}
						}
					}
				}
			}

			// Consolidate XSLT stylesheet
			{
				$xpath->registerNamespace('xsl', self::XML_NAMESPACE_XSLT);
				$xslImportNodes = $xpath->evaluate('//xsl:import');
				if ($xslImportNodes->length)
				{
					xslt\Stylesheet::consolidateDocument($dom, $directory);
				}
			}

			// Remove comments
			{
				$comments = $xpath->query('//comment()');
				foreach ($comments as $comment)
				{
					$comment->parentNode->removeChild($comment);
				}
			}

			$dom->normalizeDocument();

			$context->archive->addFromString($localName, $dom->saveXML());
		}

		private static function addFolder(ApplicationContext $context, $pathBase, $localBase)
		{
			$context->archive->addEmptyDir($localBase);

			$d = opendir($pathBase);
			while ($i = readdir($d))
			{
				if ($i == '.' || $i == '..')
					continue;
				$target = $pathBase . '/' . $i;
				$local = $localBase . '/' . $i;

				if (is_dir($target))
				{
					$this->log(' - Add folder ' . $target);
					self::addFolder($context, $target, $local);
				}
				elseif (is_file($target))
				{
					log(' - Add file ' . $target);
					self::addFile($context, $target, $local, mime_content_type($path));
				}
			}
			closedir($d);
		}

		private function log($message)
		{
			if ($this->context->options->verbose->isSet)
			{
				echo ($message . PHP_EOL);
			}
		}

		/**
		 * @var ApplicationContext
		 */
		private $context;
	} // class

	exit(Application::main($_SERVER['argv']));
}