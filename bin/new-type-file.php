#!/usr/bin/env php
<?php
namespace Parser
{


// Basic objects

/**
 * An option name
 */
class OptionName
{

	const SHORT = 1;

	const LONG = 2;

	const ANY = 3;

	/**
	 *
	 * @param string $name
	 *        	Option name
	 */
	public function __construct($name)
	{
		$this->m_name = $name;
	}

	public function __toString()
	{
		return $this->m_name;
	}

	/**
	 *
	 * @return boolean true if the option name is a short name (single character)
	 */
	public function isShort()
	{
		return (strlen($this->m_name) == 1);
	}

	/**
	 *
	 * @return string Option name (without leading dash(es))
	 */
	public function name()
	{
		return $this->m_name;
	}

	/**
	 *
	 * @return string Option name as it appears on the command line
	 */
	public function cliName()
	{
		if ($this->isShort())
		{
			return '-' . $this->m_name;
		}

		return '--' . $this->m_name;
	}

	/**
	 *
	 * @var string
	 */
	private $m_name;
}

/**
 * Array of OptionName
 */
class OptionNameList extends \ArrayObject
{

	public function __construct($options = array())
	{
		parent::__construct();
		$optionArray = array();
		foreach ($options as $k => $v)
		{
			if (is_object($v) && $v instanceof OptionName)
			{
				parent::offsetSet($v->name(), $v);
			}
			elseif (is_integer($k) && is_string($v))
			{
				parent::offsetSet($v, new OptionName($v));
			}
			else
			{
				// throw
			}
		}

		$this->uksort(array(
			get_class($this),
			'keySort'
		));
	}

	/**
	 *
	 * @param string $k
	 * @param string $v
	 */
	public function offsetSet($k, $v)
	{
		parent::offsetSet($k, $v);
		$this->uksort(array(
			get_class($this),
			'keySort'
		));
	}

	/**
	 *
	 * @param function $func
	 * @param array $argv
	 * @throws BadMethodCallException
	 */
	public function __call($func, $argv)
	{
		if (!is_callable($func) || substr($func, 0, 6) !== 'array_')
		{
			throw new BadMethodCallException(__CLASS__ . '->' . $func);
		}

		return call_user_func_array($func, array_merge(array(
			$this->getArrayCopy()
		), $argv));
	}

	/**
	 *
	 * @return array Subset of names which only contains single-letter option names
	 */
	public function getShortOptionNames()
	{
		return array_filter($this->getArrayCopy(), array(
			get_class($this),
			'filterShort'
		));
	}

	/**
	 *
	 * @return array
	 */
	public function getLongOptionNames()
	{
		return array_filter($this->getArrayCopy(), array(
			get_class($this),
			'filterLong'
		));
	}

	/**
	 * Get the first available option name
	 *
	 * @param integer $type
	 *        	Option name type
	 * @param bool $strict
	 *        	Force option name to match the desired type
	 * @return OptionName if available. Otherwise null
	 */
	public function getFirstOptionName($type = OptionName::ANY, $strict = false)
	{
		if ($type == OptionName::ANY)
		{
			$a = $this->getArrayCopy();
			list ($k, $v) = each($a);
			return $v;
		}

		$other = null;
		foreach ($this as $k => $v)
		{
			if (($type == OptionName::SHORT) && $v->isShort())
			{
				return $v;
			}
			elseif (($type == OptionName::LONG) && !$v->isShort())
			{
				return $v;
			}

			if (!$other)
			{
				$other = $v;
			}
		}

		return ($strict) ? null : $v;
	}

	/**
	 * Sort short option first, then use strcmp
	 *
	 * @param string $a
	 * @param string $b
	 */
	public static function keySort($a, $b)
	{
		$la = strlen($a);
		$lb = strlen($b);

		if (($la < $lb) && ($la == 1))
		{
			return -1;
		}
		elseif (($lb < $la) && ($lb == 1))
		{
			return 1;
		}

		return strcmp($a, $b);
	}

	public static function filterShort($option)
	{
		return $option->isShort();
	}

	public static function filterLong($option)
	{
		return !($option->isShort());
	}
}

/**
 * Text utility
 */
class Text
{

	/**
	 * Extended version of the PHP built-in <code>implode</code> function
	 *
	 * @param array $list
	 *        	Array to implode
	 * @param string $separator
	 *        	Value separator
	 * @param string $lastSeparator
	 *        	Separator between the penultimate and last values
	 */
	public static function implode($list, $separator = ', ', $lastSeparator = ' or ')
	{
		$c = count($list);

		if ($c <= 1)
		{
			return implode($separator, $list);
		}

		$last = array_pop($list);
		return implode($separator, $list) . $lastSeparator . $last;
	}
}

/**
 * Text wrapping function
 */
class TextWrap
{

	const OFFSET_NONE = 0x0;

	const OFFSET_FIRST = 0x1;

	const OFFSET_OTHER = 0x2;

	/**
	 * Maximum number of character per line
	 */
	public $lineLength;

	/**
	 * End of line string delimiter
	 */
	public $endOfLineString;

	/**
	 * Indentation character(s).
	 * Should be \t or a series of ' ' (space)
	 */
	public $indentString;

	public function __construct($length = 80, $indent = '  ', $eolString = PHP_EOL)
	{
		$this->lineLength = $length;
		$this->endOfLineString = $eolString;
		$this->indentString = $indent;
	}

	/**
	 * Wrap text
	 *
	 * @param string $text
	 *        	Text to wrap
	 * @param integer $mode
	 *        	Offset mode (flags)
	 * @param integer $level
	 *        	Indentation level
	 */
	public function wrap($text, $mode = self::OFFSET_NONE, $level = 0)
	{
		$indentation = str_repeat($this->indentString, $level);

		$firstIndent = $indentation . (($mode & self::OFFSET_FIRST) ? $this->indentString : '');
		$otherIndent = $indentation . (($mode & self::OFFSET_OTHER) ? $this->indentString : '');

		$text = $firstIndent . $text;

		$len = ($this->lineLength - strlen($otherIndent));
		$result = wordwrap($text, (($len > 0) ? $len : 1), $this->endOfLineString);
		if (($mode & self::OFFSET_OTHER) || strlen($indentation))
		{

			$result = implode($this->endOfLineString . $otherIndent,
				explode($this->endOfLineString, $result));
		}

		return $result;
	}
}

/**
 * Program usage display settings
 */
class UsageFormat
{

	const SHORT_TEXT = 0x1;

	const ABSTRACT_TEXT = 0x2;

	const DETAILED_TEXT = 0x7;

	public $textWrap;

	public $format;

	public function __construct()
	{
		$this->textWrap = new TextWrap();
		$this->format = self::DETAILED_TEXT;
	}
}

abstract class ValueValidator
{

	/**
	 *
	 * @param ParserState $state
	 *        	Parser state
	 * @param ProgramResult $result
	 *        	Program result instance
	 * @param mixed $element
	 *        	Option properties or positional argument index
	 * @param mixed $value
	 *        	Value to validate
	 */
	abstract function validate(ParserState $state, ProgramResult $result, $element, $value);

	/**
	 * Additional usage information
	 *
	 * @return string
	 */
	abstract function usage(UsageFormat $usage);

	/**
	 * Add 'Invalid argument' error message
	 *
	 * @param ParserState $state
	 * @param ProgramResult $result
	 * @param unknown_type $element
	 * @param UsageFormat $usage
	 */
	protected function appendDefaultError(ParserState $state, ProgramResult $result, $element,
		UsageFormat $usage)
	{
		if (is_object($element) && ($element instanceof OptionNameBinding))
		{
			$result->appendMessage(Message::ERROR, 1, Message::ERROR_INVALID_OPTION_VALUE,
				$element->name->cliName(), $this->usage($usage));
		}
		else
		{
			$result->appendMessage(Message::ERROR, 2, Message::ERROR_INVALID_POSARG_VALUE, $element,
				$this->usage($usage));
		}
	}
}

/**
 * Validate path-type values
 */
class PathValueValidator extends ValueValidator
{

	const EXISTS = 0x01;

	const ACCESS_READ = 0x02;

	const ACCESS_WRITE = 0x04;

	const ACCESS_EXECUTE = 0x08;

	const TYPE_FILE = 0x10;

	const TYPE_FOLDER = 0x20;

	const TYPE_SYMLINK = 0x40;

	const TYPE_ALL = 0x70;

	public function __construct($flags)
	{
		$this->flags = $flags;
	}

	public function validate(ParserState $state, ProgramResult $result, $element, $value)
	{
		$passed = true;
		if ($this->flags & self::EXISTS)
		{
			if (($this->flags & self::ACCESS_READ) && !is_readable($value))
			{
				$passed = false;
			}

			if (($this->flags & self::ACCESS_WRITE) && !is_writable($value))
			{
				$passed = false;
			}

			if (($this->flags & self::ACCESS_EXECUTE) && !is_executable($value))
			{
				$passsed = false;
			}
		}

		if (file_exists($value))
		{
			$types = ($this->flags & self::TYPE_ALL);
			if (!(($types == 0) || ($types == self::TYPE_ALL)))
			{
				$typeFound = false;
				if (($types & self::TYPE_FILE) && is_file($value))
				{
					$typeFound = true;
				}
				elseif (($types & self::TYPE_FOLDER) && is_dir($value))
				{
					$typeFound = true;
				}
				elseif (($types & self::TYPE_FOLDER) && is_link($value))
				{
					$typeFound = true;
				}

				if (!$typeFound)
				{
					$passed = False;
				}
			}
		}

		/**
		 *
		 * @todo a more customized error message ?
		 */
		if (!$passed)
		{
			$usage = new UsageFormat();
			$this->appendDefaultError($state, $result, $element, $usage);
		}

		return $passed;
	}

	public function usage(UsageFormat $usage)
	{
		$text = '';
		$eol = $usage->textWrap->endOfLineString;

		$types = ($this->flags & self::TYPE_ALL);

		if (!(($types == 0) || ($types == self::TYPE_ALL)))
		{
			$types = array(
				self::TYPE_FILE => 'file',
				self::TYPE_FOLDER => 'folder',
				self::TYPE_SYMLINK => 'symbolic link'
			);
			$names = array();

			foreach ($types as $t => $name)
			{
				if (($t & $this->flags) == $t)
				{
					$names[] = $name;
				}
			}

			$text .= 'Expected file type' . ((count($names) > 1) ? 's' : '') . ': ';
			$text .= Text::implode($names, ', ', ' or ');
		}

		$access = ($this->flags & (self::ACCESS_READ | self::ACCESS_WRITE | self::ACCESS_EXECUTE));
		if ($access)
		{
			$access = array(
				self::ACCESS_READ => 'readable',
				self::ACCESS_WRITE => 'writable',
				self::ACCESS_EXECUTE => 'executable'
			);
			$names = array();
			foreach ($access as $a => $name)
			{
				if (($a & $this->flags) == $a)
				{
					$names[] = $name;
				}
			}

			$text .= (strlen($text) ? $eol : '') . 'Path argument must be ' .
				Text::implode($names, ', ', ' and ');
		}

		return $text;
	}

	private $flags;
}

/**
 * Number value validator
 */
class NumberValueValidator extends ValueValidator
{

	public function __construct($minValue = null, $maxValue = null)
	{
		$this->minValue = $minValue;
		$this->maxValue = $maxValue;
	}

	public function validate(ParserState $state, ProgramResult $result, $element, $value)
	{
		$passed = true;
		if (!is_numeric($value))
		{
			$passed = false;
		}

		if ($passed && ($this->minValue !== null) && ($value < $this->minValue))
		{
			$passed = false;
		}

		if ($passed && ($this->maxValue !== null) && ($value > $this->maxValue))
		{
			$passed = false;
		}

		/**
		 *
		 * @todo a more customized error message ?
		 */
		if (!$passed)
		{
			$usage = new UsageFormat();
			$this->appendDefaultError($state, $result, $element, $usage);
		}

		return $passed;
	}

	public function usage(UsageFormat $usage)
	{
		$text = 'Argument value must be a number';
		if ($this->minValue !== null)
		{
			if ($this->maxValue !== null)
			{
				$text .= ' between ' . $this->minValue . ' and ' . $this->maxValue;
			}
			else
			{
				$text .= ' greater or equal than ' . $this->minValue;
			}
		}
		else
		{
			$text .= ' lesser or equal than ' . $this->maxValue;
		}

		return $text;
	}

	private $minValue;

	private $maxValue;
}

/**
 */
class EnumerationValueValidator extends ValueValidator
{

	const RESTRICT = 0x1;

	public function __construct($values, $flags = self::RESTRICT)
	{
		$this->values = $values;
		$this->flags = $flags;
	}

	public function validate(ParserState $state, ProgramResult $result, $element, $value)
	{
		if (!($this->flags & self::RESTRICT))
		{
			return true;
		}

		foreach ($this->values as $v)
		{
			if ($v == $value)
			{
				return true;
			}
		}

		$usage = new UsageFormat();
		$this->appendDefaultError($state, $result, $element, $usage);

		return false;
	}

	public function usage(UsageFormat $usage)
	{
		return 'Argument value ' . (($this->flags & self::RESTRICT) ? 'must' : 'can') . ' be ' .
			Text::implode($this->values, ', ', ' or ');
	}

	private $values;

	private $flags;
}

/**
 * Base class for all command line elements description
 */
class ItemInfo
{

	/**
	 * The element must appear on the command line
	 *
	 * @var flag
	 */
	const REQUIRED = 1;

	/**
	 *
	 * @var string
	 */
	public $abstract;

	/**
	 *
	 * @var string
	 */
	public $details;

	/**
	 *
	 * @param string $abstract
	 *        	Short description
	 * @param string $details
	 *        	Detailed description
	 */
	public function __construct($abstract = null, $details = null)
	{
		$this->abstract = $abstract;
		$this->details = $details;
	}
}

// Option infos

/**
 * Describe a program option
 */
class OptionInfo extends ItemInfo
{

	/**
	 *
	 * @var integer
	 */
	public $optionFlags;

	/**
	 *
	 * @var string
	 */
	public $variableName;

	/**
	 *
	 * @var ItemInfo null if the option is a top-level option
	 */
	public $parent;

	public $validators;

	public function __construct($variableName = null, $names = null, $flags = 0)
	{
		parent::__construct();
		$this->optionFlags = $flags;
		$this->variableName = $variableName;
		$this->optionNames = new OptionNameList();
		if ($names)
		{
			$this->setOptionNames($names);
		}
		$this->parent = null;
		$this->validators = array();
	}

	/**
	 *
	 * @return OptionMameList
	 */
	public function getOptionNames()
	{
		return $this->optionNames;
	}

	/**
	 *
	 * @param mixed $nameListOrArray
	 */
	public function setOptionNames($nameListOrArray)
	{
		if (is_array($nameListOrArray))
		{
			$this->optionNames = new OptionNameList($nameListOrArray);
		}
		elseif ($nameListOrArray instanceof OptionNameList)
		{
			$this->optionNames = $nameListOrArray;
		}
	}

	public function getKey()
	{
		$key = get_class($this);
		if ($this->parent)
		{
			$index = 0;
			foreach ($this->parent->getOptions() as $o)
			{
				if ($o == $this)
				{
					$key .= $index;
					break;
				}

				$index++;
			}

			$key = $this->parent->getKey() . $key;
		}

		return $key;
	}

	/**
	 *
	 * @var OptionNameList
	 */
	private $optionNames;
}

/**
 * Switch option description
 */
class SwitchOptionInfo extends OptionInfo
{

	public function __construct($variableName = null, $names = null, $flags = 0)
	{
		parent::__construct($variableName, $names, $flags);
	}
}

/**
 * Type of value for given to (multi-)argument options
 * and positional arguments
 */
class ArgumentType
{

	const STRING = 1;

	const MIXED = 1;

	// Alias of string
	const EXISTINGCOMMAND = 2;

	const HOSTNAME = 3;

	const PATH = 4;

	const NUMBER = 5;

	public static function usageName($type)
	{
		switch ($type)
		{
			case self::EXISTINGCOMMAND:
				return 'cmd';
			case self::HOSTNAME:
				return 'host';
			case self::PATH:
				return 'path';
			case self::NUMBER:
				return 'number';
		}

		return '?';
	}
}

/**
 * Option which require a single argument
 */
class ArgumentOptionInfo extends OptionInfo
{

	/**
	 *
	 * @var integer
	 */
	public $argumentType;

	/**
	 *
	 * @var mixed
	 */
	public $defaultValue;

	public function __construct($variableName = null, $names = null, $flags = 0)
	{
		parent::__construct($variableName, $names, $flags);
		$this->argumentType = ArgumentType::STRING;
		$this->defaultValue = null;
	}
}

/**
 * Option which require at least one argument
 */
class MultiArgumentOptionInfo extends OptionInfo
{

	/**
	 *
	 * @var integer
	 */
	public $argumentsType;

	/**
	 *
	 * @var integer
	 */
	public $minArgumentCount;

	/**
	 *
	 * @var integer
	 */
	public $maxArgumentCount;

	public function __construct($variableName = null, $names = null, $flags = 0)
	{
		parent::__construct($variableName, $names, $flags);
		$this->argumentsType = ArgumentType::STRING;
		$this->minArgumentCount = 1;
		$this->maxArgumentCount = 0;
	}
}

/**
 * Base class for GroupOptionInfo, SubcommandInfo and ProgramInfo
 */
class OptionContainerOptionInfo extends OptionInfo
{

	/**
	 *
	 * @var OptionInfo Option to select if none was set
	 */
	public $defaultOption;

	/**
	 *
	 * @param unknown $variableName
	 * @param number $flags
	 * @param OptionInfo $dflt
	 */
	public function __construct($variableName = null, $flags = 0, OptionInfo $dflt = null)
	{
		parent::__construct($variableName, null, $flags);
		$this->options = array();
		$this->defaultOption = $dflt;
	}

	/**
	 *
	 * @return array OptionInfo array
	 */
	function getOptions()
	{
		return $this->options;
	}

	/**
	 *
	 * @param OptionInfo $option
	 */
	public function appendOption(OptionInfo $option)
	{
		$this->options[] = $option;
		$option->parent = $this;
	}

	public function getOptionNameListString()
	{
		$names = array();
		foreach ($this->options as $option)
		{
			if ($option instanceof GroupOptionInfo)
			{
				$names[] = '(' . $option->getOptionNameListString() . ')';
			}
			else
			{
				$names[] = $option->getOptionNames()
					->getFirstOptionName(OptionName::LONG, false)
					->cliName();
			}
		}

		return Text::implode($names, ', ', ' or ');
	}

	public static function sortSwitchFirst($a, $b)
	{
		if ($a instanceof SwitchOptionInfo)
		{
			if ($b instanceof SwitchOptionInfo)
			{
				return self::sortRequiredOptionFirst($a, $b);
			}

			return -1;
		}

		return 1;
	}

	public static function sortRequiredOptionFirst($a, $b)
	{
		if ($a->optionFlags & ItemInfo::REQUIRED)
		{
			if ($b->optionFlags & ItemInfo::REQUIRED)
			{
				return 0;
			}

			return -1;
		}

		return 1;
	}

	protected function optionShortUsage($usage)
	{
		$text = '';

		// switch with short names, then others
		$list = $this->flattenOptionTree();
		usort($list, array(
			get_class(),
			'sortRequiredOptionFirst'
		));
		$groups = array(
			array(),
			array()
		);
		foreach ($list as $k => $option)
		{
			$firtShort = $option->getOptionNames()->getFirstOptionName(OptionName::SHORT, true);
			if ($firtShort && ($option instanceof SwitchOptionInfo))
			{
				$groups[0][] = $firtShort->name();
			}
			else
			{
				$first = $option->getOptionNames()->getFirstOptionName();
				if ($first)
				{
					$groups[1][] = array(
						'option' => $option,
						'name' => $first
					);
				}
			}
		}

		if (count($groups[0]))
		{
			natsort($groups[0]);
			$text .= '-' . implode('', $groups[0]);
		}

		foreach ($groups[1] as $other)
		{
			$option = $other['option'];
			$name = $other['name'];
			$required = ($option->optionFlags & ItemInfo::REQUIRED);
			$optionText = $name->cliName();

			if ($option instanceof ArgumentOptionInfo)
			{
				$optionText .= '=<' . ArgumentType::usageName($option->argumentType) . '>';
			}
			elseif ($option instanceof MultiArgumentOptionInfo)
			{
				$optionText .= '=<' . ArgumentType::usageName($option->argumentsType) . ' ...>';
			}

			if (!$required && strlen($optionText))
			{
				$optionText = '[' . $optionText . ']';
			}

			$text .= ((strlen($text) && strlen($optionText)) ? ' ' : '') . $optionText;
		}

		return $text;
	}

	protected function optionUsage($usage, $level = 0)
	{
		$text = '';
		$eol = $usage->textWrap->endOfLineString;

		foreach ($this->options as $o)
		{
			$optionTaxt = '';
			$subOptionsText = '';
			if (!($o instanceof GroupOptionInfo))
			{
				$names = array();

				foreach ($o->getOptionNames() as $k => $name)
				{
					$names[] = $name->cliName();
				}

				$optionTaxt = implode(', ', $names);
			}

			if (($usage->format & UsageFormat::ABSTRACT_TEXT) && strlen($o->abstract))
			{
				$optionTaxt .= (strlen($optionTaxt) ? ': ' : '') . $o->abstract;
			}

			if (($usage->format & UsageFormat::DETAILED_TEXT) && strlen($o->details))
			{
				$optionTaxt .= (strlen($optionTaxt) ? $eol : '') . $o->details;
			}

			if (!($o instanceof GroupOptionInfo))
			{
				foreach ($o->validators as $v)
				{
					$vtext = $v->usage($usage);
					if (strlen($vtext))
					{
						$optionTaxt .= (strlen($optionTaxt) ? $eol : '') . $vtext;
					}
				}
			}

			if (strlen($optionTaxt))
			{
				$text .= $usage->textWrap->wrap($optionTaxt, TextWrap::OFFSET_OTHER, $level) . $eol;
			}

			if ($o instanceof GroupOptionInfo)
			{
				$subOptionsText = $o->optionUsage($usage, $level + 1);
				if (strlen($subOptionsText))
				{
					$text .= $subOptionsText;
				}
			}
		}

		return $text;
	}

	protected function flattenOptionTree()
	{
		$list = array();
		foreach ($this->options as $o)
		{
			if ($o instanceof GroupOptionInfo)
			{
				$list = array_merge($list, $o->flattenOptionTree());
			}
			else
			{
				$list[] = $o;
			}
		}

		return $list;
	}

	protected $options;
}

/**
 * Option group
 */
class GroupOptionInfo extends OptionContainerOptionInfo
{

	/**
	 * Regular group type
	 *
	 * @var integer
	 */
	const TYPE_NORMAL = 0;

	/**
	 * Mutually exclusive option group
	 *
	 * @var integer
	 */
	const TYPE_EXCLUSIVE = 1;

	/**
	 *
	 * @var integer
	 */
	public $groupType;

	public function __construct($variableName = null, $groupType = self::TYPE_NORMAL, $flags = 0)
	{
		parent::__construct($variableName, $flags);
		$this->options = array();
		$this->groupType = $groupType;
	}
}

/**
 * Non option argument value
 */
class PositionalArgumentInfo extends ItemInfo
{

	/**
	 *
	 * @var integer
	 */
	public $positionalArgumentFlags;

	/**
	 *
	 * @var integer
	 */
	public $argumentType;

	/**
	 *
	 * @var integer
	 */
	public $maxArgumentCount;

	/**
	 *
	 * @var array of validators
	 */
	public $validators;

	public function __construct($max = 1, $type = ArgumentType::STRING, $flags = 0)
	{
		parent::__construct();
		$this->positionalArgumentFlags = $flags;
		$this->argumentType = ArgumentType::STRING;
		$this->maxArgumentCount = $max;
		$this->validators = array();
	}
}

/**
 * Base class for SubcommandInfo and ProgramInfo
 *
 * Contains options and positional argument definitions
 */
class RootItemInfo extends OptionContainerOptionInfo
{

	public function __construct()
	{
		parent::__construct();
		$this->positionalArguments = array();
	}

	public function getPositionalArgument($index)
	{
		return $this->positionalArguments[$index];
	}

	public function getPositionalArguments()
	{
		return $this->positionalArguments;
	}

	public function &appendPositionalArgument(PositionalArgumentInfo $paInfo)
	{
		$this->positionalArguments[] = $paInfo;
		return $paInfo;
	}

	public function positionalArgumentsUsage($usage, $level = 0)
	{
		$number = 1;
		$eol = $usage->textWrap->endOfLineString;
		$text = '';
		foreach ($this->positionalArguments as $pa)
		{
			$s = '#' . $number . '.';
			if ($pa->abstract)
			{
				$s .= ' ' . $pa->abstract;
			}

			foreach ($pa->validators as $v)
			{
				$vtext = $v->usage($usage);
				if (strlen($vtext))
				{
					$s .= $eol . $vtext . $eol;
				}
			}

			$text .= $usage->textWrap->wrap($s, TextWrap::OFFSET_OTHER, $level) . $eol;
			$number++;
		}

		return $text;
	}

	/**
	 *
	 * @var array of PositionalArgumentInfo
	 */
	protected $positionalArguments;
}

/**
 * Subcommand definition
 */
class SubcommandInfo extends RootItemInfo
{

	/**
	 *
	 * @var string
	 */
	public $name;

	/**
	 *
	 * @var array of string
	 */
	public $aliases;

	public function __construct($name, $aliases = array())
	{
		parent::__construct();
		$this->name = $name;
		$this->aliases = $aliases;
	}

	/**
	 *
	 * @return array of names (name + aliases)
	 */
	public function getNames()
	{
		$n = array(
			$this->name
		);
		return array_merge($n, $this->aliases);
	}
}

/**
 * Program definition
 */
class ProgramInfo extends RootItemInfo
{

	/**
	 *
	 * @var string Program name
	 */
	public $name;

	/**
	 *
	 * @var array of SubcommandInfo
	 */
	public $subcommands;

	public function __construct($name, $subcommands = array())
	{
		parent::__construct();
		$this->name = $name;
		$this->subcommands = $subcommands;
	}

	public function usage($usage = null, $subcommandName = null)
	{
		$text = 'Usage: ' . $this->name;

		$usage = ($usage) ? $usage : new UsageFormat();
		$eol = $usage->textWrap->endOfLineString;

		$subcommand = (is_string($subcommandName) ? $this->findSubcommand($subcommandName) : null);
		$root = ($subcommand) ? $subcommand : $this;

		if ($subcommand)
		{
			$text .= ' ' . $subcommand->name;
		}
		elseif (count($this->subcommands))
		{
			$text .= ' [subcommand]';
		}

		$text .= ' ' . $root->optionShortUsage($usage);
		foreach ($root->getPositionalArguments() as $pa)
		{
			$abstract = $pa->abstract ? $pa->abstract : '';
			$e = explode(PHP_EOL, $abstract);
			$abstract = $e[0];
			if ($pa->maxArgumentCount > 1)
			{
				$abstract .= ' ...';
			}
			$text .= ' [' . $abstract . ']';
		}

		$text = $usage->textWrap->wrap($text, TextWrap::OFFSET_OTHER, 0) . $eol;

		if (($usage->format & UsageFormat::ABSTRACT_TEXT) == UsageFormat::ABSTRACT_TEXT)
		{
			if ($root->abstract)
			{
				$text .= $eol . $usage->textWrap->wrap($root->abstract, 1) . $eol;
			}

			$optionsLevel = 1;
			$programOptionCount = count($this->options);
			$subcommandOptionCount = 0;
			if ($subcommand)
			{
				$subcommandOptionCount = count($subcommand->options);
			}

			if ($subcommand)
			{
				if ($subcommandOptionCount)
				{
					$optionsLevel = 2;
					$text .= $eol . $subcommand->optionUsage($usage, $optionsLevel) . $eol;
					if ($programOptionCount)
					{
						$text .= $eol .
							$usage->textWrap->wrap('Program options', 0, $optionsLevel - 1) . $eol;
					}
				}
			}
			elseif (count($this->subcommands))
			{
				$optionsLevel = 2;
				$text .= $eol . $usage->textWrap->wrap('Subcommands', 0, $optionsLevel - 1) . $eol .
					$eol;
				foreach ($this->subcommands as $subcommand)
				{
					$subcommandText = $subcommand->name;
					foreach ($subcommand->aliases as $alias)
					{
						$subcommandText .= ', ' . $alias;
					}

					if ($subcommand->abstract)
					{
						$subcommandText .= ': ' . $subcommand->abstract;
					}

					if (($usage->format & UsageFormat::DETAILED_TEXT) == UsageFormat::DETAILED_TEXT)
					{
						if ($subcommand->details)
						{
							$subcommandText .= $eol . $subcommand->details;
						}
					}

					$text .= $usage->textWrap->wrap($subcommandText, TextWrap::OFFSET_OTHER,
						$optionsLevel) . $eol;
				}

				if ($programOptionCount)
				{
					$text .= $eol . $usage->textWrap->wrap('Program options', 0, $optionsLevel - 1) .
						$eol;
				}
			}

			if ($programOptionCount)
			{
				$text .= $eol . $this->optionUsage($usage, $optionsLevel);
			}

			if (count($root->getPositionalArguments()))
			{
				$text .= $eol . $usage->textWrap->wrap('Positional arguments', 0, 1) . $eol . $eol;
				$text .= $root->positionalArgumentsUsage($usage, 2);
			}
		}

		if (($usage->format & UsageFormat::DETAILED_TEXT) == UsageFormat::DETAILED_TEXT)
		{
			if ($root->details)
			{
				$text .= $eol . $usage->textWrap->wrap($root->details, 0, 1) . $eol;
			}
		}

		return $text . $eol;
	}

	public function &appendSubcommand(SubcommandInfo $sc)
	{
		$this->subcommands[] = $sc;
		return $sc;
	}

	public function findSubcommand($name)
	{
		foreach ($this->subcommands as $s)
		{
			if ($s->name == $name)
			{
				return $s;
			}

			foreach ($s->aliases as $a)
			{
				if ($a == $name)
				{
					return $s;
				}
			}
		}

		return null;
	}

	/**
	 *
	 * @param string $xmlProgramDefinition
	 * @return boolean
	 */
	public function loadXmlDefinition($xmlProgramDefinition)
	{
		/**
		 *
		 * @todo
		 *
		 *
		 *
		 *
		 *
		 */
		return true;
	}
}

/**
 * Base class for all *Result classes
 */
interface ItemResult
{
}

/**
 * Option result
 */
abstract class OptionResult implements itemResult
{

	/**
	 * Indicates if the option is present on the command line.
	 * The type of isSet member is
	 * - integer for GroupOptionResult
	 * - boolean for all others
	 *
	 * @var mixed
	 */
	public $isSet;

	public function __construct()
	{
		$this->isSet = false;
	}

	/**
	 * Return the value of the option
	 */
	public function __invoke()
	{
		if (func_num_args() > 0)
			return call_user_func_array(array(
				$this,
				'value'
			), func_get_args());
		return $this->value();
	}

	// / Option-dependant result
	/**
	 *
	 * @return mixed
	 */
	abstract public function value();
}

/**
 * Switch option result
 */
class SwitchOptionResult extends OptionResult
{

	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Indicates if the option was set
	 *
	 * @return boolean
	 */
	public function value()
	{
		return $this->isSet;
	}
}

/**
 * Single argument option result
 */
class ArgumentOptionResult extends OptionResult
{

	/**
	 *
	 * @var mixed Option argument value
	 */
	public $argument;

	public function __construct()
	{
		parent::__construct();
		$this->argument = null;
	}

	/**
	 * Return argument value
	 *
	 * @return string Argument vaiue if option is set. Otherwise, an empty string
	 *         @note __toString() is not equivalent to value() which will return null if the option is not set.
	 */
	public function __toString()
	{
		return $this->isSet ? $this->argument : '';
	}

	/**
	 *
	 * @return mixed Option argument if set, otherwise null
	 */
	public function value()
	{
		return $this->isSet ? $this->argument : null;
	}
}

/**
 * Multi argument option result
 */
class MultiArgumentOptionResult extends OptionResult implements \Countable
{

	/**
	 *
	 * @var array Option arguments
	 */
	public $arguments;

	public function __construct()
	{
		parent::__construct();
		$this->arguments = array();
	}

	public function count()
	{
		return $this->isSet ? count($this->arguments) : 0;
	}

	/**
	 *
	 * @para integer|array|null
	 * @return mixed A single argument or array of arguments
	 */
	public function value()
	{
		$c = func_num_args();

		if ($c == 1)
		{
			$a = func_get_arg(0);
			if (\is_array($a))
			{
				return $this->multiArgumentValue($a);
			}
		}
		elseif ($c > 1)
		{
			return $this->multiArgumentValue(func_get_args());
		}

		return $this->multiArgumentValue(null);
	}

	/**
	 *
	 * @param integer|array $subset
	 *        	Argument index or array of argument indexes
	 * @throws \BadMethodCallException
	 * @return array|array|NULL|NULL[]
	 */
	private function multiArgumentValue($subset)
	{
		if (!$this->isSet)
		{
			return array();
		}

		if (\is_null($subset))
		{
			return $this->arguments;
		}
		elseif (is_integer($subset))
		{
			if (\array_key_exists($subset, $this->arguments))
			{
				return $this->arguments[$subset];
			}

			return null;
		}
		elseif (\is_array($subset))
		{
			$partial = array();
			foreach ($subset as $k)
			{
				if (!\is_integer($k))
					continue;

				$partial[$k] = array_key_exists($k, $this->arguments) ? $this->arguments[$k] : null;
			}

			return $partial;
		}
		else
		{
			$t = (is_object($subset) ? get_class($subset) : gettype($subset));
			throw new \BadMethodCallException(
				__METHOD__ . ': Invalid argument type ' . $t . ', null, integer or array expected');
		}
	}
}

/**
 * isSet represents the number of sub options represented on
 * the command line
 */
class GroupOptionResult extends OptionResult
{

	/**
	 * Reference to the OptionResult of the selectedOption
	 *
	 * @note For exclusive group option only
	 * @var OptionResult
	 */
	public $selectedOption;

	/**
	 * Variable name of the selected option
	 *
	 * @note For exclusive group option only
	 * @var string
	 */
	public $selectedOptionName;

	public function __construct()
	{
		parent::__construct();
		$this->selectedOption = null;
		$this->selectedOptionName = null;
	}

	/**
	 *
	 * @return string variable name of the selected option if set, otherwise an empty string.
	 *         If the option is not an exclusive option group, this value has no meaning
	 *         @note __toString() is not equivalent to value() which will return null if the option is not set.
	 */
	public function __toString()
	{
		return ($this->isSet) ? $this->selectedOptionName : '';
	}

	/**
	 *
	 * @return string variable name of the selected option if set, otherwise null.
	 *         If the option is not an exclusive option group, this value has no meaning
	 */
	public function value()
	{
		return ($this->isSet) ? $this->selectedOptionName : null;
	}
}

/**
 * Parser message
 */
class Message
{

	/**
	 * Debug message
	 */
	const DEBUG = 0;

	/**
	 * A warning is raised when something is ambiguous or ignored
	 * by the parser
	 */
	const WARNING = 1;

	/**
	 * An error is raised when a command line argument does not validate the program
	 * interface definition rules.
	 */
	const ERROR = 2;

	/**
	 * A fatal error is raised when a command line argument error leads to an unresumable
	 * state.
	 * Parsing stops immediately after a fatal error
	 */
	const FATALERROR = 3;

	/**
	 * Message type
	 */
	public $type;

	/**
	 * Message code
	 *
	 * @var integer
	 */
	public $code;

	/**
	 * Message string
	 */
	public $message;

	public function __construct($type, $code, $message)
	{
		$this->type = $type;
		$this->code = $code;
		$this->message = $message;
	}

	public function __toString()
	{
		return $this->message;
	}

	const FATALERROR_UNKNOWN_OPTION = 'Unknown option %s';

	/* 1 */
	const ERROR_INVALID_OPTION_VALUE = 'Invalid value for option %s. %s';

	/* 2 */
	const ERROR_INVALID_POSARG_VALUE = 'Invalid value for positional argument %d. %s';

	/* 3 */
	const ERROR_MISSING_ARG = 'Missing argument for option %s';

	/* 4 */
	const ERROR_REQUIRED_OPTION = 'Missing required option %s';

	/* 5 */
	const ERROR_REQUIRED_GROUP = 'At least one of the following options have to be set: %s';

	/* 6 */
	const ERROR_REQUIRED_XGROUP = 'One of the following options have to be set: %s';

	/* 7 */
	const ERROR_REQUIRED_POSARG = 'Required positional argument %d is missing';

	/* 8 */
	const ERROR_PROGRAM_POSARG = 'Program does not accept positional arguments';

	/* 9 */
	const ERROR_SUBCMD_POSARG = 'Subcommand %s does not accept positional arguments';

	/* 10 */
	const ERROR_TOOMANY_POSARG = 'Too many positional arguments';

	/* 11 */
	const ERROR_MISSING_MARG = 'At least %d argument(s) required for %s option, got %d';

	/* 12 */
	const ERROR_UNEXPECTED_OPTION = 'Unexpected option %s';

	/* 13 */
	const ERROR_SWITCH_ARG = 'Option %s does not allow an argument';

	const WARNING_IGNORE_EOA = 'Ignore end-of-argument marker';
}

/**
 * Base class for SubcommandResult and ProgramResult
 */
class RootItemResult implements ItemResult, \ArrayAccess
{

	public function __construct()
	{
		$this->options = array();
	}

	/**
	 *
	 * @param key $variableName
	 * @throws \InvalidArgumentException
	 * @return OptionResult if found
	 */
	public function __get($variableName)
	{
		if (array_key_exists($variableName, $this->options))
		{
			return $this->options[$variableName];
		}

		throw new \InvalidArgumentException('Invalid option key \'' . $variableName . '\'');
	}

	/**
	 *
	 * @param string $variableName
	 * @param OptionResult $result
	 * @throws \InvalidArgumentException
	 */
	public function __set($variableName, $result)
	{
		if ($this->offsetExists($variableName))
		{
			throw new \InvalidArgumentException($variableName);
		}

		if (!(is_object($result) && ($result instanceof OptionResult)))
		{
			throw new \InvalidArgumentException($variableName);
		}

		$this->options[$variableName] = $result;
	}

	/**
	 * If an option bound variable name corresponding
	 * to @param variableName, the value of the option is returned
	 *
	 * @param string $variableName
	 * @param array $args
	 */
	public function __call($variableName, $args = array())
	{
		if (array_key_exists($variableName, $this->options))
		{
			return call_user_func_array(array(
				$this->options[$variableName],
				'value'
			), $args);
		}

		throw new \InvalidArgumentException('Invalid option key \'' . $variableName . '\'');
	}

	/**
	 *
	 * @param string $variableName
	 * @param OptionResult $result
	 * @throws \InvalidArgumentException
	 */
	public function offsetSet($variableName, $result)
	{
		if ($this->offsetExists($variableName))
		{
			throw new \InvalidArgumentException($variableName . ' already exists');
		}

		if (!(is_object($result) && ($result instanceof OptionResult)))
		{
			throw new \InvalidArgumentException($variableName);
		}

		$this->options[$variableName] = $result;
	}

	/**
	 * N/A
	 */
	public function offsetUnset($variableName)
	{}

	/**
	 * Indicate if an option exists with the given bound variable name
	 *
	 * @param string $variableName
	 */
	public function offsetExists($variableName)
	{
		return array_key_exists($variableName, $this->options);
	}

	/**
	 *
	 * @param key $variableName
	 * @throws \InvalidArgumentException
	 * @return OptionResult if found
	 */
	public function offsetGet($variableName)
	{
		if (array_key_exists($variableName, $this->options))
		{
			return $this->options[$variableName];
		}

		return null;
	}

	/**
	 *
	 * @return \ArrayIterator
	 */
	public function getOptionIterator()
	{
		return new \ArrayIterator($this->options);
	}

	/**
	 *
	 * @var array of OptionResult
	 */
	private $options;
}

class SubcommandResult extends RootItemResult
{
}

/**
 * Command line parsing program result
 *
 * \Iterator interface allow use of foreach
 * to retrieve positional arguments
 */
class ProgramResult extends RootItemResult implements \Iterator
{

	/**
	 *
	 * @var string Selected sub command name
	 */
	public $subcommandName;

	/**
	 *
	 * @var SubcommandResult Selected sub command result
	 */
	public $subcommand;

	public function __construct()
	{
		parent::__construct();
		$this->messages = array();
		$this->subcommandName = null;
		$this->subcommands = array();
		$this->values = array();
		$this->valueIterator = 0;
	}

	/**
	 * Indicates if the command line argument parsing completes successfully (without any errors)
	 *
	 * @return boolean
	 * @return boolean
	 */
	public function __invoke()
	{
		return self::success($this);
	}

	/**
	 * Indicates if the command line argument parsing completes successfully (without any errors)
	 *
	 * @param ProgramResult $result
	 * @return boolean
	 */
	public static function success(ProgramResult $result)
	{
		$errors = $result->getMessages(Message::ERROR, Message::FATALERROR);
		return (count($errors) == 0);
	}

	/**
	 *
	 * @return integer Number of positional argument set
	 */
	public function valueCount()
	{
		return count($this->values);
	}

	/**
	 * Get parser result messages
	 *
	 * @param integer $minLevel
	 * @param integer $maxLevel
	 */
	public function getMessages($minLevel = Message::WARNING, $maxLevel = Message::FATALERROR)
	{
		$messages = array();
		foreach ($this->messages as $k => $m)
		{
			if ($m->type < $minLevel)
			{
				continue;
			}

			if ($m->type > $maxLevel)
			{
				continue;
			}

			$messages[] = $m;
		}

		return $messages;
	}

	/**
	 *
	 * @param integer $item
	 *        	Positional argument index
	 * @param mixed $result
	 *        	Positional argument value
	 */
	public function offsetSet($item, $result)
	{
		if (is_integer($item))
		{
			if (($item >= 0) && !array_key_exists($item, $this->values))
			{
				$this->values[$item] = $result;
			}

			return;
		}

		return parent::offsetSet($item, $result);
	}

	/**
	 * Search positional argument at @param $item index or
	 * OptionResult with @param $item as bound variable name
	 *
	 * @param mixed $item
	 * @return boolean
	 */
	public function offsetExists($item)
	{
		if (is_integer($item))
		{
			return (($item >= 0) && array_key_exists($item, $this->values));
		}

		return parent::offsetExists($item);
	}

	/**
	 * Search positional argument at @param $item index or
	 * OptionResult with @param $item as bound variable name
	 *
	 * @param mixed $item
	 * @return ItemResult
	 */
	public function offsetGet($item)
	{
		if (is_integer($item))
		{
			if (($item >= 0) && array_key_exists($item, $this->values))
			{
				return $this->values[$item];
			}

			return null;
		}

		return parent::offsetGet($item);
	}

	/**
	 * Current positional argument index
	 */
	public function key()
	{
		return $this->valueIterator;
	}

	/**
	 * Current positional argument value
	 */
	public function current()
	{
		return $this->valid() ? $this->values[$this->valueIterator] : null;
	}

	/**
	 * Move to next positional argument
	 */
	public function next()
	{
		$this->valueIterator++;
	}

	/**
	 * true if a positional argument is set at the given index
	 */
	public function valid()
	{
		return array_key_exists($this->valueIterator, $this->values);
	}

	/**
	 * Rewind positional argument iterator
	 */
	public function rewind()
	{
		return $this->valueIterator = 0;
	}

	/**
	 * Internal use
	 */
	public function setActiveSubcommand($name)
	{
		$this->subcommandName = $name;
		$this->subcommand = $this->subcommands[$name];
	}

	/**
	 * Internal use
	 */
	public function addSubcommandResult($name, SubcommandResult $result)
	{
		$this->subcommands[$name] = $result;
		return $result;
	}

	/**
	 * Add a parser pessage.
	 *
	 * @note This method should anly be called by the Parser
	 */
	public function appendMessage()
	/**
	 * $type, $code, $format , .
	 *
	 *
	 *
	 * ..
	 */
	{
		$args = func_get_args();
		$type = array_shift($args);
		$code = intval(array_shift($args));

		$this->messages[] = new Message($type, $code, call_user_func_array('sprintf', $args));
	}

	/**
	 * Add a positinal argument value
	 *
	 * @note This method should anly be called by the Parser
	 */
	public function appendValue($value)
	{
		$this->values[] = $value;
	}

	/**
	 *
	 * @var array of values
	 */
	private $values;

	/**
	 *
	 * @var array
	 */
	private $messages;

	/**
	 *
	 * @var integer
	 */
	private $valueIterator;

	/**
	 *
	 * @var array Program SubcommandResults
	 */
	private $subcommands;
}

/**
 * Internal ParserState structure.
 *
 * Bind an option name with the OptionInfo and OptionResult
 */
class OptionNameBinding
{

	/**
	 *
	 * @var OptionName
	 */
	public $name;

	/**
	 *
	 * @var OptionInfo
	 */
	public $info;

	/**
	 *
	 * @var OptionResult
	 */
	public $result;

	/**
	 * Array of the ancestors results from parent to first group
	 *
	 * @var array
	 */
	public $parentResults;

	public function __construct(OptionName $n, OptionInfo $i, $parentResults = array())
	{
		$this->name = $n;
		$this->info = $i;
		$this->result = null;
		$this->parentResult = $parentResults;
	}
}

/**
 * Parsing state
 *
 * Internal structure of the Parser class
 */
class ParserState
{

	const ENDOFOPTIONS = 0x1;

	const UNEXPECTEDOPTION = 0x2;

	const ABORT = 0x4;

	/**
	 *
	 * @var integer
	 */
	public $stateFlags;

	/**
	 *
	 * @var array
	 */
	public $argv;

	/**
	 *
	 * @var integer
	 */
	public $argIndex;

	/**
	 * - First element: Program option name bindings
	 * - Others: Subcommand option name bindings
	 *
	 * @var array
	 */
	public $optionNameBindings;

	public $optionGroupBindings;

	public $subcommandNameBindings;

	public $activeSubcommandIndex;

	/**
	 *
	 * @var unknown_type
	 */
	public $activeOption;

	public $activeOptionArguments;

	public $values;

	public function __construct(ProgramInfo $programInfo)
	{
		$this->optionNameBindings = array();
		$this->optionGroupBindings = array();
		$this->subcommandNameBindings = array();
		$this->activeOption = null;
		$this->activeOptionArguments = array();
		$this->values = array();
		$this->anonymousOptionResults = array();

		$this->optionNameBindings[0] = array();
		$this->optionGroupBindings[0] = array();
		$n = null;
		foreach ($programInfo->getOptions() as $o)
		{
			$this->initializeStateData($n, $o, 0);
		}

		$scIndex = 1;
		foreach ($programInfo->subcommands as $s)
		{
			$this->optionNameBindings[$scIndex] = array();
			$this->optionGroupBindings[$scIndex] = array();
			foreach ($s->getOptions() as $o)
			{
				$this->initializeStateData($n, $o, $scIndex);
			}

			$this->subcommandNameBindings[$s->name] = array(
				'subcommandIndex' => $scIndex,
				'subcommand' => $s
			);

			$scIndex++;
		}
	}

	/**
	 * Reset state and create a new ProgramResult
	 *
	 * @param ProgramInfo $programInfo
	 * @param unknown_type $argv
	 * @param unknown_type $startIndex
	 */
	public function prepareState(ProgramInfo $programInfo, $argv, $startIndex)
	{
		$this->stateFlags = 0;
		$this->argv = $argv;
		$this->argIndex = $startIndex;
		$this->activeOption = null;
		$this->activeOptionArguments = array();
		$this->values = array();
		$this->anonymousOptionResults = array();

		$result = new ProgramResult();
		foreach ($programInfo->getOptions() as $o)
		{
			$this->initializeStateData($result, $o, 0);
		}

		$scIndex = 1;
		foreach ($programInfo->subcommands as $s)
		{
			$scr = $result->addSubcommandResult($s->name, new SubcommandResult());
			foreach ($s->getOptions() as $o)
			{
				$this->initializeStateData($scr, $o, $scIndex);
			}

			$scIndex++;
		}

		return $result;
	}

	private function createResult(RootItemResult $rootItemResult, OptionInfo $option)
	{
		$resultClassName = preg_replace(',(.+?)Info,', '\\1Result', get_class($option));
		$result = new $resultClassName();

		if (is_string($option->variableName) && strlen($option->variableName))
		{
			$rootItemResult[$option->variableName] = $result;
		}
		else
		{
			$this->anonymousOptionResults[] = $result;
		}

		return $result;
	}

	private function initializeStateData($rootItemResult, $option, $groupIndex,
		$resultTree = array())
	{
		$result = null;

		if ($rootItemResult)
		{
			$result = $this->createResult($rootItemResult, $option);
		}

		$names = $option->getOptionNames();
		foreach ($names as $n)
		{
			if (!$rootItemResult)
			{
				$this->optionNameBindings[$groupIndex][$n->name()] = new OptionNameBinding($n,
					$option);
			}
			else // just (re)bind result
			{
				$this->optionNameBindings[$groupIndex][$n->name()]->result = $result;
				$this->optionNameBindings[$groupIndex][$n->name()]->parentResults = $resultTree;
			}
		}

		if ($option instanceof GroupOptionInfo)
		{
			// Add a dummy name
			$key = $option->getKey();
			if (!$rootItemResult)
			{
				$n = new OptionName('');
				$this->optionGroupBindings[$groupIndex][$key] = new OptionNameBinding($n, $option);
			}
			else // just (re)bind result
			{
				$this->optionGroupBindings[$groupIndex][$key]->result = $result;
				$this->optionGroupBindings[$groupIndex][$key]->parentResults = $resultTree;
			}

			foreach ($option->getOptions() as $suboption)
			{
				$parentResults = array();
				if ($rootItemResult)
				{
					$parentResults = array_merge(array(
						$result
					), $resultTree);
				}

				$this->initializeStateData($rootItemResult, $suboption, $groupIndex, $parentResults);
			}
		}
	}

	private $anonymousOptionResults;
}

/**
 * Command line parser
 */
class Parser
{

	/**
	 *
	 * @param ProgramInfo $programInfo
	 *        	Program interface definition
	 */
	public function __construct(ProgramInfo $programInfo)
	{
		$this->programInfo = $programInfo;
		$this->state = new ParserState($this->programInfo);
	}

	/**
	 * Parse command line arguments
	 *
	 * @param array $argv
	 *        	List of arguments
	 * @param integer $startIndex
	 *        	First argument to consider in $argv
	 */
	public function parse($argv = array(), $startIndex = 1)
	{
		$s = $this->state;
		$result = $s->prepareState($this->programInfo, $argv, $startIndex);

		$argc = count($argv);

		while ($s->argIndex < $argc)
		{
			$arg = $argv[$s->argIndex];

			if ($s->activeOption)
			{
				if (!$this->activeOptionAcceptsArguments())
				{
					$this->unsetActiveOption($result);
				}
			}

			if ($s->stateFlags & ParserState::ENDOFOPTIONS)
			{
				$this->processPositionalArgument($result, $arg);
			}
			elseif ($arg == '--')
			{
				$s->stateFlags |= ParserState::ENDOFOPTIONS;
				$this->unsetActiveOption($result);
			}
			elseif ($arg == '-')
			{
				if ($s->activeOption)
				{
					if ($s->activeOption->info instanceof MultiArgumentOptionInfo)
					{
						if (count($s->activeOptionArguments) == 0)
						{
							$result->appendMessage(Message::WARNING, 2, Message::WARNING_IGNORE_EOA);
							$s->activeOptionArguments[] = $arg;
						}
						else
						{
							$this->unsetActiveOption($result);
						}
					}
					elseif ($s->activeOption->info instanceof ArgumentOptionInfo)
					{
						$s->activeOptionArguments[] = $arg;
					}
				}
				else
				{
					$this->processPositionalArgument($result, $arg);
				}
			}
			elseif (substr($arg, 0, 2) == '\\-')
			{
				$arg = substr($arg, 1);
				if ($s->activeOption)
				{
					$s->activeOptionArguments[] = $arg;
				}
				else
				{
					$this->processPositionalArgument($result, $arg);
				}
			}
			elseif ($s->activeOption && (count($s->activeOptionArguments) == 0))
			{
				$s->activeOptionArguments[] = $arg;
			}
			elseif (substr($arg, 0, 2) == '--')
			{
				if ($s->activeOption)
				{
					$this->unsetActiveOption($result);
				}

				$matches = array();
				$cliName = $arg;
				$name = substr($arg, 2);
				$tail = '';
				$hasTail = (preg_match('/(.+?)=(.*)/', $name, $matches) == 1);
				if ($hasTail)
				{
					$name = $matches[1];
					$cliName = '--' . $name;
					$tail = $matches[2];
				}

				$s->activeOption = $this->findOptionByName($name);

				if ($s->activeOption)
				{
					if (!$this->optionExpected($s->activeOption))
					{
						$s->stateFlags |= ParserState::UNEXPECTEDOPTION;
					}

					if ($hasTail)
					{
						$s->activeOptionArguments[] = $tail;
					}
				}
				else
				{
					$result->appendMessage(Message::FATALERROR, 1,
						Message::FATALERROR_UNKNOWN_OPTION, $cliName);
					$s->stateFlags |= ParserState::ABORT;
					break;
				}
			}
			elseif (substr($arg, 0, 1) == '-')
			{
				$arg = substr($arg, 1);
				while (strlen($arg) > 0)
				{
					if ($s->activeOption)
					{
						$this->unsetActiveOption($result);
					}

					$name = substr($arg, 0, 1);
					$cliName = '-' . $name;
					$arg = substr($arg, 1);

					$s->activeOption = $this->findOptionByName($name);

					if ($s->activeOption)
					{
						$ao = $s->activeOption;
						if (!$this->optionExpected($ao))
						{
							$s->stateFlags |= ParserState::UNEXPECTEDOPTION;
						}

						if (($ao->info instanceof ArgumentOptionInfo) ||
							($ao->info instanceof MultiArgumentOptionInfo))
						{
							if (strlen($arg) > 0)
							{
								$s->activeOptionArguments[] = $arg;
								break;
							}
						}
					}
					else
					{
						$result->appendMessage(Message::FATALERROR, 1,
							Message::FATALERROR_UNKNOWN_OPTION, $cliName);
						$s->stateFlags |= ParserState::ABORT;
						break;
					}
				}
			}
			elseif ($s->activeOption)
			{
				$s->activeOptionArguments[] = $arg;
			}
			else
			{
				$this->processPositionalArgument($result, $arg);
			}

			if ($s->stateFlags & ParserState::ABORT)
			{
				break;
			}

			$s->argIndex++;
		}

		$this->unsetActiveOption($result);

		$changeCount = 0;
		do
		{
			$changeCount = $this->postProcessOptions($result);
		}
		while ($changeCount > 0);

		foreach ($s->optionGroupBindings as $g => $bindings)
		{
			if (($g > 0) && ($g != $s->activeSubcommandIndex))
			{
				continue;
			}

			$binding = null;

			foreach ($bindings as $n => $binding)
			{
				if (!$binding->result->isSet && $this->optionRequired($binding))
				{
					if ($binding->info->defaultOption instanceof OptionInfo)
					{
						$defaultOptionBinding = $this->findOption('info',
							$binding->info->defaultOption);

						if ($this->selectOption($result, $defaultOptionBinding))
							continue;
					}

					$nameList = $binding->info->getOptionNameListString();

					if ($binding->info->groupType == GroupOptionInfo::TYPE_EXCLUSIVE)
					{
						$result->appendMessage(Message::ERROR, 6, Message::ERROR_REQUIRED_XGROUP,
							$nameList);
					}
					else
					{
						$result->appendMessage(Message::ERROR, 5, Message::ERROR_REQUIRED_GROUP,
							$nameList);
					}
				}
			}
		}

		foreach ($s->optionNameBindings as $g => $bindings)
		{
			if (($g > 0) && ($g != $s->activeSubcommandIndex))
			{
				continue;
			}

			$binding = null;

			foreach ($bindings as $n => $b)
			{
				if ($binding && ($b->info == $binding->info))
				{
					continue;
				}

				$binding = $b;

				if (!($binding->result->isSet) && $this->optionRequired($binding))
				{
					$result->appendMessage(Message::ERROR, 4, Message::ERROR_REQUIRED_OPTION,
						$binding->name->cliName());
				}
			}
		}

		$this->postProcessPositionalArguments($result);

		return $result;
	}

	private function activeOptionAcceptsArguments()
	{
		$s = $this->state;
		$ao = $s->activeOption;
		$i = $ao->info;

		if ($i instanceof MultiArgumentOptionInfo)
		{
			if ($i->maxArgumentCount > 0)
			{
				return ((count($s->activeOptionArguments) + count($ao->result->arguments)) <
					$ao->info->maxArgumentCount);
			}

			return true;
		}
		elseif ($i instanceof ArgumentOptionInfo)
		{
			return (count($s->activeOptionArguments) == 0);
		}

		return false;
	}

	private function unsetActiveOption(ProgramResult $result)
	{
		$markSet = false;
		$s = $this->state;
		$ao = $s->activeOption;
		if (!$ao)
		{
			return;
		}

		if ($s->stateFlags & ParserState::UNEXPECTEDOPTION)
		{
			$result->appendMessage(Message::ERROR, 12, Message::ERROR_UNEXPECTED_OPTION,
				$s->activeOption->name->cliName());
		}

		if ($ao->info instanceof SwitchOptionInfo)
		{
			$markSet = true;
			if (count($s->activeOptionArguments) > 0)
			{
				if ((count($s->activeOptionArguments) > 1) ||
					(strlen($s->activeOptionArguments[0]) > 0))
				{
					$markSet = false;
					$result->appendMessage(Message::ERROR, 13, Message::ERROR_SWITCH_ARG,
						$s->activeOption->name->cliName());
				}
			}
		}
		elseif ($ao->info instanceof ArgumentOptionInfo)
		{
			if (count($s->activeOptionArguments) > 0)
			{
				$value = $s->activeOptionArguments[0];
				if (!($s->stateFlags & ParserState::UNEXPECTEDOPTION) &&
					$this->validateOptionArgument($result, $s->activeOption, $value))
				{
					$markSet = true;
					$ao->result->argument = $value;
				}
				else
				{
					$ao->result->argument = null;
				}
			}
			else
			{
				$result->appendMessage(Message::ERROR, 3, Message::ERROR_MISSING_ARG,
					$s->activeOption->name->cliName());
			}
		}
		elseif ($ao->info instanceof MultiArgumentOptionInfo)
		{
			if (count($s->activeOptionArguments) > 0)
			{
				foreach ($s->activeOptionArguments as $i => $value)
				{
					if (!($s->stateFlags & ParserState::UNEXPECTEDOPTION) &&
						$this->validateOptionArgument($result, $s->activeOption, $value))
					{
						$markSet = true;
						$ao->result->arguments[] = $value;
					}
					else
					{
						/*
						 * Temporary add a dummy arg
						 */
						$ao->result->arguments[] = null;
					}
				}
			}
			else
			{
				$result->appendMessage(Message::ERROR, 3, Message::ERROR_MISSING_ARG,
					$s->activeOption->name->cliName());
			}
		}

		if (!($s->stateFlags & ParserState::UNEXPECTEDOPTION) && $markSet)
		{
			$this->markOption($result, $ao, true);
		}

		$s->activeOptionArguments = array();
		$s->activeOption = null;
		$s->stateFlags &= ~ParserState::UNEXPECTEDOPTION;
	}

	private function markOption(ProgramResult $result, OptionNameBinding $binding, $value)
	{
		$binding->result->isSet = $value;

		// Update option tree
		$previousResult = $binding->result;
		$childInfo = $binding->info;
		$parentInfo = $childInfo->parent;

		foreach ($binding->parentResults as $parentResult)
		{
			$parentResult->isSet += ($value) ? 1 : -1;

			if ($parentInfo->groupType == GroupOptionInfo::TYPE_EXCLUSIVE)
			{
				if ($value)
				{
					$parentResult->selectedOption = $previousResult;
					$parentResult->selectedOptionName = $childInfo->variableName;
				}
			}

			assert(($parentResult->isSet >= 0)); // *This should not happen*

			if ($parentResult->isSet == 0)
			{
				$parentResult->selectedOption = null;
				$parentResult->selectedOptionName = null;
			}

			$childInfo = $childInfo->parent;
			$parentInfo = $parentInfo->parent;
			$previousResult = $parentResult;
		}
	}

	private function validateOptionArgument(ProgramResult $result, OptionNameBinding $binding,
		$value)
	{
		$s = $this->state;
		$validates = true;
		foreach ($binding->info->validators as $validator)
		{
			$v = $validator->validate($this->state, $result, $binding, $value);
			$validates = ($validates && $v);
		}

		return $validates;
	}

	private function validatePositionalArgument(ProgramResult $result,
		PositionalArgumentInfo &$paInfo, $paNumber, $value)
	{
		$s = $this->state;
		$validates = true;
		foreach ($paInfo->validators as $validator)
		{
			$validates = ($validates &&
				$validator->validate($this->state, $result, $paNumber, $value));
		}

		return $validates;
	}

	private function processPositionalArgument(ProgramResult $result, $value)
	{
		if (!($this->state->stateFlags & ParserState::ENDOFOPTIONS) &&
			($this->state->activeSubcommandIndex == 0) && (count($this->state->values) == 0))
		{
			foreach ($this->state->subcommandNameBindings as $name => $binding)
			{
				if ($name == $value)
				{
					$this->state->activeSubcommandIndex = $binding['subcommandIndex'];
					$result->setActiveSubcommand($name);
					return;
				}

				foreach ($binding['subcommand']->aliases as $alias)
				{
					if ($alias == $value)
					{
						$this->state->activeSubcommandIndex = $binding['subcommandIndex'];
						$result->setActiveSubcommand($name);
						return;
					}
				}
			}
		}

		$this->state->values[] = $value;
	}

	private function selectOption(ProgramResult $result, OptionNameBinding $binding)
	{
		if ($binding->info instanceof GroupOptionInfo)
		{
			if (!($binding->info->defaultOption instanceof OptionInfo))
				return false;
		}
		elseif ($binding->info instanceof ArgumentOptionInfo)
		{
			if ($binding->info->defaultValue === null)
				return false;

			$binding->result->argument = $binding->info->defaultValue;
		}
		elseif ($binding->info instanceof MultiArgumentOptionInfo)
		{
			if ($binding->info->minArgumentCount > 0)
				return false;
			$binding->result->arguments = array();
		}

		$this->markOption($result, $binding, true);
		return true;
	}

	private function findOption($property, $value)
	{
		$s = $this->state;
		if ($s->activeSubcommandIndex)
		{
			foreach ($s->optionNameBindings[$s->activeSubcommandIndex] as $n => $binding)
			{
				if ($binding->$property == $value)
				{
					return $binding;
				}
			}
		}

		foreach ($s->optionNameBindings[0] as $n => $binding)
		{
			if ($binding->$property == $value)
			{
				return $binding;
			}
		}

		return null;
	}

	private function findOptionByName($name)
	{
		$s = $this->state;
		if ($s->activeSubcommandIndex)
		{
			foreach ($s->optionNameBindings[$s->activeSubcommandIndex] as $n => $binding)
			{
				if ($name == $n)
				{
					return $binding;
				}
			}
		}

		foreach ($s->optionNameBindings[0] as $n => $binding)
		{
			if ($name == $n)
			{
				return $binding;
			}
		}

		return null;
	}

	/**
	 *
	 * @param OptionNameBinding $option
	 * @return boolean
	 */
	private function optionExpected(OptionNameBinding $option)
	{
		$s = $this->state;
		$parentInfo = $option->info->parent;
		$previousResult = $option->result;

		foreach ($option->parentResults as $i => $parentResult)
		{
			if ($parentInfo->groupType == GroupOptionInfo::TYPE_EXCLUSIVE)
			{
				if ($parentResult->isSet && ($parentResult->selectedOption != $previousResult))
				{
					return false;
				}
			}

			$parentInfo = $parentInfo->parent;
			$previousResult = $parentResult;
		}

		return true;
	}

	private function optionRequired(OptionNameBinding $binding)
	{
		if (!($binding->info->optionFlags & ItemInfo::REQUIRED))
		{
			return false;
		}

		$previousResult = $binding->result;
		$parentInfo = $binding->info->parent;

		foreach ($binding->parentResults as $parentResult)
		{
			if ($parentInfo->groupType == GroupOptionInfo::TYPE_EXCLUSIVE)
			{
				if (!$parentResult->isSet || ($parentResult->selectedOption != $previousResult))
				{
					return false;
				}
			}

			$parentInfo = $parentInfo->parent;
			$previousResult = $parentResult;
		}

		return true;
	}

	/**
	 *
	 * @param ProgramResult $result
	 * @return integer Number of changes
	 */
	private function postProcessOptions(ProgramResult $result)
	{
		$s = $this->state;
		$current = null;
		$changeCount = 0;
		foreach ($s->optionNameBindings as $i => $group)
		{
			foreach ($group as $name => $binding)
			{
				if ($current && ($current->info == $binding->info))
				{
					continue;
				}

				$current = $binding;

				if ($current->info instanceof ArgumentOptionInfo)
				{
					if (!$current->result->isSet)
					{
						if (($current->info->defaultValue !== null) &&
							$this->optionExpected($current))
						{
							$current->result->argument = $current->info->defaultValue;
							$this->markOption($result, $current, true);
							$changeCount++;
						}
						else
						{
							$current->result->argument = null;
						}
					}
				}
				elseif ($current->info instanceof MultiArgumentOptionInfo)
				{
					$c = count($current->result->arguments);
					if ($current->result->isSet && ($current->info->minArgumentCount > 0) &&
						($c < $current->info->minArgumentCount))
					{
						$result->appendMessage(Message::ERROR, 11, Message::ERROR_MISSING_MARG,
							$current->info->minArgumentCount, $current->name->cliName(), $c);
						$this->markOption($result, $current, false);
						$changeCount++;
					}

					if (!($current->result->isSet))
					{
						$current->result->arguments = array();
					}
				}
			}
		}

		return $changeCount;
	}

	private function postProcessPositionalArguments(ProgramResult $result)
	{
		$s = $this->state;
		$root = $this->programInfo;
		$validPositionalArgumentCount = 0;

		if ($s->activeSubcommandIndex > 0)
		{
			$root = $this->programInfo->subcommands[$s->activeSubcommandIndex - 1];
		}

		$paInfoCount = count($root->getPositionalArguments());
		if ($paInfoCount == 0 && (count($s->values) > 0))
		{
			if ($s->activeSubcommandIndex > 0)
			{
				$result->appendMessage(Message::ERROR, 9, Message::ERROR_SUBCMD_POSARG, $root->name);
			}
			else
			{
				$result->appendMessage(Message::ERROR, 8, Message::ERROR_PROGRAM_POSARG);
			}

			return $validPositionalArgumentCount;
		}

		$paInfoIndex = 0;
		$paNumber = 1;
		$currentPaiValueCount = 0;
		$processedValueCount = 0;
		$paInfo = null;
		foreach ($s->values as $value)
		{
			if ($paInfoIndex >= $paInfoCount)
			{
				break;
			}

			$currentPaiValueCount++;
			$processedValueCount++;

			$paInfo = $root->getPositionalArgument($paInfoIndex);
			if ($this->validatePositionalArgument($result, $paInfo, $paNumber, $value))
			{
				$result->appendValue($value);
				$validPositionalArgumentCount++;
			}
			else
			{
			/**
			 *
			 * @todo continue or abort ?
			 */
			}

			if (($paInfo->maxArgumentCount > 0) &&
				($currentPaiValueCount == $paInfo->maxArgumentCount))
			{
				$currentPaiValueCount = 0;
				$paInfoIndex++;
			}

			$paNumber++;
		}

		if (count($s->values) > $processedValueCount)
		{
			$result->appendMessage(Message::ERROR, 10, Message::ERROR_TOOMANY_POSARG);
		}
		elseif ($paInfoIndex < $paInfoCount)
		{
			/**
			 *
			 * @note not yet supported by schema
			 */
			for ($i = $paInfoIndex; $i < $paInfoCount; $i++)
			{
				if ($root->getPositionalArgument($i)->positionalArgumentFlags & ItemInfo::REQUIRED)
				{
					$result->appendMessage(Message::ERROR, 7, Message::ERROR_REQUIRED_POSARG, $i);
				}
			}
		}
	}

	/**
	 *
	 * @var ProgramInfo
	 */
	private $programInfo;

	/**
	 *
	 * @var ParserState
	 */
	private $state;
}


}// namespace Parser
namespace Program
{
class newTypeFileProgramInfo extends \Parser\ProgramInfo
{
	public function __construct()
	{
		parent::__construct("new-type-file");
		
		// prg:argument phpBootstrapFile
		$G_1_bootstrap = new \Parser\ArgumentOptionInfo("phpBootstrapFile", array('bootstrap', 'autoload'), (0));
		$G_1_bootstrap->argumentType = \Parser\ArgumentType::PATH;
		
		$G_1_bootstrap->abstract = 'PHP bootstrap file';
		
		$G_1_bootstrap->details = 'A PHP file to load at the beginning of the application execution';
		$G_1_bootstrap->validators[] = new \Parser\PathValueValidator(0 | \Parser\PathValueValidator::EXISTS | \Parser\PathValueValidator::TYPE_FILE);
		$this->appendOption($G_1_bootstrap);
		
		// prg:switch displayHelp
		$G_2_help = new \Parser\SwitchOptionInfo("displayHelp", array('help'), (0));
		
		$G_2_help->abstract = 'Display program usage';
		$this->appendOption($G_2_help);
		$G_1_ = $this->appendPositionalArgument( new \Parser\PositionalArgumentInfo(1, \Parser\ArgumentType::MIXED, (0)));
		
		$G_1_->abstract = 'type name';
	}
}

}// namespace Program
?>
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




