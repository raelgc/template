<?php
namespace raelgc\view {

	/**
	 * Template Management for PHP5
	 *
	 * The Template engine allows to keep the HTML code in some external files
	 * which are completely free of PHP code. This way, it's possible keep logical
	 * programmin (PHP code) away from visual structure (HTML or XML, CSS, etc).
	 *
	 * If you are familiar with PHP template concept, this class includes these
	 * features: object support, auto-detect blocks, auto-clean children blocks,
	 * warning when user call for a non-existent block, warning when a mal-formed
	 * block is detected, warning when user sets a non existant variable, and other
	 * minor features.
	 *
	 * @author Rael G.C. (rael.gc@gmail.com)
	 * @version 2.2.7
	 */
	class Template {

		/**
		 * A list of existent document variables.
		 * @var	array
		 */
		protected $vars = array();

		/**
		 * A hash with vars and values setted by the user.
		 * @var	array
		 */
		protected $values = array();

		/**
		 * A hash of existent object properties variables in the document.
		 * @var	array
		 */
		private $properties = array();

		/**
		 * A hash of the object instances setted by the user.
		 * @var	array
		 */
		protected $instances = array();

		/**
		 * List of used modifiers
		 * @var array
		 */
		protected $modifiers = array();

		/**
		 * A list of all automatic recognized blocks.
		 * @var	array
		 */
		private $blocks = array();

		/**
		 * A list of all blocks that contains at least a "child" block.
		 * @var	array
		 */
		private $parents = array();

		/**
		 * List of parsed blocks
		 * @var	array
		 */
		private $parsed = array();

		/**
		 * List of blocks to finalize
		 * @var array
		 */
		private $finally = array();

		/**
		 * Describes the replace method for blocks. See the Template::setFile()
		 * method for more details.
		 * @var	boolean
		 */
		private $accurate;

		/**
		 * Regular expression to find var and block names.
		 * Only alfa-numeric chars and the underscore char are allowed.
		 *
		 * @var		string
		 */
		private static $REG_NAME = "([[:alnum:]]|_)+";

		/**
		 * Creates a new template, using $filename as main file.
		 *
		 * When the parameter $accurate is true, blocks will be replaced perfectly
		 * (in the parse time), e.g., removing all \t (tab) characters, making the
		 * final document an accurate version. This will impact (a lot) the
		 * performance. Usefull for files using the &lt;pre&gt; or &lt;code&gt; tags.
		 *
		 * @param     string $filename		file path of the file to be loaded
		 * @param     booelan $accurate		true for accurate block parsing
		 */
		public function __construct($filename, $accurate = false){
			$this->accurate = $accurate;
			$this->loadfile(".", $filename);
		}

		/**
		 * Put the content of $filename in the template variable identified by $varname
		 *
		 * @param     string $varname		existing template var
		 * @param     string $filename		file to be loaded
		 */
		public function addFile($varname, $filename){
			if(!$this->exists($varname)) throw new \InvalidArgumentException("addFile: var $varname does not exist");
			$this->loadfile($varname, $filename);
		}

		/**
		 * Do not use. Properties setter method
		 *
		 * @param	string	$varname	template var name
		 * @param	mixed	$value		template var value
		 */
		public function __set($varname, $value){
			if(!$this->exists($varname)) throw new \RuntimeException("var $varname does not exist");
			$stringValue = $value;
			if(is_object($value)){
				$this->instances[$varname] = $value;
				if(!isset($this->properties[$varname])) $this->properties[$varname] = array();
				if(method_exists($value, "__toString")) $stringValue = $value->__toString();
				else $stringValue = "Object";
			}
			$this->setValue($varname, $stringValue);
			return $value;
		}

		/**
		 * Do not use. Properties getter method.
		 *
		 * @param	string	$varname	template var name
		 */
		public function __get($varname){
			if(isset($this->values["{".$varname."}"])) return $this->values["{".$varname."}"];
			elseif(isset($this->instances[$varname])) return $this->instances[$varname];
			throw new \RuntimeException("var $varname does not exist");
		}

		/**
		 * Check if a template var exists.
		 *
		 * This method returns true if the template var exists. Otherwise, false.
		 *
		 * @param	string	$varname	template var name
		 */
		public function exists($varname){
			return in_array($varname, $this->vars);
		}

		/**
		 * Loads a file identified by $filename.
		 *
		 * The file will be loaded and the file's contents will be assigned as the
		 * variable's value.
		 * Additionally, this method call Template::identify() that identifies
		 * all blocks and variables automatically.
		 *
		 * @param     string $varname		contains the name of a variable to load
		 * @param     string $filename		file name to be loaded
		 *
		 * @return    void
		 */
		protected function loadfile($varname, $filename) {
			if (!file_exists($filename)) throw new \InvalidArgumentException("file $filename does not exist");
			// If it's PHP file, parse it
			if($this->isPHP($filename)){
				ob_start();
				require $filename;
				$str = ob_get_contents();
				ob_end_clean();
				$this->setValue($varname, $str);
			} else {
				// Reading file and hiding comments
				$str = preg_replace("/<!---.*?--->/smi", "", file_get_contents($filename));
				if (empty($str)) throw new \InvalidArgumentException("file $filename is empty");
				$this->setValue($varname, $str);
				$blocks = $this->identify($str, $varname);
				$this->createBlocks($blocks);
			}
		}

		/**
		 * Check if file is a .php
		 */
		protected function isPHP($filename){
			foreach(array('.php', '.php5', '.cgi') as $php){
				if(0 == strcasecmp($php, substr($filename, strripos($filename, $php)))) return true;
			}
			return false;
		}

		/**
		 * Identify all blocks and variables automatically and return them.
		 *
		 * All variables and blocks are already identified at the moment when
		 * user calls Template::setFile(). This method calls Template::identifyVars()
		 * and Template::identifyBlocks() methods to do the job.
		 *
		 * @param     string	$content		file content
		 * @param     string	$varname		contains the variable name of the file
		 *
		 * @return    array		an array where the key is the block name and the value is an
		 * 						array with the children block names.
		 */
		protected function identify(&$content, $varname){
			$blocks = array();
			$queued_blocks = array();
			$this->identifyVars($content);
			$lines = explode("\n", $content);
			// Checking for minified HTML
			if(1==sizeof($lines)){
				$content = str_replace('-->', "-->\n", $content);
				$lines = explode("\n", $content);
			}
			foreach (explode("\n", $content) as $line) {
				if (strpos($line, "<!--")!==false) $this->identifyBlocks($line, $varname, $queued_blocks, $blocks);
			}
			return $blocks;
		}

		/**
		 * Identify all user defined blocks automatically.
		 *
		 * @param     string $line				contains one line of the content file
		 * @param     string $varname			contains the filename variable identifier
		 * @param     string $queued_blocks		contains a list of the current queued blocks
		 * @param     string $blocks			contains a list of all identified blocks in the current file
		 *
		 * @return    void
		 */
		protected function identifyBlocks(&$line, $varname, &$queued_blocks, &$blocks){
			$reg = "/<!--\s*BEGIN\s+(".self::$REG_NAME.")\s*-->/sm";
			preg_match($reg, $line, $m);
			if (1==preg_match($reg, $line, $m)){
				if (0==sizeof($queued_blocks)) $parent = $varname;
				else $parent = end($queued_blocks);
				if (!isset($blocks[$parent])){
					$blocks[$parent] = array();
				}
				$blocks[$parent][] = $m[1];
				$queued_blocks[] = $m[1];
			}
			$reg = "/<!--\s*END\s+(".self::$REG_NAME.")\s*-->/sm";
			if (1==preg_match($reg, $line)) array_pop($queued_blocks);
		}

		/**
		 * Identifies all variables defined in the document.
		 *
		 * @param     string $content	file content
		 */
		protected function identifyVars(&$content){
			$r = preg_match_all("/{(".self::$REG_NAME.")((\-\>(".self::$REG_NAME."))*)?((\|.*?)*)?}/", $content, $m);
			if ($r){
				for($i=0; $i<$r; $i++){
					// Object var detected
					if($m[3][$i] && (!isset($this->properties[$m[1][$i]]) || !in_array($m[3][$i], $this->properties[$m[1][$i]]))){
						$this->properties[$m[1][$i]][] = $m[3][$i];
					}
					// Modifiers detected
					if($m[7][$i] && (!isset($this->modifiers[$m[1][$i]]) || !in_array($m[7][$i], $this->modifiers[$m[1][$i].$m[3][$i]]))){
						$this->modifiers[$m[1][$i].$m[3][$i]][] = $m[1][$i].$m[3][$i].$m[7][$i];
					}
					// Common variables
					if(!in_array($m[1][$i], $this->vars)){
						$this->vars[] = $m[1][$i];
					}
				}
			}
		}

		/**
		 * Create all identified blocks given by Template::identifyBlocks().
		 *
		 * @param     array $blocks		contains all identified block names
		 * @return    void
		 */
		protected function createBlocks(&$blocks) {
			$this->parents = array_merge($this->parents, $blocks);
			foreach($blocks as $parent => $block){
				foreach($block as $chield){
					if(in_array($chield, $this->blocks)) throw new \UnexpectedValueException("duplicated block: $chield");
					$this->blocks[] = $chield;
					$this->setBlock($parent, $chield);
				}
			}
		}

		/**
		 * A variable $parent may contain a variable block defined by:
		 * &lt;!-- BEGIN $varname --&gt; content &lt;!-- END $varname --&gt;.
		 *
		 * This method removes that block from $parent and replaces it with a variable
		 * reference named $block.
		 * Blocks may be nested.
		 *
		 * @param     string $parent	contains the name of the parent variable
		 * @param     string $block		contains the name of the block to be replaced
		 * @return    void
		 */
		protected function setBlock($parent, $block) {
			$name = $block.'_value';
			$str = $this->getVar($parent);
			if($this->accurate){
				$str = str_replace("\r\n", "\n", $str);
				$reg = "/\t*<!--\s*BEGIN\s+$block\s+-->\n*(\s*.*?\n?)\t*<!--\s+END\s+$block\s*-->\n*((\s*.*?\n?)\t*<!--\s+FINALLY\s+$block\s*-->\n?)?/sm";
			}
			else $reg = "/<!--\s*BEGIN\s+$block\s+-->\s*(\s*.*?\s*)<!--\s+END\s+$block\s*-->\s*((\s*.*?\s*)<!--\s+FINALLY\s+$block\s*-->)?\s*/sm";
			if(1!==preg_match($reg, $str, $m)) throw new \UnexpectedValueException("mal-formed block $block");
			$this->setValue($name, '');
			$this->setValue($block, $m[1]);
			$this->setValue($parent, preg_replace($reg, "{".$name."}", $str));
			if(isset($m[3])) $this->finally[$block] = $m[3];
		}

		/**
		 * Internal setValue() method.
		 *
		 * The main difference between this and Template::__set() method is this
		 * method cannot be called by the user, and can be called using variables or
		 * blocks as parameters.
		 *
		 * @param     string $varname		constains a varname
		 * @param     string $value        constains the new value for the variable
		 * @return    void
		 */
		protected function setValue($varname, $value) {
			$this->values['{'.$varname.'}'] = $value;
		}

		/**
		 * Returns the value of the variable identified by $varname.
		 *
		 * @param     string	$varname	the name of the variable to get the value of
		 * @return    string	the value of the variable passed as argument
		 */
		protected function getVar($varname) {
			return $this->values['{'.$varname.'}'];
		}

		/**
		 * Clear the value of a variable.
		 *
		 * Alias for $this->setValue($varname, "");
		 *
		 * @param     string $varname	var name to be cleaned
		 * @return    void
		 */
		public function clear($varname) {
			$this->setValue($varname, "");
		}

		/**
		 * Manually assign a child block to a parent block
		 *
		 * @param string $parent	parent block
		 * @param string $block		child block
		 */
		public function setParent($parent, $block){
			$this->parents[$parent][] = $block;
		}

		/**
		 * Subst modifiers content
		 *
		 * @param	string	$value	text to be modified
		 * @param $exp
		 * @return unknown_type
		 */
		protected function substModifiers($value, $exp){
			$statements = explode('|', $exp);
			for($i=1; $i<sizeof($statements); $i++){
				$temp = explode(":", $statements[$i]);
				$function = $temp[0];
				$parameters = array_diff($temp, array($function));
				$value = call_user_func_array($function, array_merge(array($value), $parameters));
			}
			return $value;
		}

		/**
		 * Fill in all the variables contained in variable named $value.
		 * $value. The resulting string is not "cleaned" yet.
		 *
		 * @param     string 	$value		var value
		 * @return    string	content with all variables substituted.
		 */
		protected function subst($value) {
			// Common variables replacement
			$s = str_replace(array_keys($this->values), $this->values, $value);
			// Common variables with modifiers
			foreach($this->modifiers as $var => $expressions){
				if(false!==strpos($s, "{".$var."|")) foreach($expressions as $exp){
					if(false===strpos($var, "->") && isset($this->values['{'.$var.'}'])){
						$s = str_replace('{'.$exp.'}', $this->substModifiers($this->values['{'.$var.'}'], $exp), $s);
					}
				}
			}
			// Object variables replacement
			foreach($this->instances as $var => $instance){
				foreach($this->properties[$var] as $properties){
					if(false!==strpos($s, "{".$var.$properties."}") || false!==strpos($s, "{".$var.$properties."|")){
						$pointer = $instance;
						$property = explode("->", $properties);
						for($i = 1; $i < sizeof($property); $i++){
							if(!is_null($pointer)){
								$obj = strtolower(str_replace('_', '', $property[$i]));
								// Get accessor
								if(method_exists($pointer, "get$obj")) $pointer = $pointer->{"get$obj"}();
								// Magic __get accessor
								elseif(method_exists($pointer, "__get")) $pointer = $pointer->__get($property[$i]);
								// Property acessor
								elseif(property_exists($pointer, $obj)) $pointer = $pointer->$obj;
								elseif(property_exists($pointer, $property[$i])) $pointer = $pointer->{$property[$i]};
								else {
									$className = $property[$i-1] ? $property[$i-1] : get_class($instance);
									$class = is_null($pointer) ? "NULL" : get_class($pointer);
									throw new \BadMethodCallException("no accessor method in class ".$class." for ".$className."->".$property[$i]);
								}
							} else {
								$pointer = $instance->get($obj);
							}
						}
						// Checking if final value is an object...
						if(is_object($pointer)){
							$pointer_str = method_exists($pointer, "__toString") ? $pointer->__toString() : json_encode($pointer);
						// ... or an array
						} elseif(is_array($pointer)){
							$value = "";
							for($i=0; list($key, $val) = each($pointer); $i++){
								$value.= "$key => $val";
								if($i<sizeof($pointer)-1) $value.= ",";
							}
							$pointer_str = $value;
						} else {
							$pointer_str = $pointer;
						}
						// Replacing value
						$s = str_replace("{".$var.$properties."}", $pointer_str, $s);
						// Object with modifiers
						if(isset($this->modifiers[$var.$properties])){
							foreach($this->modifiers[$var.$properties] as $exp){
								$s = str_replace('{'.$exp.'}', $this->substModifiers($pointer, $exp), $s);
							}
						}
					}
				}
			}
			return $s;
		}

		/**
		 * Show a block.
		 *
		 * This method must be called when a block must be showed.
		 * Otherwise, the block will not appear in the resultant
		 * content.
		 *
		 * @param     string $block     the block name to be parsed
		 * @param     boolean $append   true if the content must be appended
		 */
		public function block($block, $append = true) {
			if(!in_array($block, $this->blocks)) throw new \InvalidArgumentException("block $block does not exist");
			// Checking finally blocks inside this block
			if(isset($this->parents[$block])) foreach($this->parents[$block] as $child){
				if(isset($this->finally[$child]) && !in_array($child, $this->parsed)){
					$this->setValue($child.'_value', $this->subst($this->finally[$child]));
					$this->parsed[] = $block;
				}
			}
			if ($append) {
				$this->setValue($block.'_value', $this->getVar($block.'_value') . $this->subst($this->getVar($block)));
			} else {
				$this->setValue($block.'_value', $this->getVar($block.'_value'));
			}
			if(!in_array($block, $this->parsed)) $this->parsed[] = $block;
			// Cleaning children
			if(isset($this->parents[$block])) foreach($this->parents[$block] as $child) $this->clear($child.'_value');
		}

		/**
		* Returns the final content
		*
		* @return    string
		*/
		public function parse() {
			// Auto assistance for parse children blocks
			foreach(array_reverse($this->parents) as $parent => $children){
				foreach($children as $block){
					if(in_array($parent, $this->blocks) && in_array($block, $this->parsed) && !in_array($parent, $this->parsed)){
						$this->setValue($parent.'_value', $this->subst($this->getVar($parent)));
						$this->parsed[] = $parent;
					}
				}
			}
			// Parsing finally blocks
			foreach($this->finally as $block => $content){
				if(!in_array($block, $this->parsed)){
					$this->setValue($block.'_value', $this->subst($content));
				}
			}
			// After subst, remove empty vars
			return preg_replace("/{(".self::$REG_NAME.")((\-\>(".self::$REG_NAME."))*)?((\|.*?)*)?}/", "", $this->subst($this->getVar(".")));
		}

		/**
		 * Print the final content.
		 */
		public function show() {
			echo $this->parse();
		}

	}

}

namespace {

	/**
	* Suitable for Template class: similar to str_replace, but using string in first param
	* @see str_replace
	* @param string $str
	* @param string $search
	* @param string $replace
	* @return mixed
	* @author Rael G.C. (rael.gc@gmail.com)
	*/
   function replace($str, $search, $replace){
	   return str_replace($search, $replace, $str);
   }

}
